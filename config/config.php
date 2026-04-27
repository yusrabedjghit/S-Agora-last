<?php



$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (!getenv($key)) {
                putenv("$key=$value");
            }
        }
    }
}


error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');


define('APP_DEBUG', getenv('APP_DEBUG') === 'true' || getenv('APP_ENV') === 'development');


date_default_timezone_set('UTC');


define('API_VERSION', '1.0.0');
define('API_NAME', 'Swapie Admin API');


define('JWT_SECRET', getenv('JWT_SECRET') ?: 'your-super-secret-key-change-in-production-2024');
define('JWT_EXPIRY', (int)(getenv('JWT_EXPIRY') ?: (3600 * 24)));


define('DEFAULT_PAGE_SIZE', 20);
define('MAX_PAGE_SIZE', 100);


define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); 
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);


define('STATUS_ACTIVE', 'active');
define('STATUS_SUSPENDED', 'suspended');
define('STATUS_PENDING', 'pending');
define('STATUS_DELETED', 'deleted');


define('TRANSACTION_PENDING', 'pending');
define('TRANSACTION_COMPLETED', 'completed');
define('TRANSACTION_CANCELLED', 'cancelled');
define('TRANSACTION_REFUNDED', 'refunded');


define('ITEM_ACTIVE', 'active');
define('ITEM_INACTIVE', 'inactive');
define('ITEM_SUSPENDED', 'suspended');
define('ITEM_PENDING', 'pending');


$allowedOriginsEnv = getenv('ALLOWED_ORIGINS');
if ($allowedOriginsEnv) {
    
    define('ALLOWED_ORIGINS', array_map('trim', explode(',', $allowedOriginsEnv)));
} else {
    
    define('ALLOWED_ORIGINS', [
        'http://localhost:5173',
        'http://localhost:3000',
        'http://127.0.0.1:5173'
    ]);
}


define('EMAIL_FROM', 'no-reply@swapie.local');
