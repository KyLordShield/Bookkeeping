<?php
// public/email_helper.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load Composer autoloader
// Since this file is in /public, go up one level to reach vendor/
require_once __DIR__ . '/../vendor/autoload.php';

function sendResetCodeEmail(
    string $toEmail,
    string $resetCode,
    string $userName = "User"
): bool {
    $mail = new PHPMailer(true);

    try {
        // === Gmail SMTP Configuration ===
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        
        // === ONLY CHANGE THESE TWO LINES ===
        $mail->Username   = 'approvativebusiness22@gmail.com';     // Your dedicated Gmail - already correct
        $mail->Password   = 'flomuexchdmqtptq';                   // Your App Password - already correct (no spaces!)
        
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Optional: Enable debug only when testing (comment out later)
        // $mail->SMTPDebug = 2;
        // $mail->Debugoutput = 'html';

        // Sender
        // You can keep this or change to match your Gmail exactly
        $mail->setFrom('approvativebusiness22@gmail.com', 'Approvative Business Documents Processing');

        // Recipient
        $mail->addAddress($toEmail);

        // Email content
        $mail->isHTML(true);
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
        return true;

    } catch (Exception $e) {
        error_log("Reset code email failed: " . $mail->ErrorInfo);
        return false;
    }
}