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

// --- PROFILE IMAGE UPLOAD ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image'])) {
    $targetDir = "../uploads/profiles/";
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

    $fileName = basename($_FILES['profile_image']['name']);
    $targetFilePath = $targetDir . $fileName;
    $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
    $allowedTypes = ['jpg','jpeg','png','gif','webp'];

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

// --- BLOCK / UNBLOCK STUDENTS ---
if (isset($_GET['block_id'])) {
    $id = intval($_GET['block_id']);
    $conn->query("UPDATE users SET status='blocked' WHERE id=$id");
}

if (isset($_GET['unblock_id'])) {
    $id = intval($_GET['unblock_id']);
    $conn->query("UPDATE users SET status='active' WHERE id=$id");
}

// --- CHANGE PASSWORD ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $student_id = intval($_POST['student_id']);
    $new_pass = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
    $stmt->bind_param("si", $new_pass, $student_id);
    if ($stmt->execute()) {
        $message = "✅ Password changed successfully!";
    } else {
        $message = "❌ Failed to update password.";
    }
    $stmt->close();
}

// --- CREATE ATTENDANCE SCHEDULE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_schedule'])) {
    $course_code = trim($_POST['course_code']);
    $year_level = trim($_POST['year_level']);
    $section = trim($_POST['section']);
    $attendance_date = $_POST['attendance_date'];

    if (!empty($course_code) && !empty($year_level) && !empty($section) && !empty($attendance_date)) {
        $sql = "INSERT INTO attendance (course_code, year_level, section, attendance_date, created_by) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssi", $course_code, $year_level, $section, $attendance_date, $user_id);
        if ($stmt->execute()) {
            $message = "✅ Attendance schedule created successfully!";
        } else {
            $message = "❌ Error saving schedule: " . $conn->error;
        }
        $stmt->close();
    } else {
        $message = "⚠️ Please fill in all fields.";
    }
}

// --- FETCH DATA ---
$schedules = $conn->query("SELECT * FROM attendance WHERE created_by = $user_id ORDER BY created_at DESC");
$students = $conn->query("SELECT * FROM users WHERE role=0 ORDER BY first_name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Teacher Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { font-family: 'Segoe UI', sans-serif; background:#f4f6f9; display:flex; }
.sidebar { width:250px; background:#14213d; color:white; height:100vh; position:fixed; top:0; left:0; padding-top:20px; }
.sidebar .profile { text-align:center; padding-bottom:15px; }
.sidebar .profile img { width:70px; height:70px; border-radius:50%; object-fit:cover; border:2px solid #fca311; }
.sidebar .profile h5 { margin-top:10px; color:#fca311; font-size:15px; }
.sidebar label { font-size:11px; background:#fca311; color:#14213d; padding:3px 6px; border-radius:5px; cursor:pointer; }
.sidebar a { display:block; padding:10px 20px; color:white; text-decoration:none; transition:0.3s; }
.sidebar a:hover { background:#1e3c72; }
.sidebar .submenu { display:none; padding-left:20px; }
.sidebar .submenu a { font-size:15px; }
.sidebar .toggle-btn { cursor:pointer; padding:10px 20px; color:#fca311; font-weight:bold; }
.main { margin-left:250px; padding:30px; flex-grow:1; }
.default-screen { text-align:center; color:#14213d; margin-top:100px; line-height:1.7; }
.default-screen h3 { font-weight:bold; color:#1e3c72; }
/* Attendance table styling */
.table-container { background:#fff; border-radius:12px; box-shadow:0 4px 16px rgba(0,0,0,.15); padding:20px; }
.status-btn { padding:6px 12px; border:none; border-radius:25px; cursor:pointer; font-size:13px; font-weight:bold; transition:.2s; }
.present-btn { background:#2d6a4f; color:#fff; }
.late-btn { background:#ffb703; color:#14213d; }
.absent-btn { background:#d62828; color:#fff; }
.excused-btn { background:#1d3557; color:#fff; }
.status-btn:hover { transform:scale(1.05); opacity:.9; }
.Present { background:#d4edda !important; }
.Late { background:#fff3cd !important; }
.Absent { background:#f8d7da !important; }
.Excused { background:#d0e1f9 !important; }
</style>
</head>
<body>

<div class="sidebar">
  <div class="profile">
    <img src="../<?= htmlspecialchars($_SESSION['profile_image'] ?? 'uploads/default.png') ?>">
    <h5><?= htmlspecialchars($fullname) ?></h5>
    <form method="POST" enctype="multipart/form-data">
      <label for="profile_image">Change Photo</label>
      <input type="file" name="profile_image" id="profile_image" accept="image/*" class="d-none" onchange="this.form.submit()">
    </form>
  </div>
  <hr>
  <div class="toggle-btn" onclick="toggleMenu()">▶ Dashboard</div>
  <div class="submenu" id="submenu">
    <a href="#" onclick="showSection('create')">📅 Create Attendance</a>
    <a href="#" onclick="showSection('manage')">🗂 Manage Attendance</a>
    <a href="#" onclick="showSection('students')">👩‍🎓 Manage Students</a>
    <a href="../controllers/logout.php" class="text-danger">🚪 Logout</a>
  </div>
</div>

<div class="main">
  <?php if (!empty($message)): ?>
    <p class="text-center fw-semibold"><?= htmlspecialchars($message) ?></p>
  <?php endif; ?>

  <!-- Default Screen -->
  <div id="default" class="default-screen">
    <h3>💻 CICS (College of Information and Computing Sciences)</h3>
    <p class="mt-3"><b>Vision:</b> CICS envisions itself as a dynamic hub of creativity and innovation—shaping future technologists, problem solvers, and visionaries.<br><br>
    <b>Mission and Approach:</b><br>
    • Nurtures students to become programmers, software engineers, and tech support specialists.<br>
    • Promotes critical thinking, research-based instruction, and experiential learning.<br>
    • Complies with CHED’s minimum requirements for IT institutions.</p>
  </div>

  <!--
