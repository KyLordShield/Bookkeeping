<?php
session_start();




// Include Database and User classes from outside public folder
require_once '../config/Database.php';
require_once '../classes/User.php';

/* If user is already logged in, redirect to their dashboard
if (User::isLoggedIn()) {
    User::redirectToDashboard();
}
*/
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username !== '' && $password !== '') {
        $user = new User();

        if ($user->login($username, $password)) {
            // Success → redirect to correct dashboard
            header('Location: ' . $user->getDashboardUrl());
            exit;
        } else {
            $error = 'Invalid username or password, or your account is not active.';
        }
    } else {
        $error = 'Please enter both username and password.';
    }
}

// Dynamic base path calculation (your original code)
$publicPath = '/'; 
$scriptName = $_SERVER['SCRIPT_NAME'];
$scriptDir = dirname($scriptName);

if ($scriptDir !== '/') {
    $publicPath = $scriptDir . '/';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login</title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Azeret+Mono:wght@300;400;500&family=Inria+Serif:wght@400;700&display=swap" rel="stylesheet">

  <!-- Your custom CSS -->
  <link rel="stylesheet" href="<?php echo $publicPath; ?>assets/css_file/login.css">
</head>
<body>

  <div class="login-container">
    <!-- LEFT SIDE - Login Form -->
    <div class="login-panel">
      <h1>Welcome Back</h1>
      <p class="subtitle">Log in to access your account</p>

      <?php if ($error): ?>
        <p style="color: #e74c3c; background: #ffe6e6; padding: 12px; border-radius: 6px; margin: 15px 0; font-size: 14px;">
          <?php echo htmlspecialchars($error); ?>
        </p>
      <?php endif; ?>

      <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
        <label for="username">Username</label>
        <input 
          type="text" 
          id="username" 
          name="username" 
          value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
          required 
          autocomplete="username"
        >

        <label for="password">Password</label>
        <input 
          type="password" 
          id="password" 
          name="password" 
          required 
          autocomplete="current-password"
        >

        <a href="../public/forgot-password.php" class="forgot">Forgot password?</a>

        <button type="submit">Log in</button>
      </form>

      <p class="signup">
        Don’t have an account? <a href="../public/register_page.php">Sign Up</a>
      </p>
      <p class="signup">
         <a href="index.php">Home</a>
      </p>
    </div>

    <!-- RIGHT SIDE - Image -->
    <div class="image-panel">
      <img src="<?php echo $publicPath; ?>assets/images/LoginImage.jpg" alt="Plants by the window">
    </div>
  </div>

</body>
</html>