<?php
// Dashboard con manejo robusto de errores para producción
error_reporting(E_ALL);
ini_set('display_errors', 0); // Desactivar en producción
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Función para conectar a la base de datos sin usar Connection class
function getDatabaseConnection() {
    // Intentar múltiples métodos de configuración
    $config = null;
    
    // Método 1: Variables de entorno
    if (getenv('DB_HOST')) {
        $config = [
            'host' => getenv('DB_HOST'),
            'port' => getenv('DB_PORT') ?: '3306',
            'database' => getenv('DB_DATABASE') ?: getenv('DB_NAME'),
            'username' => getenv('DB_USERNAME') ?: getenv('DB_USER'),
            'password' => getenv('DB_PASSWORD')
        ];
    }
    
    // Método 2: Archivo config.php
    if (!$config) {
        $configFile = __DIR__ . '/../../config/config.php';
        if (file_exists($configFile)) {
            $configData = include $configFile;
            if (isset($configData['database'])) {
                $config = [
                    'host' => $configData['database']['host'],
                    'port' => $configData['database']['port'] ?? '3306',
                    'database' => $configData['database']['name'],
                    'username' => $configData['database']['username'],
                    'password' => $configData['database']['password']
                ];
            }
        }
    }
    
    // Método 3: Configuración por defecto (para desarrollo)
    if (!$config) {
        $config = [
            'host' => 'localhost',
            'port' => '3306',
            'database' => 'iatrade_crm',
            'username' => 'root',
            'password' => ''
        ];
    }
    
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4";
    
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ];
    
    return new PDO($dsn, $config['username'], $config['password'], $options);
}

try {
    // Conectar directamente sin usar la clase Connection
    $db = getDatabaseConnection();
    
    // Obtener estadísticas básicas
    $stats = [];
    
    // 1. Total de leads
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM leads WHERE status != 'deleted'");
        $stmt->execute();
        $stats['total_leads'] = (int)$stmt->fetch()['total'];
    } catch (Exception $e) {
        $stats['total_leads'] = 0;
        $stats['leads_error'] = $e->getMessage();
    }
    
    // 2. Total de usuarios
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE status = 'active'");
        $stmt->execute();
        $stats['total_users'] = (int)$stmt->fetch()['total'];
    } catch (Exception $e) {
        $stats['total_users'] = 0;
        $stats['users_error'] = $e->getMessage();
    }
    
    // 3. Total de mesas
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM desks WHERE status = 'active'");
        $stmt->execute();
        $stats['total_desks'] = (int)$stmt->fetch()['total'];
    } catch (Exception $e) {
        $stats['total_desks'] = 0;
        $stats['desks_error'] = $e->getMessage();
    }
    
    // 4. Leads por estado
    try {
        $stmt = $db->prepare("SELECT status, COUNT(*) as count FROM leads WHERE status != 'deleted' GROUP BY status");
        $stmt->execute();
        $stats['leads_by_status'] = $stmt->fetchAll();
    } catch (Exception $e) {
        $stats['leads_by_status'] = [];
        $stats['status_error'] = $e->getMessage();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Estadísticas del dashboard obtenidas exitosamente',
        'data' => $stats,
        'timestamp' => date('Y-m-d H:i:s'),
        'version' => 'production-fix'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    
    // Log del error
    error_log("Dashboard error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener estadísticas del dashboard',
        'error_details' => [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>