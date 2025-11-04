<?php
session_start();
include "../config/db.php";

if(isset($_GET['id'])){
    $id = intval($_GET['id']);
    $conn->query("UPDATE users SET status='active' WHERE id=$id");
}
header("Location: ../views/admin_dashboard.php");
exit;
?>