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
require_once __DIR__ . '/../models/ReportType.php';


ErrorHandler::init();

$database = new Database();
$db = $database->getConnection();
$reportType = new ReportType($db);
$jwt = new JWT();

$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];


$basePath = '/api/report-types';
$path = parse_url($uri, PHP_URL_PATH);
$path = str_replace($basePath, '', $path);
$path = trim($path, '/');
$pathParts = $path ? explode('/', $path) : [];


$isAdmin = false;
$adminId = null;

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = '';

if (!empty($authHeader)) {
    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $token = $matches[1];
    }
}

if (!empty($token)) {
    $payload = $jwt->verify($token);
    if ($payload && isset($payload['admin_id'])) {
        $isAdmin = true;
        $adminId = $payload['admin_id'];
    }
}

try {
    switch ($method) {
        case 'GET':
            if (empty($pathParts)) {
                
                getReportTypes($reportType);
            } elseif ($pathParts[0] === 'stats') {
                
                if (!$isAdmin) {
                    Response::forbidden('Admin access required');
                }
                getReportTypeStats($reportType);
            } elseif (is_numeric($pathParts[0])) {
                
                getReportType($reportType, (int)$pathParts[0]);
            } else {
                Response::notFound('Endpoint not found');
            }
            break;
            
        case 'POST':
            if (!$isAdmin) {
                Response::forbidden('Admin access required');
            }
            
            createReportType($reportType);
            break;
            
        case 'PUT':
            if (!$isAdmin) {
                Response::forbidden('Admin access required');
            }
            
            if ($pathParts[0] === 'reorder') {
                
                reorderReportTypes($reportType);
            } elseif (isset($pathParts[1]) && $pathParts[1] === 'toggle') {
                
                toggleReportType($reportType, (int)$pathParts[0]);
            } elseif (is_numeric($pathParts[0])) {
                
                updateReportType($reportType, (int)$pathParts[0]);
            } else {
                Response::notFound('Endpoint not found');
            }
            break;
            
        case 'DELETE':
            if (!$isAdmin) {
                Response::forbidden('Admin access required');
            }
            
            if (isset($pathParts[0]) && is_numeric($pathParts[0])) {
                deleteReportType($reportType, (int)$pathParts[0]);
            } else {
                Response::notFound('Endpoint not found');
            }
            break;
            
        default:
            Response::methodNotAllowed();
    }
} catch (PDOException $e) {
    error_log("Report Types API - PDO Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    Response::error('Database error occurred', 500);
} catch (Exception $e) {
    error_log("Report Types API - Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    ErrorHandler::handle($e);
}






function getReportTypes($reportType) {
    try {
        
        $activeOnly = true;
        if (isset($_GET['active_only'])) {
            $activeOnly = filter_var($_GET['active_only'], FILTER_VALIDATE_BOOLEAN);
        }
        
        $entityType = isset($_GET['entity_type']) ? trim($_GET['entity_type']) : null;
        
        
        if ($entityType && !in_array($entityType, ['user', 'service', 'demand', 'all'])) {
            Response::error('Invalid entity type: ' . $entityType, 400);
            return;
        }
        
        
        $types = $reportType->getAll($activeOnly, $entityType);
        
        
        Response::success($types, 'Report types retrieved successfully');
        
    } catch (PDOException $e) {
        error_log("getReportTypes - Database Error: " . $e->getMessage());
        Response::error('Database error occurred', 500);
        return;
    } catch (Exception $e) {
        error_log("getReportTypes - Unexpected Error: " . $e->getMessage());
        Response::error('Error retrieving report types', 500);
        return;
    }
}


function getReportType($reportType, $id) {
    $type = $reportType->getById($id);
    
    if (!$type) {
        Response::notFound('Report type not found');
    }
    
    Response::success($type);
}


function createReportType($reportType) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $validator = new Validator($data ?? []);
    $validator
        ->required('name', 'Name is required')
        ->minLength('name', 2, 'Name must be at least 2 characters')
        ->maxLength('name', 100, 'Name cannot exceed 100 characters');
    
    if (isset($data['entity_type'])) {
        $validator->inArray('entity_type', ['user', 'service', 'demand', 'all'], 'Invalid entity type');
    }
    
    if ($validator->fails()) {
        Response::validationError($validator->getErrors());
    }
    
    
    if (isset($data['slug']) && $reportType->slugExists($data['slug'])) {
        Response::error('Slug already exists', 409);
    }
    
    $typeId = $reportType->create($data);
    
    if (!$typeId) {
        Response::error('Failed to create report type', 500);
    }
    
    $newType = $reportType->getById($typeId);
    Response::success($newType, 'Report type created successfully', 201);
}


function updateReportType($reportType, $id) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $existingType = $reportType->getById($id);
    if (!$existingType) {
        Response::notFound('Report type not found');
    }
    
    $validator = new Validator($data ?? []);
    
    if (isset($data['name'])) {
        $validator
            ->minLength('name', 2, 'Name must be at least 2 characters')
            ->maxLength('name', 100, 'Name cannot exceed 100 characters');
    }
    
    if (isset($data['entity_type'])) {
        $validator->inArray('entity_type', ['user', 'service', 'demand', 'all'], 'Invalid entity type');
    }
    
    if ($validator->fails()) {
        Response::validationError($validator->getErrors());
    }
    
    
    if (isset($data['slug']) && $reportType->slugExists($data['slug'], $id)) {
        Response::error('Slug already exists', 409);
    }
    
    if (!$reportType->update($id, $data)) {
        Response::error('Failed to update report type', 500);
    }
    
    $updatedType = $reportType->getById($id);
    Response::success($updatedType, 'Report type updated successfully');
}


function deleteReportType($reportType, $id) {
    $existingType = $reportType->getById($id);
    if (!$existingType) {
        Response::notFound('Report type not found');
    }
    
    if (!$reportType->delete($id)) {
        Response::error('Failed to delete report type', 500);
    }
    
    Response::success(null, 'Report type deleted successfully');
}


function toggleReportType($reportType, $id) {
    $existingType = $reportType->getById($id);
    if (!$existingType) {
        Response::notFound('Report type not found');
    }
    
    if (!$reportType->toggleActive($id)) {
        Response::error('Failed to toggle report type status', 500);
    }
    
    $updatedType = $reportType->getById($id);
    Response::success($updatedType, 'Report type status toggled successfully');
}


function reorderReportTypes($reportType) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['order']) || !is_array($data['order'])) {
        Response::error('Order array is required', 400);
    }
    
    if (!$reportType->reorder($data['order'])) {
        Response::error('Failed to reorder report types', 500);
    }
    
    Response::success(null, 'Report types reordered successfully');
}


function getReportTypeStats($reportType) {
    $stats = $reportType->getReportCounts();
    Response::success($stats);
}
