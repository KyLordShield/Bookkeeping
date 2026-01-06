<?php

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
     * Attempt to log in a user
     * @param string $username
     * @param string $password
     * @return bool True on success, false on failure
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

            if ($user && $user['status'] === 'active' && $password === $user['password_hash']) {
                // Successful login
                $this->user_id   = $user['user_id'];
                $this->username  = $user['username'];
                $this->client_id = $user['client_id'];
                $this->staff_id  = $user['staff_id'];
                $this->is_admin  = $user['is_admin'];

                // Determine role with priority: admin > staff > client
                if ($this->is_admin) {
                    $this->role = 'admin';
                } elseif ($this->staff_id !== null) {
                    $this->role = 'staff';
                } elseif ($this->client_id !== null) {
                    $this->role = 'client';
                } else {
                    $this->role = 'user'; // fallback (shouldn't happen)
                }

                // Start session and store data
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }

                session_regenerate_id(true); // Security

                $_SESSION['user_id']    = $this->user_id;
                $_SESSION['username']   = $this->username;
                $_SESSION['role']       = $this->role;

                if ($this->role === 'staff') {
                    $_SESSION['staff_id'] = $this->staff_id;
                } elseif ($this->role === 'client') {
                    $_SESSION['client_id'] = $this->client_id;
                }

                // Update last login
                $this->updateLastLogin();

                return true;
            }

            return false; // Invalid credentials or inactive
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update last_login timestamp
     */
    private function updateLastLogin()
    {
        try {
            $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
            $stmt->execute([$this->user_id]);
        } catch (Exception $e) {
            error_log("Failed to update last_login: " . $e->getMessage());
        }
    }

    /**
     * Get the dashboard URL based on user role
     * @return string
     */
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
                return '../public/login_page.php'; // fallback
        }
    }

    /**
     * Check if user is logged in (static helper)
     * @return bool
     */
    public static function isLoggedIn()
    {
        return isset($_SESSION['user_id']);
    }

    /**
     * Get current logged-in user's role (static)
     * @return string|null
     */
    public static function getRole()
    {
        return $_SESSION['role'] ?? null;
    }

    /**
     * Get current user ID
     */
    public static function getUserId()
    {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Get linked client_id or staff_id
     */
    public static function getLinkedId()
    {
        $role = self::getRole();
        if ($role === 'staff') {
            return $_SESSION['staff_id'] ?? null;
        } elseif ($role === 'client') {
            return $_SESSION['client_id'] ?? null;
        }
        return null;
    }

    /**
     * Logout the user
     */
    public static function logout()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_unset();
        session_destroy();
    }

    /**
     * Redirect to appropriate dashboard (helper for protected pages)
     */
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
}