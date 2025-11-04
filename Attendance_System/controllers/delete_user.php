<?php
session_start();
include "../config/db.php";

if (empty($_SESSION['first_name']) || empty($_SESSION['last_name'])) {
    header("Location: ../index.php");
    exit;
}

if (isset($_GET['id'])) {
    $user_id = intval($_GET['id']);

    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        $_SESSION['delete_message'] = "User deleted successfully.";
    } else {
        $_SESSION['delete_message'] = "Error deleting user.";
    }

    $stmt->close();
    $conn->close();
}

header("Location: ../views/admin.php");
exit;
?>
