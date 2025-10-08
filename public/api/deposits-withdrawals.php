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

// Manejar autenticación
try {
    $currentUser = $rbacMiddleware->authenticate($request);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit();
}

// Datos de demo para transacciones
$demoTransactions = [
    [
        'id' => 1,
        'account_id' => 1,
        'account_number' => 'MT5-200001',
        'client_name' => 'María González',
        'type' => 'deposit',
        'method' => 'bank_transfer',
        'amount' => 1000.00,
        'currency' => 'USD',
        'status' => 'pending',
        'reference' => 'DEP-2024-001',
        'notes' => 'Depósito inicial para cuenta real',
        'created_at' => '2024-01-20 14:30:00',
        'updated_at' => '2024-01-20 14:30:00',
        'approved_by' => null,
        'approved_at' => null,
        'processed_at' => null,
        'receipt_url' => null,
        'bank_details' => [
            'bank_name' => 'Banco Santander',
            'account_holder' => 'María González',
            'account_number' => '****1234',
            'swift_code' => 'BSCHESMM'
        ]
    ],
    [
        'id' => 2,
        'account_id' => 2,
        'account_number' => 'MT5-300001',
        'client_name' => 'Juan Pérez',
        'type' => 'withdrawal',
        'method' => 'bank_transfer',
        'amount' => 500.00,
        'currency' => 'USD',
        'status' => 'approved',
        'reference' => 'WTH-2024-001',
        'notes' => 'Retiro de ganancias',
        'created_at' => '2024-01-20 10:15:00',
        'updated_at' => '2024-01-20 11:30:00',
        'approved_by' => 'Admin User',
        'approved_at' => '2024-01-20 11:30:00',
        'processed_at' => null,
        'receipt_url' => null,
        'bank_details' => [
            'bank_name' => 'BBVA',
            'account_holder' => 'Juan Pérez',
            'account_number' => '****5678',
            'swift_code' => 'BBVAESMM'
        ]
    ],
    [
        'id' => 3,
        'account_id' => 3,
        'account_number' => 'MT5-100001',
        'client_name' => 'Carlos Rodríguez',
        'type' => 'deposit',
        'method' => 'credit_card',
        'amount' => 250.00,
        'currency' => 'USD',
        'status' => 'completed',
        'reference' => 'DEP-2024-002',
        'notes' => 'Depósito con tarjeta de crédito',
        'created_at' => '2024-01-19 16:45:00',
        'updated_at' => '2024-01-19 17:00:00',
        'approved_by' => 'Admin User',
        'approved_at' => '2024-01-19 16:50:00',
        'processed_at' => '2024-01-19 17:00:00',
        'receipt_url' => '/receipts/DEP-2024-002.pdf',
        'card_details' => [
            'card_type' => 'Visa',
            'last_four' => '1234',
            'expiry' => '12/25'
        ]
    ],
    [
        'id' => 4,
        'account_id' => 4,
        'account_number' => 'MT5-400001',
        'client_name' => 'Ana López',
        'type' => 'withdrawal',
        'method' => 'crypto',
        'amount' => 100.00,
        'currency' => 'USD',
        'status' => 'rejected',
        'reference' => 'WTH-2024-002',
        'notes' => 'Retiro a wallet Bitcoin',
        'rejection_reason' => 'Documentación insuficiente',
        'created_at' => '2024-01-19 12:20:00',
        'updated_at' => '2024-01-19 14:15:00',
        'approved_by' => null,
        'approved_at' => null,
        'processed_at' => null,
        'receipt_url' => null,
        'crypto_details' => [
            'currency' => 'BTC',
            'wallet_address' => '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa',
            'network' => 'Bitcoin'
        ]
    ],
    [
        'id' => 5,
        'account_id' => 1,
        'account_number' => 'MT5-200001',
        'client_name' => 'María González',
        'type' => 'deposit',
        'method' => 'e_wallet',
        'amount' => 750.00,
        'currency' => 'USD',
        'status' => 'processing',
        'reference' => 'DEP-2024-003',
        'notes' => 'Depósito vía PayPal',
        'created_at' => '2024-01-20 09:30:00',
        'updated_at' => '2024-01-20 10:00:00',
        'approved_by' => 'Admin User',
        'approved_at' => '2024-01-20 10:00:00',
        'processed_at' => null,
        'receipt_url' => null,
        'ewallet_details' => [
            'provider' => 'PayPal',
            'email' => 'm.gonzalez@email.com',
            'transaction_id' => 'PP-2024-001'
        ]
    ]
];

// Métodos de pago disponibles
$paymentMethods = [
    'bank_transfer' => [
        'name' => 'Transferencia Bancaria',
        'min_amount' => 50.00,
        'max_amount' => 50000.00,
        'processing_time' => '1-3 días hábiles',
        'fees' => [
            'deposit' => 0.00,
            'withdrawal' => 25.00
        ],
        'currencies' => ['USD', 'EUR', 'GBP']
    ],
    'credit_card' => [
        'name' => 'Tarjeta de Crédito',
        'min_amount' => 10.00,
        'max_amount' => 5000.00,
        'processing_time' => 'Inmediato',
        'fees' => [
            'deposit' => 2.5, // Porcentaje
            'withdrawal' => 0.00 // No disponible para retiros
        ],
        'currencies' => ['USD', 'EUR']
    ],
    'e_wallet' => [
        'name' => 'Monedero Electrónico',
        'min_amount' => 20.00,
        'max_amount' => 10000.00,
        'processing_time' => '1-2 horas',
        'fees' => [
            'deposit' => 1.5, // Porcentaje
            'withdrawal' => 2.0 // Porcentaje
        ],
        'currencies' => ['USD', 'EUR']
    ],
    'crypto' => [
        'name' => 'Criptomonedas',
        'min_amount' => 25.00,
        'max_amount' => 25000.00,
        'processing_time' => '30 minutos - 2 horas',
        'fees' => [
            'deposit' => 0.00,
            'withdrawal' => 0.5 // Porcentaje
        ],
        'currencies' => ['USD', 'EUR', 'BTC', 'ETH']
    ]
];

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Verificar permiso para ver depósitos y retiros
        if (!$currentUser->hasPermission('deposits_withdrawals.view')) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permisos para ver depósitos y retiros'
            ]);
            exit();
        }
        
        if (isset($_GET['methods'])) {
            // Obtener métodos de pago disponibles
            echo json_encode([
                'success' => true,
                'message' => 'Métodos de pago obtenidos correctamente',
                'data' => $paymentMethods
            ]);
        } elseif (isset($_GET['id'])) {
            // Obtener transacción específica
            $transactionId = (int)$_GET['id'];
            $transaction = array_filter($demoTransactions, function($t) use ($transactionId) {
                return $t['id'] === $transactionId;
            });
            
            if ($transaction) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Transacción encontrada',
                    'data' => array_values($transaction)[0]
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Transacción no encontrada'
                ]);
            }
        } else {
            // Obtener lista de transacciones con filtros
            $search = $_GET['search'] ?? '';
            $type = $_GET['type'] ?? '';
            $status = $_GET['status'] ?? '';
            $method_filter = $_GET['method'] ?? '';
            $account_id = $_GET['account_id'] ?? '';
            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 20);
            
            $filteredTransactions = $demoTransactions;
            
            // Aplicar filtros
            if ($search) {
                $filteredTransactions = array_filter($filteredTransactions, function($transaction) use ($search) {
                    return stripos($transaction['reference'], $search) !== false ||
                           stripos($transaction['client_name'], $search) !== false ||
                           stripos($transaction['account_number'], $search) !== false;
                });
            }
            
            if ($type) {
                $filteredTransactions = array_filter($filteredTransactions, function($transaction) use ($type) {
                    return $transaction['type'] === $type;
                });
            }
            
            if ($status) {
                $filteredTransactions = array_filter($filteredTransactions, function($transaction) use ($status) {
                    return $transaction['status'] === $status;
                });
            }
            
            if ($method_filter) {
                $filteredTransactions = array_filter($filteredTransactions, function($transaction) use ($method_filter) {
                    return $transaction['method'] === $method_filter;
                });
            }
            
            if ($account_id) {
                $filteredTransactions = array_filter($filteredTransactions, function($transaction) use ($account_id) {
                    return $transaction['account_id'] == $account_id;
                });
            }
            
            // Paginación
            $total = count($filteredTransactions);
            $totalPages = ceil($total / $limit);
            $offset = ($page - 1) * $limit;
            $paginatedTransactions = array_slice($filteredTransactions, $offset, $limit);
            
            echo json_encode([
                'success' => true,
                'message' => 'Transacciones obtenidas correctamente',
                'data' => array_values($paginatedTransactions),
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => $totalPages
                ]
            ]);
        }
        break;
        
    case 'POST':
        // Verificar permiso para crear depósitos y retiros
        if (!$currentUser->hasPermission('deposits_withdrawals.create')) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permisos para crear depósitos y retiros'
            ]);
            exit();
        }
        
        // Crear nueva transacción
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
        $required = ['account_id', 'type', 'method', 'amount'];
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
        
        // Validar tipo de transacción
        if (!in_array($input['type'], ['deposit', 'withdrawal'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Tipo de transacción inválido'
            ]);
            exit;
        }
        
        // Validar método de pago
        if (!isset($paymentMethods[$input['method']])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Método de pago inválido'
            ]);
            exit;
        }
        
        // Validar montos
        $methodConfig = $paymentMethods[$input['method']];
        if ($input['amount'] < $methodConfig['min_amount'] || $input['amount'] > $methodConfig['max_amount']) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => "El monto debe estar entre {$methodConfig['min_amount']} y {$methodConfig['max_amount']}"
            ]);
            exit;
        }
        
        // Generar referencia única
        $reference = strtoupper($input['type'] === 'deposit' ? 'DEP' : 'WTH') . '-' . date('Y') . '-' . str_pad(count($demoTransactions) + 1, 3, '0', STR_PAD_LEFT);
        
        $newTransaction = [
            'id' => count($demoTransactions) + 1,
            'account_id' => $input['account_id'],
            'account_number' => $input['account_number'] ?? 'MT5-000000',
            'client_name' => $input['client_name'] ?? 'Cliente',
            'type' => $input['type'],
            'method' => $input['method'],
            'amount' => $input['amount'],
            'currency' => $input['currency'] ?? 'USD',
            'status' => 'pending',
            'reference' => $reference,
            'notes' => $input['notes'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'approved_by' => null,
            'approved_at' => null,
            'processed_at' => null,
            'receipt_url' => null
        ];
        
        // Agregar detalles específicos del método
        if ($input['method'] === 'bank_transfer' && isset($input['bank_details'])) {
            $newTransaction['bank_details'] = $input['bank_details'];
        } elseif ($input['method'] === 'credit_card' && isset($input['card_details'])) {
            $newTransaction['card_details'] = $input['card_details'];
        } elseif ($input['method'] === 'crypto' && isset($input['crypto_details'])) {
            $newTransaction['crypto_details'] = $input['crypto_details'];
        } elseif ($input['method'] === 'e_wallet' && isset($input['ewallet_details'])) {
            $newTransaction['ewallet_details'] = $input['ewallet_details'];
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Transacción creada exitosamente',
            'data' => $newTransaction
        ]);
        break;
        
    case 'PUT':
        // Verificar permiso para editar depósitos y retiros
        if (!$currentUser->hasPermission('deposits_withdrawals.edit')) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permisos para editar depósitos y retiros'
            ]);
            exit();
        }
        
        // Actualizar transacción (aprobar, rechazar, etc.)
        $input = json_decode(file_get_contents('php://input'), true);
        $transactionId = (int)($_GET['id'] ?? 0);
        
        if (!$transactionId || !$input) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID de transacción y datos son requeridos'
            ]);
            break;
        }
        
        $transactionIndex = array_search($transactionId, array_column($demoTransactions, 'id'));
        if ($transactionIndex === false) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Transacción no encontrada'
            ]);
            break;
        }
        
        $transaction = $demoTransactions[$transactionIndex];
        
        // Manejar acciones específicas
        if (isset($input['action'])) {
            switch ($input['action']) {
                case 'approve':
                    if ($transaction['status'] !== 'pending') {
                        http_response_code(400);
                        echo json_encode([
                            'success' => false,
                            'message' => 'Solo se pueden aprobar transacciones pendientes'
                        ]);
                        exit;
                    }
                    
                    $transaction['status'] = 'approved';
                    $transaction['approved_by'] = $input['approved_by'] ?? 'Admin User';
                    $transaction['approved_at'] = date('Y-m-d H:i:s');
                    $transaction['updated_at'] = date('Y-m-d H:i:s');
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Transacción aprobada exitosamente',
                        'data' => $transaction
                    ]);
                    break;
                    
                case 'reject':
                    if ($transaction['status'] !== 'pending') {
                        http_response_code(400);
                        echo json_encode([
                            'success' => false,
                            'message' => 'Solo se pueden rechazar transacciones pendientes'
                        ]);
                        exit;
                    }
                    
                    $transaction['status'] = 'rejected';
                    $transaction['rejection_reason'] = $input['reason'] ?? 'Sin razón especificada';
                    $transaction['updated_at'] = date('Y-m-d H:i:s');
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Transacción rechazada exitosamente',
                        'data' => $transaction
                    ]);
                    break;
                    
                case 'process':
                    if ($transaction['status'] !== 'approved') {
                        http_response_code(400);
                        echo json_encode([
                            'success' => false,
                            'message' => 'Solo se pueden procesar transacciones aprobadas'
                        ]);
                        exit;
                    }
                    
                    $transaction['status'] = 'processing';
                    $transaction['updated_at'] = date('Y-m-d H:i:s');
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Transacción en procesamiento',
                        'data' => $transaction
                    ]);
                    break;
                    
                case 'complete':
                    if (!in_array($transaction['status'], ['approved', 'processing'])) {
                        http_response_code(400);
                        echo json_encode([
                            'success' => false,
                            'message' => 'Solo se pueden completar transacciones aprobadas o en procesamiento'
                        ]);
                        exit;
                    }
                    
                    $transaction['status'] = 'completed';
                    $transaction['processed_at'] = date('Y-m-d H:i:s');
                    $transaction['updated_at'] = date('Y-m-d H:i:s');
                    
                    if (isset($input['receipt_url'])) {
                        $transaction['receipt_url'] = $input['receipt_url'];
                    }
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Transacción completada exitosamente',
                        'data' => $transaction
                    ]);
                    break;
                    
                default:
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Acción no válida'
                    ]);
                    break;
            }
        } else {
            // Actualización general
            $allowedFields = ['notes', 'amount'];
            
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $transaction[$field] = $input[$field];
                }
            }
            
            $transaction['updated_at'] = date('Y-m-d H:i:s');
            
            echo json_encode([
                'success' => true,
                'message' => 'Transacción actualizada exitosamente',
                'data' => $transaction
            ]);
        }
        break;
        
    case 'DELETE':
        // Verificar permiso para eliminar depósitos y retiros
        if (!$currentUser->hasPermission('deposits_withdrawals.delete')) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permisos para eliminar depósitos y retiros'
            ]);
            exit();
        }
        
        // Eliminar transacción (solo si está pendiente)
        $transactionId = (int)($_GET['id'] ?? 0);
        
        if (!$transactionId) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID de transacción requerido'
            ]);
            break;
        }
        
        $transactionIndex = array_search($transactionId, array_column($demoTransactions, 'id'));
        if ($transactionIndex === false) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Transacción no encontrada'
            ]);
            break;
        }
        
        $transaction = $demoTransactions[$transactionIndex];
        
        if ($transaction['status'] !== 'pending') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Solo se pueden eliminar transacciones pendientes'
            ]);
            break;
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Transacción eliminada exitosamente'
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
