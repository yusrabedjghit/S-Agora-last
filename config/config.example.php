<?php





define('APP_ENV', 'development');  
define('APP_DEBUG', true);         
define('APP_URL', 'http://localhost/swapie');






define('JWT_SECRET', 'your-secret-key-change-this-in-production-make-it-long-and-random');
define('JWT_EXPIRY', 86400);       
define('JWT_ALGORITHM', 'HS256');




define('API_VERSION', '1.0.0');
define('ITEMS_PER_PAGE', 20);
define('MAX_ITEMS_PER_PAGE', 100);




define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024);  
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('UPLOAD_PATH', __DIR__ . '/../uploads/');




define('RATE_LIMIT_REQUESTS', 100);   
define('RATE_LIMIT_WINDOW', 60);      




define('CORS_ALLOWED_ORIGINS', [
    'http://localhost:3000',
    'http://localhost:5173',
    'http://127.0.0.1:3000',
    'http://127.0.0.1:5173',
]);




if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}


date_default_timezone_set('UTC');
