<?php
require_once __DIR__ . '/bootstrap.php';
// Configurar bypass de platform check para desarrollo
require_once __DIR__ . '/../../platform_check_bypass.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Cargar Composer de forma compatible y evitar el fatal de platform_check en PHP < 8.2
if (PHP_VERSION_ID >= 80200 && file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
    if (class_exists('Dotenv\\Dotenv')) {
        $dotenv = Dotenv\Dotenv::createMutable(__DIR__ . '/../../');
        $dotenv->load();
    }
} else {
    // Fallback mínimo si Dotenv/Vendor no está disponible
    $envFile = __DIR__ . '/../../.env';
    if (is_file($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (strpos(ltrim($line), '#') === 0) continue;
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $_ENV[trim($parts[0])] = trim($parts[1]);
                putenv(trim($parts[0]) . '=' . trim($parts[1]));
            }
        }
    }
}

// Cargar variables de entorno (usar repositorio mutable para priorizar .env local)
if (class_exists('Dotenv\\Dotenv')) {
    $dotenv = Dotenv\Dotenv::createMutable(__DIR__ . '/../../');
    $dotenv->load();
}

// Cargar archivos necesarios
require_once __DIR__ . '/../../src/Database/Connection.php';
require_once __DIR__ . '/../../src/Models/BaseModel.php';
require_once __DIR__ . '/../../src/Models/User.php';
require_once __DIR__ . '/../../src/Middleware/RBACMiddleware.php';
require_once __DIR__ . '/../../src/Core/Request.php';
require_once __DIR__ . '/../../src/Models/DeskState.php';
require_once __DIR__ . '/../../src/Models/StateTransition.php';

use iaTradeCRM\Database\Connection;
use IaTradeCRM\Models\User;
use IaTradeCRM\Middleware\RBACMiddleware;
use IaTradeCRM\Core\Request;
use IaTradeCRM\Models\DeskState;
use IaTradeCRM\Models\StateTransition;

try {
    $db = Connection::getInstance();
    
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
    
    // Permitir acceso a acciones específicas si el usuario puede acceder al lead concreto,
    // incluso si no tiene permisos globales de leads.view/leads.view.assigned
    $skipPermissionCheck = false;
    if (isset($_GET['action']) && in_array($_GET['action'], ['available-states', 'state-history'])) {
        $leadIdForAction = (int)($_GET['id'] ?? 0);
        if ($leadIdForAction && $rbacMiddleware->canAccessLead($currentUser, $leadIdForAction)) {
            $skipPermissionCheck = true;
        }
    }
    
    // Verificar permisos para leads - aceptar tanto leads.view como leads.view.assigned
    if (!$skipPermissionCheck && !$currentUser->hasPermission('leads.view') && !$currentUser->hasPermission('leads.view.assigned')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No tienes permisos para ver leads']);
        exit();
    }

    // ... existing code ...

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de autenticación: ' . $e->getMessage()
    ]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Handle special actions first
        if (isset($_GET['action'])) {
            switch ($_GET['action']) {
                case 'available-states':
                    // Get available states for a lead
                    $leadId = (int)($_GET['id'] ?? 0);
                    if (!$leadId) {
                        http_response_code(400);
                        echo json_encode(['error' => 'ID de lead requerido']);
                        exit();
                    }
                    
                    // Get lead's current state and desk
                    $stmt = $db->getConnection()->prepare("SELECT status, desk_id FROM leads WHERE id = ?");
                    $stmt->execute([$leadId]);
                    $lead = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$lead) {
                        http_response_code(404);
                        echo json_encode(['error' => 'Lead no encontrado']);
                        exit();
                    }
                    
                    // Get available transitions
                    $currentStateId = DeskState::getStateIdByName($lead['status'], $lead['desk_id']);
                    $availableStates = [];
                    
                    if ($currentStateId) {
                        $availableStates = StateTransition::getAvailableTransitionsStatic($currentStateId, $lead['desk_id']);
                    }
                    
                    echo json_encode(['success' => true, 'data' => $availableStates]);
                    exit();
                    
                case 'state-history':
                    // Get state change history for a lead
                    $leadId = (int)($_GET['id'] ?? 0);
                    if (!$leadId) {
                        http_response_code(400);
                        echo json_encode(['error' => 'ID de lead requerido']);
                        exit();
                    }
                    try {
                        $stmt = $db->getConnection()->prepare("
                            SELECT lsh.*, 
                                   ds_old.name as old_state_name, ds_old.color as old_state_color,
                                   ds_new.name as new_state_name, ds_new.color as new_state_color,
                                   CONCAT(u.first_name, ' ', u.last_name) as changed_by_name
                            FROM lead_state_history lsh
                            LEFT JOIN desk_states ds_old ON lsh.old_state_id = ds_old.id
                            LEFT JOIN desk_states ds_new ON lsh.new_state_id = ds_new.id
                            LEFT JOIN users u ON lsh.changed_by = u.id
                            WHERE lsh.lead_id = ?
                            ORDER BY lsh.changed_at DESC
                        ");
                        $stmt->execute([$leadId]);
                        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        echo json_encode(['success' => true, 'data' => $history]);
                    } catch (PDOException $e) {
                        // Si la tabla no existe, devolver historial vacío en producción
                        error_log('lead_state_history no disponible: ' . $e->getMessage());
                        echo json_encode(['success' => true, 'data' => []]);
                    }
                    exit();
            }
        }
        
        if (isset($_GET['id'])) {
            // Obtener lead específico
            $leadId = (int)$_GET['id'];
            
            // Verificar si el usuario puede acceder a este lead específico
            if (!$rbacMiddleware->canAccessLead($currentUser, $leadId)) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'No tienes permisos para ver este lead',
                    'error_code' => 'ACCESS_DENIED'
                ]);
                break;
            }
            
            try {
                $stmt = $db->getConnection()->prepare("
                    SELECT l.*, 
                           CONCAT(u.first_name, ' ', u.last_name) as assigned_to_name,
                           d.name as desk_name
                    FROM leads l
                    LEFT JOIN users u ON l.assigned_to = u.id
                    LEFT JOIN desks d ON l.desk_id = d.id
                    WHERE l.id = ?
                ");
                $stmt->execute([$leadId]);
                $lead = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($lead) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Lead encontrado',
                        'data' => $lead
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Lead no encontrado'
                    ]);
                }
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al obtener el lead: ' . $e->getMessage()
                ]);
            }
        } else {
            // Obtener lista de leads con filtros y control de acceso basado en roles
            $search = $_GET['search'] ?? '';
            $status = $_GET['status'] ?? '';
            $desk_id = $_GET['desk_id'] ?? '';
            $assigned_to = $_GET['assigned_to'] ?? '';
            $source = $_GET['source'] ?? '';
            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 20);
            
            try {
                // Obtener filtros basados en el rol del usuario
                $roleFilters = $rbacMiddleware->getLeadsFilters($currentUser);
                
                // Construir consulta base
                $sql = "
                    SELECT l.*, 
                           CONCAT(u.first_name, ' ', u.last_name) as assigned_to_name,
                           d.name as desk_name
                    FROM leads l
                    LEFT JOIN users u ON l.assigned_to = u.id
                    LEFT JOIN desks d ON l.desk_id = d.id
                    WHERE 1=1
                ";
                $params = [];
                
                // Aplicar filtros de rol (control de acceso)
                if (isset($roleFilters['assigned_to'])) {
                    $sql .= " AND l.assigned_to = ?";
                    $params[] = $roleFilters['assigned_to'];
                }
                
                if (isset($roleFilters['desk_ids']) && !empty($roleFilters['desk_ids'])) {
                    $placeholders = str_repeat('?,', count($roleFilters['desk_ids']) - 1) . '?';
                    $sql .= " AND l.desk_id IN ($placeholders)";
                    $params = array_merge($params, $roleFilters['desk_ids']);
                }
                
                // Aplicar filtros adicionales del usuario
                if ($search) {
                    $sql .= " AND (l.first_name LIKE ? OR l.last_name LIKE ? OR l.email LIKE ? OR l.phone LIKE ?)";
                    $searchTerm = "%{$search}%";
                    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
                }
                
                if ($status) {
                    $sql .= " AND l.status = ?";
                    $params[] = $status;
                }
                
                if ($desk_id) {
                    $sql .= " AND l.desk_id = ?";
                    $params[] = $desk_id;
                }
                
                if ($assigned_to) {
                    $sql .= " AND l.assigned_to = ?";
                    $params[] = $assigned_to;
                }
                
                if ($source) {
                    $sql .= " AND l.source = ?";
                    $params[] = $source;
                }
                
                // Contar total
                $countSql = "SELECT COUNT(*) as total FROM (" . $sql . ") as count_query";
                $countStmt = $db->getConnection()->prepare($countSql);
                $countStmt->execute($params);
                $total = $countStmt->fetch()['total'];
                
                // Agregar paginación
                $sql .= " ORDER BY l.created_at DESC LIMIT ? OFFSET ?";
                $offset = ($page - 1) * $limit;
                $params = array_merge($params, [$limit, $offset]);
                
                // Ejecutar consulta
                $stmt = $db->getConnection()->prepare($sql);
                $stmt->execute($params);
                $leads = $stmt->fetchAll();
                
                $totalPages = ceil($total / $limit);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Leads obtenidos correctamente',
                    'data' => $leads,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => (int)$total,
                        'pages' => $totalPages
                    ]
                ]);
                
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al obtener leads: ' . $e->getMessage()
                ]);
            }
        }
        break;
        
    case 'POST':
        // Verificar permisos para crear leads
        if (!$currentUser->hasPermission('leads.create')) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permisos para crear leads',
                'error_code' => 'INSUFFICIENT_PERMISSIONS'
            ]);
            break;
        }
        
        // Crear nuevo lead
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Datos inválidos'
            ]);
            break;
        }
        
        // Validaciones básicas
        $required = ['first_name', 'last_name', 'email'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => "El campo {$field} es requerido"
                ]);
                exit;
            }
        }
        
        try {
            // Preparar datos del lead
            $leadData = [
                'first_name' => $input['first_name'],
                'last_name' => $input['last_name'],
                'email' => $input['email'],
                'phone' => $input['phone'] ?? '',
                'country' => $input['country'] ?? '',
                'status' => $input['status'] ?? 'new',
                'source' => $input['source'] ?? 'Web',
                'assigned_to' => $input['assigned_to'] ?? $currentUser->id, // Auto-asignar al usuario actual
                'desk_id' => $input['desk_id'] ?? null,
                'notes' => $input['notes'] ?? '',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Verificar que el usuario puede asignar a la mesa especificada
            if ($leadData['desk_id'] && !$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
                $userDesks = $currentUser->getDesks();
                $userDeskIds = array_column($userDesks, 'id');
                
                if (!in_array($leadData['desk_id'], $userDeskIds)) {
                    http_response_code(403);
                    echo json_encode([
                        'success' => false,
                        'message' => 'No puedes asignar leads a esta mesa',
                        'error_code' => 'DESK_ACCESS_DENIED'
                    ]);
                    break;
                }
            }
            
            // Insertar en la base de datos
            $sql = "INSERT INTO leads (first_name, last_name, email, phone, country, status, source, assigned_to, desk_id, notes, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $db->getConnection()->prepare($sql);
            $stmt->execute([
                $leadData['first_name'],
                $leadData['last_name'],
                $leadData['email'],
                $leadData['phone'],
                $leadData['country'],
                $leadData['status'],
                $leadData['source'],
                $leadData['assigned_to'],
                $leadData['desk_id'],
                $leadData['notes'],
                $leadData['created_at'],
                $leadData['updated_at']
            ]);
            
            $newLeadId = $db->lastInsertId();
            $leadData['id'] = $newLeadId;
            
            echo json_encode([
                'success' => true,
                'message' => 'Lead creado exitosamente',
                'data' => $leadData
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al crear el lead: ' . $e->getMessage()
            ]);
        }
        break;
        
    case 'PUT':
        // Verificar permisos para actualizar leads
        // Aceptar tanto 'leads.update' (catálogo extendido) como el alias legacy 'leads.edit'
        if (!$currentUser->hasPermission('leads.update') && !$currentUser->hasPermission('leads.edit')) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permisos para actualizar leads',
                'error_code' => 'INSUFFICIENT_PERMISSIONS'
            ]);
            break;
        }
        
        // Actualizar lead
        $input = json_decode(file_get_contents('php://input'), true);
        $leadId = (int)($_GET['id'] ?? 0);
        
        if (!$leadId || !$input) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID de lead y datos son requeridos'
            ]);
            break;
        }
        
        // Verificar si el usuario puede acceder a este lead específico
        if (!$rbacMiddleware->canAccessLead($currentUser, $leadId)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permisos para actualizar este lead',
                'error_code' => 'ACCESS_DENIED'
            ]);
            break;
        }
        
        try {
            // Verificar que el lead existe
            $stmt = $db->getConnection()->prepare("SELECT * FROM leads WHERE id = ?");
            $stmt->execute([$leadId]);
            $existingLead = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$existingLead) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Lead no encontrado'
                ]);
                break;
            }
            
            // Obtener columnas reales de la tabla leads y preparar campos permitidos
            $leadColumns = [];
            try {
                $colStmt = $db->getConnection()->query("SHOW COLUMNS FROM leads");
                $leadColumns = array_map(function($c){ return $c['Field']; }, $colStmt->fetchAll());
            } catch (Exception $e) {
                $leadColumns = [];
            }
            // Candidatos seguros; se limitarán a columnas existentes para tolerar variaciones de esquema
            $allowedFieldsCandidate = ['first_name', 'last_name', 'email', 'phone', 'country', 'status', 'source', 'assigned_to', 'desk_id', 'notes'];
            $allowedFields = array_values(array_filter($allowedFieldsCandidate, function($f) use ($leadColumns) {
                return in_array($f, $leadColumns, true);
            }));
            $updateFields = [];
            $updateValues = [];
            
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    // Verificar permisos especiales para ciertos campos
                    if ($field === 'desk_id' && $input[$field] != $existingLead['desk_id']) {
                        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
                            $userDesks = $currentUser->getDesks();
                            $userDeskIds = array_column($userDesks, 'id');
                            
                            if (!in_array($input[$field], $userDeskIds)) {
                                http_response_code(403);
                                echo json_encode([
                                    'success' => false,
                                    'message' => 'No puedes mover leads a esta mesa',
                                    'error_code' => 'DESK_ACCESS_DENIED'
                                ]);
                                break 2;
                            }
                        }
                    }
                    
                    $updateFields[] = "$field = ?";
                    $updateValues[] = $input[$field];
                }
            }
            
            if (empty($updateFields)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'No hay campos para actualizar'
                ]);
                break;
            }
            
            // Agregar campo de auditoría si existe en el esquema
            if (in_array('updated_at', $leadColumns, true)) {
                $updateFields[] = "updated_at = ?";
                $updateValues[] = date('Y-m-d H:i:s');
            }
            $updateValues[] = $leadId;
            
            // Ejecutar actualización
            $sql = "UPDATE leads SET " . implode(', ', $updateFields) . " WHERE id = ?";
            error_log("LEAD UPDATE - SQL: $sql");
            error_log("LEAD UPDATE - Values: " . json_encode($updateValues));
            error_log("LEAD UPDATE - Lead ID: $leadId");
            error_log("LEAD UPDATE - User ID: " . $currentUser->id);
            
            $stmt = $db->getConnection()->prepare($sql);
            $result = $stmt->execute($updateValues);
            
            error_log("LEAD UPDATE - Execution result: " . ($result ? 'SUCCESS' : 'FAILED'));
            error_log("LEAD UPDATE - Affected rows: " . $stmt->rowCount());

            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                error_log("LEAD UPDATE - SQL Error: " . json_encode($errorInfo));
                throw new Exception("Error en la actualización: " . $errorInfo[2]);
            }

            // Registrar actividad nueva si hay reasignación o cambio de estado desde el perfil
            try {
                $pdo = $db->getConnection();
                $changesForActivity = [];
                if (isset($input['assigned_to']) && $input['assigned_to'] != $existingLead['assigned_to']) {
                    $changesForActivity['assigned_to'] = (int)$input['assigned_to'];
                }
                if (isset($input['status']) && $input['status'] != $existingLead['status']) {
                    $changesForActivity['status'] = (string)$input['status'];
                }
                if (!empty($changesForActivity)) {
                    // Construir descripción "Lead Asignado to {Usuario}, Status: {status}"
                    $assignedToId = $changesForActivity['assigned_to'] ?? null;
                    $statusStr = $changesForActivity['status'] ?? null;
                    $assignedToName = null;
                    if ($assignedToId) {
                        $uStmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
                        $uStmt->execute([$assignedToId]);
                        $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
                        if ($uRow) {
                            $assignedToName = trim(((string)($uRow['first_name'] ?? '')) . ' ' . ((string)($uRow['last_name'] ?? '')));
                            if ($assignedToName === '') { $assignedToName = null; }
                        }
                    }
                    $desc = 'Lead Asignado to ' . ($assignedToName ?: ('user_id ' . (string)$assignedToId));
                    if ($statusStr) { $desc .= ', Status: ' . $statusStr; }

                    // Obtener columnas disponibles de lead_activities y construir INSERT tolerante
                    $colsStmt = $pdo->query("SHOW COLUMNS FROM `lead_activities`");
                    $colsRows = $colsStmt ? $colsStmt->fetchAll(PDO::FETCH_ASSOC) : [];
                    $activityCols = array_values(array_filter(array_map(function($r){ return $r['Field'] ?? null; }, $colsRows)));

                    if (!empty($activityCols)) {
                        $fields = []; $placeholders = []; $values = [];
                        if (in_array('lead_id', $activityCols, true)) { $fields[] = 'lead_id'; $placeholders[] = '?'; $values[] = $leadId; }
                        if (in_array('user_id', $activityCols, true)) { $fields[] = 'user_id'; $placeholders[] = '?'; $values[] = (int)$currentUser->id; }
                        if (in_array('type', $activityCols, true)) { $fields[] = 'type'; $placeholders[] = '?'; $values[] = 'assignment'; }
                        if (in_array('status', $activityCols, true)) { $fields[] = 'status'; $placeholders[] = '?'; $values[] = 'done'; }
                        if (in_array('priority', $activityCols, true)) { $fields[] = 'priority'; $placeholders[] = '?'; $values[] = 'medium'; }
                        if (in_array('visibility', $activityCols, true)) { $fields[] = 'visibility'; $placeholders[] = '?'; $values[] = 'public'; }
                        if (in_array('description', $activityCols, true)) { $fields[] = 'description'; $placeholders[] = '?'; $values[] = $desc; }
                        if (in_array('metadata', $activityCols, true)) { $fields[] = 'metadata'; $placeholders[] = '?'; $values[] = json_encode(['context' => 'lead_profile_update', 'changes' => $changesForActivity]); }
                        if (in_array('created_at', $activityCols, true)) { $fields[] = 'created_at'; $placeholders[] = 'NOW()'; }
                        if (in_array('updated_at', $activityCols, true)) { $fields[] = 'updated_at'; $placeholders[] = 'NOW()'; }

                        if (!empty($fields)) {
                            $insSql = 'INSERT INTO lead_activities (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
                            $insStmt = $pdo->prepare($insSql);
                            $insStmt->execute($values);
                        }
                    }
                }
            } catch (Exception $e) {
                error_log('LEAD UPDATE - Activity log error: ' . $e->getMessage());
            }
            
            // Obtener lead actualizado
            $stmt = $db->getConnection()->prepare("
                SELECT l.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as assigned_name,
                       d.name as desk_name
                FROM leads l
                LEFT JOIN users u ON l.assigned_to = u.id
                LEFT JOIN desks d ON l.desk_id = d.id
                WHERE l.id = ?
            ");
            $stmt->execute([$leadId]);
            $updatedLead = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'message' => 'Lead actualizado exitosamente',
                'data' => $updatedLead
            ]);
            
        } catch (Exception $e) {
            error_log("LEAD UPDATE - Error en catch: " . $e->getMessage());
            
            // Manejar errores específicos de integridad referencial
            if (strpos($e->getMessage(), 'foreign key constraint fails') !== false) {
                if (strpos($e->getMessage(), 'assigned_to') !== false) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Error al asignar lead: El usuario seleccionado no existe o no está disponible'
                    ]);
                } else {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Error de integridad de datos: Referencia inválida'
                    ]);
                }
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al actualizar el lead: ' . $e->getMessage()
                ]);
            }
        }
        break;
        
    case 'DELETE':
        // Verificar permisos para eliminar leads
        // Aceptar tanto 'leads.delete' (catálogo extendido) como el alias legacy 'delete_leads'
        if (!$currentUser->hasPermission('leads.delete') && !$currentUser->hasPermission('delete_leads')) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permisos para eliminar leads',
                'error_code' => 'INSUFFICIENT_PERMISSIONS'
            ]);
            break;
        }
        
        // Eliminar lead
        $leadId = (int)($_GET['id'] ?? 0);
        
        if (!$leadId) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID de lead requerido'
            ]);
            break;
        }
        
        // Verificar si el usuario puede acceder a este lead específico
        if (!$rbacMiddleware->canAccessLead($currentUser, $leadId)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permisos para eliminar este lead',
                'error_code' => 'ACCESS_DENIED'
            ]);
            break;
        }
        
        try {
            // Verificar que el lead existe
            $stmt = $db->getConnection()->prepare("SELECT id FROM leads WHERE id = ?");
            $stmt->execute([$leadId]);
            $existingLead = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$existingLead) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Lead no encontrado'
                ]);
                break;
            }
            
            // Eliminar el lead (soft delete recomendado en producción)
            $stmt = $db->getConnection()->prepare("DELETE FROM leads WHERE id = ?");
            $stmt->execute([$leadId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Lead eliminado exitosamente'
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al eliminar el lead: ' . $e->getMessage()
            ]);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Método no permitido'
        ]);
        break;
}
?>