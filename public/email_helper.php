<?php
// public/email_helper.php - Using Environment Variables (Secure!)

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables (only for local development)
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
}

function sendResetCodeEmail(
    string $toEmail,
    string $resetCode,
    string $userName = "User"
): bool {
    $mail = new PHPMailer(true);

    try {
        // === SMTP Configuration from Environment Variables ===
        // Use getenv() which works on both local and Render
        $mail->isSMTP();
        $mail->Host       = getenv('SMTP_HOST') ?: 'smtp.sendgrid.net';
        $mail->SMTPAuth   = true;
        $mail->Username   = getenv('SMTP_USERNAME') ?: 'apikey';
        $mail->Password   = getenv('SENDGRID_API_KEY') ?: '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)(getenv('SMTP_PORT') ?: 587);
        
        // Log to check if env vars are loaded
        error_log("SMTP Config - Host: " . $mail->Host . ", Port: " . $mail->Port . ", User: " . $mail->Username);
        error_log("API Key present: " . (empty($mail->Password) ? 'NO' : 'YES'));
        
        // CRITICAL FIXES for production/Render
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true
            ]
        ];
        
        // Increase timeout for slower connections
        $mail->Timeout = 30;
        
        // Enable verbose debug output (TEMPORARILY - check logs)
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer Debug [$level]: $str");
        };

        // Sender
        $fromEmail = getenv('SMTP_FROM_EMAIL') ?: 'approvativebusiness22@gmail.com';
        $fromName = getenv('SMTP_FROM_NAME') ?: 'Approvative Business';
        $mail->setFrom($fromEmail, $fromName);
        
        // Recipient
        $mail->addAddress($toEmail);
        
        // Reply-to (helps with deliverability)
        $mail->addReplyTo($fromEmail, 'Support');

        // Email content
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'Your Password Reset Code - Client Service Management';
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2>Hello {$userName},</h2>
                <p>You requested to reset your password for your Client Service account.</p>
                
                <div style='background: #f5f5f5; padding: 20px; text-align: center; border-radius: 8px; margin: 20px 0;'>
                    <h1 style='font-size: 42px; letter-spacing: 10px; margin: 10px 0; color: #2c3e50;'>
                        {$resetCode}
                    </h1>
                    <p style='color: #666; font-size: 16px;'>
                        This code is valid for <strong>30 minutes</strong>.
                    </p>
                </div>
                
                <p>If you didn't request this reset, please ignore this email or contact support if you're concerned about account security.</p>
                <br>
                <small style='color: #888;'>Client Service Management System • Bacolod City</small>
            </div>
        ";

        $mail->AltBody = "Hello {$userName},\n\nYour password reset code is: {$resetCode}\nThis code is valid for 30 minutes.\n\nIf you didn't request this, ignore this email.";

        $mail->send();
        error_log("Password reset email sent successfully to: $toEmail");
        return true;

    } catch (Exception $e) {
        error_log("CRITICAL: Reset code email failed to $toEmail");
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        error_log("Exception Message: " . $e->getMessage());
        error_log("Stack Trace: " . $e->getTraceAsString());
        return false;
    }
}
?>