<?php
session_start();

/* Optional: CSRF check if you already use tokens */

// Unset all session variables
$_SESSION = [];

// Destroy session
session_destroy();

// Remove session cookie (extra safety)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Redirect to login page
header("Location: login_page.php");
exit;
