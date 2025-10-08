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
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de conexión a la base de datos: ' . $e->getMessage()
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $userId = $currentUser->id;
    $limit = $_GET['limit'] ?? 10;
    
    // Obtener actividades recientes del usuario
    $query = "
        SELECT 
            la.id,
            la.type,
            la.description,
            la.created_at as timestamp,
            CONCAT(l.first_name, ' ', l.last_name) as leadName,
            l.id as lead_id
        FROM lead_activities la
        LEFT JOIN leads l ON la.lead_id = l.id
        WHERE la.user_id = :user_id
        ORDER BY la.created_at DESC
        LIMIT :limit
    ";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear las actividades
    $formattedActivities = array_map(function($activity) {
        return [
            'id' => (int)$activity['id'],
            'type' => $activity['type'],
            'description' => $activity['description'],
            'timestamp' => $activity['timestamp'],
            'leadName' => $activity['leadName'],
            'lead_id' => $activity['lead_id'] ? (int)$activity['lead_id'] : null
        ];
    }, $activities);
    
    echo json_encode([
        'success' => true,
        'data' => $formattedActivities
    ]);
    
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
}
?>