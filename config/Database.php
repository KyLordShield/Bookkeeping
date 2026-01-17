<?php
class Database
{
    private static $instance = null;
    private $pdo;

    private function __construct()
    {
        $host     = getenv('DB_HOST') ?: 'localhost';
        $dbname   = getenv('DB_NAME') ?: 'bookkeeping';
        $username = getenv('DB_USER') ?: 'root';
        $password = getenv('DB_PASS') ?: '';
        $port     = getenv('DB_PORT') ?: '12488';
        $charset  = 'utf8mb4';
        $ssl      = getenv('DB_SSL') === 'true';

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        if ($ssl) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = __DIR__ . '/ca.pem'; 
        }

        try {
            $this->pdo = new PDO($dsn, $username, $password, $options);
            $this->pdo->exec("SET time_zone = '+08:00'");
        } catch (PDOException $e) {
            die(
                "<strong>Database connection failed.</strong><br>" .
                "Environment: " . (getenv('DB_HOST') ? 'Production (Aiven)' : 'Localhost') .
                "<br><br>Error: " . $e->getMessage()
            );
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    public function getConnection() { return $this->pdo; }
    private function __clone() {}
    public function __wakeup() { throw new Exception("Cannot unserialize Database singleton"); }
}
