<?php


class Database {
    
    
    
    private $useRemoteDb = false;  
    
    
    
    
    
    private $remoteHost = "your-remote-host.com";      
    private $remoteDatabase = "swapie_db";              
    private $remoteUsername = "your_remote_username";   
    private $remotePassword = "your_remote_password";   
    private $remotePort = 3306;                         
    
    
    
    
    private $localHost = "localhost";    
    private $localDatabase = "swapie_db"; 
    private $localUsername = "root";      
    private $localPassword = "";          
    private $localPort = 3306;            
    
    
    
    
    private $charset = "utf8mb4";
    public $conn;
    
    
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
