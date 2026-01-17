<?php
// public/email_helper.php - Using SendGrid HTTP API (WORKS ON RENDER & LOCALHOST!)

require_once __DIR__ . '/../vendor/autoload.php';

// Load .env file if it exists (for localhost)
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

function sendResetCodeEmail(
    string $toEmail,
    string $resetCode,
    string $userName = "User"
): bool {
    try {
        // Get API key from environment variable (works on both localhost and Render)
        $apiKey = getenv('SENDGRID_API_KEY') ?: $_ENV['SENDGRID_API_KEY'] ?? null;
        
        if (empty($apiKey)) {
            error_log("CRITICAL: SENDGRID_API_KEY not set in environment variables or .env file");
            return false;
        }
        
        $fromEmail = getenv('SMTP_FROM_EMAIL') ?: $_ENV['SMTP_FROM_EMAIL'] ?? 'approvativebusiness22@gmail.com';
        $fromName = getenv('SMTP_FROM_NAME') ?: $_ENV['SMTP_FROM_NAME'] ?? 'Approvative Business';
        
        $email = new \SendGrid\Mail\Mail();
        $email->setFrom($fromEmail, $fromName);
        $email->setSubject("Your Password Reset Code - Client Service Management");
        $email->addTo($toEmail, $userName);
        
        $htmlContent = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <div style='background: #7D1C19; padding: 20px; text-align: center; margin-bottom: 30px;'>
                    <h1 style='color: white; margin: 0;'>Approvative Business</h1>
                </div>
                
                <h2 style='color: #333;'>Hello {$userName},</h2>
                <p style='color: #666; font-size: 16px;'>You recently requested to reset your password for your account. Use the code below to complete the process:</p>
                
                <div style='background: #f8f9fa; padding: 25px; text-align: center; border-radius: 8px; margin: 30px 0; border: 2px solid #e9ecef;'>
                    <p style='color: #888; font-size: 14px; margin: 0 0 10px 0;'>Your verification code:</p>
                    <h1 style='font-size: 36px; letter-spacing: 8px; margin: 10px 0; color: #7D1C19; font-weight: bold;'>
                        {$resetCode}
                    </h1>
                    <p style='color: #888; font-size: 14px; margin: 10px 0 0 0;'>
                        Valid for 30 minutes
                    </p>
                </div>
                
                <p style='color: #666; font-size: 14px;'>If you didn't request this password reset, you can safely ignore this email. Your password will remain unchanged.</p>
                
                <hr style='border: none; border-top: 1px solid #e9ecef; margin: 30px 0;'>
                
                <p style='color: #999; font-size: 12px; text-align: center;'>
                    This is an automated message from Approvative Business Documents Processing<br>
                    Bacolod City, Philippines
                </p>
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