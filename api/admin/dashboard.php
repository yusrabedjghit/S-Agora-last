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
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/Transaction.php';
require_once __DIR__ . '/../../models/Service.php';
require_once __DIR__ . '/../../models/Report.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/JWT.php';

$database = new Database();
$db = $database->getConnection();

$user = new User($db);
$transaction = new Transaction($db);
$service = new Service($db);
$report = new Report($db);

$method = $_SERVER['REQUEST_METHOD'];
$endpoint = isset($_GET['endpoint']) ? $_GET['endpoint'] : '';

try {
    if ($method === 'GET') {
        if ($endpoint === 'stats') {
            
            $stmt = $db->query("SELECT SUM(coins) as total FROM users WHERE is_active = 1");
            $totalCoins = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            
            $queryRevenue = "SELECT SUM(coins) as total FROM transactions WHERE type = 'purchase' AND status = 'completed'";
            $stmtRevenue = $db->prepare($queryRevenue);
            $stmtRevenue->execute();
            $revenue = $stmtRevenue->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            
            $liveServices = $service->getCount(['status' => 'active']);
            
            
            $pendingDisputes = 0;
            try {
                $statsData = $report->getStatistics();
                $pendingDisputes = $statsData['pending_count'] ?? 0;
            } catch (Exception $e) {
                
            }
            
            
            $stmtUserStats = $db->query("SELECT 
                COUNT(*) as total_users,
                SUM(coins) as total_balances, 
                AVG(coins) as avg_balance
                FROM users WHERE is_active = 1");
            $userStats = $stmtUserStats->fetch(PDO::FETCH_ASSOC);
            
            
            $stmtPurchased = $db->query("SELECT SUM(coins) as total FROM transactions WHERE type = 'purchase' AND status = 'completed'");
            $totalPurchased = $stmtPurchased->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            
            $stmtBonus = $db->query("SELECT SUM(coins) as total FROM transactions WHERE type = 'bonus' AND status = 'completed'");
            $totalBonus = $stmtBonus->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            Response::success([
                'total_coins' => (int)$totalCoins,
                'revenue' => (float)$revenue,
                'live_services' => (int)$liveServices,
                'pending_disputes' => (int)$pendingDisputes,
                'user_stats' => [
                    'total_users' => (int)($userStats['total_users'] ?? 0),
                    'total_balances' => (int)($userStats['total_balances'] ?? 0),
                    'avg_balance' => round((float)($userStats['avg_balance'] ?? 0), 2),
                    'total_purchased' => (int)$totalPurchased,
                    'total_bonus_points' => (int)$totalBonus
                ]
            ]);
            
        } elseif ($endpoint === 'charts') {
            
            
            
            $stmtReg = $db->query("SELECT DATE(created_at) as day, COUNT(*) as count 
                                   FROM users 
                                   WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
                                   GROUP BY DATE(created_at) 
                                   ORDER BY day ASC");
            $registrations = $stmtReg->fetchAll(PDO::FETCH_ASSOC);
            
            
            $stmtExch = $db->query("SELECT DATE(created_at) as day, COUNT(*) as count 
                                    FROM transactions 
                                    WHERE type IN ('service_payment','demand_payment') 
                                    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
                                    GROUP BY DATE(created_at) 
                                    ORDER BY day ASC");
            $exchanges = $stmtExch->fetchAll(PDO::FETCH_ASSOC);
            
            
            $stmtCats = $db->query("SELECT c.name, COUNT(s.id) as service_count 
                                    FROM categories c 
                                    LEFT JOIN services s ON s.category_id = c.id 
                                    GROUP BY c.id, c.name 
                                    ORDER BY service_count DESC 
                                    LIMIT 8");
            $categoryStats = $stmtCats->fetchAll(PDO::FETCH_ASSOC);
            
            
            $stmtPurch = $db->query("SELECT DATE(created_at) as day, COUNT(*) as count, SUM(coins) as total_coins
                                     FROM transactions 
                                     WHERE type = 'purchase' 
                                     AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
                                     GROUP BY DATE(created_at) 
                                     ORDER BY day ASC");
            $purchases = $stmtPurch->fetchAll(PDO::FETCH_ASSOC);
            
            Response::success([
                'registrations' => $registrations,
                'exchanges' => $exchanges,
                'category_stats' => $categoryStats,
                'purchases' => $purchases
            ]);
            
        } elseif ($endpoint === 'activity') {
            
            $queryPurchases = "SELECT t.id, t.coins, t.created_at, u.full_name as user_name 
                               FROM transactions t 
                               LEFT JOIN users u ON t.to_user_id = u.id 
                               WHERE t.type = 'purchase' 
                               ORDER BY t.created_at DESC LIMIT 5";
            $stmtPurchases = $db->prepare($queryPurchases);
            $stmtPurchases->execute();
            $purchases = $stmtPurchases->fetchAll(PDO::FETCH_ASSOC);
            
            
            $pendingReports = ['reports' => []];
            try {
                $pendingReports = $report->getAll(1, 5, ['status' => 'pending']);
            } catch (Exception $e) {
                
            }
            
            
            $today = date('Y-m-d');
            
            $stmtPurchasesToday = $db->prepare("SELECT COUNT(*) as count FROM transactions WHERE type = 'purchase' AND created_at >= :today");
            $stmtPurchasesToday->execute(['today' => $today]);
            $purchasesToday = $stmtPurchasesToday->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
            
            $stmtExchanges = $db->query("SELECT COUNT(*) as count FROM transactions WHERE status = 'completed'");
            $completedExchanges = $stmtExchanges->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
            
            Response::success([
                'recent_purchases' => $purchases,
                'pending_interventions' => $pendingReports['reports'],
                'platform_status' => [
                    'purchases_today' => (int)$purchasesToday,
                    'completed_exchanges' => (int)$completedExchanges
                ]
            ]);
            
        } else {
             Response::error('Invalid endpoint', 400);
        }
    } else {
        Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    Response::error('Server error: ' . $e->getMessage(), 500);
}
