<?php
// public/test_email.php

require_once 'email_helper.php';

$to = "lumayagafranciskyle@gmail.com";   // ← CHANGE THIS TO YOUR REAL EMAIL
$code = "999999";
$name = "Test User Bacolod";

if (sendResetCodeEmail($to, $code, $name)) {
    echo "<h2 style='color: green; text-align:center;'>SUCCESS! Email sent!<br>Check your inbox and spam folder.</h2>";
} else {
    echo "<h2 style='color: red; text-align:center;'>Failed to send email.<br>Check your PHP error log (xampp/logs/php_error_log)</h2>";
}