<?php
session_start();
require_once __DIR__ . '/../../classes/Client.php';  // adjust path

// Redirect if not logged in as client
if (!isset($_SESSION['user_id']) || !isset($_SESSION['client_id'])) {
    header("Location: ../../login_page.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $clientId = (int)$_SESSION['client_id'];
    $data = [
        'first_name'    => trim($_POST['first_name'] ?? ''),
        'last_name'     => trim($_POST['last_name'] ?? ''),
        'email'         => trim($_POST['email'] ?? ''),
        'phone'         => trim($_POST['phone'] ?? ''),
        'company_name'  => trim($_POST['company_name'] ?? ''),
        'business_type' => trim($_POST['business_type'] ?? ''),
        // you can add 'account_status' if you want clients to change it (probably not)
    ];

    // Optional: add basic validation here
    if (empty($data['first_name']) || empty($data['last_name']) || empty($data['email'])) {
        $_SESSION['profile_error'] = "Required fields cannot be empty.";
    } else {
        $success = Client::update($clientId, $data);
        if ($success) {
            $_SESSION['profile_success'] = "Profile updated successfully!";
        } else {
            $_SESSION['profile_error'] = "Failed to update profile.";
        }
    }

    header("Location: client_dashboard.php"); // or wherever you want
    exit;
}