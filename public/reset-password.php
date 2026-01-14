<?php
//reset-password.php
session_start();
require_once '../config/Database.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email          = trim($_POST['email'] ?? '');
    $raw_code       = $_POST['code'] ?? '';
    $code           = preg_replace('/[^0-9]/', '', trim($raw_code));
    $newPassword    = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (strlen($code) !== 6 || !ctype_digit($code)) {
        $error = "Reset code must be exactly 6 digits (only numbers).";
    } elseif (strlen($newPassword) < 8) {
        $error = "New password must be at least 8 characters.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {
        $db = Database::getInstance()->getConnection();

        // === DEBUG BLOCK (COMMENTED OUT - UNCOMMENT FOR TROUBLESHOOTING) ===
        /*
        echo "<pre style='background:#fff3cd; padding:20px; font-family:monospace; border:2px solid #ffeeba; max-width:900px; margin:20px auto; white-space: pre-wrap;'>";
        echo "=== RESET DEBUG INFO ===\n\n";
        
        $tzCheck = $db->query("SELECT @@session.time_zone AS tz, NOW() AS mysql_now")->fetch();
        echo "PHP Server Time:     " . date('Y-m-d H:i:s') . "\n";
        echo "MySQL Server Time:   {$tzCheck['mysql_now']}\n";
        echo "MySQL Timezone:      {$tzCheck['tz']}\n";
        echo "Time Difference:     " . (strtotime($tzCheck['mysql_now']) - time()) . " seconds\n\n";
        
        echo "Entered email:       '" . htmlspecialchars($email) . "'\n";
        echo "Raw code from form:  '" . htmlspecialchars($raw_code) . "'\n";
        echo "Cleaned code:        '" . $code . "'\n";
        echo "Code length:         " . strlen($code) . "\n\n";

        $checkStmt = $db->prepare("
            SELECT code, expires_at, created_at, user_id,
                   CASE WHEN expires_at > NOW() THEN 'VALID ✓' ELSE 'EXPIRED ✗' END AS status,
                   CASE WHEN code = ? THEN 'MATCH ✓' ELSE 'NO MATCH ✗' END AS code_match,
                   TIMESTAMPDIFF(SECOND, NOW(), expires_at) AS seconds_until_expiry
            FROM password_resets WHERE email = ? ORDER BY created_at DESC LIMIT 5
        ");
        $checkStmt->execute([$code, $email]);
        $existing = $checkStmt->fetchAll(PDO::FETCH_ASSOC);

        if ($existing) {
            echo "Stored reset codes:\n";
            echo str_repeat('-', 120) . "\n";
            printf("%-10s | %-19s | %-10s | %-12s | %s\n", "Code", "Expires At", "Status", "Code Match", "Seconds Left");
            echo str_repeat('-', 120) . "\n";
            foreach ($existing as $row) {
                printf("%-10s | %-19s | %-10s | %-12s | %d\n",
                    $row['code'], $row['expires_at'], $row['status'], $row['code_match'], $row['seconds_until_expiry']);
            }
            echo str_repeat('-', 120) . "\n";
        }
        echo "</pre>";
        */
        // === END DEBUG ===

        // Actual reset check (using PHP time to avoid timezone issues)
        $currentTime = date('Y-m-d H:i:s');
        $stmt = $db->prepare("
            SELECT user_id, expires_at, code
            FROM password_resets 
            WHERE email = ? 
              AND code = ?
              AND expires_at > ?
            LIMIT 1
        ");
        $stmt->execute([$email, $code, $currentTime]);
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($reset) {
            // Hash new password
            $password_hash = password_hash($newPassword, PASSWORD_DEFAULT);

            // Update password
            $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $stmt->execute([$password_hash, $reset['user_id']]);

            // Delete used code
            $stmt = $db->prepare("DELETE FROM password_resets WHERE user_id = ?");
            $stmt->execute([$reset['user_id']]);

            $message = "success";
        } else {
            $error = "Invalid or expired reset code. Please request a new one.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password - Client Service Management</title>
  <link rel="stylesheet" href="assets/css_file/login.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
  <div class="login-container">
    <div class="login-panel">
      <h1>Set New Password</h1>
      <p>Enter the code you received and your new password.</p>

      <form method="POST">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">

        <label for="code">6-Digit Reset Code</label>
        <input type="text" id="code" name="code" maxlength="6" pattern="\d{6}" required placeholder="123456"
               style="font-size:18px; letter-spacing:3px; text-align:center;">

        <label for="new_password">New Password</label>
        <input type="password" id="new_password" name="new_password" required minlength="8">

        <label for="confirm_password">Confirm New Password</label>
        <input type="password" id="confirm_password" name="confirm_password" required minlength="8">

        <button type="submit">Reset Password</button>
      </form>

      <p style="text-align:center; margin-top:20px; color:white">
        <a href="forgot-password.php" style="color: white;">← Request new code</a> | 
        <a href="login_page.php" style="color: white;">Back to Login</a>
      </p>
    </div>
    
    <div class="image-panel">
      <img src="../public/assets/images/forgot_pass.png" alt="Plants by the window">
    </div>
  </div>

  <script>
    <?php if ($error): ?>
      Swal.fire({
        icon: 'error',
        title: 'Reset Failed',
        text: '<?php echo addslashes($error); ?>',
        confirmButtonColor: '#7D1C19',
        confirmButtonText: 'Try Again'
      });
    <?php endif; ?>

    <?php if ($message === 'success'): ?>
      Swal.fire({
        icon: 'success',
        title: 'Password Reset Successful!',
        html: 'Your password has been changed.<br><strong>Redirecting to login page...</strong>',
        timer: 3000,
        timerProgressBar: true,
        showConfirmButton: false,
        allowOutsideClick: false,
        allowEscapeKey: false
      }).then(() => {
        window.location.href = 'login_page.php';
      });
    <?php endif; ?>
  </script>
</body>
</html>