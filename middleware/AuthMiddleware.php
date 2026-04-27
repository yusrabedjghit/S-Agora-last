<?php

require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../models/Admin.php';

class AuthMiddleware {
    private $conn;
    private $admin;
    private $adminModel;
    
    public function __construct($db) {
        $this->conn = $db;
        $this->adminModel = new Admin($db);
    }

    
    public function authenticate() {
        $token = JWT::getTokenFromHeader();
        
        if (!$token) {
            Response::unauthorized("No authentication token provided");
        }
        
        $payload = JWT::decode($token);
        
        if (!$payload) {
            Response::unauthorized("Invalid or expired token");
        }
        
        if (!isset($payload['admin_id'])) {
            Response::unauthorized("Invalid token payload");
        }
        
        $query = "SELECT id, username, email, full_name, role, status, last_login 
                  FROM admins 
                  WHERE id = :id AND status = 'active'
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['id' => $payload['admin_id']]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$admin) {
            Response::unauthorized("Admin account not found or inactive");
        }
        
        $this->admin = $admin;
        return $this->admin;
    }
    
    
    public function requireRole($allowedRoles = ['super_admin', 'admin']) {
        if (!$this->admin) {
            $this->authenticate();
        }
        
        if (!in_array($this->admin['role'], $allowedRoles)) {
            Response::forbidden("Insufficient permissions for this action");
        }
        
        return $this->admin;
    }
    
    
    public function isSuperAdmin() {
        if (!$this->admin) {
            $this->authenticate();
        }
        
        return $this->admin['role'] === 'super_admin';
    }
    
    
    public function getAdmin() {
        return $this->admin;
    }
    
    
    public function getAdminId() {
        return $this->admin ? $this->admin['id'] : null;
    }
    
    
    public function logActivity($action, $entityType, $entityId = null, $details = null) {
        if (!$this->admin) {
            return false;
        }
        
        try {
            $query = "INSERT INTO admin_activity_logs 
                      (admin_id, action, entity_type, entity_id, details, ip_address, user_agent, created_at)
                      VALUES (:admin_id, :action, :entity_type, :entity_id, :details, :ip_address, :user_agent, NOW())";
            
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([
                'admin_id' => $this->admin['id'],
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'details' => $details ? json_encode($details) : null,
                'ip_address' => $this->getClientIp(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (Exception $e) {
            
            error_log("Failed to log activity: " . $e->getMessage());
            return false;
        }
    }
    
    
    public function logAction($action, $entityType, $entityId = null, $details = null) {
        return $this->logActivity($action, $entityType, $entityId, $details);
    }
    
    
    private function getClientIp() {
        $ipKeys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return 'unknown';
    }
    
    
    public function optionalAuth() {
        $token = JWT::getTokenFromHeader();
        
        if (!$token) {
            return null;
        }
        
        $payload = JWT::decode($token);
        
        if (!$payload || !isset($payload['admin_id'])) {
            return null;
        }
        
        $query = "SELECT id, username, email, full_name, role, status 
                  FROM admins 
                  WHERE id = :id AND status = 'active'
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['id' => $payload['admin_id']]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin) {
            $this->admin = $admin;
        }
        
        return $admin;
    }
}
