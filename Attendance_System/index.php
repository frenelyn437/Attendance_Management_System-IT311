<?php
session_start();
$loginErrors = $_SESSION['login_errors'] ?? [];
unset($_SESSION['login_errors']);
$showLogin = !empty($loginErrors);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Attendance Management System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    body {
      height: 100vh;
      margin: 0;
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(to bottom, #add8e6 0%, #2575fc 50%, #ffffff 100%);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      position: relative;
      overflow: hidden;
    }

    /* College Banner */
    .college-banner {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      padding: 20px 40px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      display: flex;
      align-items: center;
      justify-content: space-between;
      z-index: 5;
    }

    .college-info {
      display: flex;
      align-items: center;
      gap: 20px;
    }

    .college-logo {
      width: 70px;
      height: 70px;
      border-radius: 10px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    }

    .college-text h2 {
      margin: 0;
      font-size: 1.2rem;
      font-weight: 700;
      color: #1a5edc;
      line-height: 1.3;
    }

    .college-text p {
      margin: 0;
      font-size: 0.9rem;
      color: #666;
      font-weight: 500;
    }

    /* Main Title Section */
    .main-title-section {
      text-align: center;
      margin-top: 80px;
      animation: fadeInDown 0.8s ease-in-out;
    }

    @keyframes fadeInDown {
      from {
        opacity: 0;
        transform: translateY(-30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .system-badge {
      background: rgba(255, 255, 255, 0.2);
      backdrop-filter: blur(10px);
      color: white;
      padding: 8px 20px;
      border-radius: 20px;
      font-size: 0.9rem;
      font-weight: 600;
      display: inline-block;
      margin-bottom: 15px;
      border: 2px solid rgba(255, 255, 255, 0.3);
    }

    h1 {
      color: white;
      font-weight: 700;
      font-size: 3rem;
      text-shadow: 2px 2px 6px rgba(0, 0, 0, 0.3);
      margin: 0;
    }

    .subtitle {
      color: rgba(255, 255, 255, 0.9);
      font-size: 1.2rem;
      margin-top: 10px;
      font-weight: 500;
      text-shadow: 1px 1px 4px rgba(0, 0, 0, 0.2);
    }

    /* Login Button (Top Right) */
    .login-btn {
      background-color: #2575fc;
      color: white;
      border: none;
      border-radius: 8px;
      padding: 10px 18px;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
      transition: background 0.3s, transform 0.2s;
    }

    .login-btn:hover {
      background-color: #1a5edc;
      transform: scale(1.05);
    }

    /* Actions container in banner */
    .banner-actions {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    /* Register Button */
    .register-btn {
      background-color: #198754; /* Bootstrap green */
      color: white;
      border: none;
      border-radius: 8px;
      padding: 10px 18px;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
      cursor: pointer;
      transition: background 0.3s, transform 0.2s;
    }

    .register-btn:hover {
      background-color: #157347;
      transform: scale(1.05);
    }

    /* Hidden login card */
    .login-card {
      width: 420px;
      background: linear-gradient(135deg, #2563fc 0%, #1e40af 100%);
      border-radius: 0; /* rectangular */
      box-shadow: 0 6px 20px rgba(0,0,0,0.25);
      padding: 32px;
      text-align: center;
      display: none;
      z-index: 10;
      animation: fadeIn 0.28s ease-in-out;
      color: #ffffff;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: scale(0.9);
      }
      to {
        opacity: 1;
        transform: scale(1);
      }
    }

    .logo img {
      width: 0; /* removed/hidden */
      display: none;
    }

    h3 {
      color: #fff;
      font-weight: 700;
      margin-bottom: 20px;
      font-size: 1.25rem;
    }

    .login-card .form-control {
      background: #ffffff;
      border: none;
      border-radius: 6px;
      padding: 12px 14px;
      margin-bottom: 12px;
    }

    .btn-login {
      background: #ffffff;
      border: none;
      color: #1e40af;
      font-weight: 700;
      padding: 12px;
      border-radius: 6px;
      width: 100%;
      transition: transform 0.15s ease, box-shadow 0.15s ease;
    }

    .btn-login:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 18px rgba(0,0,0,0.12);
    }

    .noAcc a {
      color: #2575fc;
      text-decoration: none;
      font-weight: 500;
    }

    .noAcc a:hover {
      text-decoration: underline;
    }

    .back-btn {
      margin-top: 15px;
      background: #6c757d;
      color: white;
      border: none;
      padding: 8px 14px;
      border-radius: 6px;
      cursor: pointer;
      transition: background 0.3s;
    }

    .back-btn:hover {
      background: #5a6268;
    }

    /* Decorative elements */
    .floating-shapes {
      position: absolute;
      width: 100%;
      height: 100%;
      pointer-events: none;
      z-index: 1;
    }

    .shape {
      position: absolute;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 50%;
      animation: float 6s infinite ease-in-out;
    }

    .shape1 {
      width: 150px;
      height: 150px;
      top: 20%;
      left: 10%;
      animation-delay: 0s;
    }

    .shape2 {
      width: 100px;
      height: 100px;
      top: 60%;
      right: 15%;
      animation-delay: 2s;
    }

    .shape3 {
      width: 80px;
      height: 80px;
      bottom: 20%;
      left: 20%;
      animation-delay: 4s;
    }

    @keyframes float {
      0%, 100% {
        transform: translateY(0) rotate(0deg);
      }
      50% {
        transform: translateY(-20px) rotate(180deg);
      }
    }

    /* Responsive Design */
    @media (max-width: 768px) {
      .college-banner {
        padding: 15px 20px;
      }

      .college-text h2 {
        font-size: 1rem;
      }

      .college-text p {
        font-size: 0.8rem;
      }

      .college-logo {
        width: 50px;
        height: 50px;
      }

      h1 {
        font-size: 2rem;
      }

      .subtitle {
        font-size: 1rem;
      }

      .login-card {
        width: 90%;
        max-width: 350px;
      }
    }
  </style>
</head>
<body>
  <!-- Floating Shapes -->
  <div class="floating-shapes">
    <div class="shape shape1"></div>
    <div class="shape shape2"></div>
    <div class="shape shape3"></div>
  </div>

  <!-- College Banner -->
  <div class="college-banner">
    <div class="college-info">
      <img src="./images/logo.png" alt="College Logo" class="college-logo">
      <img src="./images/CICS.png" alt="College Logo" class="college-logo">
      <div class="college-text">
        <h2>COLLEGE OF INFORMATION AND COMPUTING SCIENCE</h2>
        <p>Cagayan State University - Gonzaga Campus</p>
      </div>
    </div>
    
    <!-- Actions -->
    <div class="banner-actions">
      <button class="login-btn" id="showLoginBtn">
        <i class="bi bi-person-fill"></i> Login
      </button>
      <a class="register-btn" href="./views/register.php">
        <i class="bi bi-person-plus-fill"></i> Register
      </a>
    </div>
  </div>

  <!-- Main Title Section -->
  <div class="main-title-section" id="titleSection">
    <div class="system-badge">
      <i class="bi bi-clipboard-check"></i> Smart Attendance System
    </div>
    <h1>Attendance Management System</h1>
    <p class="subtitle">Track, Manage, and Monitor Student Attendance Efficiently</p>
  </div>

  <!-- Hidden Login Card -->
  <div class="login-card" id="loginCard" style="display: <?php echo $showLogin ? 'block' : 'none'; ?>;">
    <div class="logo">
      
    </div>
    <h3>Login to Continue</h3>
    <?php if (!empty($loginErrors)): ?>
      <div class="alert alert-danger" role="alert">
        <?php foreach ($loginErrors as $e): ?>
          <div><?php echo htmlspecialchars($e); ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <form action="./controllers/login.php" method="POST">
      <div class="mb-3">
        <input type="text" class="form-control" name="idnum" placeholder="ID Number" required>
      </div>
      <div class="mb-3">
        <input type="password" class="form-control" name="password" placeholder="Password" required>
      </div>
      <button type="submit" class="btn-login">Login</button>
    </form>
    <p class="noAcc mt-3">Don't have an account? <a href="./views/register.php">Register</a></p>
    <button class="back-btn" id="backBtn">Back to Homepage</button>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const loginCard = document.getElementById('loginCard');
    const showLoginBtn = document.getElementById('showLoginBtn');
    const backBtn = document.getElementById('backBtn');
    const titleSection = document.getElementById('titleSection');
    const hasLoginErrors = <?php echo $showLogin ? 'true' : 'false'; ?>;

    if (hasLoginErrors) {
      loginCard.style.display = 'block';
      showLoginBtn.style.display = 'none';
      titleSection.style.display = 'none';
    }

    showLoginBtn.addEventListener('click', () => {
      loginCard.style.display = 'block';
      showLoginBtn.style.display = 'none';
      titleSection.style.display = 'none';
    });

    backBtn.addEventListener('click', () => {
      loginCard.style.display = 'none';
      showLoginBtn.style.display = 'flex';
      titleSection.style.display = 'block';
    });
  </script>
</body>
</html>