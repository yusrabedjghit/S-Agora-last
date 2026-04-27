<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Service.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/JWT.php';

$database = new Database();
$db = $database->getConnection();
$service = new Service($db);

$method = $_SERVER['REQUEST_METHOD'];
$endpoint = isset($_GET['endpoint']) ? $_GET['endpoint'] : '';

try {
    if ($method === 'GET') {
        if ($endpoint === 'stats') {
            $total = $service->getCount([]); 
            $inprogress = $service->getCount(['status' => 'active']); 
            $waiting = $service->getCount(['status' => 'pending']); 
            $suspended = $service->getCount(['status' => 'suspended']);
            $completed = $service->getCount(['status' => 'completed']);
            
            Response::success([
                'total' => $total,
                'inprogress' => $inprogress,
                'waiting' => $waiting,
                'suspended' => $suspended,
                'completed' => $completed
            ]);
        } else {
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
            $filters = [];
            
            if (isset($_GET['status'])) {
                if ($_GET['status'] === 'Inprogress') $filters['status'] = 'active';
                else if ($_GET['status'] === 'waiting') $filters['status'] = 'pending';
                else $filters['status'] = $_GET['status'];
            }
            if (isset($_GET['search'])) $filters['search'] = $_GET['search'];
            
            $result = $service->getAll($page, $perPage, $filters);
            
            
            foreach ($result['services'] as &$s) {
                if ($s['status'] === 'active') $s['status_label'] = 'Inprogress';
                elseif ($s['status'] === 'pending') $s['status_label'] = 'waiting';
                else $s['status_label'] = $s['status'];
                
                
                
                if ($s['images'] && is_string($s['images']) && $s['images'][0] === '[') {
                    $imgs = json_decode($s['images'], true);
                    $s['image'] = $imgs[0] ?? null; 
                } else {
                    $s['image'] = $s['images'];
                }
            }
            
            Response::success($result);
        }
    } elseif ($method === 'POST') {
        if ($endpoint === 'suspend') {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['service_id'])) {
                Response::error('Service ID required', 400);
            }
            
            $serviceId = $data['service_id'];
            $serviceDetails = $service->getById($serviceId);
            
            if (!$serviceDetails) {
                Response::error('Service not found', 404);
            }
            
            
            $currentStatus = $serviceDetails['status'];
            $newStatus = ($currentStatus === 'suspended') ? 'pending' : 'suspended'; 
            
            
            
            if ($currentStatus === 'suspended') {
                $newStatus = 'active'; 
            } else {
                $newStatus = 'suspended';
            }
            
            $success = $service->updateStatus($serviceId, $newStatus);
            
            if ($success) {
                Response::success([
                    'message' => 'Service status updated', 
                    'new_status' => $newStatus,
                    'new_status_label' => ($newStatus === 'active' ? 'Inprogress' : $newStatus)
                ]);
            } else {
                Response::error('Failed to update service status', 500);
            }
        }
    } else {
        Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    Response::error('Server error: ' . $e->getMessage(), 500);
}
