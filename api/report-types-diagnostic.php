<?php


require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $diagnostics = [
        'database_connected' => false,
        'table_exists' => false,
        'table_columns' => [],
        'sample_data' => [],
        'errors' => []
    ];
    
    
    try {
        $result = $db->query("SELECT 1");
        $diagnostics['database_connected'] = true;
    } catch (Exception $e) {
        $diagnostics['errors'][] = "Database connection failed: " . $e->getMessage();
    }
    
    
    try {
        $result = $db->query("SHOW TABLES LIKE 'report_types'");
        $tableExists = $result && $result->rowCount() > 0;
        $diagnostics['table_exists'] = $tableExists;
        
        if (!$tableExists) {
            $diagnostics['errors'][] = "Table 'report_types' does not exist";
        }
    } catch (Exception $e) {
        $diagnostics['errors'][] = "Failed to check table existence: " . $e->getMessage();
    }
    
    
    if ($diagnostics['table_exists']) {
        try {
            $result = $db->query("DESCRIBE report_types");
            $diagnostics['table_columns'] = $result->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $diagnostics['errors'][] = "Failed to get table structure: " . $e->getMessage();
        }
        
        
        try {
            $result = $db->query("SELECT * FROM report_types LIMIT 5");
            $diagnostics['sample_data'] = $result->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $diagnostics['errors'][] = "Failed to fetch sample data: " . $e->getMessage();
        }
    }
    
    Response::success($diagnostics);
    
} catch (Exception $e) {
    Response::error("Diagnostic error: " . $e->getMessage(), 500);
}
?>
