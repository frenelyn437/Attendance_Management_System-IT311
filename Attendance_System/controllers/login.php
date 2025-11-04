<?php
session_start();
include "../config/db.php";

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idnum = sanitize($_POST['idnum'] ?? '');
    $password = $_POST['password'] ?? '';

    $errors = [];

    if (empty($idnum)) $errors[] = 'ID Number is required.';
    if (empty($password)) $errors[] = 'Password is required.';

    if (empty($errors)) {
        // ✅ Check user credentials
        $stmt = $conn->prepare("SELECT id, id_number, password, first_name, last_name, role, profile_image 
                                FROM users WHERE id_number = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $idnum);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $user = $result->fetch_assoc();

                if (password_verify($password, $user['password'])) {
                    // ✅ Store session data
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['id_number'] = $user['id_number'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['profile_image'] = !empty($user['profile_image']) ? $user['profile_image'] : 'default.png';
                    $_SESSION['logged_in'] = true;

                    // ✅ Redirect by role
                    if ($user['role'] == 1) {
                        // Teacher
                        header("Location: ../views/teacher.php");
                        exit;
                    } elseif ($user['role'] == 0) {
                        // Student
                        header("Location: ../views/student.php");
                        exit;
                    } elseif ($user['role'] == 2) {
                        // Admin
                        header("Location: ../views/admin.php");
                        exit;
                    } else {
                        $errors[] = 'Invalid user role.';
                    }
                } else {
                    $errors[] = 'Wrong password. Try again!';
                }
            } else {
                $errors[] = 'User not found.';
            }

            $stmt->close();
        } else {
            $errors[] = 'Database query failed.';
        }
    }

    if (!empty($errors)) {
        $_SESSION['login_errors'] = $errors;
        header("Location: ../index.php");
        exit;
    }
} else {
    header("Location: ../index.php");
    exit;
}

$conn->close();
?>
