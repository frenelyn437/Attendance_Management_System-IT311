<?php
session_start();
include "../config/db.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course = $_POST['course'];
    $year = intval($_POST['year']);
    $section = $_POST['section'];
    $attendance_date = $_POST['attendance_date'];
    $start_time = $_POST['start_time'] ?? null;
    $end_time = $_POST['end_time'] ?? null;
    $created_by = $_SESSION['user_id'];

    // Validate time range if provided
    if ($start_time && $end_time && $start_time >= $end_time) {
        $_SESSION['error'] = 'Start time must be before end time.';
        header("Location: ../views/create_attendance.php");
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO attendance (course_code, year_level, section, attendance_date, start_time, end_time, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sissssi", $course, $year, $section, $attendance_date, $start_time, $end_time, $created_by);
    $stmt->execute();
    $attendance_id = $stmt->insert_id;
    $stmt->close();

    $students = $conn->query("SELECT id_number FROM users WHERE year_level=$year AND section='$section'");
    
    while ($student = $students->fetch_assoc()) {
        $sid = $conn->real_escape_string($student['id_number']);
        $conn->query("INSERT INTO attendance_details (attendance_id, student_id, status) VALUES ($attendance_id, '$sid', '-')");
    }

    $_SESSION['success'] = "Attendance created with " . $students->num_rows . " students.";
    header("Location: ../views/view_attendance.php?id=$attendance_id");
    exit;
}
?>
