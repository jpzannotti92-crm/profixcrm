<?php
require_once __DIR__ . '/../../vendor/autoload.php';
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
    $secret = 'your-super-secret-jwt-key-change-in-production-2024';
    
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

// SOLO DATOS REALES - Sin datos simulados
// Los instrumentos se obtienen de la base de datos real
function getInstrumentsFromDatabase() {
    try {
        $db = Connection::getInstance();
        $stmt = $db->query("SELECT * FROM trading_symbols WHERE is_active = 1");
        $symbols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Organizar por categorías
        $instruments = [];
        foreach ($symbols as $symbol) {
            $category = ucfirst($symbol['category']);
            if (!isset($instruments[$category])) {
                $instruments[$category] = [];
            }
            $instruments[$category][$symbol['symbol']] = [
                'name' => $symbol['name'],
                'digits' => $symbol['digits'],
                'pip_size' => $symbol['pip_size'],
                'spread' => $symbol['typical_spread'],
                'base_currency' => $symbol['base_currency'],
                'quote_currency' => $symbol['quote_currency']
            ];
        }
        
        return $instruments;
    } catch (Exception $e) {
        error_log("Error obteniendo instrumentos: " . $e->getMessage());
        return [];
    }
}

// Las órdenes se obtienen de la base de datos real
function getOrdersFromDatabase($accountId) {
    try {
        $db = Connection::getInstance();
        $stmt = $db->query("SELECT * FROM trading_orders WHERE account_id = ? AND status = 'open'", [$accountId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error obteniendo órdenes: " . $e->getMessage());
        return [];
    }
}

// Las cuentas se obtienen de la base de datos real con información del lead vinculado
function getTradingAccountsFromDatabase($userId) {
    try {
        $db = Connection::getInstance();
        $stmt = $db->query("
            SELECT ta.*, 
                   l.first_name as lead_first_name, 
                   l.last_name as lead_last_name,
                   l.email as lead_email,
                   l.phone as lead_phone,
                   l.country as lead_country,
                   l.status as lead_status
            FROM trading_accounts ta
            LEFT JOIN leads l ON ta.lead_id = l.id
            WHERE ta.user_id = ? AND ta.status = 'active'
            ORDER BY ta.created_at DESC
        ", [$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error obteniendo cuentas: " . $e->getMessage());
        return [];
    }
}

$method = $_SERVER['REQUEST_METHOD'];
$endpoint = $_GET['endpoint'] ?? '';

switch ($endpoint) {
    case 'instruments':
        // Obtener instrumentos reales de la base de datos
        $instruments = getInstrumentsFromDatabase();
        
        echo json_encode([
            'success' => true,
            'data' => $instruments
        ]);
        break;
        
    case 'prices':
        // Los precios en tiempo real no están disponibles vía HTTP
        // Usa el feed en tiempo real del frontend (WebSocket/simulación)
        echo json_encode([
            'success' => false,
            'message' => 'Precios en tiempo real no disponibles por HTTP'
        ]);
        break;
        
    case 'orders':
        // Obtener órdenes reales de la base de datos
        $account_id = $_GET['account_id'] ?? 0;
        
        if (!$account_id) {
            echo json_encode([
                'success' => false,
                'message' => 'ID de cuenta requerido'
            ]);
            break;
        }
        
        $orders = getOrdersFromDatabase($account_id);
        
        echo json_encode([
            'success' => true,
            'data' => $orders
        ]);
        break;
        
    case 'accounts':
        // Obtener cuentas reales de la base de datos
        $user_id = $currentUser->user_id ?? 0;
        
        if (!$user_id) {
            echo json_encode([
                'success' => false,
                'message' => 'Usuario no autenticado'
            ]);
            break;
        }
        
        $accounts = getTradingAccountsFromDatabase($user_id);
        
        echo json_encode([
            'success' => true,
            'data' => $accounts
        ]);
        break;
        
    case 'create_order':
        if ($method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Validar datos requeridos
            $required_fields = ['account_id', 'symbol', 'type', 'volume'];
            foreach ($required_fields as $field) {
                if (!isset($input[$field]) || empty($input[$field])) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => "Campo requerido: $field"
                    ]);
                    exit;
                }
            }
            
            try {
                $db = Connection::getInstance();
                
                // Verificar que la cuenta existe y pertenece al usuario
                $stmt = $db->query("SELECT * FROM trading_accounts WHERE id = ? AND user_id = ?", [$input['account_id'], $currentUser->user_id ?? 0]);
                $account = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$account) {
                    http_response_code(403);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Cuenta no encontrada o sin permisos'
                    ]);
                    break;
                }
                
                // Crear nueva orden
                $stmt = $db->query("
                    INSERT INTO trading_orders (
                        account_id, symbol, type, order_type, volume, 
                        price, stop_loss, take_profit, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'open', NOW())
                ", [
                    $input['account_id'],
                    $input['symbol'],
                    $input['type'],
                    $input['order_type'] ?? 'market',
                    $input['volume'],
                    $input['price'] ?? null,
                    $input['stop_loss'] ?? null,
                    $input['take_profit'] ?? null
                ]);
                
                $order_id = $db->lastInsertId();
                
                // Registrar actividad en el lead si está vinculado
                if ($account['lead_id']) {
                    $description = "Nueva orden de trading: {$input['type']} {$input['volume']} lotes de {$input['symbol']}";
                    $stmt = $db->query("
                        INSERT INTO lead_activities (
                            lead_id, type, description, created_at, created_by
                        ) VALUES (?, 'trading', ?, NOW(), ?)
                    ", [
                        $account['lead_id'],
                        $description,
                        $currentUser->user_id ?? null
                    ]);
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Orden creada exitosamente',
                    'data' => [
                        'order_id' => $order_id,
                        'account_id' => $input['account_id'],
                        'symbol' => $input['symbol'],
                        'type' => $input['type'],
                        'volume' => $input['volume']
                    ]
                ]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Error creando la orden: ' . $e->getMessage()
                ]);
            }
        }
        break;

    case 'lead_activity':
        // Registrar actividad de trading en el lead
        if ($method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['account_id']) || !isset($input['activity_type']) || !isset($input['description'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Datos de actividad incompletos'
                ]);
                break;
            }
            
            try {
                $db = Connection::getInstance();
                
                // Obtener el lead_id de la cuenta
                $stmt = $db->query("SELECT lead_id FROM trading_accounts WHERE id = ?", [$input['account_id']]);
                $account = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($account && $account['lead_id']) {
                    $stmt = $db->query("
                        INSERT INTO lead_activities (
                            lead_id, type, description, created_at, created_by
                        ) VALUES (?, ?, ?, NOW(), ?)
                    ", [
                        $account['lead_id'],
                        $input['activity_type'],
                        $input['description'],
                        $currentUser->user_id ?? null
                    ]);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Actividad registrada en el lead'
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Cuenta no vinculada a ningún lead'
                    ]);
                }
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Error registrando actividad: ' . $e->getMessage()
                ]);
            }
        }
        break;
        if ($method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $order_id = $input['order_id'] ?? 0;
            
            if (!$order_id) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'ID de orden requerido'
                ]);
                break;
            }
            
            // Cerrar orden real en la base de datos
            try {
                $db = Connection::getInstance();
                $stmt = $db->query("UPDATE trading_orders SET status = 'closed', closed_at = NOW() WHERE id = ?", [$order_id]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Orden cerrada exitosamente',
                    'data' => [
                        'order_id' => $order_id,
                        'closed_at' => date('Y-m-d H:i:s')
                    ]
                ]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Error cerrando la orden: ' . $e->getMessage()
                ]);
            }
        }
        break;
        
    case 'close_order':
        if ($method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $order_id = $input['order_id'] ?? 0;
            
            if (!$order_id) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'ID de orden requerido'
                ]);
                break;
            }
            
            // Cerrar orden real en la base de datos
            try {
                $db = Connection::getInstance();
                
                // Obtener información de la orden antes de cerrarla
                $stmt = $db->query("
                    SELECT o.*, ta.lead_id 
                    FROM trading_orders o
                    JOIN trading_accounts ta ON o.account_id = ta.id
                    WHERE o.id = ?
                ", [$order_id]);
                $order = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$order) {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Orden no encontrada'
                    ]);
                    break;
                }
                
                // Cerrar la orden
                $stmt = $db->query("UPDATE trading_orders SET status = 'closed', closed_at = NOW() WHERE id = ?", [$order_id]);
                
                // Registrar actividad en el lead si está vinculado
                if ($order['lead_id']) {
                    $description = "Orden cerrada: {$order['type']} {$order['volume']} lotes de {$order['symbol']}";
                    $stmt = $db->query("
                        INSERT INTO lead_activities (
                            lead_id, type, description, created_at, created_by
                        ) VALUES (?, 'trading', ?, NOW(), ?)
                    ", [
                        $order['lead_id'],
                        $description,
                        $currentUser->user_id ?? null
                    ]);
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Orden cerrada exitosamente',
                    'data' => [
                        'order_id' => $order_id,
                        'closed_at' => date('Y-m-d H:i:s')
                    ]
                ]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Error cerrando la orden: ' . $e->getMessage()
                ]);
            }
        }
        break;
        
    case 'account_info':
        // Información real de la cuenta de trading desde la base de datos
        $account_id = $_GET['account_id'] ?? 0;
        
        if (!$account_id) {
            echo json_encode([
                'success' => false,
                'message' => 'ID de cuenta requerido'
            ]);
            break;
        }
        
        try {
            $db = Connection::getInstance();
            $stmt = $db->query("SELECT * FROM trading_accounts WHERE id = ?", [$account_id]);
            $account = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$account) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Cuenta no encontrada'
                ]);
                break;
            }
            
            echo json_encode([
                'success' => true,
                'data' => $account
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error obteniendo información de la cuenta: ' . $e->getMessage()
            ]);
        }
        break;
        
    default:
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Endpoint no encontrado'
        ]);
        break;
}
?>
