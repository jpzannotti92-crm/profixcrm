<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';
require_once '../includes/jwt_helper.php';

// Verificar JWT
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
    http_response_code(401);
    echo json_encode(['error' => 'Token de autorización requerido']);
    exit;
}

$token = substr($authHeader, 7);
$decoded = verifyJWT($token);

if (!$decoded) {
    http_response_code(401);
    echo json_encode(['error' => 'Token inválido']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$userId = $decoded->user_id ?? 1; // En un sistema real, obtener del JWT

try {
    $pdo = new PDO($dsn, $username, $password, $options);
    
    switch ($method) {
        case 'GET':
            handleGetNotifications($pdo, $userId);
            break;
        case 'POST':
            handleCreateNotification($pdo, $userId);
            break;
        case 'PUT':
            handleUpdateNotification($pdo, $userId);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de base de datos: ' . $e->getMessage()]);
}

function handleGetNotifications($pdo, $userId) {
    $limit = $_GET['limit'] ?? 20;
    $unreadOnly = $_GET['unread_only'] ?? false;
    
    $whereClause = "WHERE (user_id = ? OR user_id IS NULL)";
    $params = [$userId];
    
    if ($unreadOnly) {
        $whereClause .= " AND is_read = 0";
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            id,
            title,
            message,
            type,
            actions,
            is_read,
            created_at,
            expires_at
        FROM notifications 
        $whereClause
        AND (expires_at IS NULL OR expires_at > NOW())
        ORDER BY created_at DESC 
        LIMIT ?
    ");
    
    $params[] = (int)$limit;
    $stmt->execute($params);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Decodificar actions JSON
    foreach ($notifications as &$notification) {
        if ($notification['actions']) {
            $notification['actions'] = json_decode($notification['actions'], true);
        }
    }
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications
    ]);
}

function handleCreateNotification($pdo, $userId) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $title = $input['title'] ?? null;
    $message = $input['message'] ?? null;
    $type = $input['type'] ?? 'info';
    $targetUserId = $input['user_id'] ?? null; // null = para todos los usuarios
    $actions = $input['actions'] ?? null;
    $expiresAt = $input['expires_at'] ?? null;
    
    if (!$title || !$message) {
        http_response_code(400);
        echo json_encode(['error' => 'Título y mensaje son requeridos']);
        return;
    }
    
    // Validar tipo
    $validTypes = ['info', 'success', 'warning', 'error'];
    if (!in_array($type, $validTypes)) {
        $type = 'info';
    }
    
    // Codificar actions como JSON
    $actionsJson = $actions ? json_encode($actions) : null;
    
    $stmt = $pdo->prepare("
        INSERT INTO notifications (title, message, type, user_id, actions, expires_at, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([$title, $message, $type, $targetUserId, $actionsJson, $expiresAt]);
    
    $notificationId = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Notificación creada exitosamente',
        'notification_id' => $notificationId
    ]);
}

function handleUpdateNotification($pdo, $userId) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $notificationId = $input['notification_id'] ?? null;
    $action = $input['action'] ?? null;
    
    if (!$notificationId || !$action) {
        http_response_code(400);
        echo json_encode(['error' => 'ID de notificación y acción requeridos']);
        return;
    }
    
    switch ($action) {
        case 'mark_read':
            $stmt = $pdo->prepare("
                UPDATE notifications 
                SET is_read = 1, read_at = NOW() 
                WHERE id = ? AND (user_id = ? OR user_id IS NULL)
            ");
            $stmt->execute([$notificationId, $userId]);
            break;
            
        case 'mark_unread':
            $stmt = $pdo->prepare("
                UPDATE notifications 
                SET is_read = 0, read_at = NULL 
                WHERE id = ? AND (user_id = ? OR user_id IS NULL)
            ");
            $stmt->execute([$notificationId, $userId]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Acción no válida']);
            return;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Notificación actualizada exitosamente'
    ]);
}

// Función helper para crear notificaciones del sistema
function createSystemNotification($pdo, $title, $message, $type = 'info', $userId = null, $actions = null) {
    $actionsJson = $actions ? json_encode($actions) : null;
    
    $stmt = $pdo->prepare("
        INSERT INTO notifications (title, message, type, user_id, actions, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    return $stmt->execute([$title, $message, $type, $userId, $actionsJson]);
}

// Función para notificar nuevos leads
function notifyNewLead($pdo, $leadId, $leadName, $leadEmail) {
    $actions = [
        [
            'label' => 'Ver Lead',
            'action' => 'view_lead',
            'type' => 'primary',
            'url' => "/views/leads/lead-detail.html?id=$leadId"
        ]
    ];
    
    return createSystemNotification(
        $pdo,
        'Nuevo Lead Registrado',
        "Se ha registrado un nuevo lead: $leadName ($leadEmail)",
        'info',
        null, // Para todos los usuarios
        $actions
    );
}

// Función para notificar vinculación de cuentas
function notifyAccountLinked($pdo, $leadId, $leadName, $accountNumber) {
    $actions = [
        [
            'label' => 'Ver Lead',
            'action' => 'view_lead',
            'type' => 'primary',
            'url' => "/views/leads/lead-detail.html?id=$leadId"
        ],
        [
            'label' => 'Abrir WebTrader',
            'action' => 'open_webtrader',
            'type' => 'secondary',
            'url' => "/webtrader?account=$accountNumber"
        ]
    ];
    
    return createSystemNotification(
        $pdo,
        'Cuenta de Trading Vinculada',
        "Se ha vinculado la cuenta $accountNumber al lead $leadName",
        'success',
        null,
        $actions
    );
}

// Función para notificar actividad de trading
function notifyTradingActivity($pdo, $leadId, $leadName, $accountNumber, $activity) {
    $actions = [
        [
            'label' => 'Ver Lead',
            'action' => 'view_lead',
            'type' => 'primary',
            'url' => "/views/leads/lead-detail.html?id=$leadId"
        ]
    ];
    
    return createSystemNotification(
        $pdo,
        'Actividad de Trading',
        "Nueva actividad en cuenta $accountNumber del lead $leadName: $activity",
        'info',
        null,
        $actions
    );
}
?>