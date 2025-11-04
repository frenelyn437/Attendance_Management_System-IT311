<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION['first_name'], $_SESSION['last_name'])) {
    header("Location: ../index.php");
    exit;
}

$fullname = $_SESSION['first_name'] . " " . $_SESSION['last_name'];
$user_id = $_SESSION['user_id'];
$message = "";

// Auto-mark overdue '-' statuses as Absent for this teacher
$auto = $conn->prepare("UPDATE attendance_details ad
    JOIN attendance a ON a.id = ad.attendance_id
    SET ad.status = 'Absent'
    WHERE ad.status = '-' AND a.created_by = ? AND (
      a.attendance_date < CURDATE() OR
      (a.attendance_date = CURDATE() AND a.end_time IS NOT NULL AND a.end_time <> '' AND a.end_time <= CURTIME())
    )");
if ($auto) { $auto->bind_param("i", $user_id); $auto->execute(); $auto->close(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_course'])) {
    $uc_code = trim($_POST['uc_course_code'] ?? '');
    $uc_name = trim($_POST['uc_course_name'] ?? '');
    $uc_desc = trim($_POST['uc_course_description'] ?? '');
    if ($uc_code !== '') {
        $upd = $conn->prepare("UPDATE courses SET course_name=?, course_description=? WHERE course_code=? AND teacher_id=?");
        $upd->bind_param("sssi", $uc_name, $uc_desc, $uc_code, $user_id);
        $upd->execute();
        if ($upd->affected_rows === 0) {
            $ins = $conn->prepare("INSERT INTO courses (course_code, course_name, course_description, teacher_id) VALUES (?,?,?,?)");
            $ins->bind_param("sssi", $uc_code, $uc_name, $uc_desc, $user_id);
            $ins->execute();
            $ins->close();
        }
        $upd->close();
        $message = "✅ Course details saved.";
    }
}

// Profile Image Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image'])) {
    $targetDir = "../uploads/profiles/";
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

    $fileName = basename($_FILES['profile_image']['name']);
    $targetFilePath = $targetDir . $fileName;
    $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (in_array($fileType, $allowedTypes)) {
        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetFilePath)) {
            $relativePath = "uploads/profiles/" . $fileName;
            $update = $conn->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
            $update->bind_param("si", $relativePath, $user_id);
            if ($update->execute()) {
                $_SESSION['profile_image'] = $relativePath;
                $message = "✅ Profile picture updated successfully!";
            } else {
                $message = "❌ Database error while updating profile.";
            }
            $update->close();
        } else {
            $message = "⚠️ Failed to upload file.";
        }
    } else {
        $message = "⚠️ Invalid file type. Allowed: JPG, PNG, GIF, WEBP.";
    }
}

// CREATE ATTENDANCE HANDLER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_attendance'])) {
    // course_choice may be an existing code or '__new__' for new course
    $course_choice = $_POST['course_choice'] ?? '';
    $course_code = '';
    $course_description_new = '';
    if ($course_choice === '__new__') {
        $course_code = trim($_POST['course_code_new'] ?? '');
        $course_description_new = trim($_POST['course_description_new'] ?? '');
    } else {
        $course_code = trim($course_choice);
    }

    $year_level = $_POST['year_level'] ?? '';
    $section = $_POST['section'] ?? '';
    $attendance_date = $_POST['attendance_date'] ?? date('Y-m-d');
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';

    if ($course_choice === '__new__' && $course_code) {
        $ins = $conn->prepare("INSERT INTO courses (course_code, course_name, course_description, teacher_id) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE course_name = VALUES(course_name), course_description = VALUES(course_description), teacher_id = VALUES(teacher_id)");
        $course_name_new = null; // no name provided during creation
        $ins->bind_param("sssi", $course_code, $course_name_new, $course_description_new, $user_id);
        $ins->execute();
        $ins->close();
    }
        if($start_time >= $end_time) {
            $message = "⚠️ Start time must be before end time.";
        } else {
            $stmt = $conn->prepare("INSERT INTO attendance (course_code, year_level, section, attendance_date, start_time, end_time, created_by, created_at) VALUES (?,?,?,?,?,?,?,NOW())");
            $stmt->bind_param("ssssssi", $course_code, $year_level, $section, $attendance_date, $start_time, $end_time, $user_id);
            if($stmt->execute()){
                $new_attendance_id = $stmt->insert_id;

                // Copy previously added students (enrolled in this course/year/section) into the new attendance roster
                $copied = 0;
                $copy = $conn->prepare("INSERT INTO attendance_details (attendance_id, student_id, status)
                    SELECT ?, ad.student_id, '-' 
                    FROM attendance_details ad
                    JOIN attendance a ON a.id = ad.attendance_id
                    WHERE a.created_by = ? AND a.course_code = ? AND a.year_level = ? AND a.section = ?
                    GROUP BY ad.student_id");
                if ($copy) {
                    $copy->bind_param("iisss", $new_attendance_id, $user_id, $course_code, $year_level, $section);
                    $copy->execute();
                    $copied = $copy->affected_rows;
                    $copy->close();
                }
                // Fallback: if no exact match, copy by course only (all prior sections/years under this course)
                if ($copied <= 0) {
                    $copyCourse = $conn->prepare("INSERT INTO attendance_details (attendance_id, student_id, status)
                        SELECT ?, ad.student_id, '-' 
                        FROM attendance_details ad
                        JOIN attendance a ON a.id = ad.attendance_id
                        WHERE a.created_by = ? AND a.course_code = ?
                        GROUP BY ad.student_id");
                    if ($copyCourse) {
                        $copyCourse->bind_param("iis", $new_attendance_id, $user_id, $course_code);
                        $copyCourse->execute();
                        $copied = $copyCourse->affected_rows;
                        $copyCourse->close();
                    }
                }

                $message = "✅ Attendance created. Loaded roster: $copied students.";
            } else {
                $message = "❌ Failed to create attendance.";
            }
            $stmt->close();
        }
    }


// Get counts for dashboard
$attendance_count = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE created_by = $user_id")->fetch_assoc()['count'];
$courses_count = $conn->query("SELECT COUNT(DISTINCT course_code) as count FROM attendance WHERE created_by = $user_id")->fetch_assoc()['count'];
$students_count = $conn->query("SELECT COUNT(DISTINCT student_id) as count FROM attendance_details ad JOIN attendance a ON ad.attendance_id = a.id WHERE a.created_by = $user_id")->fetch_assoc()['count'];

// Fetch schedules
$filter_course = isset($_GET['course']) ? $_GET['course'] : '';
$filter_year = isset($_GET['year']) ? $_GET['year'] : '';
$filter_section = isset($_GET['section']) ? $_GET['section'] : '';

if ($filter_course && $filter_year && $filter_section) {
    $stmt = $conn->prepare("SELECT a.*, c.course_description FROM attendance a LEFT JOIN courses c ON c.course_code = a.course_code AND c.teacher_id = ? WHERE a.created_by = ? AND a.course_code = ? AND a.year_level = ? AND a.section = ? ORDER BY a.attendance_date DESC, a.start_time DESC");
    $stmt->bind_param("iisss", $user_id, $user_id, $filter_course, $filter_year, $filter_section);
    $stmt->execute();
    $schedules = $stmt->get_result();
    $stmt->close();
} elseif ($filter_course) {
    // Show history for this course across all years/sections
    $stmt = $conn->prepare("SELECT a.*, c.course_description FROM attendance a LEFT JOIN courses c ON c.course_code = a.course_code AND c.teacher_id = ? WHERE a.created_by = ? AND a.course_code = ? ORDER BY a.attendance_date DESC, a.start_time DESC");
    $stmt->bind_param("iis", $user_id, $user_id, $filter_course);
    $stmt->execute();
    $schedules = $stmt->get_result();
    $stmt->close();
} else {
    $schedules = $conn->query("SELECT a.*, c.course_description FROM attendance a LEFT JOIN courses c ON c.course_code = a.course_code AND c.teacher_id = $user_id WHERE a.created_by = $user_id ORDER BY a.created_at DESC");
}

// View Attendance
$view_attendance_id = isset($_GET['view']) ? intval($_GET['view']) : 0;
$attendance_students = [];
$add_student_list = [];
$added_students = [];

if ($view_attendance_id) {
    $stmt = $conn->prepare("SELECT ad.id AS detail_id, u.id_number, u.first_name, u.last_name, ad.status 
                            FROM attendance_details ad 
                            JOIN users u ON ad.student_id = u.id_number 
                            WHERE ad.attendance_id = ? ORDER BY u.id_number ASC");
    $stmt->bind_param("i", $view_attendance_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $attendance_students[] = $row;
    $stmt->close();

    $stmt = $conn->prepare("SELECT student_id FROM attendance_details WHERE attendance_id=?");
    $stmt->bind_param("i", $view_attendance_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $added_students[] = $r['student_id'];
    $stmt->close();

    $search = $_GET['search'] ?? '';
    $sql = "SELECT id_number, first_name, last_name, year_level, section FROM users WHERE role=0 AND status='active'";
    $params = [];
    $types = '';
    if ($search) {
        $sql .= " AND (id_number LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR section LIKE ? OR year_level LIKE ?)";
        $params = array_fill(0, 5, "%$search%");
        $types = "sssss";
    }
    $stmt = $conn->prepare($sql);
    if ($search) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $add_student_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Add student inline
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student_id'])) {
    $student_id = $_POST['add_student_id'];
    $stmt = $conn->prepare("SELECT id FROM attendance_details WHERE attendance_id=? AND student_id=?");
    $stmt->bind_param("is", $view_attendance_id, $student_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        $insert = $conn->prepare("INSERT INTO attendance_details (attendance_id, student_id, status) VALUES (?,?, '-')");
        $insert->bind_param("is", $view_attendance_id, $student_id);
        $insert->execute();
        $insert->close();
    }
    $stmt->close();
    header("Location: teacher.php?view=$view_attendance_id&add=1");
    exit;
}
if (isset($_GET['print']) && $_GET['print'] === 'course' && isset($_GET['course'], $_GET['year'], $_GET['section'])) {
    $pcourse = $_GET['course'];
    $pyear = $_GET['year'];
    $psection = $_GET['section'];
    $head = $conn->prepare("SELECT CONCAT(u.first_name,' ',u.last_name) as tname FROM users u WHERE u.id=?");
    $head->bind_param("i", $user_id);
    $head->execute();
    $tname = ($head->get_result()->fetch_assoc()['tname'] ?? '');
    $head->close();
    $stuStmt = $conn->prepare("SELECT u.id_number, u.first_name, u.last_name FROM users u WHERE u.role=0 AND EXISTS (SELECT 1 FROM attendance a JOIN attendance_details ad ON a.id=ad.attendance_id WHERE a.created_by=? AND a.course_code=? AND a.year_level=? AND a.section=? AND ad.student_id=u.id_number) ORDER BY u.last_name,u.first_name");
    $stuStmt->bind_param("isss", $user_id, $pcourse, $pyear, $psection);
    $stuStmt->execute();
    $students = $stuStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stuStmt->close();
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Course Attendance</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="p-4">';
    echo '<div class="d-flex justify-content-between align-items-center mb-3"><div><h4 class="mb-1">Overall Attendance</h4><div class="text-muted">Course: '.htmlspecialchars($pcourse).' • Year '.htmlspecialchars($pyear).' • Section '.htmlspecialchars($psection).'</div><div class="text-muted">Teacher: '.htmlspecialchars($tname).'</div></div><button class="btn btn-primary d-print-none" onclick="window.print()">Print</button></div>';
    echo '<div class="table-responsive"><table class="table table-bordered table-sm align-middle"><thead class="table-light"><tr><th>ID Number</th><th>Name</th><th>Present</th><th>Absent</th><th>Late</th><th>Total</th><th>Rate</th></tr></thead><tbody>';
    if ($students) {
        $statStmt = $conn->prepare("SELECT 
            SUM(CASE WHEN ad.status='Present' THEN 1 ELSE 0 END) p,
            SUM(CASE WHEN ad.status='Absent' THEN 1 ELSE 0 END) a,
            SUM(CASE WHEN ad.status='Late' THEN 1 ELSE 0 END) l,
            COUNT(*) t
            FROM attendance a
            JOIN attendance_details ad ON a.id=ad.attendance_id
            WHERE a.created_by=? AND a.course_code=? AND a.year_level=? AND a.section=? AND ad.student_id=?");
        foreach ($students as $s) {
            $sid = $s['id_number'];
            $statStmt->bind_param("issss", $user_id, $pcourse, $pyear, $psection, $sid);
            $statStmt->execute();
            $row = $statStmt->get_result()->fetch_assoc() ?: ['p'=>0,'a'=>0,'l'=>0,'t'=>0];
            $rate = ($row['t'] ?? 0) > 0 ? round(($row['p']/$row['t'])*100) : 0;
            echo '<tr>';
            echo '<td>'.htmlspecialchars($sid).'</td>';
            echo '<td>'.htmlspecialchars($s['last_name'].', '.$s['first_name']).'</td>';
            echo '<td>'.(int)($row['p'] ?? 0).'</td>';
            echo '<td>'.(int)($row['a'] ?? 0).'</td>';
            echo '<td>'.(int)($row['l'] ?? 0).'</td>';
            echo '<td>'.(int)($row['t'] ?? 0).'</td>';
            echo '<td>'.$rate.'%</td>';
            echo '</tr>';
        }
        $statStmt->close();
    } else {
        echo '<tr><td colspan="7" class="text-center text-muted">No students found</td></tr>';
    }
    echo '</tbody></table></div>';
    $sessStmt = $conn->prepare("SELECT id, attendance_date, TIME_FORMAT(start_time,'%h:%i %p') st, TIME_FORMAT(end_time,'%h:%i %p') et FROM attendance WHERE created_by=? AND course_code=? AND year_level=? AND section=? ORDER BY attendance_date ASC, start_time ASC");
    $sessStmt->bind_param("isss", $user_id, $pcourse, $pyear, $psection);
    $sessStmt->execute();
    $sessions = $sessStmt->get_result();
    if ($sessions && $sessions->num_rows) {
        echo '<hr class="my-4">';
        while ($sess = $sessions->fetch_assoc()) {
            $hdate = date('D, M d, Y', strtotime($sess['attendance_date']));
            echo '<div class="mb-3">';
            echo '<h5 class="mb-2">Session: '.htmlspecialchars($hdate).' • '.htmlspecialchars($sess['st']).' - '.htmlspecialchars($sess['et']).'</h5>';
            $detStmt = $conn->prepare("SELECT u.id_number, u.first_name, u.last_name, COALESCE(ad.status,'-') as status FROM users u LEFT JOIN attendance_details ad ON ad.student_id=u.id_number AND ad.attendance_id=? WHERE u.role=0 AND EXISTS (SELECT 1 FROM attendance a JOIN attendance_details ad2 ON a.id=ad2.attendance_id WHERE a.created_by=? AND a.course_code=? AND a.year_level=? AND a.section=? AND ad2.student_id=u.id_number) ORDER BY u.last_name,u.first_name");
            $detStmt->bind_param("iisss", $sess['id'], $user_id, $pcourse, $pyear, $psection);
            $detStmt->execute();
            $detRes = $detStmt->get_result();
            echo '<div class="table-responsive"><table class="table table-bordered table-sm align-middle"><thead class="table-light"><tr><th>ID Number</th><th>Name</th><th>Status</th></tr></thead><tbody>';
            if ($detRes && $detRes->num_rows) {
                while ($d = $detRes->fetch_assoc()) {
                    echo '<tr>';
                    echo '<td>'.htmlspecialchars($d['id_number']).'</td>';
                    echo '<td>'.htmlspecialchars($d['last_name'].', '.$d['first_name']).'</td>';
                    echo '<td>'.htmlspecialchars($d['status']).'</td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="3" class="text-center text-muted">No students found</td></tr>';
            }
            echo '</tbody></table></div>';
            $detStmt->close();
            echo '</div>';
        }
    }
    $sessStmt->close();
    echo '</body></html>';
    exit;
}

// Print course-wide (all years/sections) when only course is provided
if (isset($_GET['print']) && $_GET['print'] === 'course' && isset($_GET['course']) && !isset($_GET['year']) && !isset($_GET['section'])) {
    $pcourse = $_GET['course'];
    $head = $conn->prepare("SELECT CONCAT(u.first_name,' ',u.last_name) as tname FROM users u WHERE u.id=?");
    $head->bind_param("i", $user_id);
    $head->execute();
    $tname = ($head->get_result()->fetch_assoc()['tname'] ?? '');
    $head->close();

    // All students who ever appeared under this course for this teacher
    $stuStmt = $conn->prepare("SELECT DISTINCT u.id_number, u.first_name, u.last_name FROM users u WHERE u.role=0 AND EXISTS (SELECT 1 FROM attendance a JOIN attendance_details ad ON a.id=ad.attendance_id WHERE a.created_by=? AND a.course_code=? AND ad.student_id=u.id_number) ORDER BY u.last_name,u.first_name");
    $stuStmt->bind_param("is", $user_id, $pcourse);
    $stuStmt->execute();
    $students = $stuStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stuStmt->close();

    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Course Attendance</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="p-4">';
    echo '<div class="d-flex justify-content-between align-items-center mb-3"><div><h4 class="mb-1">Overall Attendance</h4><div class="text-muted">Course: '.htmlspecialchars($pcourse).'</div><div class="text-muted">Teacher: '.htmlspecialchars($tname).'</div></div><button class="btn btn-primary d-print-none" onclick="window.print()">Print</button></div>';
    echo '<div class="table-responsive"><table class="table table-bordered table-sm align-middle"><thead class="table-light"><tr><th>ID Number</th><th>Name</th><th>Present</th><th>Absent</th><th>Late</th><th>Total</th><th>Rate</th></tr></thead><tbody>';
    if ($students) {
        $statStmt = $conn->prepare("SELECT 
            SUM(CASE WHEN ad.status='Present' THEN 1 ELSE 0 END) p,
            SUM(CASE WHEN ad.status='Absent' THEN 1 ELSE 0 END) a,
            SUM(CASE WHEN ad.status='Late' THEN 1 ELSE 0 END) l,
            COUNT(*) t
            FROM attendance a
            JOIN attendance_details ad ON a.id=ad.attendance_id
            WHERE a.created_by=? AND a.course_code=? AND ad.student_id=?");
        foreach ($students as $s) {
            $sid = $s['id_number'];
            $statStmt->bind_param("iss", $user_id, $pcourse, $sid);
            $statStmt->execute();
            $row = $statStmt->get_result()->fetch_assoc() ?: ['p'=>0,'a'=>0,'l'=>0,'t'=>0];
            $rate = ($row['t'] ?? 0) > 0 ? round((($row['p'] ?? 0)/$row['t'])*100) : 0;
            echo '<tr>';
            echo '<td>'.htmlspecialchars($sid).'</td>';
            echo '<td>'.htmlspecialchars($s['last_name'].', '.$s['first_name']).'</td>';
            echo '<td>'.(int)($row['p'] ?? 0).'</td>';
            echo '<td>'.(int)($row['a'] ?? 0).'</td>';
            echo '<td>'.(int)($row['l'] ?? 0).'</td>';
            echo '<td>'.(int)($row['t'] ?? 0).'</td>';
            echo '<td>'.$rate.'%</td>';
            echo '</tr>';
        }
        $statStmt->close();
    } else {
        echo '<tr><td colspan="7" class="text-center text-muted">No students found</td></tr>';
    }
    echo '</tbody></table></div>';

    // List all sessions for this course
    $sessStmt = $conn->prepare("SELECT id, attendance_date, year_level, section, TIME_FORMAT(start_time,'%h:%i %p') st, TIME_FORMAT(end_time,'%h:%i %p') et FROM attendance WHERE created_by=? AND course_code=? ORDER BY attendance_date ASC, start_time ASC");
    $sessStmt->bind_param("is", $user_id, $pcourse);
    $sessStmt->execute();
    $sessions = $sessStmt->get_result();
    if ($sessions && $sessions->num_rows) {
        echo '<hr class="my-4">';
        while ($sess = $sessions->fetch_assoc()) {
            $hdate = date('D, M d, Y', strtotime($sess['attendance_date']));
            echo '<div class="mb-3">';
            echo '<h5 class="mb-2">Session: '.htmlspecialchars($hdate).' • '.htmlspecialchars($sess['st']).' - '.htmlspecialchars($sess['et']).' • '.htmlspecialchars($sess['year_level']).' / '.htmlspecialchars($sess['section']).'</h5>';
            $detStmt = $conn->prepare("SELECT u.id_number, u.first_name, u.last_name, COALESCE(ad.status,'-') as status FROM users u LEFT JOIN attendance_details ad ON ad.student_id=u.id_number AND ad.attendance_id=? WHERE u.role=0 AND EXISTS (SELECT 1 FROM attendance a JOIN attendance_details ad2 ON a.id=ad2.attendance_id WHERE a.created_by=? AND a.course_code=? AND ad2.student_id=u.id_number) ORDER BY u.last_name,u.first_name");
            $detStmt->bind_param("iis", $sess['id'], $user_id, $pcourse);
            $detStmt->execute();
            $detRes = $detStmt->get_result();
            echo '<div class="table-responsive"><table class="table table-bordered table-sm align-middle"><thead class="table-light"><tr><th>ID Number</th><th>Name</th><th>Status</th></tr></thead><tbody>';
            if ($detRes && $detRes->num_rows) {
                while ($d = $detRes->fetch_assoc()) {
                    echo '<tr>';
                    echo '<td>'.htmlspecialchars($d['id_number']).'</td>';
                    echo '<td>'.htmlspecialchars($d['last_name'].', '.$d['first_name']).'</td>';
                    echo '<td>'.htmlspecialchars($d['status']).'</td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="3" class="text-center text-muted">No students found</td></tr>';
            }
            echo '</tbody></table></div>';
            $detStmt->close();
            echo '</div>';
        }
    }
    $sessStmt->close();
    echo '</body></html>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Teacher Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Segoe UI',sans-serif; background:#f5f7fa; display:flex; min-height:100vh; }

/* Sidebar */
.sidebar {
  width:70px;
  background:#1e293b;
  color:white;
  height:100vh;
  position:fixed;
  top:0;
  left:0;
  transition:width .3s ease;
  overflow:hidden;
  z-index:1000;
  box-shadow:2px 0 10px rgba(0,0,0,0.1);
}
.sidebar:hover { width:250px; }
.sidebar .logo {
  padding:20px;
  text-align:center;
  font-size:1.5rem;
  font-weight:700;
  color:#3b82f6;
  border-bottom:1px solid rgba(255,255,255,0.1);
}
.sidebar a {
  display:flex;
  align-items:center;
  gap:15px;
  padding:15px 20px;
  color:#94a3b8;
  text-decoration:none;
  transition:all .3s;
  white-space:nowrap;
}
.sidebar a:hover, .sidebar a.active {
  background:rgba(59,130,246,0.1);
  color:#3b82f6;
  border-left:3px solid #3b82f6;
}
.sidebar .icon { font-size:1.3rem; min-width:30px; }
.sidebar .text { opacity:0; transition:opacity .3s; }
.sidebar:hover .text { opacity:1; }

/* Top Bar */
.topbar {
  position:fixed;
  top:0;
  left:70px;
  right:0;
  height:70px;
  background:#fff;
  display:flex;
  justify-content:space-between;
  align-items:center;
  padding:0 30px;
  box-shadow:0 2px 8px rgba(0,0,0,0.08);
  z-index:999;
}
.topbar h2 {
  font-size:1.5rem;
  color:#1e293b;
  font-weight:700;
}
.topbar .user-section {
  display:flex;
  align-items:center;
  gap:15px;
}
.topbar .profile-img {
  width:45px;
  height:45px;
  border-radius:50%;
  object-fit:cover;
  border:2px solid #3b82f6;
  cursor:pointer;
}
.topbar .user-name {
  font-weight:600;
  color:#1e293b;
}
.topbar .change-photo {
  font-size:11px;
  background:#3b82f6;
  color:#fff;
  padding:4px 10px;
  border-radius:5px;
  cursor:pointer;
  transition:.3s;
}
.topbar .change-photo:hover { background:#2563eb; }
.topbar .logout-btn {
  background:#ef4444;
  color:#fff;
  padding:8px 16px;
  border-radius:6px;
  text-decoration:none;
  font-weight:600;
  transition:.3s;
}
.topbar .logout-btn:hover { background:#dc2626; }

.user-dropdown { position:relative; }
.user-dropdown .dropdown-toggle { display:flex; align-items:center; gap:10px; padding:6px 10px; border:1px solid #e5e7eb; border-radius:8px; background:#fff; cursor:pointer; }
.user-dropdown .dropdown-name { font-weight:600; color:#1e293b; }
.user-dropdown .caret { font-size:12px; color:#64748b; }
.user-dropdown .dropdown-menu { position:absolute; right:0; top:110%; min-width:220px; background:#fff; border:1px solid #e5e7eb; border-radius:8px; box-shadow:0 8px 20px rgba(0,0,0,0.08); padding:10px; display:none; z-index:1001; }
.user-dropdown.open .dropdown-menu { display:block; }
.user-dropdown .menu-item { display:block; width:100%; padding:8px 10px; border-radius:6px; text-decoration:none; color:#1e293b; background:#f8fafc; text-align:left; border:none; cursor:pointer; }
.user-dropdown .menu-item:hover { background:#eef2f7; }

/* Main Content */
.main {
  margin-left:70px;
  margin-top:70px;
  padding:30px;
  flex-grow:1;
  width:calc(100% - 70px);
}

/* Dashboard Cards */
.dashboard-cards {
  display:grid;
  grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));
  gap:20px;
  margin-bottom:30px;
}
.dashboard-card {
  background:#fff;
  border-radius:12px;
  padding:25px;
  box-shadow:0 2px 8px rgba(0,0,0,0.08);
  transition:transform .3s, box-shadow .3s;
  cursor:pointer;
  position:relative;
  overflow:hidden;
}
.dashboard-card::before {
  content:'';
  position:absolute;
  top:0;
  left:0;
  width:100%;
  height:5px;
  background:linear-gradient(90deg, #3b82f6, #8b5cf6);
}
.dashboard-card.courses::before { background:linear-gradient(90deg, #10b981, #14b8a6); }
.dashboard-card.students::before { background:linear-gradient(90deg, #f59e0b, #f97316); }
.dashboard-card:hover {
  transform:translateY(-5px);
  box-shadow:0 8px 20px rgba(0,0,0,0.15);
}
.dashboard-card .icon {
  font-size:2.5rem;
  margin-bottom:10px;
}
.dashboard-card h3 {
  font-size:1.2rem;
  color:#64748b;
  margin-bottom:10px;
}
.dashboard-card .count {
  font-size:2.5rem;
  font-weight:700;
  color:#1e293b;
}
.dashboard-card p {
  color:#94a3b8;
  margin-top:5px;
  font-size:0.9rem;
}

/* Sections */
.section { display:none; }
.default-screen {
  text-align:center;
  color:#1e293b;
  margin-top:50px;
  line-height:1.8;
}
.default-screen h3 {
  font-weight:700;
  color:#1e293b;
  margin-bottom:20px;
}

/* Tables */
.card {
  background:#fff;
  border-radius:12px;
  box-shadow:0 2px 8px rgba(0,0,0,0.08);
}
.table thead th {
  background:#f8fafc;
  color:#1e293b;
  font-weight:600;
  border-bottom:2px solid #e2e8f0;
}
.table tbody tr {
  border-bottom:1px solid #f1f5f9;
}
.table tbody tr:hover {
  background:#f8fafc;
}

/* Status Colors */
.Present { background:#d4edda!important; }
.Late { background:#fff3cd!important; }
.Absent { background:#f8d7da!important; }
.Excused { background:#d0e1f9!important; }
.status-btn {
  padding:6px 12px;
  border:none;
  border-radius:25px;
  cursor:pointer;
  font-size:13px;
  font-weight:bold;
  transition:.2s;
}
.present-btn { background:#2d6a4f;color:#fff; }
.late-btn { background:#ffb703;color:#14213d; }
.absent-btn { background:#d62828;color:#fff; }
.excused-btn { background:#1d3557;color:#fff; }
.status-btn:hover { transform:scale(1.05); opacity:.9; }
.create-form { display:none; margin-bottom:20px; }
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
  <div class="logo">📚</div>
  <a href="#" onclick="showSection('dashboard')" class="active">
    <span class="icon">🏠</span>
    <span class="text">Dashboard</span>
  </a>
  <a href="#" onclick="showSection('manage')">
    <span class="icon">📋</span>
    <span class="text">Manage Attendance</span>
  </a>
  <a href="#" onclick="showSection('courses')">
    <span class="icon">📚</span>
    <span class="text">Courses</span>
  </a>
  <a href="#" onclick="showSection('students')">
    <span class="icon">👨‍🎓</span>
    <span class="text">Manage Students</span>
  </a>
</div>

<!-- Top Bar -->
<div class="topbar">
  <h2>Teacher Dashboard</h2>
  <div class="user-section">
    <div class="user-dropdown" id="userDropdown">
      <div class="dropdown-toggle" id="userDropdownToggle">
        <img src="../<?= htmlspecialchars($_SESSION['profile_image'] ?? 'uploads/default.png') ?>" class="profile-img">
        <span class="dropdown-name"><?= htmlspecialchars($fullname) ?></span>
        <span class="caret">▼</span>
      </div>
      <div class="dropdown-menu">
        <div class="mb-2" style="padding:6px 8px; color:#64748b; font-size:12px;">Account</div>
        <form method="POST" enctype="multipart/form-data" id="profileFormTop">
          <input type="file" name="profile_image" id="profile_image_top" accept="image/*" class="d-none" onchange="document.getElementById('profileFormTop').submit()">
          <button type="button" class="menu-item" onclick="document.getElementById('profile_image_top').click()">Change Photo</button>
        </form>
        <a href="../controllers/logout.php" class="menu-item">Logout</a>
      </div>
    </div>
  </div>
</div>

<!-- Main Content -->
<div class="main">
<?php if ($message) echo "<div class='alert alert-info text-center'>".htmlspecialchars($message)."</div>"; ?>

<!-- Dashboard Section -->
<div id="dashboard" class="section" style="display:block;">
  <div class="default-screen">
    <h3>Welcome to the Teacher Dashboard</h3>
    <p class="mt-3">

    </p>
  </div>

  <div class="dashboard-cards mt-5">
    <div class="dashboard-card" onclick="showSection('manage')">
      <div class="icon">📋</div>
      <h3>Manage Attendance</h3>
      <div class="count"><?= $attendance_count ?></div>
      <p>Total attendance records</p>
    </div>

    <div class="dashboard-card courses" onclick="showSection('courses')">
      <div class="icon">📚</div>
      <h3>Courses</h3>
      <div class="count"><?= $courses_count ?></div>
      <p>Active courses</p>
    </div>

    <div class="dashboard-card students" onclick="showSection('students')">
      <div class="icon">👨‍🎓</div>
      <h3>Manage Students</h3>
      <div class="count"><?= $students_count ?></div>
      <p>Enrolled students</p>
    </div>
  </div>
</div>

<!-- Manage Attendance -->
<div id="manage" class="section">
<div class="card p-4">
<h5>📋 Manage Attendance <?= $filter_course ? "- $filter_course ($filter_year $filter_section)" : "" ?></h5>

<button class="btn btn-success mb-3" onclick="document.querySelector('.create-form').style.display='block'; this.style.display='none';">➕ Create New Attendance</button>

<div class="create-form card p-3 mb-3">
<form method="POST" class="row g-3">
  <div class="col-md-4">
    <?php
      // fetch previously used course codes by this teacher
      $courseOpts = $conn->query("SELECT DISTINCT course_code FROM attendance WHERE created_by = $user_id ORDER BY course_code ASC");
    ?>
    <select name="course_choice" id="course_choice" class="form-control" required onchange="toggleCourseInput()">
      <option value="">Select Course or choose New...</option>
      <option value="__new__">-- Create New Course --</option>
      <?php while($co = $courseOpts->fetch_assoc()): ?>
        <option value="<?= htmlspecialchars($co['course_code']) ?>"><?= htmlspecialchars($co['course_code']) ?></option>
      <?php endwhile; ?>
    </select>
    <input type="text" id="course_code_new" name="course_code_new" class="form-control" placeholder="Enter new course code" style="display:none">
    <textarea id="course_description_new" name="course_description_new" class="form-control mt-2" placeholder="Enter course description" rows="2" style="display:none"></textarea>
  </div>
  <script>
    function toggleCourseInput(){
      var sel = document.getElementById('course_choice');
      var txt = document.getElementById('course_code_new');
      var descField = document.getElementById('course_description_new');
      if(sel.value === '__new__') { txt.style.display = 'block'; descField.style.display = 'block'; } else { txt.style.display = 'none'; descField.style.display = 'none'; }
    }
  </script>

<script>
// Simple dropdown toggle
document.addEventListener('DOMContentLoaded', function(){
  var dd = document.getElementById('userDropdown');
  var toggle = document.getElementById('userDropdownToggle');
  if (dd && toggle) {
    toggle.addEventListener('click', function(e){
      e.stopPropagation();
      dd.classList.toggle('open');
    });
    document.addEventListener('click', function(){
      dd.classList.remove('open');
    });
  }
});
</script>

  <div class="col-md-4">
    <select name="year_level" class="form-control" required>
      <option value="">Select Year Level</option>
      <option value="1">1</option>
      <option value="2">2</option>
      <option value="3">3</option>
      <option value="4">4</option>
    </select>
  </div>

  <div class="col-md-4">
    <select name="section" class="form-control" required>
      <option value="">Select Section</option>
      <option value="A">A</option>
      <option value="B">B</option>
      <option value="C">C</option>
      <option value="D">D</option>
    </select>
  </div>

  <div class="col-md-4">
    <input type="date" name="attendance_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
  </div>

  <div class="col-md-4">
    <input type="time" name="start_time" class="form-control" placeholder="Start Time" required>
  </div>

  <div class="col-md-4">
    <input type="time" name="end_time" class="form-control" placeholder="End Time" required>
  </div>

  <div class="col-12">
    <button type="submit" name="create_attendance" class="btn btn-success mt-2">Create</button>
  </div>
</form>
</div>

<div class="table-responsive mt-3">
<table class="table table-bordered text-center align-middle">
<thead><tr><th>No.</th><th>Course</th><th>Description</th><th>Year</th><th>Section</th><th>Date</th><th>Time Range</th><th>Action</th></tr></thead>
<tbody>
<?php if ($schedules->num_rows > 0): $n=1; while($s=$schedules->fetch_assoc()): ?>
<tr>
<td><?= $n++ ?></td>
<td><?= htmlspecialchars($s['course_code']) ?></td>
<td><?= htmlspecialchars($s['course_description'] ?? '') ?></td>
<td><?= htmlspecialchars($s['year_level']) ?></td>
<td><?= htmlspecialchars($s['section']) ?></td>
<td><?= htmlspecialchars($s['attendance_date']) ?></td>
<td>
  <?php
    $stDisp = $s['start_time'] ? date('g:i A', strtotime($s['start_time'])) : '';
    $etDisp = $s['end_time'] ? date('g:i A', strtotime($s['end_time'])) : '';
  ?>
  <?= htmlspecialchars(trim($stDisp)) ?><?= $etDisp ? ' - ' : '' ?><?= htmlspecialchars(trim($etDisp)) ?>
</td>
<td>
  <a href="?view=<?= $s['id'] ?>" class="btn btn-primary btn-sm">View</a>
  <a href="../controllers/delete_attendance.php?id=<?= $s['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this record?')">Delete</a>
</td>
</tr>
<?php endwhile; else: ?>
<tr><td colspan="8">No attendance records</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</div>

<!-- Courses Section -->
<div id="courses" class="section">
<div class="card p-4">
<h5>📚 Courses</h5>
<div class="table-responsive mt-3">
<table class="table table-bordered text-center align-middle">
<thead><tr><th>Course Code</th><th>Description</th><th>Total Sessions</th><th>Unique Students</th><th>Action</th></tr></thead>
<tbody>
<?php
$courses_query = $conn->query("
  SELECT a.course_code,
         c.course_name,
         c.course_description,
         COUNT(*) AS sessions,
         COUNT(DISTINCT ad.student_id) AS student_count
  FROM attendance a
  LEFT JOIN attendance_details ad ON a.id = ad.attendance_id
  LEFT JOIN courses c ON c.course_code = a.course_code AND c.teacher_id = $user_id
  WHERE a.created_by = $user_id
  GROUP BY a.course_code, c.course_name, c.course_description
  ORDER BY a.course_code ASC
");
if ($courses_query->num_rows > 0):
  while($course = $courses_query->fetch_assoc()):
?>
<tr>
<td><?= htmlspecialchars($course['course_code']) ?></td>
<td><?= htmlspecialchars($course['course_description'] ?? ($course['course_name'] ?? '')) ?></td>
<td><?= (int)$course['sessions'] ?></td>
<td><?= (int)$course['student_count'] ?></td>
<td>
  <a href="?course=<?= urlencode($course['course_code']) ?>" class="btn btn-info btn-sm me-1">View History</a>
  <a href="teacher.php?print=course&course=<?= urlencode($course['course_code']) ?>" target="_blank" class="btn btn-secondary btn-sm me-1">Print</a>
  <button type="button" class="btn btn-outline-primary btn-sm" onclick="toggleCourseSessions('<?= rawurlencode($course['course_code']) ?>')">Sessions</button>
  <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleEditCourse('<?= rawurlencode($course['course_code']) ?>','ALL','ALL')">Edit</button>
  <div id="edit-course-<?= rawurlencode($course['course_code']) ?>-ALL-ALL" class="card p-2 mt-2" style="display:none;">
    <form method="POST" class="row g-2">
      <input type="hidden" name="uc_course_code" value="<?= htmlspecialchars($course['course_code']) ?>">
      <div class="col-md-4">
        <input type="text" class="form-control form-control-sm" name="uc_course_name" placeholder="Course name" value="<?= htmlspecialchars($course['course_name'] ?? '') ?>">
      </div>
      <div class="col-md-6">
        <input type="text" class="form-control form-control-sm" name="uc_course_description" placeholder="Course description" value="<?= htmlspecialchars($course['course_description'] ?? '') ?>">
      </div>
      <div class="col-md-2 d-grid">
        <button type="submit" name="update_course" class="btn btn-success btn-sm">Save</button>
      </div>
    </form>
    <div class="text-muted small mt-1">Editing applies to all sections for this course code.</div>
  </div>
</td>
</tr>
<tr id="sessions-row-<?= rawurlencode($course['course_code']) ?>" style="display:none;">
  <td colspan="5">
    <div class="card p-2">
      <div class="fw-semibold mb-2">Previous Sessions for <?= htmlspecialchars($course['course_code']) ?></div>
      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle text-center mb-0">
          <thead class="table-light"><tr><th>Date</th><th>Start</th><th>End</th><th>Year</th><th>Section</th><th>Action</th></tr></thead>
          <tbody>
          <?php
            $code = $course['course_code'];
            $sess = $conn->prepare("SELECT id, attendance_date, year_level, section, TIME_FORMAT(start_time,'%h:%i %p') st, TIME_FORMAT(end_time,'%h:%i %p') et FROM attendance WHERE created_by=? AND course_code=? ORDER BY attendance_date DESC, start_time DESC");
            $sess->bind_param("is", $user_id, $code);
            $sess->execute();
            $sessRes = $sess->get_result();
            if ($sessRes && $sessRes->num_rows):
              while($sr = $sessRes->fetch_assoc()):
          ?>
            <tr>
              <td><?= htmlspecialchars($sr['attendance_date']) ?></td>
              <td><?= htmlspecialchars($sr['st']) ?></td>
              <td><?= htmlspecialchars($sr['et']) ?></td>
              <td><?= htmlspecialchars($sr['year_level']) ?></td>
              <td><?= htmlspecialchars($sr['section']) ?></td>
              <td>
                <a href="?view=<?= (int)$sr['id'] ?>" class="btn btn-primary btn-sm">View</a>
              </td>
            </tr>
          <?php
              endwhile;
            else:
          ?>
            <tr><td colspan="6" class="text-muted">No sessions yet.</td></tr>
          <?php endif; $sess->close(); ?>
          </tbody>
        </table>
      </div>
    </div>
  </td>
</tr>
<?php endwhile; else: ?>
<tr><td colspan="5">No courses found</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</div>
<!-- Manage Students Section -->
<div id="students" class="section">
<div class="card p-4">
<h5>👨‍🎓 Manage Students</h5>

<?php
// First, get all unique year level and section combinations
$groups_query = $conn->query("
  SELECT DISTINCT year_level, section
  FROM users
  WHERE role = 0
  ORDER BY year_level, section
");

if ($groups_query->num_rows > 0):
  while($group = $groups_query->fetch_assoc()):
    $year = $group['year_level'];
    $section = $group['section'];
?>

<div class="mt-4">
  <h6 class="mb-3">Year <?= htmlspecialchars($year) ?> - Section <?= htmlspecialchars($section) ?></h6>
  <div class="table-responsive">
    <table class="table table-bordered text-center align-middle">
      <thead>
        <tr>
          <th>ID Number</th>
          <th>Name</th>
          <th>Total Attendance</th>
        </tr>
      </thead>
      <tbody>
      <?php
      $students_query = $conn->query("
        SELECT DISTINCT u.id_number, u.first_name, u.last_name,
        COUNT(ad.id) as attendance_count
        FROM users u
        LEFT JOIN attendance_details ad ON u.id_number = ad.student_id
        LEFT JOIN attendance a ON ad.attendance_id = a.id
        WHERE u.role = 0 
        AND u.year_level = '$year' 
        AND u.section = '$section'
        AND a.created_by = $user_id
        GROUP BY u.id_number
        ORDER BY u.last_name, u.first_name
      ");
      
      if ($students_query->num_rows > 0):
        while($student = $students_query->fetch_assoc()):
      ?>
      <tr>
        <td><?= htmlspecialchars($student['id_number']) ?></td>
        <td><?= htmlspecialchars($student['first_name']." ".$student['last_name']) ?></td>
        <td><?= $student['attendance_count'] ?></td>
      </tr>
      <?php endwhile; else: ?>
      <tr><td colspan="3">No students found</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php 
  endwhile;
else: 
?>
<div class="mt-3">
  <p class="text-muted">No students found</p>
</div>
<?php endif; ?>

</div>
</div>

<?php if ($view_attendance_id): ?>
<div id="view" class="section">
<div class="card p-4">
<h5>📋 Attendance Details</h5>
<div class="d-flex justify-content-between mb-3">
<a href="#" onclick="showSection('manage')" class="btn btn-secondary">← Back</a>
<a href="?view=<?= $view_attendance_id ?>&add=1" class="btn btn-warning">+ Add Student</a>
</div>

<?php if (!isset($_GET['add'])): ?>
<div class="table-responsive">
<table class="table table-hover align-middle text-center" id="attendanceTable">
<thead class="table-dark"><tr><th>ID Number</th><th>Name</th><th>Status</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach ($attendance_students as $row): ?>
<tr class="<?= $row['status'] ?>" data-detail-id="<?= $row['detail_id'] ?>">
<td><?= htmlspecialchars($row['id_number']) ?></td>
<td><?= htmlspecialchars($row['first_name']." ".$row['last_name']) ?></td>
<td class="status-text"><strong><?= htmlspecialchars($row['status']) ?></strong></td>
<td class="d-flex justify-content-center gap-2">
<?php foreach (['Present'=>'✔','Late'=>'⏰','Absent'=>'❌','Excused'=>'📘'] as $status=>$icon): ?>
<button class="status-btn <?= strtolower($status) ?>-btn" onclick="updateStatus(<?= $row['detail_id'] ?>,'<?= $status ?>',this)">
<?= $icon ?> <?= $status ?>
</button>
<?php endforeach; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<?php else: ?>
<div class="table-responsive mt-3">
<form method="GET" class="d-flex mb-3">
<input type="hidden" name="view" value="<?= $view_attendance_id ?>">
<input type="text" name="search" class="form-control me-2" placeholder="Search student..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
<button class="btn btn-info">Search</button>
</form>
<table class="table table-bordered table-striped text-center align-middle">
<thead><tr><th>ID Number</th><th>Name</th><th>Year</th><th>Section</th><th>Action</th></tr></thead>
<tbody>
<?php foreach ($add_student_list as $s): ?>
<tr>
<td><?= htmlspecialchars($s['id_number']) ?></td>
<td><?= htmlspecialchars($s['first_name']." ".$s['last_name']) ?></td>
<td><?= htmlspecialchars($s['year_level']) ?></td>
<td><?= htmlspecialchars($s['section']) ?></td>
<td>
<?php if (in_array($s['id_number'], $added_students)): ?>
<button class="btn btn-success" disabled>Added</button>
<?php else: ?>
<form method="POST" class="d-inline">
<input type="hidden" name="add_student_id" value="<?= htmlspecialchars($s['id_number']) ?>">
<button type="submit" class="btn btn-warning">Add</button>
</form>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>

</div>
</div>
<?php endif; ?>

</div>

<script>
function showSection(section, dontUpdateUrl = false) {
  document.querySelectorAll('.section').forEach(sec => sec.style.display = 'none');
  document.getElementById(section).style.display = 'block';
  
  // Update active link
  document.querySelectorAll('.sidebar a').forEach(link => link.classList.remove('active'));
  let sidebarLink = document.querySelector(`.sidebar a[onclick*="showSection('${section}')"]`);
  if (sidebarLink) {
    sidebarLink.classList.add('active');
  }

  // Keep URL parameters when switching sections
  if (!dontUpdateUrl) {
    const urlParams = new URLSearchParams(window.location.search);
    const preserveParams = ['course', 'year', 'section'];
    const newParams = new URLSearchParams();
    
    preserveParams.forEach(param => {
      if (urlParams.has(param)) {
        newParams.set(param, urlParams.get(param));
      }
    });

    const newUrl = newParams.toString() ? 
      `?${newParams.toString()}` : 
      window.location.pathname;
    
    history.pushState(null, '', newUrl);
  }
}

function updateStatus(detailId,status,btn){
fetch('../controllers/update_status.php',{
method:'POST',
headers:{'Content-Type':'application/x-www-form-urlencoded'},
body:`detail_id=${detailId}&status=${status}`
}).then(res=>res.text())
.then(data=>{
  const row = btn.closest('tr');
  row.className = status;
  row.querySelector('.status-text').textContent=status;
}).catch(err=>console.error(err));
}

function viewCourse(course, year, section) {
  const params = new URLSearchParams({
    course: course,
    year: year,
    section: section
  });
  window.location.href = `?${params.toString()}`;
}

function toggleEditCourse(courseCode, yearLevel, section){
  var id = 'edit-course-' + courseCode + '-' + yearLevel + '-' + section;
  var el = document.getElementById(id);
  if(!el) return;
  var visible = window.getComputedStyle(el).display !== 'none';
  el.style.display = visible ? 'none' : 'block';
}

function toggleCourseSessions(courseCode){
  // courseCode is already rawurlencoded in the markup id
  var id = 'sessions-row-' + courseCode;
  var row = document.getElementById(id);
  if(!row) return;
  var visible = window.getComputedStyle(row).display !== 'none';
  row.style.display = visible ? 'none' : 'table-row';
  if (!visible) {
    row.scrollIntoView({behavior:'smooth', block:'nearest'});
  }
}

window.addEventListener('DOMContentLoaded', () => {
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.has('view')) {
    showSection('view', true);
  } else if (urlParams.has('course') && urlParams.has('year') && urlParams.has('section')) {
    showSection('manage', true);
  } else if (urlParams.has('course')) {
    // If only course is provided, still show Manage with course-wide history
    showSection('manage', true);
  }
});
</script>

</body>
</html>