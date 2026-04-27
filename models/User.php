<?php


class User {
    private $conn;
    private $table = 'users';
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    
    public function authenticate($identifier, $password) {
        $identifier = strtolower(trim($identifier));
        $query = "SELECT id, username, email, password, full_name, phone, profile_image, bio, 
                         is_active, is_verified, coins, last_login 
                  FROM {$this->table} 
                  WHERE (email = :email OR username = :username) AND is_active = 1
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['email' => $identifier, 'username' => $identifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            
            $this->updateLastLogin($user['id']);
            
            
            unset($user['password']);
            return $user;
        }
        
        return false;
    }
    
    
    public function updateLastLogin($id) {
        $query = "UPDATE {$this->table} SET last_login = NOW() WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute(['id' => $id]);
    }
    
    
    public function getAll($page = 1, $perPage = 10, $filters = []) {
        $offset = ($page - 1) * $perPage;
        $where = ["is_active = 1"];
        $params = [];
        
        if (isset($filters['is_active'])) {
            $where = ["is_active = :is_active"];
            $params['is_active'] = $filters['is_active'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(username LIKE :search OR email LIKE :search OR full_name LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        if (isset($filters['is_verified'])) {
            $where[] = "is_verified = :is_verified";
            $params['is_verified'] = $filters['is_verified'];
        }
        
        $whereClause = implode(' AND ', $where);
        
        
        $countQuery = "SELECT COUNT(*) as total FROM {$this->table} WHERE {$whereClause}";
        $stmt = $this->conn->prepare($countQuery);
        $stmt->execute($params);
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        
        $query = "SELECT id, username, email, full_name, phone, profile_image, bio, is_active, 
                         is_verified, coins, last_login, created_at, updated_at 
                  FROM {$this->table} 
                  WHERE {$whereClause}
                  ORDER BY created_at DESC
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return [
            'users' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => (int)$total
        ];
    }
    
    
    public function getById($id) {
        $query = "SELECT id, username, email, full_name, phone, profile_image, bio, is_active, 
                         is_verified, coins, last_login, created_at, updated_at 
                  FROM {$this->table} 
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            
            $user['total_services'] = $this->getServiceCount($id);
            $user['total_demands'] = $this->getDemandCount($id);
        }
        
        return $user;
    }
    
    
    public function getDetails($id) {
        $user = $this->getById($id);
        
        if (!$user) {
            return null;
        }
        
        
        $query = "SELECT id, title, status, price, created_at FROM services 
                  WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 5";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['user_id' => $id]);
        $user['recent_services'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        
        $query = "SELECT id, title, status, budget, created_at FROM demands 
                  WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 5";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['user_id' => $id]);
        $user['recent_demands'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        
        $query = "SELECT id, type, coins, status, created_at FROM transactions 
                  WHERE from_user_id = :uid1 OR to_user_id = :uid2 ORDER BY created_at DESC LIMIT 5";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['uid1' => $id, 'uid2' => $id]);
        $user['recent_transactions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $user;
    }
    
    
    public function getByEmail($email) {
        $query = "SELECT id, username, email, full_name, phone, profile_image, is_active, 
                         is_verified, coins, created_at 
                  FROM {$this->table} 
                  WHERE email = :email AND is_active = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['email' => strtolower(trim($email))]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    
    public function getByUsername($username) {
        $query = "SELECT id, username, email, full_name, phone, profile_image, is_active, 
                         is_verified, coins, created_at 
                  FROM {$this->table} 
                  WHERE username = :username AND is_active = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['username' => strtolower(trim($username))]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    
    public function create($data) {
        $coins = $data['coins'] ?? $data['coin_balance'] ?? 50;
        
        try {
            $this->conn->beginTransaction();
            
            $query = "INSERT INTO {$this->table} 
                      (username, email, password, full_name, phone, profile_image, bio, is_active, is_verified, coins, created_at, updated_at)
                      VALUES (:username, :email, :password, :full_name, :phone, :profile_image, :bio, :is_active, :is_verified, :coins, NOW(), NOW())";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                'username' => strtolower(trim($data['username'])),
                'email' => strtolower(trim($data['email'])),
                'password' => password_hash($data['password'], PASSWORD_DEFAULT),
                'full_name' => trim($data['full_name'] ?? $data['username']),
                'phone' => $data['phone'] ?? null,
                'profile_image' => $data['profile_image'] ?? $data['avatar'] ?? null,
                'bio' => $data['bio'] ?? null,
                'is_active' => 1,
                'is_verified' => $data['is_verified'] ?? 0,
                'coins' => $coins
            ]);
            
            $userId = $this->conn->lastInsertId();
            
            if ($userId && $coins > 0) {
                
                $queryTrans = "INSERT INTO transactions (from_user_id, to_user_id, type, coins, status, notes, created_at) 
                               VALUES (9, :to_id, 'bonus', :amount, 'completed', 'Welcome Bonus', NOW())";
                $stmtTrans = $this->conn->prepare($queryTrans);
                $stmtTrans->execute([
                    'to_id' => $userId,
                    'amount' => $coins
                ]);
            }
            
            $this->conn->commit();
            return $userId;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }
    
    
    public function update($id, $data) {
        $fields = [];
        $params = ['id' => $id];
        
        $allowedFields = ['username', 'email', 'full_name', 'phone', 'profile_image', 'bio', 'is_active', 'is_verified', 'coins', 'location', 'skills'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = :{$field}";
                if (in_array($field, ['username', 'email'])) {
                    $params[$field] = strtolower(trim($data[$field]));
                } elseif (is_string($data[$field])) {
                    $params[$field] = trim($data[$field]);
                } else {
                    $params[$field] = $data[$field];
                }
            }
        }
        
        
        if (!empty($data['password'])) {
            $fields[] = "password = :password";
            $params['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $fields[] = "updated_at = NOW()";
        
        $query = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute($params);
    }
    
    
    public function updateCoinBalance($id, $amount) {
        $query = "UPDATE {$this->table} SET coins = coins + :amount, updated_at = NOW() WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute(['id' => $id, 'amount' => $amount]);
    }
    
    
    public function getCoinBalance($id) {
        $query = "SELECT coins FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['coins'] : 0;
    }
    
    
    public function delete($id) {
        $query = "UPDATE {$this->table} SET is_active = 0, updated_at = NOW() WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute(['id' => $id]);
    }
    
    
    public function hardDelete($id) {
        $query = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute(['id' => $id]);
    }
    
    
    public function emailExists($email, $excludeId = null) {
        $query = "SELECT COUNT(*) as count FROM {$this->table} WHERE email = :email AND is_active = 1";
        $params = ['email' => strtolower(trim($email))];
        
        if ($excludeId) {
            $query .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    }
    
    
    public function usernameExists($username, $excludeId = null) {
        $query = "SELECT COUNT(*) as count FROM {$this->table} WHERE username = :username AND is_active = 1";
        $params = ['username' => strtolower(trim($username))];
        
        if ($excludeId) {
            $query .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    }
    
    
    public function getCount($filters = []) {
        $where = ["is_active = 1"];
        $params = [];
        
        if (isset($filters['is_active'])) {
            $where = ["is_active = :is_active"];
            $params['is_active'] = $filters['is_active'];
        }
        
        $whereClause = implode(' AND ', $where);
        
        $query = "SELECT COUNT(*) as count FROM {$this->table} WHERE {$whereClause}";
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    
    private function getServiceCount($userId) {
        $query = "SELECT COUNT(*) as count FROM services WHERE user_id = :user_id AND status != 'deleted'";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['user_id' => $userId]);
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    
    private function getDemandCount($userId) {
        $query = "SELECT COUNT(*) as count FROM demands WHERE user_id = :user_id AND status != 'deleted'";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['user_id' => $userId]);
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    
    public function verifyEmail($id) {
        $query = "UPDATE {$this->table} SET is_verified = 1, email_verified_at = NOW(), updated_at = NOW() WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute(['id' => $id]);
    }
}
