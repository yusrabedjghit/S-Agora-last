<?php


class ErrorHandler {
    private static $db = null;
    private static $initialized = false;
    
    
    const SEVERITY_DEBUG = 'debug';
    const SEVERITY_INFO = 'info';
    const SEVERITY_NOTICE = 'notice';
    const SEVERITY_WARNING = 'warning';
    const SEVERITY_ERROR = 'error';
    const SEVERITY_CRITICAL = 'critical';
    const SEVERITY_ALERT = 'alert';
    const SEVERITY_EMERGENCY = 'emergency';
    
    
    public static function init($db = null) {
        if (self::$initialized) {
            return;
        }
        
        self::$db = $db;
        
        
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
        
        self::$initialized = true;
    }
    
    
    public static function setDatabase($db) {
        self::$db = $db;
    }
    
    
    public static function handleError($errno, $errstr, $errfile, $errline) {
        
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        $severity = self::mapErrorSeverity($errno);
        $errorType = self::getErrorTypeName($errno);
        
        
        self::logError([
            'error_type' => $errorType,
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline,
            'severity' => $severity
        ]);
        
        
        if (in_array($errno, [E_ERROR, E_USER_ERROR, E_CORE_ERROR, E_COMPILE_ERROR])) {
            self::sendErrorResponse(
                'An internal error occurred',
                500,
                self::isDebugMode() ? [
                    'type' => $errorType,
                    'message' => $errstr,
                    'file' => $errfile,
                    'line' => $errline
                ] : null
            );
        }
        
        
        return true;
    }
    
    
    public static function handleException($exception) {
        $severity = self::SEVERITY_ERROR;
        
        
        if ($exception instanceof PDOException) {
            $severity = self::SEVERITY_CRITICAL;
        }
        
        
        self::logError([
            'error_type' => get_class($exception),
            'error_code' => $exception->getCode(),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'severity' => $severity
        ]);
        
        
        $statusCode = self::getHttpStatusFromException($exception);
        $message = self::getPublicMessage($exception);
        
        self::sendErrorResponse(
            $message,
            $statusCode,
            self::isDebugMode() ? [
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine()
            ] : null
        );
    }
    
    
    public static function handleShutdown() {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            self::logError([
                'error_type' => self::getErrorTypeName($error['type']),
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'severity' => self::SEVERITY_CRITICAL
            ]);
            
            
            if (!headers_sent()) {
                self::sendErrorResponse('A fatal error occurred', 500);
            }
        }
    }
    
    
    public static function handle($e, $exitAfter = true) {
        self::handleException($e);
        if ($exitAfter) {
            exit;
        }
    }
    
    
    private static function logError($data) {
        
        self::logToFile($data);
        
        
        if (self::$db) {
            self::logToDatabase($data);
        }
    }
    
    
    private static function logToFile($data) {
        $logDir = __DIR__ . '/../logs';
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/error_' . date('Y-m-d') . '.log';
        
        $logEntry = sprintf(
            "[%s] [%s] [%s] %s in %s:%d\n%s\n%s\n",
            date('Y-m-d H:i:s'),
            strtoupper($data['severity'] ?? 'error'),
            $data['error_type'] ?? 'Unknown',
            $data['message'] ?? 'No message',
            $data['file'] ?? 'Unknown',
            $data['line'] ?? 0,
            isset($data['trace']) ? "Stack trace:\n" . $data['trace'] : '',
            str_repeat('-', 80)
        );
        
        error_log($logEntry, 3, $logFile);
    }
    
    
    private static function logToDatabase($data) {
        try {
            
            $tableCheck = self::$db->query("SHOW TABLES LIKE 'error_logs'");
            if ($tableCheck->rowCount() === 0) {
                return; 
            }
            
            $query = "INSERT INTO error_logs 
                      (error_code, error_type, error_message, file, line, trace, 
                       request_uri, request_method, request_data, 
                       ip_address, user_agent, severity, created_at)
                      VALUES 
                      (:error_code, :error_type, :error_message, :file, :line, :trace,
                       :request_uri, :request_method, :request_data,
                       :ip_address, :user_agent, :severity, NOW())";
            
            $stmt = self::$db->prepare($query);
            $stmt->execute([
                'error_code' => $data['error_code'] ?? null,
                'error_type' => $data['error_type'] ?? 'Unknown',
                'error_message' => $data['message'] ?? 'No message',
                'file' => $data['file'] ?? null,
                'line' => $data['line'] ?? null,
                'trace' => $data['trace'] ?? null,
                'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? null,
                'request_data' => json_encode(self::getRequestData()),
                'ip_address' => self::getClientIp(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'severity' => $data['severity'] ?? self::SEVERITY_ERROR
            ]);
        } catch (Exception $e) {
            
            error_log("Failed to log error to database: " . $e->getMessage());
        }
    }
    
    
    private static function sendErrorResponse($message, $statusCode = 500, $debug = null) {
        
        if (ob_get_length()) {
            ob_clean();
        }
        
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        $response = [
            'success' => false,
            'message' => $message,
            'error_code' => $statusCode,
            'timestamp' => date('c')
        ];
        
        if ($debug && self::isDebugMode()) {
            $response['debug'] = $debug;
        }
        
        echo json_encode($response);
        exit;
    }
    
    
    private static function mapErrorSeverity($errno) {
        $map = [
            E_ERROR => self::SEVERITY_CRITICAL,
            E_WARNING => self::SEVERITY_WARNING,
            E_PARSE => self::SEVERITY_CRITICAL,
            E_NOTICE => self::SEVERITY_NOTICE,
            E_CORE_ERROR => self::SEVERITY_CRITICAL,
            E_CORE_WARNING => self::SEVERITY_WARNING,
            E_COMPILE_ERROR => self::SEVERITY_CRITICAL,
            E_COMPILE_WARNING => self::SEVERITY_WARNING,
            E_USER_ERROR => self::SEVERITY_ERROR,
            E_USER_WARNING => self::SEVERITY_WARNING,
            E_USER_NOTICE => self::SEVERITY_NOTICE,
            E_STRICT => self::SEVERITY_DEBUG,
            E_RECOVERABLE_ERROR => self::SEVERITY_ERROR,
            E_DEPRECATED => self::SEVERITY_NOTICE,
            E_USER_DEPRECATED => self::SEVERITY_NOTICE
        ];
        
        return $map[$errno] ?? self::SEVERITY_ERROR;
    }
    
    
    private static function getErrorTypeName($errno) {
        $types = [
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED'
        ];
        
        return $types[$errno] ?? 'UNKNOWN';
    }
    
    
    private static function getHttpStatusFromException($exception) {
        
        if (method_exists($exception, 'getStatusCode')) {
            return $exception->getStatusCode();
        }
        
        
        $className = get_class($exception);
        
        $statusMap = [
            'InvalidArgumentException' => 400,
            'BadMethodCallException' => 400,
            'UnexpectedValueException' => 400,
            'AuthenticationException' => 401,
            'UnauthorizedException' => 401,
            'ForbiddenException' => 403,
            'NotFoundException' => 404,
            'PDOException' => 500,
            'RuntimeException' => 500
        ];
        
        return $statusMap[$className] ?? 500;
    }
    
    
    private static function getPublicMessage($exception) {
        
        if (self::isDebugMode()) {
            return $exception->getMessage();
        }
        
        
        $className = get_class($exception);
        
        $messageMap = [
            'PDOException' => 'A database error occurred. Please try again later.',
            'InvalidArgumentException' => 'Invalid request data provided.',
            'AuthenticationException' => 'Authentication failed.',
            'UnauthorizedException' => 'Authentication required.',
            'ForbiddenException' => 'Access denied.',
            'NotFoundException' => 'Resource not found.'
        ];
        
        return $messageMap[$className] ?? 'An error occurred. Please try again later.';
    }
    
    
    private static function isDebugMode() {
        return defined('DEBUG_MODE') && DEBUG_MODE === true;
    }
    
    
    private static function getRequestData() {
        $data = [];
        
        
        if (!empty($_GET)) {
            $data['GET'] = $_GET;
        }
        
        
        if (!empty($_POST)) {
            $data['POST'] = self::sanitizeData($_POST);
        }
        
        
        $rawBody = file_get_contents('php://input');
        if ($rawBody) {
            $jsonData = json_decode($rawBody, true);
            if ($jsonData) {
                $data['JSON'] = self::sanitizeData($jsonData);
            }
        }
        
        return $data;
    }
    
    
    private static function sanitizeData($data) {
        $sensitiveFields = ['password', 'token', 'secret', 'api_key', 'credit_card', 'cvv'];
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::sanitizeData($value);
            } elseif (in_array(strtolower($key), $sensitiveFields)) {
                $data[$key] = '[REDACTED]';
            }
        }
        
        return $data;
    }
    
    
    private static function getClientIp() {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 
                   'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                return trim($ip);
            }
        }
        
        return 'Unknown';
    }
    
    
    public static function createError($message, $code = 500, $severity = self::SEVERITY_ERROR) {
        self::logError([
            'error_type' => 'CustomError',
            'error_code' => $code,
            'message' => $message,
            'file' => debug_backtrace()[0]['file'] ?? null,
            'line' => debug_backtrace()[0]['line'] ?? null,
            'severity' => $severity
        ]);
        
        self::sendErrorResponse($message, $code);
    }
    
    
    public static function log($message, $severity = self::SEVERITY_INFO, $context = []) {
        self::logError(array_merge([
            'error_type' => 'Log',
            'message' => $message,
            'severity' => $severity
        ], $context));
    }
}






class AuthenticationException extends Exception {
    public function getStatusCode() {
        return 401;
    }
}


class UnauthorizedException extends Exception {
    public function getStatusCode() {
        return 401;
    }
}


class ForbiddenException extends Exception {
    public function getStatusCode() {
        return 403;
    }
}


class NotFoundException extends Exception {
    public function getStatusCode() {
        return 404;
    }
}


class ValidationException extends Exception {
    private $errors = [];
    
    public function __construct($message = "Validation failed", $errors = []) {
        parent::__construct($message);
        $this->errors = $errors;
    }
    
    public function getStatusCode() {
        return 422;
    }
    
    public function getErrors() {
        return $this->errors;
    }
}


class RateLimitException extends Exception {
    public function getStatusCode() {
        return 429;
    }
}
