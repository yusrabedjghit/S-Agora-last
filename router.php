<?php

header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));


if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    $ext = pathinfo($uri, PATHINFO_EXTENSION);
    
    
    if ($ext === 'php') {
        require __DIR__ . $uri;
        return true;
    }
    
    
    return false;
}


require __DIR__ . '/index.php';
