<?php
// Calculate the correct base path dynamically
$publicPath = '/'; // fallback for production (when public is root)

// Detect if we're currently in a subfolder like /public
$scriptName = $_SERVER['SCRIPT_NAME']; 
$scriptDir = dirname($scriptName);    

// If the current directory is not root, adjust the base
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

  <!-- Dynamic CSS path -->
  <link rel="stylesheet" href="<?php echo $publicPath; ?>assets/css_file/login.css">
</head>
<body>

  <div class="login-container">
    <!-- LEFT SIDE -->
    <div class="login-panel">
      <h1>Welcome Back</h1>
      <p class="subtitle">Log in to access your account</p>

      <form action="<?php echo $publicPath; ?>login" method="POST">
        <label for="username">Email</label>
        <input type="text" id="username" name="username" required>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>

        <a href="#" class="forgot">forgot password?</a>

        <button type="submit">Log in</button>
      </form>

      <p class="signup">
        Don’t have an account? <a href="#">Sign Up</a>
      </p>
    </div>

    <!-- RIGHT SIDE -->
    <div class="image-panel">
      <!-- Dynamic image path -->
      <img src="<?php echo $publicPath; ?>assets/images/LoginImage.jpg" alt="Plants by the window">
    </div>
  </div>

</body>
</html>