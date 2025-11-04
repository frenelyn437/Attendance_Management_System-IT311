<?php
session_start();

if (!isset($_SESSION['first_name'], $_SESSION['last_name'])) {
    header("Location: ../index.php");
    exit;
}

$fullname = $_SESSION['first_name'] . " " . $_SESSION['last_name'];

require "../config/db.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course = trim($_POST['course'] ?? '');
    $year = trim($_POST['year'] ?? '');
    $section = trim($_POST['section'] ?? '');
    $attendanceDate = trim($_POST['attendance_date'] ?? '');

    if ($course && $year && $section && $attendanceDate) {
        $stmt = $conn->prepare("INSERT INTO attendance (course_code, year_level, section, attendance_date, created_by) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            $createdBy = $_SESSION['user_id'] ?? 0;
            $stmt->bind_param("sissi", $course, $year, $section, $attendanceDate, $createdBy);
            if ($stmt->execute()) {
                $success = "Attendance record saved.";
            } else {
                $error = "Something went wrong while saving.";
            }
            $stmt->close();
        } else {
            $error = "Database error: " . $conn->error;
        }
    } else {
        $error = "All fields are required.";
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Attendance</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;font-family:Segoe UI, Tahoma, sans-serif}
        body{background:#87ceeb;color:#333}
        nav{height:60px;background:#14213d;display:flex;justify-content:space-between;align-items:center;padding:0 2rem;color:#fff;box-shadow:0 3px 8px rgba(0,0,0,.3)}
        nav p{font-size:1.4rem;font-weight:600}
        nav div{display:flex;gap:1rem;align-items:center}
        .logoutBtn{background:#fca311;color:#14213d;padding:8px 18px;border-radius:6px;font-weight:600;text-decoration:none;transition:.25s}
        .logoutBtn:hover{background:#d48806}
        .container{max-width:500px;margin:2rem auto;background:#fff;padding:2rem;border-radius:10px;box-shadow:0 4px 10px rgba(0,0,0,.1)}
        h2{color:#14213d;margin-bottom:1rem;text-align:center}
        label{font-weight:600;margin-top:1rem;display:block}
        input,select{width:100%;padding:.7rem;margin-top:.3rem;border:1.5px solid #ccc;border-radius:6px;font-size:1rem;transition:.25s}
        input:focus,select:focus{border-color:#14213d;outline:none}
        button{margin-top:1.5rem;background:#fca311;color:#14213d;padding:.8rem;border:none;border-radius:6px;font-weight:700;cursor:pointer;transition:.25s;width:100%}
        button:hover{background:#d48806}
        .message{margin-top:1rem;padding:.8rem;border-radius:6px;text-align:center}
        .success{background:#d4edda;color:#155724}
        .error{background:#f8d7da;color:#721c24}
    </style>
</head>
<body>
    <nav>
        <p>Attendance Management</p>
        <div>
            <span><?= htmlspecialchars($fullname) ?></span>
            <a href="../controllers/logout.php" class="logoutBtn">Logout</a>
        </div>
    </nav>
    <div class="container">
        <h2>Create Attendance</h2>
        <?php if (!empty($success)): ?>
            <div class="message success"><?= htmlspecialchars($success) ?></div>
        <?php elseif (!empty($error)): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <label for="course">Course Code</label>
            <input type="text" id="course" name="course" required>
            
            <label for="year">Year Level</label>
            <select id="year" name="year" required>
                <option value="">-- Select Year --</option>
                <option value="1">1st Year</option>
                <option value="2">2nd Year</option>
                <option value="3">3rd Year</option>
                <option value="4">4th Year</option>
            </select>
            
            <label for="section">Section</label>
            <select id="section" name="section" required>
                <option value="">-- Select Section --</option>
                <option value="A">Section A</option>
                <option value="B">Section B</option>
                <option value="C">Section C</option>
                <option value="D">Section D</option>
            </select>
            
            <label for="attendance_date">Date</label>
            <input type="date" id="attendance_date" name="attendance_date" required>

            <label for="start_time">Start Time</label>
            <input type="time" id="start_time" name="start_time" required>

            <label for="end_time">End Time</label>
            <input type="time" id="end_time" name="end_time" required>
            
            <button type="submit">Save Attendance</button>
        </form>
    </div>
</body>
</html>
