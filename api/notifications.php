<?php


header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/ErrorHandler.php';
require_once __DIR__ . '/../models/Notification.php';


ErrorHandler::init();

try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed',
        'error' => APP_DEBUG ? $e->getMessage() : 'Internal server error'
    ]);
    exit;
}

try {
    $notification = new Notification($db);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Notification model initialization failed',
        'error' => APP_DEBUG ? $e->getMessage() : 'Internal server error'
    ]);
    exit;
}
$jwt = new JWT();

$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];


$basePath = '/api/notifications';
$path = parse_url($uri, PHP_URL_PATH);
$path = str_replace($basePath, '', $path);
$path = trim($path, '/');
$pathParts = $path ? explode('/', $path) : [];


$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = '';

if (!empty($authHeader)) {
    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $token = $matches[1];
    }
}


if (empty($token) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    Response::unauthorized('Authentication token required. Please provide a valid Bearer token in the Authorization header.');
}


if (empty($token)) {
    Response::unauthorized('Authentication required');
}

try {
    $payload = $jwt->verify($token);
    if (!$payload) {
        Response::unauthorized('Invalid or expired token');
    }
} catch (Exception $e) {
    Response::unauthorized('Token verification failed: ' . $e->getMessage());
}


$userId = $payload['user_id'] ?? $payload['admin_id'] ?? null;
if (!$userId) {
    Response::unauthorized('Invalid token payload');
}

try {
    switch ($method) {
        case 'GET':
            if (empty($pathParts)) {
                
                getNotifications($notification, $userId);
            } elseif ($pathParts[0] === 'unread') {
                
                getUnreadNotifications($notification, $userId);
            } elseif ($pathParts[0] === 'count') {
                
                getUnreadCount($notification, $userId);
            } elseif ($pathParts[0] === 'stats') {
                
                getStats($notification, $userId);
            } elseif (is_numeric($pathParts[0])) {
                
                getNotification($notification, (int)$pathParts[0], $userId);
            } else {
                Response::notFound('Endpoint not found');
            }
            break;
            
        case 'PUT':
            if ($pathParts[0] === 'read-all') {
                
                markAllAsRead($notification, $userId);
            } elseif ($pathParts[0] === 'read-multiple') {
                
                markMultipleAsRead($notification, $userId);
            } elseif ($pathParts[0] === 'read-type') {
                
                markTypeAsRead($notification, $userId);
            } elseif (isset($pathParts[1]) && $pathParts[1] === 'read') {
                
                markAsRead($notification, (int)$pathParts[0], $userId);
            } else {
                Response::notFound('Endpoint not found');
            }
            break;
            
        case 'DELETE':
            if ($pathParts[0] === 'read') {
                
                deleteReadNotifications($notification, $userId);
            } elseif (is_numeric($pathParts[0])) {
                
                deleteNotification($notification, (int)$pathParts[0], $userId);
            } else {
                Response::notFound('Endpoint not found');
            }
            break;
            
        default:
            Response::methodNotAllowed();
    }
} catch (Exception $e) {
    error_log('Notifications API Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred',
        'error' => APP_DEBUG ? $e->getMessage() : 'Internal server error'
    ]);
    exit;
}






function getNotifications($notification, $userId) {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = isset($_GET['per_page']) ? min((int)$_GET['per_page'], MAX_PAGE_SIZE) : DEFAULT_PAGE_SIZE;
    
    $filters = [];
    
    if (isset($_GET['is_read'])) {
        $filters['is_read'] = filter_var($_GET['is_read'], FILTER_VALIDATE_BOOLEAN);
    }
    
    if (!empty($_GET['type'])) {
        $filters['type'] = $_GET['type'];
    }
    
    if (!empty($_GET['category'])) {
        $filters['category'] = $_GET['category'];
    }
    
    $result = $notification->getByUser($userId, $page, $perPage, $filters);
    
    
    $unreadCount = $notification->getUnreadCount($userId);
    
    Response::success([
        'notifications' => $result['notifications'],
        'unread_count' => $unreadCount,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $perPage,
            'total_items' => $result['total'],
            'total_pages' => ceil($result['total'] / $perPage),
            'has_next' => $page < ceil($result['total'] / $perPage),
            'has_prev' => $page > 1
        ]
    ]);
}


function getUnreadNotifications($notification, $userId) {
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 50) : 10;
    
    $notifications = $notification->getUnread($userId, $limit);
    $unreadCount = $notification->getUnreadCount($userId);
    
    Response::success([
        'notifications' => $notifications,
        'total_unread' => $unreadCount
    ]);
}


function getUnreadCount($notification, $userId) {
    $count = $notification->getUnreadCount($userId);
    
    Response::success([
        'unread_count' => $count
    ]);
}


function getStats($notification, $userId) {
    $stats = $notification->getStats($userId);
    Response::success($stats);
}


function getNotification($notification, $id, $userId) {
    $notificationData = $notification->getById($id);
    
    if (!$notificationData) {
        Response::notFound('Notification not found');
    }
    
    
    if ($notificationData['user_id'] != $userId) {
        Response::forbidden('You can only view your own notifications');
    }
    
    Response::success($notificationData);
}


function markAsRead($notification, $id, $userId) {
    $notificationData = $notification->getById($id);
    
    if (!$notificationData) {
        Response::notFound('Notification not found');
    }
    
    if ($notificationData['user_id'] != $userId) {
        Response::forbidden('You can only mark your own notifications as read');
    }
    
    if (!$notification->markAsRead($id, $userId)) {
        Response::error('Failed to mark notification as read', 500);
    }
    
    Response::success([
        'notification_id' => $id,
        'is_read' => true
    ], 'Notification marked as read');
}


function markMultipleAsRead($notification, $userId) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['ids']) || !is_array($data['ids'])) {
        Response::error('Notification IDs array is required', 400);
    }
    
    
    $ids = array_filter(array_map('intval', $data['ids']));
    
    if (empty($ids)) {
        Response::error('No valid notification IDs provided', 400);
    }
    
    $updatedCount = $notification->markMultipleAsRead($ids, $userId);
    
    Response::success([
        'updated_count' => $updatedCount,
        'requested_count' => count($ids)
    ], "{$updatedCount} notifications marked as read");
}


function markAllAsRead($notification, $userId) {
    $updatedCount = $notification->markAllAsRead($userId);
    
    Response::success([
        'updated_count' => $updatedCount
    ], 'All notifications marked as read');
}


function markTypeAsRead($notification, $userId) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $validator = new Validator($data ?? []);
    $validator
        ->required('type', 'Notification type is required')
        ->inArray('type', ['message', 'transaction', 'service', 'demand', 'report', 'rating', 'system', 'promotion'], 'Invalid notification type');
    
    if ($validator->fails()) {
        Response::validationError($validator->getErrors());
    }
    
    $updatedCount = $notification->markTypeAsRead($userId, $data['type']);
    
    Response::success([
        'type' => $data['type'],
        'updated_count' => $updatedCount
    ], "All {$data['type']} notifications marked as read");
}


function deleteNotification($notification, $id, $userId) {
    $notificationData = $notification->getById($id);
    
    if (!$notificationData) {
        Response::notFound('Notification not found');
    }
    
    if ($notificationData['user_id'] != $userId) {
        Response::forbidden('You can only delete your own notifications');
    }
    
    if (!$notification->delete($id, $userId)) {
        Response::error('Failed to delete notification', 500);
    }
    
    Response::success(null, 'Notification deleted successfully');
}


function deleteReadNotifications($notification, $userId) {
    $deletedCount = $notification->deleteRead($userId);
    
    Response::success([
        'deleted_count' => $deletedCount
    ], "{$deletedCount} read notifications deleted");
}
