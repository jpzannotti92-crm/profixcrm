<?php
/**
 * API para gestionar las finanzas de un lead
 * Obtiene depósitos, retiros y estadísticas financieras
 */

// Configurar bypass de platform check para desarrollo
require_once __DIR__ . '/../../platform_check_bypass.php';

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Database/Connection.php';

// Headers CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verificar método HTTP
$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = iaTradeCRM\Database\Connection::getInstance()->getConnection();
    
    switch ($method) {
        case 'GET':
            handleGetFinances($db);
            break;
            
        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Método no permitido'
            ]);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor: ' . $e->getMessage()
    ]);
}

/**
 * Obtener información financiera de un lead
 */
function handleGetFinances($db) {
    $leadId = $_GET['lead_id'] ?? null;
    
    if (!$leadId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'ID del lead es requerido'
        ]);
        return;
    }
    
    try {
        // Obtener todas las cuentas de trading del lead
        $accountsQuery = "
            SELECT id, account_number, account_type, platform, currency, balance, equity
            FROM trading_accounts 
            WHERE lead_id = ? AND status = 'active'
            ORDER BY created_at DESC
        ";
        $stmt = $db->prepare($accountsQuery);
        $stmt->execute([$leadId]);
        $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($accounts)) {
            echo json_encode([
                'success' => true,
                'data' => [
                    'accounts' => [],
                    'transactions' => [],
                    'summary' => [
                        'total_deposits' => 0,
                        'total_withdrawals' => 0,
                        'net_balance' => 0,
                        'transaction_count' => 0
                    ]
                ]
            ]);
            return;
        }
        
        $accountIds = array_column($accounts, 'id');
        $accountIdsPlaceholder = str_repeat('?,', count($accountIds) - 1) . '?';
        
        // Obtener transacciones de todas las cuentas del lead
        $transactionsQuery = "
            SELECT 
                t.*,
                ta.account_number,
                ta.account_type,
                ta.platform,
                CONCAT(u.first_name, ' ', u.last_name) as processed_by_name,
                CONCAT(pb.first_name, ' ', pb.last_name) as agent_name
            FROM transactions t
            LEFT JOIN trading_accounts ta ON t.account_id = ta.id
            LEFT JOIN users u ON t.user_id = u.id
            LEFT JOIN users pb ON t.processed_by = pb.id
            WHERE t.account_id IN ($accountIdsPlaceholder)
            ORDER BY t.created_at DESC
            LIMIT 50
        ";
        $stmt = $db->prepare($transactionsQuery);
        $stmt->execute($accountIds);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calcular resumen financiero
        $summaryQuery = "
            SELECT 
                SUM(CASE WHEN type = 'deposit' AND status = 'completed' THEN amount ELSE 0 END) as total_deposits,
                SUM(CASE WHEN type = 'withdrawal' AND status = 'completed' THEN amount ELSE 0 END) as total_withdrawals,
                COUNT(*) as transaction_count
            FROM transactions 
            WHERE account_id IN ($accountIdsPlaceholder)
        ";
        $stmt = $db->prepare($summaryQuery);
        $stmt->execute($accountIds);
        $summaryResult = $stmt->fetch(PDO::FETCH_ASSOC);
        $summary = $summaryResult ?: [
            'total_deposits' => 0,
            'total_withdrawals' => 0,
            'transaction_count' => 0
        ];
        
        $summary['net_balance'] = ($summary['total_deposits'] ?? 0) - ($summary['total_withdrawals'] ?? 0);
        
        // Formatear transacciones para la respuesta
        $formattedTransactions = array_map(function($transaction) {
            return [
                'id' => $transaction['id'],
                'account_number' => $transaction['account_number'] ?? '',
                'account_type' => $transaction['account_type'] ?? '',
                'platform' => $transaction['platform'] ?? '',
                'type' => $transaction['type'],
                'method' => $transaction['method'] ?? '',
                'amount' => floatval($transaction['amount'] ?? 0),
                'currency' => $transaction['currency'] ?? 'USD',
                'status' => $transaction['status'],
                'reference_number' => $transaction['reference_number'] ?? '',
                'notes' => $transaction['notes'] ?? '',
                'agent_name' => $transaction['agent_name'] ?: $transaction['processed_by_name'] ?: 'Sistema',
                'created_at' => $transaction['created_at'],
                'processed_at' => $transaction['processed_at'] ?? null,
                'completed_at' => $transaction['completed_at'] ?? null
            ];
        }, $transactions);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'accounts' => $accounts,
                'transactions' => $formattedTransactions,
                'summary' => [
                    'total_deposits' => floatval($summary['total_deposits'] ?? 0),
                    'total_withdrawals' => floatval($summary['total_withdrawals'] ?? 0),
                    'net_balance' => floatval($summary['net_balance'] ?? 0),
                    'transaction_count' => intval($summary['transaction_count'] ?? 0)
                ]
            ]
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error obteniendo datos financieros: ' . $e->getMessage()
        ]);
    }
}
?>