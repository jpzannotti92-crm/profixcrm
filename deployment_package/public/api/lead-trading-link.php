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

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verificar autenticación JWT
function verifyJWT($required = true) {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    
    if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        if ($required) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Token de autorización requerido']);
            exit();
        }
        return null;
    }
    
    $token = $matches[1];
    // Usar el mismo secreto que login.php para coherencia
    $secret = $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET') ?? 'password';
    
    try {
        $decoded = JWT::decode($token, new Key($secret, 'HS256'));
        return $decoded;
    } catch (Exception $e) {
        if ($required) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Token inválido']);
            exit();
        }
        return null;
    }
}

$currentUser = verifyJWT(false);

// Helpers de esquema dinámico para tolerar variaciones de columnas/tablas
function getPdoConn(): PDO {
    return Connection::getInstance()->getConnection();
}

function tableExists(string $table): bool {
    try {
        $pdo = getPdoConn();
        $stmt = $pdo->query("SHOW TABLES LIKE '" . str_replace("'", "''", $table) . "'");
        return $stmt && $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Schema check tableExists error: " . $e->getMessage());
        return false;
    }
}

function getTableColumns(string $table): array {
    try {
        $pdo = getPdoConn();
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
        $rows = $stmt ? $stmt->fetchAll() : [];
        return array_values(array_filter(array_map(function($r){ return $r['Field'] ?? $r[0] ?? null; }, $rows)));
    } catch (Exception $e) {
        error_log("Schema check getTableColumns error: " . $e->getMessage());
        return [];
    }
}

function columnExists(string $table, string $column): bool {
    $cols = getTableColumns($table);
    return in_array($column, $cols, true);
}

// Funciones "safe" que respetan el esquema dinámico
function linkTradingAccountToLeadSafe($accountId, $leadId) {
    if (!tableExists('trading_accounts') || !columnExists('trading_accounts', 'lead_id')) {
        return false;
    }
    try {
        $pdo = getPdoConn();
        $stmt = $pdo->prepare("UPDATE trading_accounts SET lead_id = ? WHERE id = ?");
        return $stmt->execute([$leadId, $accountId]);
    } catch (Exception $e) {
        error_log("Safe link error: " . $e->getMessage());
        return false;
    }
}

function getTradingAccountsByLeadIdSafe($leadId) {
    if (!tableExists('trading_accounts')) { return []; }
    try {
        $pdo = getPdoConn();
        $cols = getTableColumns('trading_accounts');
        if (!in_array('lead_id', $cols, true)) { return []; }
        $hasUserId = in_array('user_id', $cols, true);
        $hasCreatedAt = in_array('created_at', $cols, true);
        $select = "SELECT ta.*" . ($hasUserId ? ", u.first_name, u.last_name, u.email as user_email" : "");
        $sql = $select . " FROM trading_accounts ta "
             . ($hasUserId ? "LEFT JOIN users u ON ta.user_id = u.id " : "")
             . "WHERE ta.lead_id = ?" 
             . ($hasCreatedAt ? " ORDER BY ta.created_at DESC" : "");
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$leadId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Safe get accounts error: " . $e->getMessage());
        return [];
    }
}

function autoLinkAccountToLeadSafe($accountId, $emailOverride = null) {
    if (!tableExists('trading_accounts')) { return false; }
    try {
        $pdo = getPdoConn();
        $cols = getTableColumns('trading_accounts');
        $email = $emailOverride;
        if (!$email) {
            if (!in_array('email', $cols, true)) { return false; }
            $stmt = $pdo->prepare("SELECT email FROM trading_accounts WHERE id = ?");
            $stmt->execute([$accountId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || empty($row['email'])) { return false; }
            $email = $row['email'];
        }
        $leadStmt = $pdo->prepare("SELECT id FROM leads WHERE email = ? LIMIT 1");
        $leadStmt->execute([$email]);
        $lead = $leadStmt->fetch(PDO::FETCH_ASSOC);
        if ($lead && columnExists('trading_accounts', 'lead_id')) {
            $upd = $pdo->prepare("UPDATE trading_accounts SET lead_id = ? WHERE id = ?");
            $upd->execute([$lead['id'], $accountId]);
            return $lead['id'];
        }
        return false;
    } catch (Exception $e) {
        error_log("Safe auto link error: " . $e->getMessage());
        return false;
    }
}

function unlinkTradingAccountSafe($accountId) {
    if (!tableExists('trading_accounts') || !columnExists('trading_accounts', 'lead_id')) {
        return false;
    }
    try {
        $pdo = getPdoConn();
        $stmt = $pdo->prepare("UPDATE trading_accounts SET lead_id = NULL WHERE id = ?");
        $stmt->execute([$accountId]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Safe unlink error: " . $e->getMessage());
        return false;
    }
}

/**
 * Buscar lead por email
 */
function findLeadByEmail($email) {
    try {
        $db = Connection::getInstance();
        $stmt = $db->query("SELECT * FROM leads WHERE email = ? LIMIT 1", [$email]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Agregar logging para debug
        error_log("Búsqueda de lead por email '$email': " . ($result ? "Encontrado ID: " . $result['id'] : "No encontrado"));
        
        return $result;
    } catch (Exception $e) {
        error_log("Error buscando lead por email: " . $e->getMessage());
        return null;
    }
}

/**
 * Vincular cuenta de trading con lead
 */
function linkTradingAccountToLead($accountId, $leadId) {
    try {
        $db = Connection::getInstance();
        $stmt = $db->prepare("UPDATE trading_accounts SET lead_id = ? WHERE id = ?");
        return $stmt->execute([$leadId, $accountId]);
    } catch (Exception $e) {
        error_log("Error vinculando cuenta con lead: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtener cuentas de trading vinculadas a un lead
 */
function getTradingAccountsByLeadId($leadId) {
    try {
        $db = Connection::getInstance();
        $stmt = $db->query("
            SELECT ta.*, u.first_name, u.last_name, u.email as user_email
            FROM trading_accounts ta
            LEFT JOIN users u ON ta.user_id = u.id
            WHERE ta.lead_id = ?
            ORDER BY ta.created_at DESC
        ", [$leadId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error obteniendo cuentas del lead: " . $e->getMessage());
        return [];
    }
}

/**
 * Proceso de vinculación automática
 */
function autoLinkAccountToLead($accountId) {
    try {
        $db = Connection::getInstance();
        
        // Obtener el email de la cuenta de trading
        $accountStmt = $db->query("SELECT email FROM trading_accounts WHERE id = ?", [$accountId]);
        $account = $accountStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$account) {
            return false;
        }
        
        // Buscar lead con el mismo email
        $stmt = $db->query("SELECT * FROM leads WHERE email = ? LIMIT 1", [$account['email']]);
        $lead = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($lead) {
            // Vincular la cuenta al lead
            $updateStmt = $db->query("UPDATE trading_accounts SET lead_id = ? WHERE id = ?", [$lead['id'], $accountId]);
            
            error_log("Auto-vinculación exitosa: Cuenta {$accountId} vinculada al lead {$lead['id']}");
            return $lead['id'];
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Error en auto-vinculación: " . $e->getMessage());
        return false;
    }
}

/**
 * Desvincular cuenta de trading
 */
function unlinkTradingAccount($accountId) {
    try {
        $db = Connection::getInstance();
        $stmt = $db->query("UPDATE trading_accounts SET lead_id = NULL WHERE id = ?", [$accountId]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Error desvinculando cuenta: " . $e->getMessage());
        return false;
    }
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        switch ($action) {
            case 'lead_accounts':
                $leadId = (int)($_GET['lead_id'] ?? 0);
                
                if (!$leadId) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'ID de lead requerido'
                    ]);
                    break;
                }
                
                if (!tableExists('trading_accounts') || !columnExists('trading_accounts', 'lead_id')) {
                    echo json_encode([
                        'success' => true,
                        'data' => [],
                        'count' => 0,
                        'message' => 'Sin cuentas disponibles (lead_id no existe)'
                    ]);
                    break;
                }

                $accounts = getTradingAccountsByLeadIdSafe($leadId);
                
                echo json_encode([
                    'success' => true,
                    'data' => $accounts,
                    'count' => count($accounts)
                ]);
                break;
                
            case 'find_lead':
                $email = $_GET['email'] ?? '';
                
                if (!$email) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Email requerido'
                    ]);
                    break;
                }
                
                $lead = findLeadByEmail($email);
                
                if ($lead) {
                    echo json_encode([
                        'success' => true,
                        'lead_found' => true,
                        'data' => $lead
                    ]);
                } else {
                    echo json_encode([
                        'success' => true,
                        'lead_found' => false,
                        'message' => 'No se encontró lead con este email'
                    ]);
                }
                break;
                
            default:
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Acción no encontrada'
                ]);
                break;
        }
        break;
        
    case 'POST':
        switch ($action) {
            case 'auto_link':
                $input = json_decode(file_get_contents('php://input'), true);
                
                if (!$input || empty($input['email']) || empty($input['account_id'])) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Email y account_id son requeridos'
                    ]);
                    break;
                }
                
                $linkedLeadId = autoLinkAccountToLeadSafe($input['account_id'], $input['email']);
                if ($linkedLeadId) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Auto-vinculación realizada',
                        'lead_id' => $linkedLeadId
                    ]);
                } else {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'No se pudo vincular automáticamente'
                    ]);
                }
                break;
                
            case 'manual_link':
                $input = json_decode(file_get_contents('php://input'), true);
                
                if (!$input || empty($input['lead_id']) || empty($input['account_id'])) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'lead_id y account_id son requeridos'
                    ]);
                    break;
                }
                
                $linked = linkTradingAccountToLead($input['account_id'], $input['lead_id']);
                
                if ($linked) {
                    // Crear notificación para vinculación de cuenta
                    try {
                        require_once 'notifications.php';
                        
                        // Obtener información del lead y cuenta
                        $leadStmt = $pdo->prepare("SELECT first_name, last_name FROM leads WHERE id = ?");
                        $leadStmt->execute([$input['lead_id']]);
                        $lead = $leadStmt->fetch(PDO::FETCH_ASSOC);
                        
                        $accountStmt = $pdo->prepare("SELECT account_number FROM trading_accounts WHERE id = ?");
                        $accountStmt->execute([$input['account_id']]);
                        $account = $accountStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($lead && $account) {
                            $leadName = trim($lead['first_name'] . ' ' . $lead['last_name']);
                            notifyAccountLinked($pdo, $input['lead_id'], $leadName, $account['account_number']);
                        }
                    } catch (Exception $e) {
                        error_log("Error creating notification: " . $e->getMessage());
                    }
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Cuenta vinculada manualmente al lead'
                    ]);
                } else {
                    http_response_code(500);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Error al vincular la cuenta con el lead'
                    ]);
                }
                break;
                
            default:
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Acción no encontrada'
                ]);
                break;
        }
        break;
        
    case 'DELETE':
        // Desvincular cuenta de lead
        $accountId = (int)($_GET['account_id'] ?? 0);
        
        if (!$accountId) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID de cuenta requerido'
            ]);
            break;
        }
        
        try {
            $unlinked = unlinkTradingAccountSafe($accountId);
            
            if ($unlinked) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Cuenta desvinculada del lead'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al desvincular la cuenta'
                ]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
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
