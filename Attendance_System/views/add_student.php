<?php
session_start();
include "../config/db.php";

if (!isset($_GET['attendance_id'])) {
    header("Location: teacher_dashboard.php");
    exit;
}

$attendance_id = intval($_GET['attendance_id']);
$error = "";
$search = "";

if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}

$sql = "SELECT id_number, first_name, last_name, year_level, section 
        FROM users 
        WHERE role = '0' AND status = 'active'";

$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (id_number LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR section LIKE ? OR year_level LIKE ?)";
    $params = array_fill(0, 5, "%" . $search . "%");
    $types = "sssss";
}

$stmt = $conn->prepare($sql);
if (!empty($search)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$students_result = $stmt->get_result();

$added_students = [];
$added_sql = "SELECT student_id FROM attendance_details WHERE attendance_id = ?";
$added_stmt = $conn->prepare($added_sql);
$added_stmt->bind_param("i", $attendance_id);
$added_stmt->execute();
$res_added = $added_stmt->get_result();
while ($r = $res_added->fetch_assoc()) {
    $added_students[] = $r['student_id'];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['student_id'])) {
    $student_id = trim($_POST['student_id']);

    $check_sql = "SELECT id_number FROM users WHERE id_number = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $student_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $dup_sql = "SELECT id FROM attendance_details WHERE attendance_id = ? AND student_id = ?";
        $dup_stmt = $conn->prepare($dup_sql);
        $dup_stmt->bind_param("is", $attendance_id, $student_id);
        $dup_stmt->execute();
        $dup_result = $dup_stmt->get_result();

        if ($dup_result->num_rows > 0) {
            $error = "Student already added.";
        } else {
            $insert_sql = "INSERT INTO attendance_details (attendance_id, student_id, status) VALUES (?, ?, '-')";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("is", $attendance_id, $student_id);
            $insert_stmt->execute();
            header("Location: add_student.php?attendance_id=" . $attendance_id);
            exit;
        }
    } else {
        $error = "Student not found.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Student</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: skyblue;
            min-height: 100vh;
        }
        .container {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.15);
            padding: 25px 30px;
            margin-top: 40px;
        }
        h2 {
            color: #14213d;
            text-align: center;
            margin-bottom: 25px;
            font-weight: 700;
        }
        .btn-back {
            background: #14213d;
            color: #fff;
            font-weight: bold;
        }
        .btn-back:hover {
            background: #000814;
        }
        .btn-search {
            background: #fca311;
            color: #14213d;
            font-weight: bold;
        }
        .btn-search:hover {
            background: #e29500;
            color: #fff;
        }
        .btn-add {
            background: #fca311;
            color: #14213d;
            font-weight: bold;
            border-radius: 6px;
        }
        .btn-add:hover {
            background: #e29500;
            color: #fff;
        }
        .btn-added {
            background: #28a745;
            color: #fff;
            border-radius: 6px;
            font-weight: bold;
            cursor: default;
        }
        table {
            border-radius: 8px;
            overflow: hidden;
        }
        th {
            background: #14213d;
            color: white;
        }
        .error {
            color: red;
            text-align: center;
            font-size: 15px;
            margin-top: 10px;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Add Student to Attendance</h2>

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
        <a href="view_attendance.php?id=<?php echo $attendance_id; ?>" class="btn btn-back">← Go Back</a>
        <form class="d-flex" method="GET">
            <input type="hidden" name="attendance_id" value="<?php echo $attendance_id; ?>">
            <input type="text" name="search" class="form-control me-2" placeholder="Search student..." value="<?php echo htmlspecialchars($search); ?>">
            <button class="btn btn-search" type="submit">Search</button>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle text-center">
            <thead>
                <tr>
                    <th>ID Number</th>
                    <th>Full Name</th>
                    <th>Year</th>
                    <th>Section</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($students_result && $students_result->num_rows > 0): ?>
                    <?php while ($s = $students_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($s['id_number']); ?></td>
                            <td><?php echo htmlspecialchars($s['first_name'] . " " . $s['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($s['year_level']); ?></td>
                            <td><?php echo htmlspecialchars($s['section']); ?></td>
                            <td>
                                <?php if (in_array($s['id_number'], $added_students)): ?>
                                    <button class="btn btn-added" disabled>Added</button>
                                <?php else: ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($s['id_number']); ?>">
                                        <button type="submit" class="btn btn-add">Add</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5">No students found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
</div>

</body>
</html>
