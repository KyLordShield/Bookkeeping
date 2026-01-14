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
        
        // FORCE timezone again (in case Database.php didn't work)
        $db->exec("SET time_zone = '+08:00'");

        // === ENHANCED DEBUG BLOCK ===
        echo "<pre style='background:#fff3cd; padding:20px; font-family:monospace; border:2px solid #ffeeba; max-width:900px; margin:20px auto; white-space: pre-wrap;'>";
        echo "=== RESET DEBUG INFO ===\n\n";
        
        // Check if timezone is actually set
        $tzCheck = $db->query("SELECT @@session.time_zone AS tz, NOW() AS mysql_now")->fetch();
        echo "PHP Server Time:     " . date('Y-m-d H:i:s') . "\n";
        echo "MySQL Server Time:   {$tzCheck['mysql_now']}\n";
        echo "MySQL Timezone:      {$tzCheck['tz']}\n";
        echo "Time Difference:     " . (strtotime($tzCheck['mysql_now']) - time()) . " seconds\n\n";
        
        echo "Entered email:                  '" . htmlspecialchars($email) . "'\n";
        echo "Raw code from form:             '" . htmlspecialchars($raw_code) . "'\n";
        echo "Cleaned code (only digits):     '" . $code . "'\n";
        echo "Code length after cleaning:     " . strlen($code) . "\n\n";

        // Show ALL stored codes with detailed comparison
        $checkStmt = $db->prepare("
            SELECT 
                code, 
                expires_at,
                created_at,
                user_id,
                CASE 
                    WHEN expires_at > NOW() THEN 'VALID ✓'
                    ELSE 'EXPIRED ✗'
                END AS status,
                CASE 
                    WHEN code = ? THEN 'MATCH ✓'
                    ELSE 'NO MATCH ✗'
                END AS code_match,
                TIMESTAMPDIFF(SECOND, NOW(), expires_at) AS seconds_until_expiry
            FROM password_resets 
            WHERE email = ?
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $checkStmt->execute([$code, $email]);
        $existing = $checkStmt->fetchAll(PDO::FETCH_ASSOC);

        if ($existing) {
            echo "Stored reset codes for this email:\n";
            echo str_repeat('-', 120) . "\n";
            printf("%-10s | %-19s | %-10s | %-12s | %s\n", 
                "Code", "Expires At", "Status", "Code Match", "Seconds Left");
            echo str_repeat('-', 120) . "\n";
            
            foreach ($existing as $row) {
                printf("%-10s | %-19s | %-10s | %-12s | %d\n",
                    $row['code'],
                    $row['expires_at'],
                    $row['status'],
                    $row['code_match'],
                    $row['seconds_until_expiry']
                );
            }
            echo str_repeat('-', 120) . "\n\n";
        } else {
            echo "❌ No reset codes found in database for this email!\n\n";
        }

        // Test the EXACT query being used
        echo "Testing exact reset query:\n";
        $testStmt = $db->prepare("
            SELECT user_id, expires_at, code,
                   expires_at > NOW() AS is_not_expired,
                   code = ? AS code_matches,
                   TIMESTAMPDIFF(SECOND, NOW(), expires_at) AS time_left
            FROM password_resets 
            WHERE email = ? 
              AND code = ?
              AND expires_at > NOW()
            LIMIT 1
        ");
        $testStmt->execute([$code, $email, $code]);
        $testResult = $testStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($testResult) {
            echo "✓ QUERY FOUND A MATCH!\n";
            echo "  User ID: {$testResult['user_id']}\n";
            echo "  Code: {$testResult['code']}\n";
            echo "  Expires: {$testResult['expires_at']}\n";
            echo "  Time left: {$testResult['time_left']} seconds\n";
        } else {
            echo "✗ QUERY RETURNED NO RESULTS\n";
            echo "  Checking each condition separately:\n\n";
            
            // Check email
            $emailCheck = $db->prepare("SELECT COUNT(*) as cnt FROM password_resets WHERE email = ?");
            $emailCheck->execute([$email]);
            $emailResult = $emailCheck->fetch();
            echo "  ✓ Email exists: " . ($emailResult['cnt'] > 0 ? 'YES' : 'NO') . "\n";
            
            // Check code
            $codeCheck = $db->prepare("SELECT COUNT(*) as cnt FROM password_resets WHERE email = ? AND code = ?");
            $codeCheck->execute([$email, $code]);
            $codeResult = $codeCheck->fetch();
            echo "  " . ($codeResult['cnt'] > 0 ? '✓' : '✗') . " Code matches: " . ($codeResult['cnt'] > 0 ? 'YES' : 'NO') . "\n";
            
            // Check expiry
            $expiryCheck = $db->prepare("SELECT COUNT(*) as cnt FROM password_resets WHERE email = ? AND code = ? AND expires_at > NOW()");
            $expiryCheck->execute([$email, $code]);
            $expiryResult = $expiryCheck->fetch();
            echo "  " . ($expiryResult['cnt'] > 0 ? '✓' : '✗') . " Not expired: " . ($expiryResult['cnt'] > 0 ? 'YES' : 'NO') . "\n";
        }
        echo "</pre>";
        // === END DEBUG ===

        // Actual reset check (FIX: Compare times in PHP timezone)
        $currentTime = date('Y-m-d H:i:s'); // PHP's current time
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

            $message = "Your password has been successfully reset!<br>"
                     . "You can now <a href='login_page.php' style='color:#27ae60; font-weight:bold;'>log in</a> with your new password.";
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
</head>
<body>
  <div class="login-container">
    <div class="login-panel">
      <h1>Set New Password</h1>
      <p>Enter the code you received and your new password.</p>

      <?php if ($error): ?>
        <p class="error" style="color:#e74c3c; background:#ffe6e6; padding:12px; border-radius:6px;">
          <?php echo htmlspecialchars($error); ?>
        </p>
      <?php endif; ?>

      <?php if ($message): ?>
        <p class="success" style="color:#27ae60; background:#e8f5e9; padding:12px; border-radius:6px;">
          <?php echo $message; ?>
        </p>
      <?php endif; ?>

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
        <a href="forgot-password.php" style="color: white;" >← Request new code</a> | 
        <a href="login_page.php" style="color: white;" >Back to Login</a>
      </p>
    </div>
    <div class="image-panel">
      <img src="../public/assets/images/forgot_pass.png" alt="Plants by the window">
    </div>
  </div>
</body>
</html>