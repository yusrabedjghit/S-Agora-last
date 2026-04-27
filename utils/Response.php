<?php


class Response {
    
    
    public static function success($data = null, $message = "Success", $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c')
        ]);
        exit;
    }
    
    
    public static function error($message = "An error occurred", $statusCode = 400, $errors = []) {
        http_response_code($statusCode);
        echo json_encode([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'timestamp' => date('c')
        ]);
        exit;
    }
    
    
    public static function paginated($data, $page, $perPage, $total) {
        $totalPages = ceil($total / $perPage);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'current_page' => (int)$page,
                'per_page' => (int)$perPage,
                'total_items' => (int)$total,
                'total_pages' => (int)$totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ],
            'timestamp' => date('c')
        ]);
        exit;
    }
    
    
    public static function validationError($errors) {
        self::error("Validation failed", 422, $errors);
    }
    
    
    public static function unauthorized($message = "Unauthorized access") {
        self::error($message, 401);
    }
    
    
    public static function forbidden($message = "Access forbidden") {
        self::error($message, 403);
    }
    
    
    public static function notFound($message = "Resource not found") {
        self::error($message, 404);
    }
    
    
    public static function methodNotAllowed() {
        self::error("Method not allowed", 405);
    }
    
    
    public static function conflict($message = "Resource already exists") {
        self::error($message, 409);
    }
    
    
    public static function tooManyRequests($message = "Too many requests", $retryAfter = 60) {
        header("Retry-After: {$retryAfter}");
        self::error($message, 429);
    }
    
    
    public static function serverError($message = "Internal server error") {
        self::error($message, 500);
    }
    
    
    public static function serviceUnavailable($message = "Service temporarily unavailable") {
        self::error($message, 503);
    }
    
    
    public static function json($data, $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }
}
