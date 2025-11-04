<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION['user_id'], $_SESSION['first_name'], $_SESSION['last_name'])) {
    header("Location: ../index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../views/student.php");
    exit;
}

$detail_id = isset($_POST['detail_id']) ? intval($_POST['detail_id']) : 0;
$attendance_id = isset($_POST['attendance_id']) ? intval($_POST['attendance_id']) : 0;
$status = isset($_POST['status']) ? trim($_POST['status']) : '';

$allowed_statuses = ['Present', 'Late', 'Absent'];
if (!in_array($status, $allowed_statuses, true)) {
    $_SESSION['error'] = 'Invalid status selection.';
    header("Location: ../views/student.php");
    exit;
}

// Fetch attendance detail ensuring it belongs to the logged in student and is for today and currently unset '-'
$id_number = null;
$stmt = $conn->prepare("SELECT id_number FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $id_number = $row['id_number'];
}
$stmt->close();

if (!$id_number) {
    $_SESSION['error'] = 'Unable to validate your account.';
    header("Location: ../views/student.php");
    exit;
}

$sql = "SELECT ad.id AS detail_id, ad.status, a.attendance_date, a.course_code
        FROM attendance_details ad
        JOIN attendance a ON a.id = ad.attendance_id
        WHERE ad.id = ? AND ad.attendance_id = ? AND ad.student_id = ? LIMIT 1";
$check = $conn->prepare($sql);
$check->bind_param("iis", $detail_id, $attendance_id, $id_number);
$check->execute();
$valid = $check->get_result()->fetch_assoc();
$check->close();

if (!$valid) {
    $_SESSION['error'] = 'Record not found or does not belong to you.';
    header("Location: ../views/student.php");
    exit;
}

$today = date('Y-m-d');
if ($valid['attendance_date'] !== $today) {
    $_SESSION['error'] = 'You can only submit status for today\'s schedule.';
    header("Location: ../views/student.php");
    exit;
}

if ($valid['status'] !== '-') {
    $_SESSION['error'] = 'Status already submitted.';
    header("Location: ../views/student.php");
    exit;
}

$upd = $conn->prepare("UPDATE attendance_details SET status = ? WHERE id = ?");
$upd->bind_param("si", $status, $detail_id);
if ($upd->execute()) {
    $_SESSION['success'] = 'Attendance submitted successfully.';
} else {
    $_SESSION['error'] = 'Failed to submit attendance.';
}
$upd->close();

header("Location: ../views/student.php");
exit;
