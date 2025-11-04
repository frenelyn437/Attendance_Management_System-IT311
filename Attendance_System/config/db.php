<?php
// Set default timezone for consistent time comparisons (change if needed)
date_default_timezone_set('Asia/Manila');

$host = 'localhost';
$dbname = 'attendance_db';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}
?>
