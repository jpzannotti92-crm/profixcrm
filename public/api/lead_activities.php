<?php
// Configurar bypass de platform check para desarrollo
require_once __DIR__ . '/../../platform_check_bypass.php';

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Database/Connection.php';
require_once __DIR__ . '/../../src/Models/BaseModel.php';
require_once __DIR__ . '/../../src/Models/User.php';
require_once __DIR__ . '/../../src/Middleware/RBACMiddleware.php';
require_once __DIR__ . '/../../src/Core/Request.php';

use iaTradeCRM\Database\Connection;
use IaTradeCRM\Models\User;
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

try {
    $db = Connection::getInstance()->getConnection();
    
    // Inicializar middleware RBAC
    $rbacMiddleware = new RBACMiddleware();
    $request = new Request();
    
    // Autenticar usuario
    $authResult = $rbacMiddleware->handle($request);
    if ($authResult !== true) {
        // El middleware ya envió la respuesta de error
        exit();
    }
    
    $currentUser = $request->user;
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de conexión a la base de datos: ' . $e->getMessage()
    ]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Verificar permiso para ver actividades
        if (!$currentUser->hasPermission('activities.view')) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permisos para ver actividades'
            ]);
            exit();
        }
        handleGetActivities($db, $currentUser, $rbacMiddleware);
        break;
    case 'POST':
        // Verificar permiso para crear actividades
        if (!$currentUser->hasPermission('activities.create')) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permisos para crear actividades'
            ]);
            exit();
        }
        handleCreateActivity($db, $currentUser);
        break;
    case 'PUT':
        // Verificar permiso para editar actividades
        if (!$currentUser->hasPermission('activities.edit')) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permisos para editar actividades'
            ]);
            exit();
        }
        handleUpdateActivity($db, $currentUser);
        break;
    case 'DELETE':
        // Verificar permiso para eliminar actividades
        if (!$currentUser->hasPermission('activities.delete')) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permisos para eliminar actividades'
            ]);
            exit();
        }
        handleDeleteActivity($db, $currentUser);
        break;
    default:
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Método no permitido'
        ]);
        break;
}

function handleGetActivities($db, $currentUser, $rbacMiddleware) {
    try {
        $leadId = $_GET['lead_id'] ?? null;
        
        if (!$leadId) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID del lead es requerido'
            ]);
            return;
        }
        
        // Verificar si el usuario puede acceder a este lead específico
        if (!$rbacMiddleware->canAccessLead($currentUser, $leadId)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permisos para ver las actividades de este lead'
            ]);
            return;
        }
        
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 20);
        $offset = ($page - 1) * $limit;
        
        // Obtener actividades del lead
        $stmt = $db->prepare("
            SELECT 
                la.*,
                CONCAT(u.first_name, ' ', u.last_name) as user_name,
                u.avatar as user_avatar
            FROM lead_activities la
            LEFT JOIN users u ON la.user_id = u.id
            WHERE la.lead_id = ?
            ORDER BY la.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$leadId, $limit, $offset]);
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Contar total de actividades
        $countStmt = $db->prepare("SELECT COUNT(*) as total FROM lead_activities WHERE lead_id = ?");
        $countStmt->execute([$leadId]);
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Procesar actividades para el frontend
        $processedActivities = array_map(function($activity) {
            // Decodificar metadata si existe
            if ($activity['metadata']) {
                $activity['metadata'] = json_decode($activity['metadata'], true);
            }
            
            // Formatear fechas
            $activity['created_at_formatted'] = date('d/m/Y H:i', strtotime($activity['created_at']));
            if ($activity['scheduled_at']) {
                $activity['scheduled_at_formatted'] = date('d/m/Y H:i', strtotime($activity['scheduled_at']));
            }
            
            return $activity;
        }, $activities);
        
        echo json_encode([
            'success' => true,
            'message' => 'Actividades obtenidas exitosamente',
            'data' => $processedActivities,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($total / $limit),
                'total_items' => (int)$total,
                'items_per_page' => $limit
            ]
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error obteniendo actividades: ' . $e->getMessage()
        ]);
    }
}

function handleCreateActivity($db, $currentUser) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Datos inválidos'
            ]);
            return;
        }
        
        // Validar campos requeridos
        $requiredFields = ['lead_id', 'type', 'subject'];
        foreach ($requiredFields as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => "Campo requerido: $field"
                ]);
                return;
            }
        }
        
        // Verificar acceso al lead
        $leadId = (int)$input['lead_id'];
        $rbacMiddleware = new RBACMiddleware($db);
        if (!$rbacMiddleware->canAccessLead($currentUser, $leadId)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permisos para crear actividades en este lead'
            ]);
            return;
        }
        
        // Preparar datos para inserción
        $data = [
            'lead_id' => $leadId,
            'user_id' => $currentUser->getId(), // Usar el usuario actual
            'type' => $input['type'],
            'subject' => $input['subject'],
            'description' => $input['description'] ?? null,
            'status' => $input['status'] ?? 'pending',
            'scheduled_at' => $input['scheduled_at'] ?? null,
            'duration_minutes' => $input['duration_minutes'] ?? null,
            'outcome' => $input['outcome'] ?? 'neutral',
            'next_action' => $input['next_action'] ?? null,
            'priority' => $input['priority'] ?? 'medium',
            'visibility' => $input['visibility'] ?? 'public',
            'is_system_generated' => $input['is_system_generated'] ?? false,
            'metadata' => isset($input['metadata']) ? json_encode($input['metadata']) : null
        ];
        
        // Insertar actividad
        $sql = "INSERT INTO lead_activities (
            lead_id, user_id, type, subject, description, status, 
            scheduled_at, duration_minutes, outcome, next_action, 
            priority, visibility, is_system_generated, metadata, 
            created_at, updated_at
        ) VALUES (
            :lead_id, :user_id, :type, :subject, :description, :status,
            :scheduled_at, :duration_minutes, :outcome, :next_action,
            :priority, :visibility, :is_system_generated, :metadata,
            NOW(), NOW()
        )";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($data);
        
        $activityId = $db->lastInsertId();
        
        // Obtener la actividad creada con información del usuario
        $stmt = $db->prepare("
            SELECT 
                la.*,
                CONCAT(u.first_name, ' ', u.last_name) as user_name,
                u.avatar as user_avatar
            FROM lead_activities la
            LEFT JOIN users u ON la.user_id = u.id
            WHERE la.id = ?
        ");
        $stmt->execute([$activityId]);
        $activity = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Procesar metadata
        if ($activity['metadata']) {
            $activity['metadata'] = json_decode($activity['metadata'], true);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Actividad creada exitosamente',
            'data' => $activity
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error creando actividad: ' . $e->getMessage()
        ]);
    }
}

function handleUpdateActivity($db, $currentUser) {
    try {
        $activityId = $_GET['id'] ?? null;
        
        if (!$activityId) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID de actividad es requerido'
            ]);
            return;
        }
        
        // Verificar que la actividad existe y obtener información del lead
        $checkStmt = $db->prepare("SELECT lead_id, user_id FROM lead_activities WHERE id = ?");
        $checkStmt->execute([$activityId]);
        $activity = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$activity) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Actividad no encontrada'
            ]);
            return;
        }
        
        // Verificar acceso al lead
        $rbacMiddleware = new RBACMiddleware($db);
        if (!$rbacMiddleware->canAccessLead($currentUser, $activity['lead_id'])) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permisos para editar actividades de este lead'
            ]);
            return;
        }
        
        // Verificar si el usuario puede editar actividades de otros usuarios
        if ($activity['user_id'] != $currentUser->getId() && !$currentUser->hasPermission('activities.edit_all')) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Solo puedes editar tus propias actividades'
            ]);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Datos inválidos'
            ]);
            return;
        }
        
        // Construir query de actualización dinámicamente
        $updateFields = [];
        $params = ['id' => $activityId];
        
        $allowedFields = [
            'subject', 'description', 'status', 'scheduled_at', 
            'duration_minutes', 'outcome', 'next_action', 'priority', 
            'visibility', 'metadata'
        ];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updateFields[] = "$field = :$field";
                $params[$field] = $field === 'metadata' ? json_encode($input[$field]) : $input[$field];
            }
        }
        
        if (empty($updateFields)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'No hay campos para actualizar'
            ]);
            return;
        }
        
        $updateFields[] = "updated_at = NOW()";
        
        $sql = "UPDATE lead_activities SET " . implode(', ', $updateFields) . " WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Actividad no encontrada'
            ]);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Actividad actualizada exitosamente'
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error actualizando actividad: ' . $e->getMessage()
        ]);
    }
}

function handleDeleteActivity($db, $currentUser) {
    try {
        $activityId = $_GET['id'] ?? null;
        
        if (!$activityId) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID de actividad es requerido'
            ]);
            return;
        }
        
        // Verificar que la actividad existe y obtener información del lead
        $checkStmt = $db->prepare("SELECT lead_id, user_id FROM lead_activities WHERE id = ?");
        $checkStmt->execute([$activityId]);
        $activity = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$activity) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Actividad no encontrada'
            ]);
            return;
        }
        
        // Verificar acceso al lead
        $rbacMiddleware = new RBACMiddleware($db);
        if (!$rbacMiddleware->canAccessLead($currentUser, $activity['lead_id'])) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permisos para eliminar actividades de este lead'
            ]);
            return;
        }
        
        // Verificar si el usuario puede eliminar actividades de otros usuarios
        if ($activity['user_id'] != $currentUser->getId() && !$currentUser->hasPermission('activities.delete_all')) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Solo puedes eliminar tus propias actividades'
            ]);
            return;
        }
        
        $stmt = $db->prepare("DELETE FROM lead_activities WHERE id = ?");
        $stmt->execute([$activityId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Actividad eliminada exitosamente'
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error eliminando actividad: ' . $e->getMessage()
        ]);
    }
}
?>