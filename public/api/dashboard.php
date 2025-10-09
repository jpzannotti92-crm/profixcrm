<?php
require_once __DIR__ . '/bootstrap.php';
// Manejo robusto de errores para producción
error_reporting(E_ALL);
ini_set('display_errors', 0); // Desactivar en producción
ini_set('log_errors', 1);

header('Content-Type: application/json');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
header('Vary: Origin');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Función para conectar a la base de datos con múltiples métodos
function getDatabaseConnection() {
    // Método 1: Usar la clase Connection si está disponible
    try {
        $connectionPath = __DIR__ . '/../../src/Database/Connection.php';
        
        // Evitar cargar Composer para máxima compatibilidad en PHP 8.0
        if (file_exists($connectionPath)) {
            require_once $connectionPath;
            $db = \iaTradeCRM\Database\Connection::getInstance()->getConnection();
            return $db;
        }
    } catch (Exception $e) {
        error_log("Error usando Connection class: " . $e->getMessage());
    }
    
    // Método 2: Conexión directa con configuración
    $config = null;
    
    // Intentar cargar configuración desde config.php
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
    
    // Configuración por defecto si no se encuentra
    if (!$config) {
        $config = [
            'host' => 'localhost',
            'port' => '3306',
            // Fallback alineado con cambios recientes
            'database' => 'spin2pay_profixcrm',
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

// Verificar autenticación JWT (opcional para algunos endpoints)
function verifyJWT($required = false) { // Cambiar a false por defecto para producción
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
    // Alinear secreto con login.php
    $secret = $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET') ?? 'password';
    
    try {
        // Intentar cargar JWT si está disponible
        if (class_exists('Firebase\JWT\JWT')) {
            $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($secret, 'HS256'));
            return $decoded;
        }
    } catch (Exception $e) {
        error_log("JWT error: " . $e->getMessage());
    }
    
    if ($required) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token inválido']);
        exit();
    }
    return null;
}

// Para operaciones de lectura, hacer la autenticación opcional
$currentUser = verifyJWT(false);

try {
    $db = getDatabaseConnection();
    
    // Obtener estadísticas reales de la base de datos
    
    // 1. Total de leads (tolerante a esquemas)
    $totalLeads = 0;
    try {
        $leadsStmt = $db->prepare("SELECT COUNT(*) as total FROM leads WHERE status != 'deleted'");
        $leadsStmt->execute();
        $row = $leadsStmt->fetch();
        $totalLeads = isset($row['total']) ? (int)$row['total'] : 0;
    } catch (Exception $e) {
        error_log("Dashboard: conteo de leads falló: " . $e->getMessage());
        $totalLeads = 0;
    }
    
    // 2. Leads por estado
    $leadsStatus = [];
    try {
        $leadsStatusStmt = $db->prepare("
            SELECT status, COUNT(*) as count 
            FROM leads 
            WHERE status != 'deleted' 
            GROUP BY status
        ");
        $leadsStatusStmt->execute();
        $leadsStatus = $leadsStatusStmt->fetchAll();
    } catch (Exception $e) {
        error_log("Dashboard: leads por estado falló: " . $e->getMessage());
        $leadsStatus = [];
    }
    
    // 3. Total de usuarios activos
    $totalUsers = 0;
    try {
        $usersStmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE status = 'active'");
        $usersStmt->execute();
        $row = $usersStmt->fetch();
        $totalUsers = isset($row['total']) ? (int)$row['total'] : 0;
    } catch (Exception $e) {
        error_log("Dashboard: conteo de usuarios falló: " . $e->getMessage());
        $totalUsers = 0;
    }
    
    // 4. Total de mesas activas
    $totalDesks = 0;
    try {
        $desksStmt = $db->prepare("SELECT COUNT(*) as total FROM desks WHERE status = 'active'");
        $desksStmt->execute();
        $row = $desksStmt->fetch();
        $totalDesks = isset($row['total']) ? (int)$row['total'] : 0;
    } catch (Exception $e) {
        // Si la columna status no existe, contar todas las mesas
        try {
            $desksStmt2 = $db->prepare("SELECT COUNT(*) as total FROM desks");
            $desksStmt2->execute();
            $row2 = $desksStmt2->fetch();
            $totalDesks = isset($row2['total']) ? (int)$row2['total'] : 0;
        } catch (Exception $e2) {
            error_log("Dashboard: conteo de desks falló: " . $e2->getMessage());
            $totalDesks = 0;
        }
    }
    
    // 5. Leads por mesa
    $leadsByDesk = [];
    try {
        $leadsByDeskStmt = $db->prepare("
            SELECT d.name as desk_name, COUNT(l.id) as leads_count
            FROM desks d
            LEFT JOIN leads l ON d.id = l.desk_id AND l.status != 'deleted'
            WHERE COALESCE(d.status, 'active') = 'active'
            GROUP BY d.id, d.name
            ORDER BY leads_count DESC
        ");
        $leadsByDeskStmt->execute();
        $leadsByDesk = $leadsByDeskStmt->fetchAll();
    } catch (Exception $e) {
        error_log("Dashboard: leads por mesa falló: " . $e->getMessage());
        $leadsByDesk = [];
    }
    
    // 6. Actividad reciente (últimos 7 días)
    $recentActivity = [];
    try {
        $recentActivityStmt = $db->prepare("
            SELECT DATE(created_at) as date, COUNT(*) as count
            FROM leads 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ");
        $recentActivityStmt->execute();
        $recentActivity = $recentActivityStmt->fetchAll();
    } catch (Exception $e) {
        error_log("Dashboard: actividad reciente falló: " . $e->getMessage());
        $recentActivity = [];
    }
    
    // 7. Conversiones (leads con status 'converted')
    $totalConversions = 0;
    try {
        $conversionsStmt = $db->prepare("SELECT COUNT(*) as total FROM leads WHERE status = 'converted'");
        $conversionsStmt->execute();
        $row = $conversionsStmt->fetch();
        $totalConversions = isset($row['total']) ? (int)$row['total'] : 0;
    } catch (Exception $e) {
        error_log("Dashboard: conversiones falló: " . $e->getMessage());
        $totalConversions = 0;
    }
    
    // 8. Tasa de conversión
    $conversionRate = $totalLeads > 0 ? round(($totalConversions / $totalLeads) * 100, 2) : 0;
    
    // 9. Top usuarios por leads asignados
    $topUsers = [];
    try {
        $topUsersStmt = $db->prepare("
            SELECT u.first_name, u.last_name, u.username, COUNT(l.id) as leads_count
            FROM users u
            LEFT JOIN leads l ON u.id = l.assigned_to AND l.status != 'deleted'
            WHERE COALESCE(u.status, 'active') = 'active'
            GROUP BY u.id, u.first_name, u.last_name, u.username
            ORDER BY leads_count DESC
            LIMIT 5
        ");
        $topUsersStmt->execute();
        $topUsers = $topUsersStmt->fetchAll();
    } catch (Exception $e) {
        error_log("Dashboard: top usuarios falló: " . $e->getMessage());
        $topUsers = [];
    }
    
    // Preparar respuesta
    $dashboardStats = [
        'total_leads' => (int)$totalLeads,
        'total_conversions' => (int)$totalConversions,
        'total_users' => (int)$totalUsers,
        'total_desks' => (int)$totalDesks,
        'conversion_rate' => $conversionRate,
        'leads_by_status' => $leadsStatus,
        'leads_by_desk' => $leadsByDesk,
        'recent_activity' => $recentActivity,
        'top_users' => $topUsers,
        'last_updated' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode([
        'success' => true,
        'message' => 'Estadísticas del dashboard obtenidas exitosamente',
        'data' => $dashboardStats
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    
    // Log del error para producción
    error_log("Dashboard error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener estadísticas del dashboard',
        'error_details' => [
            'message' => $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
}
?>
