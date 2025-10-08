<?php
// Cargar Composer de forma compatible y evitar el fatal de platform_check en PHP < 8.2
if (PHP_VERSION_ID >= 80200 && file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
    if (class_exists('Dotenv\\Dotenv')) {
        $dotenv = Dotenv\Dotenv::createMutable(__DIR__ . '/../../');
        if (method_exists($dotenv, 'overload')) { $dotenv->overload(); } else { $dotenv->load(); }
    }
} else {
    // Fallback mínimo: cargar .env sin Composer y cargar JWT directamente
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
    // Incluir clases JWT sin autoload para evitar el fatal
    $jwtPath = __DIR__ . '/../../vendor/firebase/php-jwt/src/JWT.php';
    $keyPath = __DIR__ . '/../../vendor/firebase/php-jwt/src/Key.php';
    if (is_file($jwtPath)) { require_once $jwtPath; }
    if (is_file($keyPath)) { require_once $keyPath; }
}
require_once __DIR__ . '/../../src/Database/Connection.php';

use iaTradeCRM\Database\Connection;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Helpers de esquema dinámico
function getPdoConn(): PDO {
    return Connection::getInstance()->getConnection();
}

function getTableColumns(string $table): array {
    try {
        $pdo = getPdoConn();
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
        $rows = $stmt ? $stmt->fetchAll() : [];
        return array_values(array_filter(array_map(function($r){ return $r['Field'] ?? $r[0] ?? null; }, $rows)));
    } catch (Exception $e) {
        error_log("lead-activities getTableColumns error: " . $e->getMessage());
        return [];
    }
}

function columnExists(string $table, string $column): bool {
    $cols = getTableColumns($table);
    return in_array($column, $cols, true);
}

// Obtiene valores permitidos de columnas ENUM (p.ej. type, status, priority)
function getEnumValues(string $table, string $column): array {
    try {
        $pdo = getPdoConn();
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
        $stmt->execute([$column]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['Type'])) return [];
        $type = $row['Type']; // ej: enum('note','call')
        if (preg_match("/enum\((.*)\)/i", $type, $m)) {
            $inner = $m[1];
            // separar por comas respetando quotes
            $values = array_map(function($v){
                $v = trim($v);
                return trim($v, "'\"");
            }, explode(',', $inner));
            return $values;
        }
        return [];
    } catch (Exception $e) {
        error_log("lead-activities getEnumValues error: " . $e->getMessage());
        return [];
    }
}

function sanitizeEnumValue(string $table, string $column, ?string $value, string $fallback): string {
    $allowed = getEnumValues($table, $column);
    if (!$allowed) return $fallback; // no enum o desconocido, usar fallback
    if ($value && in_array($value, $allowed, true)) return $value;
    // si el fallback está permitido, usarlo; si no, usar el primer permitido
    return in_array($fallback, $allowed, true) ? $fallback : $allowed[0];
}

// Cargar variables de entorno para asegurar que JWT_SECRET esté disponible
if (class_exists('Dotenv\\Dotenv')) {
    $dotenv = Dotenv\Dotenv::createMutable(__DIR__ . '/../../');
    if (method_exists($dotenv, 'overload')) {
        $dotenv->overload();
    } else {
        $dotenv->load();
    }
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verificar JWT (soportar diferentes servidores/cabeceras)
$headers = function_exists('getallheaders') ? getallheaders() : [];
$authHeader = $headers['Authorization']
    ?? ($headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));

if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    http_response_code(401);
    echo json_encode(['error' => 'Token de autorización requerido']);
    exit();
}

$token = $matches[1];

try {
    // Usar el mismo secreto que login.php: $_ENV['JWT_SECRET'] o fallback 'password'
    $secret = $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET') ?? 'password';
    $decoded = JWT::decode($token, new Key($secret, 'HS256'));
    $userId = $decoded->user_id ?? ($decoded->sub ?? 1);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Token inválido']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = Connection::getInstance()->getConnection();
    
    switch ($method) {
        case 'GET':
            handleGetActivities($db);
            break;
        case 'POST':
            handleCreateActivity($db);
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

function handleGetActivities($pdo) {
    $leadId = $_GET['lead_id'] ?? null;
    
    if (!$leadId) {
        http_response_code(400);
        echo json_encode(['error' => 'ID del lead requerido']);
        return;
    }
    
    // Parámetros de paginación
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = max(1, min(50, intval($_GET['limit'] ?? 3))); // Máximo 50, por defecto 3
    $offset = ($page - 1) * $limit;
    
    // Filtro de fecha
    $dateFilter = $_GET['date'] ?? null;
    
    // Construir la consulta base
    $whereClause = "WHERE la.lead_id = ?";
    $params = [$leadId];
    
    if ($dateFilter) {
        $whereClause .= " AND DATE(la.created_at) = ?";
        $params[] = $dateFilter;
    }
    
    // Consulta para obtener el total de registros
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM lead_activities la
        $whereClause
    ");
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Consulta para obtener las actividades paginadas
    $stmt = $pdo->prepare("
        SELECT 
            la.id,
            la.description,
            la.created_at,
            la.user_id,
            CONCAT(u.first_name, ' ', u.last_name) as user_name,
            u.username
        FROM lead_activities la
        LEFT JOIN users u ON la.user_id = u.id
        $whereClause
        ORDER BY la.created_at DESC
        LIMIT $limit OFFSET $offset
    ");
    
    $stmt->execute($params);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular información de paginación
    $totalPages = ceil($totalRecords / $limit);
    $hasNextPage = $page < $totalPages;
    $hasPrevPage = $page > 1;
    
    echo json_encode([
        'success' => true,
        'activities' => $activities,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
            'limit' => $limit,
            'has_next_page' => $hasNextPage,
            'has_prev_page' => $hasPrevPage
        ]
    ]);
}

function handleCreateActivity($pdo) {
    global $userId; // Usar el userId del token JWT
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $leadId = $input['lead_id'] ?? null;
    $description = $input['description'] ?? null;
    $type = $input['type'] ?? 'note';
    $status = $input['status'] ?? 'pending';
    // Permitir que el frontend envíe 'leadStatus' para actualizar el estado del lead
    $leadStatus = $input['leadStatus'] ?? null;
    $subject = $input['subject'] ?? null;
    $priority = $input['priority'] ?? 'medium';
    $scheduled_at = $input['scheduled_at'] ?? null;
    $completed_at = $input['completed_at'] ?? null;
    $duration_minutes = $input['duration_minutes'] ?? null;
    $outcome = $input['outcome'] ?? null;
    $metadata = $input['metadata'] ?? null;
    
    if (!$leadId || !$description) {
        http_response_code(400);
        echo json_encode(['error' => 'Datos incompletos']);
        return;
    }
    
    // Verificar que el lead existe
    $stmt = $pdo->prepare("SELECT id FROM leads WHERE id = ?");
    $stmt->execute([$leadId]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Lead no encontrado']);
        return;
    }
    
    // Verificar existencia de tabla
    $columns = getTableColumns('lead_activities');
    if (!$columns) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Tabla lead_activities no existe o no se pudo obtener columnas']);
        return;
    }
    $insertCols = [];
    $placeholders = [];
    $values = [];

    // Campos base
    if (in_array('lead_id', $columns, true)) { $insertCols[] = 'lead_id'; $placeholders[] = '?'; $values[] = $leadId; }
    if (in_array('user_id', $columns, true)) {
        // Validar que el usuario existe; si no, insertar NULL para evitar error de clave foránea
        $validUserId = null;
        try {
            if ($userId) {
                $uStmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
                $uStmt->execute([$userId]);
                $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
                if ($uRow && isset($uRow['id'])) {
                    $validUserId = (int)$uRow['id'];
                }
            }
        } catch (Exception $e) {
            // Si falla la verificación, dejar user_id en NULL
            error_log('lead-activities: verificación user_id falló: ' . $e->getMessage());
        }
        $insertCols[] = 'user_id';
        $placeholders[] = '?';
        $values[] = $validUserId; // puede ser NULL
    }
    if (in_array('description', $columns, true)) { $insertCols[] = 'description'; $placeholders[] = '?'; $values[] = $description; }

    // Campos opcionales con defaults seguros (validando ENUMs según el esquema)
    if (in_array('type', $columns, true)) { 
        $insertCols[] = 'type'; 
        $placeholders[] = '?'; 
        $values[] = sanitizeEnumValue('lead_activities', 'type', $type, 'note'); 
    }
    if (in_array('status', $columns, true)) { 
        $insertCols[] = 'status'; 
        $placeholders[] = '?'; 
        $values[] = sanitizeEnumValue('lead_activities', 'status', $status, 'pending'); 
    }
    if (in_array('subject', $columns, true)) { $insertCols[] = 'subject'; $placeholders[] = '?'; $values[] = $subject; }
    if (in_array('priority', $columns, true)) { 
        $insertCols[] = 'priority'; 
        $placeholders[] = '?'; 
        $values[] = sanitizeEnumValue('lead_activities', 'priority', $priority, 'medium'); 
    }
    if (in_array('scheduled_at', $columns, true) && $scheduled_at) { $insertCols[] = 'scheduled_at'; $placeholders[] = '?'; $values[] = $scheduled_at; }
    if (in_array('completed_at', $columns, true) && $completed_at) { $insertCols[] = 'completed_at'; $placeholders[] = '?'; $values[] = $completed_at; }
    if (in_array('duration_minutes', $columns, true) && $duration_minutes !== null) { $insertCols[] = 'duration_minutes'; $placeholders[] = '?'; $values[] = $duration_minutes; }
    if (in_array('outcome', $columns, true) && $outcome) { $insertCols[] = 'outcome'; $placeholders[] = '?'; $values[] = $outcome; }
    if (in_array('visibility', $columns, true) && isset($input['visibility'])) { 
        $insertCols[] = 'visibility'; 
        $placeholders[] = '?'; 
        $values[] = sanitizeEnumValue('lead_activities', 'visibility', (string)$input['visibility'], 'public'); 
    }
    if (in_array('is_system_generated', $columns, true) && isset($input['is_system_generated'])) { $insertCols[] = 'is_system_generated'; $placeholders[] = '?'; $values[] = $input['is_system_generated'] ? 1 : 0; }
    if (in_array('metadata', $columns, true) && $metadata) { $insertCols[] = 'metadata'; $placeholders[] = '?'; $values[] = is_array($metadata) ? json_encode($metadata) : $metadata; }

    // Timestamps: si existen columnas pero no tienen default, podemos dejar que el motor ponga default; si queremos forzar:
    // Preferimos no incluir created_at/updated_at explícitos para respetar defaults del esquema

    if (empty($insertCols)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'No se pudo construir la inserción: esquema inesperado']);
        return;
    }

    $sql = "INSERT INTO lead_activities (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $placeholders) . ")";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        
        $activityId = $pdo->lastInsertId();

        // Si se especificó leadStatus, actualizar el estado del lead de forma segura (tolerante a ENUM)
        if ($leadStatus) {
            try {
                // Verificar columna 'status' en leads y sanear valor si es ENUM
                $leadCols = getTableColumns('leads');
                if (in_array('status', $leadCols, true)) {
                    $safeStatus = sanitizeEnumValue('leads', 'status', (string)$leadStatus, 'contacted');
                    $upd = $pdo->prepare("UPDATE leads SET status = ? WHERE id = ?");
                    $upd->execute([$safeStatus, $leadId]);
                }
            } catch (Exception $e) {
                // No bloquear la creación por fallo al actualizar el estado del lead
                error_log('lead-activities: fallo actualizando estado del lead: ' . $e->getMessage());
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Actividad registrada exitosamente',
            'activity_id' => $activityId
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error creando actividad: ' . $e->getMessage()
        ]);
    }
}
?>