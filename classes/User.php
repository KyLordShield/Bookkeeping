<?php

// Adjust path if your config folder is located differently
require_once __DIR__ . '/../config/Database.php';

class User
{
    private $db;
    private $user_id;
    private $username;
    private $role; // 'admin', 'staff', 'client'
    private $client_id;
    private $staff_id;
    private $is_admin;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Attempt to log in a user (using plain text password - DEVELOPMENT ONLY)
     */
    public function login($username, $password)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT user_id, username, password_hash, client_id, staff_id, is_admin, status 
                FROM users 
                WHERE username = ? 
                LIMIT 1
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            // Plain text comparison - DEVELOPMENT/TESTING PURPOSES ONLY
            if ($user && $user['status'] === 'active' && $password === $user['password_hash']) {
                $this->user_id   = $user['user_id'];
                $this->username  = $user['username'];
                $this->client_id = $user['client_id'];
                $this->staff_id  = $user['staff_id'];
                $this->is_admin  = $user['is_admin'];

                // Determine role
                if ($this->is_admin) {
                    $this->role = 'admin';
                } elseif ($this->staff_id !== null) {
                    $this->role = 'staff';
                } elseif ($this->client_id !== null) {
                    $this->role = 'client';
                } else {
                    $this->role = 'user'; // fallback
                }

                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }

                session_regenerate_id(true);

                $_SESSION['user_id']    = $this->user_id;
                $_SESSION['username']   = $this->username;
                $_SESSION['role']       = $this->role;

                if ($this->role === 'staff') {
                    $_SESSION['staff_id'] = $this->staff_id;
                } elseif ($this->role === 'client') {
                    $_SESSION['client_id'] = $this->client_id;
                }

                $this->updateLastLogin();

                return true;
            }

            return false;
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return false;
        }
    }

    private function updateLastLogin()
    {
        try {
            $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
            $stmt->execute([$this->user_id]);
        } catch (Exception $e) {
            error_log("Failed to update last_login: " . $e->getMessage());
        }
    }

    public function getDashboardUrl()
    {
        switch ($this->role) {
            case 'admin':
                return '../public/admin_pages/admin_dashboard.php';
            case 'staff':
                return '../public/staff_pages/staff_dashboard.php';
            case 'client':
                return '../public/client_pages/client_dashboard.php';
            default:
                return '../public/login_page.php';
        }
    }

    public static function isLoggedIn()
    {
        return isset($_SESSION['user_id']);
    }

    public static function getRole()
    {
        return $_SESSION['role'] ?? null;
    }

    public static function getUserId()
    {
        return $_SESSION['user_id'] ?? null;
    }

    public static function getLinkedId()
    {
        $role = self::getRole();
        if ($role === 'staff') return $_SESSION['staff_id'] ?? null;
        if ($role === 'client') return $_SESSION['client_id'] ?? null;
        return null;
    }

    public static function logout()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_unset();
        session_destroy();
    }

    public static function redirectToDashboard()
    {
        if (!self::isLoggedIn()) {
            header('Location: login.php');
            exit;
        }

        $role = self::getRole();
        $url = match ($role) {
            'admin'  => 'admin_dashboard.php',
            'staff'  => 'staff_dashboard.php',
            'client' => 'client_dashboard.php',
            default  => 'dashboard.php'
        };

        header("Location: $url");
        exit;
    }

    /**
     * Create new client user account - PLAIN TEXT password storage (DEVELOPMENT ONLY)
     */
    public static function createClientUser(
        int $client_id,
        string $username,
        string $password,
        bool $is_active = true,
        string &$error = ''
    ): bool {
        try {
            $db = Database::getInstance()->getConnection();

            // Check if username already exists
            $check = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $check->execute([$username]);
            if ($check->fetchColumn() > 0) {
                $error = "Username '$username' is already taken.";
                return false;
            }

            // Check if client already has a user account
            $checkClient = $db->prepare("SELECT COUNT(*) FROM users WHERE client_id = ?");
            $checkClient->execute([$client_id]);
            if ($checkClient->fetchColumn() > 0) {
                $error = "This client already has a user account.";
                return false;
            }

            $status = $is_active ? 'active' : 'inactive';

            $stmt = $db->prepare("
                INSERT INTO users 
                (username, password_hash, client_id, is_admin, status, created_at) 
                VALUES (?, ?, ?, FALSE, ?, NOW())
            ");

            // Store password as plain text (DEVELOPMENT ONLY - NOT SECURE FOR PRODUCTION)
            $success = $stmt->execute([$username, $password, $client_id, $status]);

            if (!$success) {
                $error = "Database error while creating user.";
            }

            return $success;

        } catch (Exception $e) {
            error_log("Error creating client user: " . $e->getMessage());
            $error = "System error: " . $e->getMessage();
            return false;
        }
    }
}