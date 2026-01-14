<?php
//forgot-password.php file
session_start();
require_once '../config/Database.php';
require_once '../classes/User.php';
require_once '../public/email_helper.php';

$message = '';
$messageType = ''; // 'success' or 'generic'
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');

    if (empty($username) || empty($email)) {
        $error = "Please enter both username and email.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($username) < 3) {
        $error = "Username must be at least 3 characters.";
    } else {
        $db = Database::getInstance()->getConnection();

        // Check BOTH username AND email match
        $stmt = $db->prepare("
            SELECT u.user_id, u.username, 
                   COALESCE(c.first_name, s.first_name) AS first_name,
                   COALESCE(c.last_name, s.last_name) AS last_name
            FROM users u
            LEFT JOIN clients c ON u.client_id = c.client_id
            LEFT JOIN staff s ON u.staff_id = s.staff_id
            WHERE u.username = ?
              AND (c.email = ? OR s.email = ?)
            LIMIT 1
        ");
        $stmt->execute([$username, $email, $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Generate 6-digit code
            $code = sprintf("%06d", mt_rand(0, 999999));
            $expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));

            // Store reset request
            $stmt = $db->prepare("
                INSERT INTO password_resets 
                (user_id, email, code, expires_at, created_at)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    code = ?, expires_at = ?, created_at = NOW()
            ");
            $stmt->execute([
                $user['user_id'], 
                $email, 
                $code, 
                $expires,
                $code, 
                $expires
            ]);

            // Send real email
            $userName = trim($user['first_name'] . ' ' . $user['last_name']) ?: $user['username'];

            if (sendResetCodeEmail($email, $code, $userName)) {
                $message = "success";
                $messageType = "success";
            } else {
                $error = "We couldn't send the reset code right now. Please try again later or contact support.";
            }
        } else {
            // Security: same message regardless of whether the combo exists
            $message = "generic";
            $messageType = "generic";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password - Client Service Management</title>
  <link rel="stylesheet" href="assets/css_file/login.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
  <div class="login-container">
    <div class="login-panel">
      <h1>Reset Your Password</h1>
      <p>Enter your username and email to receive a reset code.</p>

      <form method="POST">
        <label for="username">Username</label>
        <input 
          type="text" 
          id="username" 
          name="username" 
          required 
          autocomplete="username"
          placeholder="yourusername"
          value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
        >

        <label for="email">Email Address</label>
        <input 
          type="email" 
          id="email" 
          name="email" 
          required 
          autocomplete="email"
          placeholder="your.email@example.com"
          value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
        >

        <button type="submit">Send Reset Code</button>
      </form>

      <p style="text-align:center; margin-top:20px; color:white">
        <a href="login_page.php" style="color: white;">← Back to Login</a>
      </p>
      <p style="text-align:center; margin-top:20px; color:white">
        <a href="reset-password.php" style="color: white;">Already have a code? →</a>
      </p>
    </div>
    
    <!-- RIGHT SIDE - Image -->
    <div class="image-panel">
      <img src="../public/assets/images/forgot_pass.png" alt="Plants by the window">
    </div>
  </div>

  <script>
    <?php if ($error): ?>
      Swal.fire({
        icon: 'error',
        title: 'Oops...',
        text: '<?php echo addslashes($error); ?>',
        confirmButtonColor: '#7D1C19',
        confirmButtonText: 'Try Again'
      });
    <?php endif; ?>

    <?php if ($messageType === 'success'): ?>
      Swal.fire({
        icon: 'success',
        title: 'Code Sent!',
        html: 'A reset code has been sent to your email.<br><strong>Check your inbox and spam folder.</strong>',
        confirmButtonText: 'Enter Code Now',
        confirmButtonColor: '#27ae60',
        showCancelButton: true,
        cancelButtonText: 'Later',
        cancelButtonColor: '#6c757d',
        allowOutsideClick: false
      }).then((result) => {
        if (result.isConfirmed) {
          window.location.href = 'reset-password.php';
        }
      });
    <?php elseif ($messageType === 'generic'): ?>
      Swal.fire({
        icon: 'info',
        title: 'Check Your Email',
        text: 'If the username and email match an existing account, you will receive a reset code shortly.',
        confirmButtonColor: '#7D1C19',
        confirmButtonText: 'OK'
      });
    <?php endif; ?>
  </script>
</body>
</html>