<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

if (isset($_GET['id'])) {
    $attendanceId = intval($_GET['id']);
    $created_by = $_SESSION['user_id'];

    $stmtDetails = $conn->prepare("DELETE FROM attendance_details WHERE attendance_id = ?");
    $stmtDetails->bind_param("i", $attendanceId);
    $stmtDetails->execute();
    $stmtDetails->close();

    $stmt = $conn->prepare("DELETE FROM attendance WHERE id = ? AND created_by = ?");
    $stmt->bind_param("ii", $attendanceId, $created_by);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Attendance record deleted successfully.";
    } else {
        $_SESSION['message'] = "Error deleting attendance record.";
    }

    $stmt->close();
}

$conn->close();
header("Location: ../views/teacher.php");
exit;
?>
