<?php 
session_start();
$errors = $_SESSION['register_errors'] ?? [];
$formData = $_SESSION['form_data'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    body {
      background: #ffffff; /* make page background white */
      color: #0f172a;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .register-card {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
      width: 100%;
      max-width: 600px;
      padding: 2.5rem;
      border-top: 6px solid #0077b6;
    }
    h1 {
      color: #0077b6;
      font-weight: 700;
      text-align: center;
      margin-bottom: 1.5rem;
    }
    .btn-primary {
      background-color: #0077b6;
      border: none;
    }
    .btn-primary:hover {
      background-color: #005f8a;
    }
    .alert {
      border-radius: 8px;
    }
    a {
      color: #0077b6;
      text-decoration: none;
      font-weight: 600;
    }
    a:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>

<div class="register-card">
  
  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
      <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
          <li><?= htmlspecialchars($error) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php unset($_SESSION['register_errors']); ?>
  <?php endif; ?>

  <form action="../controllers/register.php" method="POST">
    <h1>Create Account</h1>

    <div class="row g-3">
      <div class="col-md-6">
        <label for="fname" class="form-label fw-semibold text-primary">First Name</label>
        <input type="text" class="form-control" id="fname" name="fname" placeholder="First name" 
               value="<?= htmlspecialchars($formData['fname'] ?? '') ?>" required>
      </div>
      <div class="col-md-6">
        <label for="lname" class="form-label fw-semibold text-primary">Last Name</label>
        <input type="text" class="form-control" id="lname" name="lname" placeholder="Last name" 
               value="<?= htmlspecialchars($formData['lname'] ?? '') ?>" required>
      </div>
    </div>

    <div class="row g-3 mt-2">
      <div class="col-md-6">
        <label for="idnum" class="form-label fw-semibold text-primary">ID Number</label>
        <input type="text" class="form-control" id="idnum" name="idnum" placeholder="e.g. 12-34567" 
               value="<?= htmlspecialchars($formData['idnum'] ?? '') ?>" required>
      </div>
      <div class="col-md-6">
        <label for="yearLevel" class="form-label fw-semibold text-primary">Year Level</label>
        <select class="form-select" id="yearLevel" name="yearLevel" required>
          <option value="">-- Select Year --</option>
          <option value="1" <?= (isset($formData['yearLevel']) && $formData['yearLevel']=='1')?'selected':'' ?>>1st Year</option>
          <option value="2" <?= (isset($formData['yearLevel']) && $formData['yearLevel']=='2')?'selected':'' ?>>2nd Year</option>
          <option value="3" <?= (isset($formData['yearLevel']) && $formData['yearLevel']=='3')?'selected':'' ?>>3rd Year</option>
          <option value="4" <?= (isset($formData['yearLevel']) && $formData['yearLevel']=='4')?'selected':'' ?>>4th Year</option>
        </select>
      </div>
    </div>

    <div class="row g-3 mt-2">
      <div class="col-md-6">
        <label for="section" class="form-label fw-semibold text-primary">Section</label>
        <select class="form-select" id="section" name="section" required>
          <option value="">-- Select Section --</option>
          <option value="A" <?= (isset($formData['section']) && $formData['section']=='A')?'selected':'' ?>>A</option>
          <option value="B" <?= (isset($formData['section']) && $formData['section']=='B')?'selected':'' ?>>B</option>
          <option value="C" <?= (isset($formData['section']) && $formData['section']=='C')?'selected':'' ?>>C</option>
          <option value="D" <?= (isset($formData['section']) && $formData['section']=='D')?'selected':'' ?>>D</option>
        </select>
      </div>
      <div class="col-md-6">
        <label for="password" class="form-label fw-semibold text-primary">Password</label>
        <input type="password" class="form-control" id="password" name="password" placeholder="Create password" minlength="6" required>
      </div>
    </div>

    <div class="row g-3 mt-2">
      <div class="col-md-6">
        <label for="confirmPassword" class="form-label fw-semibold text-primary">Confirm Password</label>
        <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" placeholder="Confirm password" minlength="6" required>
      </div>
    </div>

    <div class="mt-4 d-grid">
      <button type="submit" class="btn btn-primary">Register</button>
    </div>

    <p class="text-center mt-3 mb-0">
      Already have an account? <a href="../index.php">Login</a>
    </p>
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
