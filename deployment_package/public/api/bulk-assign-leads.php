<?php
// Endpoint para asignación masiva de leads
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Cargar Composer de forma compatible y evitar el fatal de platform_check en PHP < 8.2
if (PHP_VERSION_ID >= 80200 && file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
    if (class_exists('Dotenv\\Dotenv')) {
        $dotenv = Dotenv\Dotenv::createMutable(__DIR__ . '/../../');
        if (method_exists($dotenv, 'overload')) { $dotenv->overload(); } else { $dotenv->load(); }
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

require_once __DIR__ . '/../../src/Database/Connection.php';
require_once __DIR__ . '/../../src/Models/BaseModel.php';
require_once __DIR__ . '/../../src/Models/User.php';
require_once __DIR__ . '/../../src/Middleware/RBACMiddleware.php';
require_once __DIR__ . '/../../src/Core/Request.php';

use iaTradeCRM\Database\Connection;
use IaTradeCRM\Models\User;
use IaTradeCRM\Middleware\RBACMiddleware;
use IaTradeCRM\Core\Request;

// Utilidades
function tableColumns(PDO $pdo, string $table): array {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        return array_values(array_filter(array_map(function($r){ return $r['Field'] ?? null; }, $rows)));
    } catch (Exception $e) { return []; }
}

// Ayuda: obtener nombre completo del usuario
function getUserFullName(PDO $pdo, int $userId): ?string {
    try {
        $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($u) {
            $name = trim(((string)($u['first_name'] ?? '')) . ' ' . ((string)($u['last_name'] ?? '')));
            return $name !== '' ? $name : null;
        }
    } catch (Exception $e) { /* ignore */ }
    return null;
}

// Registrar actividad en lead_activities de forma tolerante al esquema
function logLeadActivity(PDO $pdo, int $leadId, int $userId, array $changes, string $context = 'bulk_assign') {
    try {
        $activityCols = tableColumns($pdo, 'lead_activities');
        if (empty($activityCols)) { return; } // tabla no disponible

        // Construir mensaje solicitado: "Lead Asignado to {Usuario}, Status: {status}"
        $assignedToId = isset($changes['assigned_to']) ? (int)$changes['assigned_to'] : null;
        $statusStr = isset($changes['status']) ? (string)$changes['status'] : null;
        $assignedToName = $assignedToId ? getUserFullName($pdo, $assignedToId) : null;

        if ($assignedToId || $statusStr) {
            $desc = 'Lead Asignado to ' . ($assignedToName ?: ('user_id ' . (string)$assignedToId));
            if ($statusStr) { $desc .= ', Status: ' . $statusStr; }
        } else {
            // Fallback: describir cambios genéricamente
            $parts = [];
            foreach ($changes as $k => $v) {
                if ($v === null) continue;
                $parts[] = $k . ' => ' . (is_scalar($v) ? (string)$v : json_encode($v));
            }
            $desc = (empty($parts) ? 'Asignación aplicada' : ('Asignación: ' . implode(', ', $parts)));
        }

        $fields = [];
        $placeholders = [];
        $values = [];

        // Campos comunes si existen
        if (in_array('lead_id', $activityCols, true)) { $fields[] = 'lead_id'; $placeholders[] = '?'; $values[] = $leadId; }
        if (in_array('user_id', $activityCols, true)) { $fields[] = 'user_id'; $placeholders[] = '?'; $values[] = $userId; }
        if (in_array('type', $activityCols, true)) { $fields[] = 'type'; $placeholders[] = '?'; $values[] = 'assignment'; }
        if (in_array('status', $activityCols, true)) { $fields[] = 'status'; $placeholders[] = '?'; $values[] = 'done'; }
        if (in_array('priority', $activityCols, true)) { $fields[] = 'priority'; $placeholders[] = '?'; $values[] = 'medium'; }
        if (in_array('visibility', $activityCols, true)) { $fields[] = 'visibility'; $placeholders[] = '?'; $values[] = 'public'; }
        if (in_array('description', $activityCols, true)) { $fields[] = 'description'; $placeholders[] = '?'; $values[] = $desc; }

        // Metadatos opcionales con contexto
        if (in_array('metadata', $activityCols, true)) {
            $fields[] = 'metadata';
            $placeholders[] = '?';
            $values[] = json_encode(['context' => $context, 'changes' => $changes]);
        }

        // Timestamps si existen
        if (in_array('created_at', $activityCols, true)) { $fields[] = 'created_at'; $placeholders[] = 'NOW()'; }
        if (in_array('updated_at', $activityCols, true)) { $fields[] = 'updated_at'; $placeholders[] = 'NOW()'; }

        if (empty($fields)) { return; }

        $sql = 'INSERT INTO lead_activities (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
    } catch (Exception $e) {
        // No bloquear por errores de logging
        error_log('bulk-assign logLeadActivity error: ' . $e->getMessage());
    }
}

try {
    $db = Connection::getInstance();
    $pdo = $db->getConnection();

    // Inicializar middleware RBAC
    $rbac = new RBACMiddleware();
    $request = new Request();
    $auth = $rbac->handle($request);
    if ($auth !== true) { exit(); }
    $currentUser = $request->user;

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        exit();
    }

    // Permiso requerido: aceptar tanto leads.update como un permiso dedicado leads.assign
    if (!($currentUser->hasPermission('leads.update') || $currentUser->hasPermission('leads.assign'))) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No tienes permisos para asignación masiva de leads']);
        exit();
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $leadIds = $input['leadIds'] ?? $input['lead_ids'] ?? [];
    $assignments = $input['assignments'] ?? [];

    // Normalizar: permitir que assignments sea objeto (aplicar a todos) o arreglo por-lead
    $isAssignmentsArray = is_array($assignments) && array_keys($assignments) === range(0, count($assignments) - 1);
    $assignAll = !$isAssignmentsArray && is_array($assignments);

    if (!is_array($leadIds) || empty($leadIds)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'leadIds requerido y debe ser un arreglo no vacío']);
        exit();
    }

    // Campos permitidos para actualización masiva
    $allowedFields = ['assigned_to', 'desk_id', 'status', 'notes'];
    $leadCols = tableColumns($pdo, 'leads');
    $updatable = array_values(array_filter($allowedFields, function($f) use ($leadCols){ return in_array($f, $leadCols, true); }));

    if ($assignAll) {
        $data = array_intersect_key($assignments, array_flip($updatable));
        if (empty($data)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Sin campos válidos para actualizar']);
            exit();
        }

        // Validar desk access para usuarios no admin
        if (isset($data['desk_id']) && !$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            $userDesks = $currentUser->getDesks();
            $userDeskIds = array_column($userDesks, 'id');
            if (!in_array((int)$data['desk_id'], $userDeskIds, true)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'No puedes asignar leads a esta mesa']);
                exit();
            }
        }

        // Verificar acceso por lead si el usuario no es admin
        $failed = [];
        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            foreach ($leadIds as $lid) {
                if (!$rbac->canAccessLead($currentUser, (int)$lid)) {
                    $failed[] = (int)$lid;
                }
            }
            if (!empty($failed)) {
                // Política: actualización parcial, reportar fallidos
                $leadIds = array_values(array_diff($leadIds, $failed));
            }
        }

        if (empty($leadIds)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No tienes acceso a los leads seleccionados']);
            exit();
        }

        // Construir UPDATE dinámico con IN (...)
        $setParts = [];
        $values = [];
        foreach ($data as $col => $val) {
            $setParts[] = "`$col` = ?";
            $values[] = $val;
        }
        if (in_array('updated_at', $leadCols, true)) { $setParts[] = '`updated_at` = NOW()'; }
        $placeholders = implode(', ', $setParts);
        $inMarks = implode(', ', array_fill(0, count($leadIds), '?'));

        $sql = "UPDATE leads SET {$placeholders} WHERE id IN ({$inMarks})";
        $stmt = $pdo->prepare($sql);
        $ok = $stmt->execute(array_merge($values, array_map('intval', $leadIds)));

        $updatedCount = $ok ? $stmt->rowCount() : 0;

        // Log de actividad por cada lead actualizado
        if ($ok && $updatedCount > 0) {
            foreach ($leadIds as $lid) {
                logLeadActivity($pdo, (int)$lid, (int)$currentUser->id, $data, 'bulk_assign_all');
            }
        }

        echo json_encode([
            'success' => (bool)$ok,
            'message' => $ok ? 'Asignación masiva aplicada' : 'Error aplicando asignación masiva',
            'updated_count' => $updatedCount,
            'failed' => $failed ?? []
        ]);
        exit();
    }

    // assignments como arreglo: cada item puede incluir { lead_id, assigned_to, desk_id, ... }
    if ($isAssignmentsArray) {
        $updated = 0; $failed = [];
        $pdo->beginTransaction();
        try {
            foreach ($assignments as $item) {
                $lid = (int)($item['lead_id'] ?? $item['id'] ?? null);
                if (!$lid || !in_array($lid, array_map('intval', $leadIds), true)) { $failed[] = $lid; continue; }

                // Acceso por lead para usuarios no admin
                if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
                    if (!$rbac->canAccessLead($currentUser, $lid)) { $failed[] = $lid; continue; }
                }

                $data = array_intersect_key($item, array_flip($updatable));
                if (empty($data)) { $failed[] = $lid; continue; }

                if (isset($data['desk_id']) && !$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
                    $userDesks = $currentUser->getDesks();
                    $userDeskIds = array_column($userDesks, 'id');
                    if (!in_array((int)$data['desk_id'], $userDeskIds, true)) { $failed[] = $lid; continue; }
                }

                $setParts = []; $values = [];
                foreach ($data as $col => $val) { $setParts[] = "`$col` = ?"; $values[] = $val; }
                if (in_array('updated_at', $leadCols, true)) { $setParts[] = '`updated_at` = NOW()'; }
                $sql = 'UPDATE leads SET ' . implode(', ', $setParts) . ' WHERE id = ?';
                $stmt = $pdo->prepare($sql);
                $ok = $stmt->execute(array_merge($values, [$lid]));
                if ($ok && $stmt->rowCount() > 0) {
                    $updated++;
                    // Registrar actividad por lead
                    logLeadActivity($pdo, $lid, (int)$currentUser->id, $data, 'bulk_assign_item');
                } else { $failed[] = $lid; }
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
            exit();
        }

        echo json_encode([
            'success' => true,
            'message' => 'Asignación masiva procesada',
            'updated_count' => $updated,
            'failed' => $failed
        ]);
        exit();
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Formato de assignments no válido']);
    exit();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor: ' . $e->getMessage()
    ]);
}