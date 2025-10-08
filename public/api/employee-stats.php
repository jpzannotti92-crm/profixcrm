<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Database/Connection.php';
require_once __DIR__ . '/../../src/Models/BaseModel.php';
require_once __DIR__ . '/../../src/Models/User.php';
require_once __DIR__ . '/../../src/Middleware/RBACMiddleware.php';
require_once __DIR__ . '/../../src/Core/Request.php';

use iaTradeCRM\Database\Connection;
use iaTradeCRM\Models\User;
use IaTradeCRM\Middleware\RBACMiddleware;
use IaTradeCRM\Core\Request;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Inicializar middleware RBAC
$rbacMiddleware = new RBACMiddleware();
$request = new Request();

// Autenticar usuario
try {
    $authResult = $rbacMiddleware->handle($request);
    if ($authResult !== true) {
        // El middleware ya envió la respuesta de error
        exit();
    }
    
    $currentUser = $request->user;
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit();
}

try {
    $db = Connection::getInstance()->getConnection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $userId = $currentUser->id;
        $period = $_GET['period'] ?? 'month';
        
        // Calcular fechas según el período
        $dateCondition = '';
        switch ($period) {
            case 'today':
                $dateCondition = "DATE(created_at) = CURDATE()";
                break;
            case 'week':
                $dateCondition = "created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                break;
            case 'month':
                $dateCondition = "created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                break;
            case 'quarter':
                $dateCondition = "created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
                break;
            default:
                $dateCondition = "created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        }
        
        // Obtener estadísticas de leads del usuario
        $leadsQuery = "
            SELECT 
                COUNT(*) as total_leads,
                SUM(CASE WHEN status IN ('new', 'contacted', 'qualified') THEN 1 ELSE 0 END) as active_leads,
                SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as converted_leads
            FROM leads 
            WHERE assigned_to = :user_id AND $dateCondition
        ";
        
        $stmt = $db->prepare($leadsQuery);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        $leadsStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calcular tasa de conversión
        $conversionRate = $leadsStats['total_leads'] > 0 
            ? round(($leadsStats['converted_leads'] / $leadsStats['total_leads']) * 100, 1)
            : 0;
        
        // Obtener actividades del día
        $activitiesQuery = "
            SELECT COUNT(*) as today_activities
            FROM lead_activities 
            WHERE user_id = :user_id AND DATE(created_at) = CURDATE()
        ";
        
        $stmt = $db->prepare($activitiesQuery);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        $todayActivities = $stmt->fetch(PDO::FETCH_ASSOC)['today_activities'] ?? 0;
        
        // Obtener actividades de la semana
        $weekActivitiesQuery = "
            SELECT COUNT(*) as week_activities
            FROM lead_activities 
            WHERE user_id = :user_id AND created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)
        ";
        
        $stmt = $db->prepare($weekActivitiesQuery);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        $weekActivities = $stmt->fetch(PDO::FETCH_ASSOC)['week_activities'] ?? 0;
        
        // Obtener objetivo mensual del usuario (desde configuración o tabla de objetivos)
        $targetQuery = "
            SELECT monthly_target 
            FROM user_targets 
            WHERE user_id = :user_id AND YEAR(created_at) = YEAR(NOW()) AND MONTH(created_at) = MONTH(NOW())
            ORDER BY created_at DESC 
            LIMIT 1
        ";
        
        $stmt = $db->prepare($targetQuery);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        $target = $stmt->fetch(PDO::FETCH_ASSOC);
        $monthlyTarget = $target['monthly_target'] ?? 50; // Default 50 si no hay objetivo configurado
        
        // Obtener progreso mensual
        $monthlyProgressQuery = "
            SELECT COUNT(*) as monthly_progress
            FROM leads 
            WHERE assigned_to = :user_id 
            AND YEAR(created_at) = YEAR(NOW()) 
            AND MONTH(created_at) = MONTH(NOW())
        ";
        
        $stmt = $db->prepare($monthlyProgressQuery);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        $monthlyProgress = $stmt->fetch(PDO::FETCH_ASSOC)['monthly_progress'] ?? 0;
        
        $stats = [
            'totalLeads' => (int)$leadsStats['total_leads'],
            'activeLeads' => (int)$leadsStats['active_leads'],
            'convertedLeads' => (int)$leadsStats['converted_leads'],
            'conversionRate' => $conversionRate,
            'monthlyTarget' => (int)$monthlyTarget,
            'monthlyProgress' => (int)$monthlyProgress,
            'todayActivities' => (int)$todayActivities,
            'weekActivities' => (int)$weekActivities
        ];
        
        echo json_encode([
            'success' => true,
            'data' => $stats
        ]);
        
    } else {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Método no permitido'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor: ' . $e->getMessage()
    ]);
}
?>