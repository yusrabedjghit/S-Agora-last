<?php


class Demand {
    private $conn;
    private $table = 'demands';
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    
    public function getAll($page = 1, $perPage = 10, $filters = []) {
        $offset = ($page - 1) * $perPage;
        $where = ["d.status != 'deleted'"];
        $params = [];
        
        if (!empty($filters['status'])) {
            $where[] = "d.status = :status";
            $params['status'] = $filters['status'];
        }
        
        if (!empty($filters['category_id'])) {
            $where[] = "d.category_id = :category_id";
            $params['category_id'] = $filters['category_id'];
        }
        
        if (!empty($filters['user_id'])) {
            $where[] = "d.user_id = :user_id";
            $params['user_id'] = $filters['user_id'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(d.title LIKE :search OR d.description LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        if (!empty($filters['min_budget'])) {
            $where[] = "d.budget >= :min_budget";
            $params['min_budget'] = $filters['min_budget'];
        }
        
        if (!empty($filters['max_budget'])) {
            $where[] = "d.budget <= :max_budget";
            $params['max_budget'] = $filters['max_budget'];
        }
        
        if (!empty($filters['urgency'])) {
            $where[] = "d.urgency = :urgency";
            $params['urgency'] = $filters['urgency'];
        }
        
        $whereClause = implode(' AND ', $where);
        
        
        $countQuery = "SELECT COUNT(*) as total FROM {$this->table} d WHERE {$whereClause}";
        $stmt = $this->conn->prepare($countQuery);
        $stmt->execute($params);
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        
        $query = "SELECT d.*, 
                         u.username, u.full_name as requester_name, u.profile_image as requester_avatar,
                         c.name as category_name
                  FROM {$this->table} d
                  LEFT JOIN users u ON d.user_id = u.id
                  LEFT JOIN categories c ON d.category_id = c.id
                  WHERE {$whereClause}
                  ORDER BY d.created_at DESC
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return [
            'demands' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => (int)$total
        ];
    }
    
    
    public function getById($id) {
        $query = "SELECT d.*, 
                         u.username, u.full_name as requester_name, u.email as requester_email, 
                         u.profile_image as requester_avatar, u.is_active as requester_status,
                         c.name as category_name
                  FROM {$this->table} d
                  LEFT JOIN users u ON d.user_id = u.id
                  LEFT JOIN categories c ON d.category_id = c.id
                  WHERE d.id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    
    public function getByUserId($userId, $page = 1, $perPage = 10) {
        return $this->getAll($page, $perPage, ['user_id' => $userId]);
    }
    
    
    public function getByCategory($categoryId, $page = 1, $perPage = 10) {
        return $this->getAll($page, $perPage, ['category_id' => $categoryId]);
    }
    
    
    public function create($data) {
        $query = "INSERT INTO {$this->table} 
                  (user_id, category_id, title, description, budget, deadline, urgency, location, attachments, tags, status, created_at, updated_at)
                  VALUES (:user_id, :category_id, :title, :description, :budget, :deadline, :urgency, :location, :attachments, :tags, :status, NOW(), NOW())";
        
        $stmt = $this->conn->prepare($query);
        $result = $stmt->execute([
            'user_id' => $data['user_id'],
            'category_id' => $data['category_id'],
            'title' => trim($data['title']),
            'description' => trim($data['description']),
            'budget' => $data['budget'],
            'deadline' => $data['deadline'] ?? null,
            'urgency' => $data['urgency'] ?? 'normal',
            'location' => $data['location'] ?? null,
            'attachments' => isset($data['attachments']) ? (is_array($data['attachments']) ? json_encode($data['attachments']) : $data['attachments']) : null,
            'tags' => isset($data['tags']) ? (is_array($data['tags']) ? json_encode($data['tags']) : $data['tags']) : null,
            'status' => $data['status'] ?? 'open'
        ]);
        
        return $result ? $this->conn->lastInsertId() : false;
    }
    
    
    public function update($id, $data) {
        $fields = [];
        $params = ['id' => $id];
        
        $allowedFields = ['category_id', 'title', 'description', 'budget', 'deadline', 'urgency', 'location', 'attachments', 'tags', 'status'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = :{$field}";
                if (in_array($field, ['attachments', 'tags']) && is_array($data[$field])) {
                    $params[$field] = json_encode($data[$field]);
                } elseif (is_string($data[$field])) {
                    $params[$field] = trim($data[$field]);
                } else {
                    $params[$field] = $data[$field];
                }
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $fields[] = "updated_at = NOW()";
        
        $query = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute($params);
    }
    
    
    public function updateStatus($id, $status) {
        $query = "UPDATE {$this->table} SET status = :status, updated_at = NOW() WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute(['id' => $id, 'status' => $status]);
    }
    
    
    public function delete($id) {
        $query = "UPDATE {$this->table} SET status = 'deleted', updated_at = NOW() WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute(['id' => $id]);
    }
    
    
    public function hardDelete($id) {
        $query = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute(['id' => $id]);
    }
    
    
    public function getCount($filters = []) {
        $where = ["status != 'deleted'"];
        $params = [];
        
        if (!empty($filters['status'])) {
            $where[] = "status = :status";
            $params['status'] = $filters['status'];
        }
        
        if (!empty($filters['category_id'])) {
            $where[] = "category_id = :category_id";
            $params['category_id'] = $filters['category_id'];
        }
        
        if (!empty($filters['user_id'])) {
            $where[] = "user_id = :user_id";
            $params['user_id'] = $filters['user_id'];
        }
        
        $whereClause = implode(' AND ', $where);
        
        $query = "SELECT COUNT(*) as count FROM {$this->table} WHERE {$whereClause}";
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    
    public function getRecent($limit = 10) {
        $query = "SELECT d.*, 
                         u.username, u.full_name as requester_name, u.profile_image as requester_avatar,
                         c.name as category_name
                  FROM {$this->table} d
                  LEFT JOIN users u ON d.user_id = u.id
                  LEFT JOIN categories c ON d.category_id = c.id
                  WHERE d.status = 'open'
                  ORDER BY d.created_at DESC
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    
    public function search($keyword, $page = 1, $perPage = 10) {
        return $this->getAll($page, $perPage, ['search' => $keyword, 'status' => 'open']);
    }
    
    
    public function incrementViews($id) {
        $query = "UPDATE {$this->table} SET views = views + 1 WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute(['id' => $id]);
    }
    
    
    public function belongsToUser($demandId, $userId) {
        $query = "SELECT COUNT(*) as count FROM {$this->table} WHERE id = :demand_id AND user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['demand_id' => $demandId, 'user_id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    }
    
    
    public function getExpiringSoon($days = 7, $limit = 10) {
        $query = "SELECT d.*, 
                         u.username, u.full_name as requester_name,
                         c.name as category_name
                  FROM {$this->table} d
                  LEFT JOIN users u ON d.user_id = u.id
                  LEFT JOIN categories c ON d.category_id = c.id
                  WHERE d.status = 'open' 
                    AND d.deadline IS NOT NULL 
                    AND d.deadline <= DATE_ADD(NOW(), INTERVAL :days DAY)
                    AND d.deadline >= NOW()
                  ORDER BY d.deadline ASC
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':days', (int)$days, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
