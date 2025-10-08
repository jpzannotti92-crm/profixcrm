<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Models/DeskState.php';
require_once __DIR__ . '/../../src/Models/StateTransition.php';
use iaTradeCRM\Database\Connection;
use IaTradeCRM\Models\DeskState as DeskStateModel;
use IaTradeCRM\Models\StateTransition as StateTransitionModel;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Inicializar PDO y repositorios
$pdo = Connection::getInstance()->getConnection();
$deskStatesRepo = new DeskStateModel($pdo);
$transitionsRepo = new StateTransitionModel($pdo);

// JWT Authentication
function authenticateUser() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    
    if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(['error' => 'Token no proporcionado']);
        exit();
    }
    
    $token = $matches[1];
    $secretKey = $_ENV['JWT_SECRET'] ?? 'your-secret-key';
    
    try {
        $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));
        return $decoded;
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(['error' => 'Token inválido']);
        exit();
    }
}

// Check permissions
function checkPermission($user, $permission) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM user_roles ur 
        JOIN role_permissions rp ON ur.role_id = rp.role_id 
        JOIN permissions p ON rp.permission_id = p.id 
        WHERE ur.user_id = ? AND p.name = ?
    ");
    $stmt->execute([$user->user_id, $permission]);
    
    return $stmt->fetchColumn() > 0;
}

$user = authenticateUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

if (!checkPermission($user, 'change_lead_state')) {
    http_response_code(403);
    echo json_encode(['error' => 'Sin permisos para cambiar estados de leads']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$leadId = $input['lead_id'] ?? null;
$newStateId = $input['new_state_id'] ?? null;
$reason = $input['reason'] ?? '';

if (!$leadId || !$newStateId) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de lead y nuevo estado requeridos']);
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Get current lead data
    $stmt = $pdo->prepare("SELECT id, status, desk_id FROM leads WHERE id = ?");
    $stmt->execute([$leadId]);
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lead) {
        http_response_code(404);
        echo json_encode(['error' => 'Lead no encontrado']);
        exit();
    }
    
    // Obtener estado actual por nombre+desk
    $currentStmt = $pdo->prepare("SELECT id FROM desk_states WHERE name = ? AND desk_id = ? LIMIT 1");
    $currentStmt->execute([$lead['status'], $lead['desk_id']]);
    $currentStateId = $currentStmt->fetchColumn() ?: null;
    
    // Get new state data
    $newState = $deskStatesRepo->getState($newStateId);
    if (!$newState) {
        http_response_code(400);
        echo json_encode(['error' => 'Estado destino no válido']);
        exit();
    }
    
    // Validate transition is allowed
    if ($currentStateId) {
        $isValidTransition = $transitionsRepo->isValidTransition($currentStateId, $newStateId, null);
        if (!$isValidTransition) {
            http_response_code(400);
            echo json_encode(['error' => 'Transición de estado no permitida']);
            exit();
        }
    }
    
    // Update lead status
    $stmt = $pdo->prepare("UPDATE leads SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$newState['name'], $leadId]);
    
    // Record state change in history
    $stmt = $pdo->prepare("
        INSERT INTO lead_state_history (lead_id, old_state_id, new_state_id, changed_by, reason, changed_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$leadId, $currentStateId, $newStateId, $user->user_id, $reason]);
    
    $pdo->commit();
    
    // Get updated lead data
    $stmt = $pdo->prepare("
        SELECT l.*, 
               ds.name as state_name, ds.color as state_color,
               CONCAT(u.first_name, ' ', u.last_name) as assigned_to_name,
               d.name as desk_name
        FROM leads l
        LEFT JOIN desk_states ds ON ds.name = l.status AND ds.desk_id = l.desk_id
        LEFT JOIN users u ON l.assigned_to = u.id
        LEFT JOIN desks d ON l.desk_id = d.id
        WHERE l.id = ?
    ");
    $stmt->execute([$leadId]);
    $updatedLead = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'Estado del lead actualizado exitosamente',
        'data' => $updatedLead
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Error al cambiar estado: ' . $e->getMessage()]);
}
?>