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
require_once __DIR__ . '/../models/Report.php';
require_once __DIR__ . '/../models/ReportType.php';
require_once __DIR__ . '/../models/Notification.php';


ErrorHandler::init();

$database = new Database();
$db = $database->getConnection();
$report = new Report($db);
$reportType = new ReportType($db);
$notification = new Notification($db);
$jwt = new JWT();

$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];


$basePath = '/api/reports';
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


$currentUser = null;
$isAdmin = false;

if (!empty($token)) {
    $payload = $jwt->verify($token);
    if ($payload) {
        if (isset($payload['admin_id'])) {
            $isAdmin = true;
            $currentUser = ['id' => $payload['admin_id'], 'type' => 'admin'];
        } elseif (isset($payload['user_id'])) {
            $currentUser = ['id' => $payload['user_id'], 'type' => 'user'];
        }
    }
}

try {
    
    switch ($method) {
        case 'GET':
            if (empty($pathParts)) {
                
                if (!$isAdmin) {
                    Response::forbidden('Admin access required');
                }
                getReports($report);
            } elseif ($pathParts[0] === 'my') {
                
                if (!$currentUser) {
                    Response::unauthorized('Authentication required');
                }
                getMyReports($report, $currentUser['id']);
            } elseif ($pathParts[0] === 'statistics') {
                
                if (!$isAdmin) {
                    Response::forbidden('Admin access required');
                }
                getStatistics($report);
            } elseif ($pathParts[0] === 'types') {
                
                getReportTypes($reportType);
            } elseif (is_numeric($pathParts[0])) {
                
                getReport($report, (int)$pathParts[0], $currentUser, $isAdmin);
            } else {
                Response::notFound('Endpoint not found');
            }
            break;
            
        case 'POST':
            if (empty($pathParts)) {
                
                if (!$currentUser) {
                    Response::unauthorized('Authentication required');
                }
                createReport($report, $reportType, $notification, $currentUser['id'], $db);
            } elseif (isset($pathParts[1]) && $pathParts[1] === 'note') {
                
                if (!$isAdmin) {
                    Response::forbidden('Admin access required');
                }
                addInternalNote($report, (int)$pathParts[0], $currentUser['id']);
            } else {
                Response::notFound('Endpoint not found');
            }
            break;
            
        case 'PUT':
            if (!$isAdmin) {
                Response::forbidden('Admin access required');
            }
            
            if (isset($pathParts[1])) {
                $reportId = (int)$pathParts[0];
                
                if ($pathParts[1] === 'status') {
                    
                    updateStatus($report, $notification, $reportId, $currentUser['id']);
                } elseif ($pathParts[1] === 'resolve') {
                    
                    resolveReport($report, $notification, $reportId, $currentUser['id'], $db);
                } else {
                    Response::notFound('Endpoint not found');
                }
            } else {
                Response::notFound('Endpoint not found');
            }
            break;
            
        case 'DELETE':
            if (!$isAdmin) {
                Response::forbidden('Admin access required');
            }
            
            if (isset($pathParts[0]) && is_numeric($pathParts[0])) {
                deleteReport($report, (int)$pathParts[0]);
            } else {
                Response::notFound('Endpoint not found');
            }
            break;
            
        default:
            Response::methodNotAllowed();
    }
} catch (Exception $e) {
    ErrorHandler::handle($e);
}






function createReport($report, $reportType, $notification, $userId, $db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $validator = new Validator($data ?? []);
    $validator
        ->required('report_type_id', 'Report type is required')
        ->numeric('report_type_id', 'Invalid report type')
        ->required('reason', 'Reason is required')
        ->minLength('reason', 10, 'Reason must be at least 10 characters')
        ->maxLength('reason', 200, 'Reason cannot exceed 200 characters');
    
    
    $hasEntity = !empty($data['reported_user_id']) || 
                 !empty($data['reported_service_id']) || 
                 !empty($data['reported_demand_id']);
    
    if (!$hasEntity) {
        Response::error('You must specify a user, service, or demand to report', 400);
    }
    
    if ($validator->fails()) {
        Response::validationError($validator->getErrors());
    }
    
    
    $type = $reportType->getById($data['report_type_id']);
    if (!$type || !$type['is_active']) {
        Response::error('Invalid report type', 400);
    }
    
    
    $entityType = null;
    $entityId = null;
    
    if (!empty($data['reported_user_id'])) {
        $entityType = 'user';
        $entityId = $data['reported_user_id'];
        
        
        if ($entityId == $userId) {
            Response::error('You cannot report yourself', 400);
        }
        
        
        $checkQuery = "SELECT id FROM users WHERE id = :id AND status != 'deleted'";
        $stmt = $db->prepare($checkQuery);
        $stmt->execute(['id' => $entityId]);
        if (!$stmt->fetch()) {
            Response::error('User not found', 404);
        }
    }
    
    if (!empty($data['reported_service_id'])) {
        $entityType = 'service';
        $entityId = $data['reported_service_id'];
        
        
        $checkQuery = "SELECT id, user_id FROM services WHERE id = :id AND status != 'deleted'";
        $stmt = $db->prepare($checkQuery);
        $stmt->execute(['id' => $entityId]);
        $service = $stmt->fetch();
        if (!$service) {
            Response::error('Service not found', 404);
        }
        
        if ($service['user_id'] == $userId) {
            Response::error('You cannot report your own service', 400);
        }
    }
    
    if (!empty($data['reported_demand_id'])) {
        $entityType = 'demand';
        $entityId = $data['reported_demand_id'];
        
        
        $checkQuery = "SELECT id, user_id FROM demands WHERE id = :id AND status != 'deleted'";
        $stmt = $db->prepare($checkQuery);
        $stmt->execute(['id' => $entityId]);
        $demand = $stmt->fetch();
        if (!$demand) {
            Response::error('Demand not found', 404);
        }
        
        if ($demand['user_id'] == $userId) {
            Response::error('You cannot report your own demand', 400);
        }
    }
    
    
    if ($report->hasAlreadyReported($userId, $entityType, $entityId)) {
        Response::error('You have already submitted a report for this item that is still being reviewed', 400);
    }
    
    
    
    
    
    
    
    $reportData = [
        'reporter_id' => $userId,
        'report_type_id' => $data['report_type_id'],
        'reported_user_id' => $data['reported_user_id'] ?? null,
        'reported_service_id' => $data['reported_service_id'] ?? null,
        'reported_demand_id' => $data['reported_demand_id'] ?? null,
        'reason' => $data['reason'],
        'description' => $data['description'] ?? null,
        'evidence' => $data['evidence'] ?? null,
        'priority' => $data['priority'] ?? 'medium'
    ];
    
    $reportId = $report->create($reportData);
    
    if (!$reportId) {
        Response::error('Failed to create report', 500);
    }
    
    $newReport = $report->getById($reportId);
    Response::success($newReport, 'Report submitted successfully', 201);
}


function getReports($report) {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = isset($_GET['per_page']) ? min((int)$_GET['per_page'], MAX_PAGE_SIZE) : DEFAULT_PAGE_SIZE;
    
    $filters = [
        'status' => $_GET['status'] ?? null,
        'priority' => $_GET['priority'] ?? null,
        'report_type_id' => $_GET['report_type_id'] ?? null,
        'entity_type' => $_GET['entity_type'] ?? null,
        'search' => $_GET['search'] ?? null,
        'date_from' => $_GET['date_from'] ?? null,
        'date_to' => $_GET['date_to'] ?? null
    ];
    
    $result = $report->getAll($page, $perPage, array_filter($filters));
    
    Response::paginated($result['reports'], $page, $perPage, $result['total']);
}


function getMyReports($report, $userId) {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = isset($_GET['per_page']) ? min((int)$_GET['per_page'], MAX_PAGE_SIZE) : DEFAULT_PAGE_SIZE;
    
    $result = $report->getByReporter($userId, $page, $perPage);
    
    Response::paginated($result['reports'], $page, $perPage, $result['total']);
}


function getReport($report, $id, $currentUser, $isAdmin) {
    $reportData = $report->getById($id);
    
    if (!$reportData) {
        Response::notFound('Report not found');
    }
    
    
    if (!$isAdmin && $reportData['reporter_id'] != $currentUser['id']) {
        Response::forbidden('You can only view your own reports');
    }
    
    
    if (!$isAdmin) {
        unset($reportData['internal_notes']);
        unset($reportData['admin_note']);
    }
    
    Response::success($reportData);
}


function updateStatus($report, $notification, $id, $adminId) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $validator = new Validator($data ?? []);
    $validator
        ->required('status', 'Status is required')
        ->inArray('status', ['pending', 'under_review', 'resolved', 'dismissed', 'escalated'], 'Invalid status');
    
    if ($validator->fails()) {
        Response::validationError($validator->getErrors());
    }
    
    $existingReport = $report->getById($id);
    if (!$existingReport) {
        Response::notFound('Report not found');
    }
    
    $adminNote = $data['admin_note'] ?? null;
    
    if (!$report->updateStatus($id, $data['status'], $adminId, $adminNote)) {
        Response::error('Failed to update report status', 500);
    }
    
    
    $notification->notifyReportStatus($existingReport['reporter_id'], $id, $data['status']);
    
    $updatedReport = $report->getById($id);
    Response::success($updatedReport, 'Report status updated successfully');
}


function resolveReport($report, $notification, $id, $adminId, $db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $validator = new Validator($data ?? []);
    $validator
        ->required('resolution_type', 'Resolution type is required')
        ->inArray('resolution_type', ['warning_issued', 'content_removed', 'user_suspended', 'user_banned', 'no_action', 'duplicate'], 'Invalid resolution type');
    
    if ($validator->fails()) {
        Response::validationError($validator->getErrors());
    }
    
    $existingReport = $report->getById($id);
    if (!$existingReport) {
        Response::notFound('Report not found');
    }
    
    
    $resolutionType = $data['resolution_type'];
    
    
    switch ($resolutionType) {
        case 'user_suspended':
            if ($existingReport['reported_user_id']) {
                $stmt = $db->prepare("UPDATE users SET status = 'suspended' WHERE id = :id");
                $stmt->execute(['id' => $existingReport['reported_user_id']]);
            }
            break;
            
        case 'user_banned':
            if ($existingReport['reported_user_id']) {
                $stmt = $db->prepare("UPDATE users SET status = 'banned' WHERE id = :id");
                $stmt->execute(['id' => $existingReport['reported_user_id']]);
            }
            break;
            
        case 'content_removed':
            if ($existingReport['reported_service_id']) {
                $stmt = $db->prepare("UPDATE services SET status = 'deleted' WHERE id = :id");
                $stmt->execute(['id' => $existingReport['reported_service_id']]);
            }
            if ($existingReport['reported_demand_id']) {
                $stmt = $db->prepare("UPDATE demands SET status = 'deleted' WHERE id = :id");
                $stmt->execute(['id' => $existingReport['reported_demand_id']]);
            }
            break;
    }
    
    
    $resolveData = [
        'resolution_type' => $resolutionType,
        'admin_note' => $data['admin_note'] ?? null,
        'admin_id' => $adminId
    ];
    
    if (!$report->resolve($id, $resolveData)) {
        Response::error('Failed to resolve report', 500);
    }
    
    
    $notification->notifyReportStatus($existingReport['reporter_id'], $id, 'resolved');
    
    $updatedReport = $report->getById($id);
    Response::success($updatedReport, 'Report resolved successfully');
}


function addInternalNote($report, $id, $adminId) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $validator = new Validator($data ?? []);
    $validator
        ->required('note', 'Note is required')
        ->minLength('note', 5, 'Note must be at least 5 characters');
    
    if ($validator->fails()) {
        Response::validationError($validator->getErrors());
    }
    
    if (!$report->addInternalNote($id, $data['note'], $adminId)) {
        Response::error('Failed to add note', 500);
    }
    
    Response::success(null, 'Note added successfully');
}


function getStatistics($report) {
    $stats = $report->getStatistics();
    Response::success($stats);
}


function getReportTypes($reportType) {
    $entityType = $_GET['entity_type'] ?? null;
    $types = $reportType->getAll(true, $entityType);
    Response::success($types);
}


function deleteReport($report, $id) {
    if (!$report->getById($id)) {
        Response::notFound('Report not found');
    }
    
    if (!$report->delete($id)) {
        Response::error('Failed to delete report', 500);
    }
    
    Response::success(null, 'Report deleted successfully');
}
