<?php

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/config/config.php';


header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Max-Age: 86400");


header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/utils/Response.php';

$requestUri = $_SERVER['REQUEST_URI'];

$path = parse_url($requestUri, PHP_URL_PATH);


$basePaths = ['/swapie/api', '/swapie', '/backend/backend/api', '/backend/backend', '/backend/api', '/backend', '/api', ''];
foreach ($basePaths as $basePath) {
    if ($basePath && strpos($path, $basePath) === 0) {
        $path = substr($path, strlen($basePath));
        break;
    }
}

$path = trim($path, '/');
$segments = explode('/', $path);


if (isset($segments[0]) && $segments[0] === 'api') {
    array_shift($segments);
}


$endpoint = $segments[0] ?? '';

switch ($endpoint) {
    case 'auth':
        require_once __DIR__ . '/api/auth.php';
        break;
    case 'users':
        require_once __DIR__ . '/api/users.php';
        break;
    case 'services':
        require_once __DIR__ . '/api/services.php';
        break;
    case 'demands':
        require_once __DIR__ . '/api/demands.php';
        break;
    case 'categories':
        require_once __DIR__ . '/api/categories.php';
        break;
    case 'transactions':
        require_once __DIR__ . '/api/transactions.php';
        break;
    case 'admins':
        require_once __DIR__ . '/api/admins.php';
        break;
    case 'messages':
        require_once __DIR__ . '/api/messages.php';
        break;
    case 'profile':
        require_once __DIR__ . '/api/profile.php';
        break;
    case 'ratings':
        require_once __DIR__ . '/api/ratings.php';
        break;
    case 'reports':
        require_once __DIR__ . '/api/reports.php';
        break;
    case 'report-types':
        require_once __DIR__ . '/api/report-types.php';
        break;
    case 'notifications':
        require_once __DIR__ . '/api/notifications.php';
        break;
    case 'stats':
        require_once __DIR__ . '/api/stats.php';
        break;
    case 'amanda':
        
        $amandaEndpoint = $segments[1] ?? '';
        switch ($amandaEndpoint) {
            case 'wallet':
                require_once __DIR__ . '/api/amanda/wallet.php';
                break;
            case 'buy_coins':
            case 'buy-coins':
                require_once __DIR__ . '/api/amanda/buy_coins.php';
                break;
            case 'profile':
                require_once __DIR__ . '/api/amanda/profile.php';
                break;
            case 'dashboard':
                require_once __DIR__ . '/api/amanda/dashboard.php';
                break;
            case 'forgot_password':
            case 'forgot-password':
                require_once __DIR__ . '/api/amanda/forgot_password.php';
                break;
            case 'reset_password':
            case 'reset-password':
                require_once __DIR__ . '/api/amanda/reset_password.php';
                break;
            default:
                Response::notFound("Amanda endpoint not found");
        }
        break;
    case 'dashboard':
        require_once __DIR__ . '/api/dashboard.php';
        break;
    case 'admin':
        
        $adminEndpoint = $segments[1] ?? '';
        switch ($adminEndpoint) {
            case 'users':
                require_once __DIR__ . '/api/admin/users';
                break;
            case 'reports':
                require_once __DIR__ . '/api/admin/reports';
                break;
            case 'transactions':
                require_once __DIR__ . '/api/admin/transactions';
                break;
            case 'categories':
                require_once __DIR__ . '/api/admin/categories';
                break;
            case 'services':
                require_once __DIR__ . '/api/admin/services';
                break;
            case 'dashboard':
                require_once __DIR__ . '/api/admin/dashboard';
                break;
            default:
                Response::notFound("Admin endpoint not found");
        }
        break;
    case '':
        Response::success([
            'name' => API_NAME,
            'version' => API_VERSION,
            'status' => 'running',
            'endpoints' => [
                'auth' => '/api/auth',
                'users' => '/api/users',
                'services' => '/api/services',
                'demands' => '/api/demands',
                'categories' => '/api/categories',
                'transactions' => '/api/transactions',
                'dashboard' => '/api/dashboard',
                'admins' => '/api/admins',
                'messages' => '/api/messages',
                'profile' => '/api/profile',
                'amanda' => '/api/amanda'
            ]
        ], 'Swapie Admin API is running');
        break;
    default:
        Response::notFound("Endpoint not found");
}
