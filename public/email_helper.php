<?php
// public/email_helper.php - Using SendGrid HTTP API (WORKS ON RENDER!)

require_once __DIR__ . '/../vendor/autoload.php';

function sendResetCodeEmail(
    string $toEmail,
    string $resetCode,
    string $userName = "User"
): bool {
    try {
        // Get API key from environment variable
        $apiKey = getenv('SENDGRID_API_KEY');
        
        if (empty($apiKey)) {
            error_log("CRITICAL: SENDGRID_API_KEY not set in environment variables");
            return false;
        }
        
        $fromEmail = getenv('SMTP_FROM_EMAIL') ?: 'approvativebusiness22@gmail.com';
        $fromName = getenv('SMTP_FROM_NAME') ?: 'Approvative Business';
        
        $email = new \SendGrid\Mail\Mail();
        $email->setFrom($fromEmail, $fromName);
        $email->setSubject("Your Password Reset Code - Client Service Management");
        $email->addTo($toEmail, $userName);
        
        $htmlContent = "
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
        
        $email->addContent("text/html", $htmlContent);
        
        $textContent = "Hello {$userName},\n\n" .
                      "Your password reset code is: {$resetCode}\n" .
                      "This code is valid for 30 minutes.\n\n" .
                      "If you didn't request this, ignore this email.";
        
        $email->addContent("text/plain", $textContent);
        
        $sendgrid = new \SendGrid($apiKey);
        $response = $sendgrid->send($email);
        
        if ($response->statusCode() >= 200 && $response->statusCode() < 300) {
            error_log("Password reset email sent successfully to: $toEmail via SendGrid HTTP API");
            return true;
        } else {
            error_log("SendGrid API error: " . $response->statusCode() . " - " . $response->body());
            return false;
        }
        
    } catch (Exception $e) {
        error_log("CRITICAL: SendGrid email failed to $toEmail");
        error_log("Error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return false;
    }
}
?>