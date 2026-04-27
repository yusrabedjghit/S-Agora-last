<?php


class Database {
    private $useRemoteDb = true;
    
    
    
    
    
    private $remoteHost;
    private $remoteDatabase;
    private $remoteUsername;
    private $remotePassword;
    private $remotePort;
    
    private $localHost = "localhost";
    private $localDatabase = "swapie_db";
    private $localUsername = "root";
    private $localPassword = "";
    private $localPort = 3306;
 
    private $charset = "utf8mb4";
    public $conn;
    
    public function __construct() {
        
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    if (!getenv($key)) {
                        putenv("$key=$value");
                    }
                }
            }
        }
        
        
        $this->remoteHost = getenv('DB_HOST') ?: "hopper.proxy.rlwy.net";
        $this->remoteDatabase = getenv('DB_NAME') ?: "railway";
        $this->remoteUsername = getenv('DB_USER') ?: "root";
        $this->remotePassword = getenv('DB_PASSWORD') ?: "AgQKHIuBjfjqlbOibzQDfCUEYjqVSyKE";
        $this->remotePort = (int)(getenv('DB_PORT') ?: "45501");
    }
    
    
    public function getConnection() {
        $this->conn = null;
        
        
        if ($this->useRemoteDb) {
            $host = $this->remoteHost;
            $database = $this->remoteDatabase;
            $username = $this->remoteUsername;
            $password = $this->remotePassword;
            $port = $this->remotePort;
        } else {
            $host = $this->localHost;
            $database = $this->localDatabase;
            $username = $this->localUsername;
            $password = $this->localPassword;
            $port = $this->localPort;
        }
        
        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 30,
            ];
            
            $this->conn = new PDO($dsn, $username, $password, $options);
        } catch(PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            throw new Exception("Database connection failed: " . ($this->useRemoteDb ? "Remote" : "Local"));
        }
        
        return $this->conn;
    }
    
    
    public function isRemote() {
        return $this->useRemoteDb;
    }
}
