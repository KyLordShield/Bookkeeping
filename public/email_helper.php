<?php
// public/email_helper.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

function sendResetCodeEmail(
    string $toEmail,
    string $resetCode,
    string $userName = "User"
): bool {
    $mail = new PHPMailer(true);

    try {
        // === SENDGRID SMTP Configuration (Works on Render!) ===
        $mail->isSMTP();
        $mail->Host       = 'smtp.sendgrid.net';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'apikey';  // This is literally the word "apikey"
        $mail->Password   = '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';  // Paste your SG.xxxx key here
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
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
        $mail->setFrom('approvativebusiness22@gmail.com', 'Approvative Business Documents Processing');
        
        // Recipient
        $mail->addAddress($toEmail);
        
        // Reply-to (helps with deliverability)
        $mail->addReplyTo('approvativebusiness22@gmail.com', 'Support');

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