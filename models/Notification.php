<?php


class Notification {
    private $conn;
    private $table = 'notifications';
    
    
    const TYPE_MESSAGE = 'message';
    const TYPE_TRANSACTION = 'transaction';
    const TYPE_SERVICE = 'service';
    const TYPE_DEMAND = 'demand';
    const TYPE_REPORT = 'report';
    const TYPE_RATING = 'rating';
    const TYPE_SYSTEM = 'system';
    const TYPE_PROMOTION = 'promotion';
    
    
    const CATEGORY_INFO = 'info';
    const CATEGORY_SUCCESS = 'success';
    const CATEGORY_WARNING = 'warning';
    const CATEGORY_ERROR = 'error';
    const CATEGORY_PROMOTION = 'promotion';
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    
    public function create($data) {
        
        $query = "INSERT INTO {$this->table} 
                  (user_id, type, title, content)
                  VALUES (:user_id, :type, :title, :content)";
        
        $stmt = $this->conn->prepare($query);
        $result = $stmt->execute([
            'user_id' => $data['user_id'],
            'type' => $data['type'],
            'title' => isset($data['title']) ? trim($data['title']) : '',
            'content' => isset($data['content']) ? trim($data['content']) : ''
        ]);
        
        return $result ? $this->conn->lastInsertId() : false;
    }
    
    
    public function createBulk($notifications) {
        $count = 0;
        $this->conn->beginTransaction();
        
        try {
            foreach ($notifications as $data) {
                if ($this->create($data)) {
                    $count++;
                }
            }
            $this->conn->commit();
        } catch (Exception $e) {
            $this->conn->rollBack();
            return 0;
        }
        
        return $count;
    }
    
    
    public function sendToAll($data, $filters = []) {
        $where = ["is_active = 1"];
        $params = [];
        
        if (!empty($filters['email_verified'])) {
            $where[] = "is_verified = 1";
        }
        
        $whereClause = implode(' AND ', $where);
        
        $query = "SELECT id FROM users WHERE {$whereClause}";
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $notifications = [];
        foreach ($users as $userId) {
            $notifications[] = array_merge($data, ['user_id' => $userId]);
        }
        
        return $this->createBulk($notifications);
    }
    
    
    public function getById($id) {
        $query = "SELECT id, user_id, type, title, content, reference_id, reference_type, is_read, read_at FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    
    public function getByUser($userId, $page = 1, $perPage = 20, $filters = []) {
        $offset = ($page - 1) * $perPage;
        $where = ["user_id = :user_id"];
        $params = ['user_id' => $userId];
        
        if (isset($filters['is_read'])) {
            $where[] = "is_read = :is_read";
            $params['is_read'] = $filters['is_read'] ? 1 : 0;
        }
        
        if (!empty($filters['type'])) {
            $where[] = "type = :type";
            $params['type'] = $filters['type'];
        }
        
        
        
        
        
        
        
        $whereClause = implode(' AND ', $where);
        
        
        $countQuery = "SELECT COUNT(*) as total FROM {$this->table} WHERE {$whereClause}";
        $stmt = $this->conn->prepare($countQuery);
        $stmt->execute($params);
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        
        $query = "SELECT id, user_id, type, title, content, reference_id, reference_type, is_read, read_at FROM {$this->table} 
                  WHERE {$whereClause}
                  ORDER BY id DESC
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return [
            'notifications' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => (int)$total
        ];
    }
    
    
    public function getUnread($userId, $limit = 10) {
        $query = "SELECT id, user_id, type, title, content, reference_id, reference_type, is_read, read_at FROM {$this->table} 
                  WHERE user_id = :user_id AND is_read = 0 
                  ORDER BY id DESC
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    
    public function getUnreadCount($userId) {
        $query = "SELECT COUNT(*) as count FROM {$this->table} 
                  WHERE user_id = :user_id AND is_read = 0";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['user_id' => $userId]);
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    
    public function markAsRead($id, $userId) {
        $query = "UPDATE {$this->table} SET is_read = 1, read_at = NOW() WHERE id = :id AND user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute(['id' => $id, 'user_id' => $userId]);
    }
    
    
    public function markMultipleAsRead($ids, $userId) {
        if (empty($ids)) return 0;
        
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $query = "UPDATE {$this->table} SET is_read = 1, read_at = NOW() 
                  WHERE id IN ({$placeholders}) AND user_id = ?";
        
        $stmt = $this->conn->prepare($query);
        $params = array_merge($ids, [$userId]);
        $stmt->execute($params);
        
        return $stmt->rowCount();
    }
    
    
    public function markAllAsRead($userId) {
        $query = "UPDATE {$this->table} SET is_read = 1, read_at = NOW() WHERE user_id = :user_id AND is_read = 0";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->rowCount();
    }
    
    
    public function markTypeAsRead($userId, $type) {
        $query = "UPDATE {$this->table} SET is_read = 1, read_at = NOW() 
                  WHERE user_id = :user_id AND type = :type AND is_read = 0";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['user_id' => $userId, 'type' => $type]);
        return $stmt->rowCount();
    }
    
    
    public function delete($id, $userId) {
        $query = "DELETE FROM {$this->table} WHERE id = :id AND user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute(['id' => $id, 'user_id' => $userId]);
    }
    
    
    public function deleteRead($userId) {
        $query = "DELETE FROM {$this->table} WHERE user_id = :user_id AND is_read = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->rowCount();
    }
    
    
    public function deleteExpired() {
        
        return 0;
    }
    
    
    public function deleteOld($daysOld = 30) {
        $query = "DELETE FROM {$this->table} 
                  WHERE is_read = 1 AND read_at IS NOT NULL AND read_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['days' => $daysOld]);
        return $stmt->rowCount();
    }
    
    
    public function getStats($userId) {
        $stats = [];
        
        
        $query = "SELECT 
                    SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread,
                    SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) as `read`,
                    COUNT(*) as total
                  FROM {$this->table} 
                  WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['user_id' => $userId]);
        $stats['counts'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        
        $query = "SELECT type, COUNT(*) as count 
                  FROM {$this->table} 
                  WHERE user_id = :user_id
                  GROUP BY type";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['user_id' => $userId]);
        $stats['by_type'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        return $stats;
    }
    
    
    
    
    
    
    public function notifyNewMessage($userId, $senderName, $senderId) {
        return $this->create([
            'user_id' => $userId,
            'type' => self::TYPE_MESSAGE,
            'category' => self::CATEGORY_INFO,
            'title' => 'New Message',
            'message' => "You have a new message from {$senderName}",
            'data' => ['sender_id' => $senderId],
            'action_url' => "/messages/{$senderId}"
        ]);
    }
    
    
    public function notifyNewRating($userId, $serviceId, $serviceTitle, $rating, $reviewerName) {
        return $this->create([
            'user_id' => $userId,
            'type' => self::TYPE_RATING,
            'category' => self::CATEGORY_INFO,
            'title' => 'New Rating Received',
            'message' => "{$reviewerName} rated your service \"{$serviceTitle}\" with {$rating} stars",
            'data' => ['service_id' => $serviceId, 'rating' => $rating],
            'action_url' => "/services/{$serviceId}/ratings"
        ]);
    }
    
    
    public function notifyTransaction($userId, $type, $amount, $transactionId) {
        $titles = [
            'earning' => 'Payment Received',
            'purchase' => 'Payment Sent',
            'refund' => 'Refund Processed',
            'bonus' => 'Bonus Received'
        ];
        
        return $this->create([
            'user_id' => $userId,
            'type' => self::TYPE_TRANSACTION,
            'category' => $type === 'earning' || $type === 'bonus' ? self::CATEGORY_SUCCESS : self::CATEGORY_INFO,
            'title' => $titles[$type] ?? 'Transaction Update',
            'message' => "Transaction of {$amount} coins has been processed",
            'data' => ['transaction_id' => $transactionId, 'amount' => $amount, 'type' => $type],
            'action_url' => "/transactions/{$transactionId}"
        ]);
    }
    
    
    public function notifyReportStatus($userId, $reportId, $status) {
        $messages = [
            'under_review' => 'Your report is now under review',
            'resolved' => 'Your report has been resolved',
            'dismissed' => 'Your report has been reviewed and closed'
        ];
        
        return $this->create([
            'user_id' => $userId,
            'type' => self::TYPE_REPORT,
            'category' => self::CATEGORY_INFO,
            'title' => 'Report Status Update',
            'message' => $messages[$status] ?? "Your report status has been updated to {$status}",
            'data' => ['report_id' => $reportId, 'status' => $status],
            'action_url' => "/reports/{$reportId}"
        ]);
    }
    
    
    public function notifyServiceStatus($userId, $serviceId, $serviceTitle, $status) {
        $categories = [
            'active' => self::CATEGORY_SUCCESS,
            'rejected' => self::CATEGORY_ERROR,
            'suspended' => self::CATEGORY_WARNING
        ];
        
        return $this->create([
            'user_id' => $userId,
            'type' => self::TYPE_SERVICE,
            'category' => $categories[$status] ?? self::CATEGORY_INFO,
            'title' => 'Service Status Update',
            'message' => "Your service \"{$serviceTitle}\" is now {$status}",
            'data' => ['service_id' => $serviceId, 'status' => $status],
            'action_url' => "/services/{$serviceId}"
        ]);
    }
    
    
    public function notifySystem($userId, $title, $message, $data = null) {
        return $this->create([
            'user_id' => $userId,
            'type' => self::TYPE_SYSTEM,
            'category' => self::CATEGORY_INFO,
            'title' => $title,
            'message' => $message,
            'data' => $data
        ]);
    }
}
