<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Database/Connection.php';
require_once __DIR__ . '/../../src/Models/BaseModel.php';
require_once __DIR__ . '/../../src/Models/User.php';
require_once __DIR__ . '/../../src/Middleware/RBACMiddleware.php';
require_once __DIR__ . '/../../src/Http/Request.php';

use iaTradeCRM\Database\Connection;
use iaTradeCRM\Models\User;
use iaTradeCRM\Middleware\RBACMiddleware;
use iaTradeCRM\Http\Request;

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
    $currentUser = $rbacMiddleware->handle($request);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit();
}

// Datos de demo para cuentas de trading
$demoTradingAccounts = [
    [
        'id' => 1,
        'account_number' => 'MT5-100001',
        'lead_id' => 1,
        'client_name' => 'Carlos Rodríguez',
        'client_email' => 'carlos.rodriguez@email.com',
        'account_type' => 'demo',
        'platform' => 'MT5',
        'balance' => 10000.00,
        'equity' => 10000.00,
        'margin' => 0.00,
        'free_margin' => 10000.00,
        'margin_level' => 0.00,
        'leverage' => '1:100',
        'currency' => 'USD',
        'status' => 'active',
        'server' => 'Demo-Server-01',
        'password' => 'Demo123!',
        'investor_password' => 'Inv123!',
        'created_at' => '2024-01-20 10:30:00',
        'last_login' => '2024-01-20 15:45:00',
        'trades_count' => 0,
        'profit_loss' => 0.00,
        'commission' => 0.00,
        'swap' => 0.00
    ],
    [
        'id' => 2,
        'account_number' => 'MT5-200001',
        'lead_id' => 2,
        'client_name' => 'María González',
        'client_email' => 'maria.gonzalez@email.com',
        'account_type' => 'real',
        'platform' => 'MT5',
        'balance' => 5000.00,
        'equity' => 5247.50,
        'margin' => 1250.00,
        'free_margin' => 3997.50,
        'margin_level' => 419.80,
        'leverage' => '1:200',
        'currency' => 'USD',
        'status' => 'active',
        'server' => 'Real-Server-01',
        'password' => 'Real456!',
        'investor_password' => 'Inv456!',
        'created_at' => '2024-01-18 14:20:00',
        'last_login' => '2024-01-20 16:30:00',
        'trades_count' => 5,
        'profit_loss' => 247.50,
        'commission' => 12.50,
        'swap' => -5.25
    ],
    [
        'id' => 3,
        'account_number' => 'MT5-637001',
        'lead_id' => 637,
        'client_name' => 'Alvaro Jose Arias Figueroa',
        'client_email' => 'alvaroarias@gmail.com',
        'account_type' => 'demo',
        'platform' => 'MT5',
        'balance' => 50000.00,
        'equity' => 52150.75,
        'margin' => 8500.00,
        'free_margin' => 43650.75,
        'margin_level' => 613.54,
        'leverage' => '1:500',
        'currency' => 'USD',
        'status' => 'active',
        'server' => 'Demo-Server-02',
        'password' => 'Demo789!',
        'investor_password' => 'Inv789!',
        'created_at' => '2025-09-17 08:15:00',
        'last_login' => '2025-09-17 09:45:00',
        'trades_count' => 12,
        'profit_loss' => 2150.75,
        'commission' => 45.80,
        'swap' => -12.35
    ],
    [
        'id' => 4,
        'account_number' => 'MT5-300001',
        'lead_id' => 3,
        'client_name' => 'Juan Pérez',
        'client_email' => 'juan.perez@email.com',
        'account_type' => 'vip',
        'platform' => 'MT5',
        'balance' => 50000.00,
        'equity' => 52150.75,
        'margin' => 8500.00,
        'free_margin' => 43650.75,
        'margin_level' => 613.54,
        'leverage' => '1:500',
        'currency' => 'USD',
        'status' => 'active',
        'server' => 'VIP-Server-01',
        'password' => '********',
        'investor_password' => '********',
        'created_at' => '2024-01-18 16:20:00',
        'last_login' => '2024-01-20 16:45:00',
        'trades_count' => 23,
        'profit_loss' => 2150.75,
        'commission' => -45.80,
        'swap' => -15.20
    ],
    [
        'id' => 4,
        'account_number' => 'MT5-400001',
        'lead_id' => 4,
        'client_name' => 'Ana López',
        'client_email' => 'ana.lopez@email.com',
        'account_type' => 'micro',
        'platform' => 'MT5',
        'balance' => 500.00,
        'equity' => 485.30,
        'margin' => 125.00,
        'free_margin' => 360.30,
        'margin_level' => 388.24,
        'leverage' => '1:50',
        'currency' => 'USD',
        'status' => 'active',
        'server' => 'Micro-Server-01',
        'password' => '********',
        'investor_password' => '********',
        'created_at' => '2024-01-17 11:45:00',
        'last_login' => '2024-01-20 12:30:00',
        'trades_count' => 8,
        'profit_loss' => -14.70,
        'commission' => -3.20,
        'swap' => -1.80
    ],
    [
        'id' => 5,
        'account_number' => 'MT5-500001',
        'lead_id' => 5,
        'client_name' => 'Pedro Martínez',
        'client_email' => 'pedro.martinez@email.com',
        'account_type' => 'standard',
        'platform' => 'MT5',
        'balance' => 2500.00,
        'equity' => 2500.00,
        'margin' => 0.00,
        'free_margin' => 2500.00,
        'margin_level' => 0.00,
        'leverage' => '1:100',
        'currency' => 'USD',
        'status' => 'suspended',
        'server' => 'Standard-Server-01',
        'password' => '********',
        'investor_password' => '********',
        'created_at' => '2024-01-16 08:30:00',
        'last_login' => '2024-01-18 14:20:00',
        'trades_count' => 0,
        'profit_loss' => 0.00,
        'commission' => 0.00,
        'swap' => 0.00
    ],
    [
        'id' => 6,
        'account_number' => 'MT5-100002',
        'lead_id' => 6,
        'client_name' => 'Laura Sánchez',
        'client_email' => 'laura.sanchez@email.com',
        'account_type' => 'demo',
        'platform' => 'MT5',
        'balance' => 10000.00,
        'equity' => 9875.40,
        'margin' => 500.00,
        'free_margin' => 9375.40,
        'margin_level' => 1975.08,
        'leverage' => '1:100',
        'currency' => 'USD',
        'status' => 'active',
        'server' => 'Demo-Server-02',
        'password' => 'Demo456!',
        'investor_password' => 'Inv456!',
        'created_at' => '2024-01-20 16:00:00',
        'last_login' => '2024-01-20 16:30:00',
        'trades_count' => 3,
        'profit_loss' => -124.60,
        'commission' => -2.40,
        'swap' => 0.00
    ]
];

// Configuraciones de tipos de cuenta
$accountTypes = [
    'demo' => [
        'name' => 'Demo',
        'description' => 'Cuenta de demostración con dinero virtual',
        'initial_balance' => 10000.00,
        'max_leverage' => '1:500',
        'min_deposit' => 0.00,
        'spreads_markup' => 0.0,
        'commission_rate' => 0.0,
        'swap_enabled' => false
    ],
    'micro' => [
        'name' => 'Micro',
        'description' => 'Cuenta micro para principiantes',
        'initial_balance' => 0.00,
        'max_leverage' => '1:100',
        'min_deposit' => 10.00,
        'spreads_markup' => 0.5,
        'commission_rate' => 0.0,
        'swap_enabled' => true
    ],
    'standard' => [
        'name' => 'Standard',
        'description' => 'Cuenta estándar para traders regulares',
        'initial_balance' => 0.00,
        'max_leverage' => '1:200',
        'min_deposit' => 250.00,
        'spreads_markup' => 0.3,
        'commission_rate' => 0.0,
        'swap_enabled' => true
    ],
    'real' => [
        'name' => 'Real',
        'description' => 'Cuenta real con condiciones mejoradas',
        'initial_balance' => 0.00,
        'max_leverage' => '1:300',
        'min_deposit' => 1000.00,
        'spreads_markup' => 0.2,
        'commission_rate' => 0.0,
        'swap_enabled' => true
    ],
    'vip' => [
        'name' => 'VIP',
        'description' => 'Cuenta VIP con las mejores condiciones',
        'initial_balance' => 0.00,
        'max_leverage' => '1:500',
        'min_deposit' => 10000.00,
        'spreads_markup' => 0.0,
        'commission_rate' => 0.0,
        'swap_enabled' => true
    ]
];

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Verificar permiso para ver cuentas de trading
        if (!$currentUser->hasPermission('trading_accounts.view')) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permisos para ver cuentas de trading'
            ]);
            exit();
        }
        
        if (isset($_GET['types'])) {
            // Obtener tipos de cuenta disponibles
            echo json_encode([
                'success' => true,
                'message' => 'Tipos de cuenta obtenidos correctamente',
                'data' => $accountTypes
            ]);
        } elseif (isset($_GET['id'])) {
            // Obtener cuenta específica
            $accountId = (int)$_GET['id'];
            $account = array_filter($demoTradingAccounts, function($a) use ($accountId) {
                return $a['id'] === $accountId;
            });
            
            if ($account) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Cuenta encontrada',
                    'data' => array_values($account)[0]
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Cuenta no encontrada'
                ]);
            }
        } else {
            // Obtener lista de cuentas con filtros desde la base de datos
            $search = $_GET['search'] ?? '';
            $account_type = $_GET['account_type'] ?? '';
            $status = $_GET['status'] ?? '';
            $lead_id = $_GET['lead_id'] ?? '';
            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 20);
            
            try {
                $db = Connection::getInstance();
                
                // Construir consulta base con JOIN para obtener información del lead
                $baseQuery = "
                    SELECT 
                        ta.*,
                        CONCAT(l.first_name, ' ', l.last_name) as client_name,
                        0 as trades_count,
                        0.00 as profit_loss,
                        0.00 as commission,
                        0.00 as swap,
                        ta.created_at as last_login
                    FROM trading_accounts ta
                    LEFT JOIN leads l ON ta.lead_id = l.id
                    WHERE 1=1
                ";
                
                $params = [];
                $conditions = [];
                
                // Aplicar filtros
                if ($search) {
                    $conditions[] = "(ta.account_number LIKE ? OR CONCAT(l.first_name, ' ', l.last_name) LIKE ? OR l.email LIKE ?)";
                    $searchParam = "%{$search}%";
                    $params[] = $searchParam;
                    $params[] = $searchParam;
                    $params[] = $searchParam;
                }
                
                if ($account_type) {
                    $conditions[] = "ta.account_type = ?";
                    $params[] = $account_type;
                }
                
                if ($status) {
                    $conditions[] = "ta.status = ?";
                    $params[] = $status;
                }
                
                if ($lead_id) {
                    $conditions[] = "ta.lead_id = ?";
                    $params[] = $lead_id;
                }
                
                // Agregar condiciones a la consulta
                if (!empty($conditions)) {
                    $baseQuery .= " AND " . implode(" AND ", $conditions);
                }
                
                // Contar total de registros
                $countQuery = "SELECT COUNT(*) as total FROM (" . $baseQuery . ") as counted";
                $stmt = $db->query($countQuery, $params);
                $totalResult = $stmt->fetch();
                $total = $totalResult['total'];
                
                // Agregar ordenamiento y paginación
                $baseQuery .= " ORDER BY ta.created_at DESC";
                $offset = ($page - 1) * $limit;
                $baseQuery .= " LIMIT {$limit} OFFSET {$offset}";
                
                // Ejecutar consulta principal
                $stmt = $db->query($baseQuery, $params);
                $accounts = $stmt->fetchAll();
                
                // Calcular páginas totales
                $totalPages = ceil($total / $limit);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Cuentas obtenidas correctamente',
                    'data' => $accounts,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $total,
                        'pages' => $totalPages
                    ]
                ]);
                
            } catch (Exception $e) {
                error_log("Error obteniendo cuentas de trading: " . $e->getMessage());
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Error interno del servidor',
                    'error' => $e->getMessage()
                ]);
            }
        }
        break;
        
    case 'POST':
        // Verificar permiso para crear cuentas de trading
        if (!$currentUser->hasPermission('trading_accounts.create')) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permisos para crear cuentas de trading'
            ]);
            exit();
        }
        
        // Crear nueva cuenta de trading
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Log de datos recibidos para debugging
        error_log("Datos de cuenta de trading recibidos: " . json_encode($input));
        
        if (!$input) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Datos inválidos'
            ]);
            break;
        }
        
        // Función para buscar lead por email
        function findLeadByEmail($email) {
            try {
                $db = Connection::getInstance();
                $stmt = $db->query("SELECT * FROM leads WHERE email = ? LIMIT 1", [$email]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                error_log("Búsqueda de lead por email '$email': " . ($result ? "Encontrado ID: " . $result['id'] : "No encontrado"));
                return $result;
            } catch (Exception $e) {
                error_log("Error buscando lead por email: " . $e->getMessage());
                return null;
            }
        }
        
        // Validaciones básicas - ahora lead_id es opcional si se proporciona client_email
        $required = ['account_type', 'leverage'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => "El campo {$field} es requerido"
                ]);
                break 2;
            }
        }
        
        // Vinculación automática por email si no se proporciona lead_id
        $leadId = $input['lead_id'] ?? null;
        $autoLinked = false;
        $lead = null;
        
        if (!$leadId && !empty($input['client_email'])) {
            error_log("Intentando auto-vinculación para email: " . $input['client_email']);
            $lead = findLeadByEmail($input['client_email']);
            if ($lead) {
                $leadId = $lead['id'];
                $autoLinked = true;
                error_log("Auto-vinculación exitosa: Cuenta vinculada automáticamente al lead ID: " . $leadId);
            } else {
                error_log("Auto-vinculación fallida: No se encontró lead con email: " . $input['client_email']);
            }
        }
        
        // Validar tipo de cuenta
        if (!isset($accountTypes[$input['account_type']])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Tipo de cuenta inválido'
            ]);
            exit;
        }
        
        $accountConfig = $accountTypes[$input['account_type']];
        
        try {
            // Obtener conexión a la base de datos
            $db = Connection::getInstance();
            
            // Generar número de cuenta único
            $accountNumber = strtoupper($input['platform'] ?? 'MT5') . '-' . 
                            ($input['account_type'] === 'demo' ? '1' : '2') . 
                            str_pad(rand(10000, 99999), 5, '0', STR_PAD_LEFT);
            
            // Preparar datos para insertar en la base de datos
            $accountData = [
                'account_number' => $accountNumber,
                'lead_id' => $leadId,
                'account_type' => $input['account_type'],
                'platform' => $input['platform'] ?? 'MT5',
                'balance' => $accountConfig['initial_balance'],
                'equity' => $accountConfig['initial_balance'],
                'margin' => 0.00,
                'free_margin' => $accountConfig['initial_balance'],
                'leverage' => $input['leverage'],
                'currency' => $input['currency'] ?? 'USD',
                'status' => 'active',
                'server' => ucfirst($input['account_type']) . '-Server-01',
                'password' => !empty($input['password']) ? $input['password'] : ($input['account_type'] === 'demo' ? 'Demo' . rand(100, 999) . '!' : '********'),
                'investor_password' => $input['account_type'] === 'demo' ? 'Inv' . rand(100, 999) . '!' : '********',
                'client_email' => $input['client_email'] ?? null
            ];
            
            // Insertar en la base de datos
            $newAccountId = $db->insert('trading_accounts', $accountData);
            
            // Obtener la cuenta recién creada
            $stmt = $db->query(
                "SELECT * FROM trading_accounts WHERE id = ?", 
                [$newAccountId]
            );
            $newAccount = $stmt->fetch();
            
            $response = [
                'success' => true,
                'message' => 'Cuenta de trading creada exitosamente',
                'data' => $newAccount
            ];
            
        } catch (Exception $e) {
            error_log("Error creando cuenta de trading: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error interno del servidor al crear la cuenta',
                'error' => $e->getMessage()
            ]);
            break;
        }
        
        // Agregar información de vinculación automática si ocurrió
        if ($autoLinked) {
            $response['auto_linked'] = true;
            $response['lead_info'] = [
                'id' => $leadId,
                'name' => $lead['first_name'] . ' ' . $lead['last_name'],
                'email' => $lead['email']
            ];
            $response['message'] .= ' y vinculada automáticamente al lead';
        }
        
        echo json_encode($response);
        break;
        
    case 'PUT':
        // Verificar permiso para editar cuentas de trading
        if (!$currentUser->hasPermission('trading_accounts.edit')) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permisos para editar cuentas de trading'
            ]);
            exit();
        }
        
        // Actualizar cuenta de trading
        $input = json_decode(file_get_contents('php://input'), true);
        $accountId = (int)($_GET['id'] ?? 0);
        
        if (!$accountId || !$input) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID de cuenta y datos son requeridos'
            ]);
            break;
        }
        
        $accountIndex = array_search($accountId, array_column($demoTradingAccounts, 'id'));
        if ($accountIndex === false) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Cuenta no encontrada'
            ]);
            break;
        }
        
        // Actualizar campos permitidos
        $updatedAccount = $demoTradingAccounts[$accountIndex];
        $allowedFields = ['leverage', 'status', 'balance', 'equity', 'margin', 'free_margin', 'margin_level'];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updatedAccount[$field] = $input[$field];
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Cuenta actualizada exitosamente',
            'data' => $updatedAccount
        ]);
        break;
        
    case 'DELETE':
        // Verificar permiso para eliminar cuentas de trading
        if (!$currentUser->hasPermission('trading_accounts.delete')) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permisos para eliminar cuentas de trading'
            ]);
            exit();
        }
        
        // Eliminar cuenta de trading
        $accountId = (int)($_GET['id'] ?? 0);
        
        if (!$accountId) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID de cuenta requerido'
            ]);
            break;
        }
        
        $accountIndex = array_search($accountId, array_column($demoTradingAccounts, 'id'));
        if ($accountIndex === false) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Cuenta no encontrada'
            ]);
            break;
        }
        
        // Verificar si la cuenta tiene posiciones abiertas
        if ($demoTradingAccounts[$accountIndex]['margin'] > 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'No se puede eliminar una cuenta con posiciones abiertas'
            ]);
            break;
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Cuenta eliminada exitosamente'
        ]);
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
