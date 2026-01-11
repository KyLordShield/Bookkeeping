<?php



class Database
{
    // This will hold our single database connection
    private static $instance = null;

    // This is the actual PDO connection
    private $pdo;

    // Private constructor so no one can create multiple instances
    private function __construct()
    {
        // ========== DATABASE SETTINGS ==========
        // TODO: Change these to match your setup!
        $host     = 'localhost';                    // Usually 'localhost'
        $dbname   = 'client_service_management'; //'client_service_management';    // Your database name
        //$dbname   = 'bookkeeping';        // Your database name
          
        $username = 'root';                         // Default XAMPP/WAMP username
        $password = '';                             // Default is empty for local dev
        $charset  = 'utf8mb4';                      // Best for full character support

        // This is the connection string (DSN = Data Source Name)
        $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

        // Options to make PDO safer and easier to use
        $options = [
            // Show errors as exceptions 
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,

            // Return results as associative arrays 
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

            // Use real prepared statements (very important for security!)
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            // Create the connection
            $this->pdo = new PDO($dsn, $username, $password, $options);
            
            
        } catch (PDOException $e) {
            // Friendly error message for students
            die("Oops! Could not connect to the database. <br>
                 Check if:<br>
                 • MySQL is running<br>
                 • Database name is correct<br>
                 • Username and password are right<br><br>
                 Error details (for learning): " . $e->getMessage());
        }
    }

    
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();  // Create it only once
        }
        return self::$instance;
    }

    /**
     * Get the PDO connection to use in queries
     */
    public function getConnection()
    {
        return $this->pdo;
    }

    // Prevent copying the instance
    private function __clone() {}

    // Prevent unserializing
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize Database singleton");
    }
}