<?php
session_start();
include "../config/db.php";

if (empty($_SESSION['first_name']) || empty($_SESSION['last_name']) || $_SESSION['role'] != 2) {
    header("Location: ../index.php");
    exit;
}

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_number = trim($_POST['id_number']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $password = $_POST['password'];

    if (!$id_number || !$first_name || !$last_name || !$password) {
        $message = "Please fill in all fields.";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE id_number=?");
        $check->bind_param("s", $id_number);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $message = "ID Number already exists!";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $role = 1;
            $stmt = $conn->prepare("INSERT INTO users (id_number, first_name, last_name, password, role) VALUES (?,?,?,?,?)");
            $stmt->bind_param("ssssi", $id_number, $first_name, $last_name, $hashed, $role);
            if ($stmt->execute()) {
                $message = "Teacher added successfully!";
                $id_number = $first_name = $last_name = $password = "";
            } else {
                $message = "Error adding teacher.";
            }
            $stmt->close();
        }
        $check->close();
    }
}

$teachers = [];
$res = $conn->query("SELECT id_number, first_name, last_name FROM users WHERE role=1 ORDER BY id DESC");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $teachers[] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Teacher</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{
  font-family: Arial, sans-serif;
  background: linear-gradient(135deg,#6a11cb,#2575fc);
  margin:0;
  padding:0;
  display:flex;
  justify-content:center;
  align-items:flex-start;
  min-height:100vh;
}
.container{
  background:#fff;
  padding:2rem;
  margin:30px 0;
  border-radius:20px;
  width:420px;
  box-shadow:0 10px 30px rgba(0,0,0,0.2);
}
h2{
  text-align:center;
  color:#2575fc;
  margin-bottom:1rem;
}
form{
  display:flex;
  flex-direction:column;
  gap:1rem;
}
input[type="text"],input[type="number"],input[type="password"]{
  padding:.8rem;
  border:1px solid #ccc;
  border-radius:10px;
  font-size:1rem;
}
button{
  padding:.9rem;
  border:none;
  border-radius:10px;
  font-weight:bold;
  background:linear-gradient(135deg,#2575fc,#6a11cb);
  color:#fff;
  cursor:pointer;
  transition:.3s;
}
button:hover{
  transform:translateY(-2px);
  box-shadow:0 6px 18px rgba(37,117,252,0.4);
}
.message{
  margin-bottom:1rem;
  font-weight:600;
  text-align:center;
  color:green;
}
.teacher-list{
  margin-top:2rem;
}
.teacher-list h3{
  text-align:center;
  color:#14213d;
  margin-bottom:1rem;
}
.teacher-list ul{
  list-style:none;
  padding:0;
}
.teacher-list li{
  padding:.5rem 0;
  border-bottom:1px solid #eee;
}
.back-link{
  display:inline-block;
  margin-bottom:1rem;
  color:#2575fc;
  text-decoration:none;
  font-weight:600;
}
.back-link:hover{
  text-decoration:underline;
}
</style>
</head>
<body>
<div class="container">
    <a href="./admin.php" class="back-link">← Go Back to Dashboard</a>
    <h2>Add Teacher</h2>

    <?php if ($message): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="number" name="id_number" placeholder="ID Number" value="<?= isset($id_number)?htmlspecialchars($id_number):'' ?>" required>
        <input type="text" name="first_name" placeholder="First Name" value="<?= isset($first_name)?htmlspecialchars($first_name):'' ?>" required>
        <input type="text" name="last_name" placeholder="Last Name" value="<?= isset($last_name)?htmlspecialchars($last_name):'' ?>" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Add Teacher</button>
    </form>

    <div class="teacher-list">
        <h3>Existing Teachers</h3>
        <ul>
        <?php foreach($teachers as $t): ?>
            <li><?= htmlspecialchars($t['id_number']." - ".$t['first_name']." ".$t['last_name']) ?></li>
        <?php endforeach; ?>
        </ul>
    </div>
</div>
</body>
</html>
