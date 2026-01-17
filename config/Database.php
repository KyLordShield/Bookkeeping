<?php

class Database
{
    // Hold the single instance
    private static $instance = null;

    // Hold the PDO connection
    private $pdo;

    // Private constructor (Singleton pattern)
    private function __construct()
    {
        // ===============================
        // AUTO-DETECT ENVIRONMENT
        // ===============================
        $host     = getenv('DB_HOST') ?: 'localhost';
        $dbname   = getenv('DB_NAME') ?: 'client_service_management';
        $username = getenv('DB_USER') ?: 'root';
        $password = getenv('DB_PASS') ?: '';
        $port     = getenv('DB_PORT') ?: '3306';
        $charset  = 'utf8mb4';

        // ===============================
        // DSN (Aiven + Localhost compatible)
        // ===============================
        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $username, $password, $options);

            // Set timezone to Philippines (UTC+8)
            $this->pdo->exec("SET time_zone = '+08:00'");

        } catch (PDOException $e) {
            die(
                "<strong>Database connection failed.</strong><br><br>" .
                "Environment: " . (getenv('DB_HOST') ? 'Production (Aiven)' : 'Localhost') .
                "<br><br>Error details:<br>" . $e->getMessage()
            );
        }
    }

    // Get the single instance
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Get the PDO connection
    public function getConnection()
    {
        return $this->pdo;
    }

    // Prevent cloning
    private function __clone() {}

    // Prevent unserialization
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize Database singleton");
    }
}
