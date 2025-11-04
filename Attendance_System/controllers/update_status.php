<?php
session_start();
include "../config/db.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $detail_id = intval($_POST['detail_id']);
    $attendance_id = intval($_POST['attendance_id']);
    $status = $_POST['status'];

    $allowed_statuses = ['Present', 'Late', 'Absent', 'Excused'];
    if (!in_array($status, $allowed_statuses)) {
        die("Invalid status.");
    }

    $update = $conn->prepare("UPDATE attendance_details SET status = ? WHERE id = ?");
    if ($update) {
        $update->bind_param("si", $status, $detail_id);
        $update->execute();
        $update->close();
    } else {
        die("Database error: " . $conn->error);
    }

    header("Location: ../views/view_attendance.php?id=" . $attendance_id);
    exit;
}
?>
