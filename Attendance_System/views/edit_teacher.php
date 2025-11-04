<?php
include "../config/db.php"; // DB connection

// Check if ID is passed
if (!isset($_GET['id'])) {
    echo "Teacher ID is missing!";
    exit;
}

$id = intval($_GET['id']);

// Fetch teacher data from `users` table where role = 1 (teacher)
$query = "SELECT id, id_number, first_name, last_name, status FROM users WHERE id = ? AND role = 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo "Teacher not found!";
    exit;
}

$teacher = $result->fetch_assoc();

// Update teacher data when form is submitted
if (isset($_POST['update'])) {
    $id_number = trim($_POST['id_number']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $status = in_array($_POST['status'], ['active', 'blocked']) ? $_POST['status'] : 'active';

    $update = "UPDATE users SET id_number = ?, first_name = ?, last_name = ?, status = ? WHERE id = ? AND role = 1";
    $stmt2 = $conn->prepare($update);
    $stmt2->bind_param("sssii", $id_number, $first_name, $last_name, $status, $id);

    if ($stmt2->execute()) {
        echo "<script>alert('Teacher updated successfully!'); window.location='admin.php';</script>";
        exit;
    } else {
        echo "Update failed: " . htmlspecialchars($stmt2->error);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Edit Teacher</title>
    <style>
        body{font-family:Arial,Helvetica,sans-serif;background:#f3f4f6;padding:2rem}
        .card{background:#fff;padding:1.5rem;border-radius:8px;max-width:600px;margin:0 auto;box-shadow:0 6px 18px rgba(0,0,0,0.08)}
        label{display:block;margin-top:.75rem;font-weight:600}
        input,select{width:100%;padding:.6rem;margin-top:.25rem;border:1px solid #d1d5db;border-radius:6px}
        button{margin-top:1rem;padding:.7rem 1rem;background:#2563eb;color:#fff;border:none;border-radius:6px;cursor:pointer}
    </style>
</head>
<body>

<div class="card">
    <h2>Edit Teacher</h2>

    <form method="POST">
        <label>ID Number</label>
        <input type="text" name="id_number" value="<?php echo htmlspecialchars($teacher['id_number']); ?>" required>

        <label>First Name</label>
        <input type="text" name="first_name" value="<?php echo htmlspecialchars($teacher['first_name']); ?>" required>

        <label>Last Name</label>
        <input type="text" name="last_name" value="<?php echo htmlspecialchars($teacher['last_name']); ?>" required>

        <label>Status</label>
        <select name="status">
            <option value="active" <?php echo ($teacher['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
            <option value="blocked" <?php echo ($teacher['status'] === 'blocked') ? 'selected' : ''; ?>>Blocked</option>
        </select>

        <button type="submit" name="update">Update</button>
        <a href="admin.php" style="margin-left:1rem;color:#2563eb;text-decoration:none;">← Back to Dashboard</a>
    </form>
</div>

</body>
</html>
