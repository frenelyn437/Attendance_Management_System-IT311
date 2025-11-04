<?php
session_start();
include "../config/db.php";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname = sanitize($_POST['fname'] ?? '');
    $lname = sanitize($_POST['lname'] ?? '');
    $idnum = sanitize($_POST['idnum'] ?? '');
    $yearLevel = sanitize($_POST['yearLevel'] ?? '');
    $section = sanitize($_POST['section'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';

    if ($password && $confirmPassword && $password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE id_number = ?");
        if ($stmt) {
            $stmt->bind_param('s', $idnum);
            $stmt->execute();
            $stmt->bind_result($count);
            $stmt->fetch();
            $stmt->close();

            if ($count > 0) {
                $errors[] = "ID Number already registered.";
            }
        } else {
            $errors[] = "Database query error.";
        }
    }

    if (empty($errors)) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, id_number, year_level, section, password) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('ssssss', $fname, $lname, $idnum, $yearLevel, $section, $passwordHash);
            if ($stmt->execute()) {
                $stmt->close();
                $conn->close();
                header("Location: ../index.php");
                exit;
            } else {
                $errors[] = "Failed to register, please try again.";
                $stmt->close();
            }
        } else {
            $errors[] = "Database query error.";
        }
    }

    if (!empty($errors)) {
        $_SESSION['register_errors'] = $errors;
        $conn->close();
        header("Location: ../views/register.php");
        exit;
    }
} else {
    $conn->close();
    header("Location: ../views/register.php");
    exit;
}
?>
