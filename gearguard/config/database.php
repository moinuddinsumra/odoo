<?php
/**
 * GearGuard CMMS - Database Configuration
 * Production-Ready PDO Connection Handler
 */

class database {
    public static $instance = null;
    public $connection;
    
    // Database credentials
    public $host = 'localhost';
    public $dbname = 'gearguard_cmms';
    public $username = 'root';
    
    public $port = '3306';
    public $password = '';
    public $charset = 'utf8mb4';
    
    public function __construct() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset}"
            ];
            
            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
        } catch(PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            die("Database connection failed. Please check configuration.");
        }
    }
    
    /**
     * Singleton pattern - ensures only one database connection
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get PDO connection object
     */
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * Prevent cloning of instance
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

/**
 * Quick helper function to get database connection
 */
function getDB() {
    return Database::getInstance()->getConnection();
}
?>