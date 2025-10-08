<?php
// Configurar headers para Server-Sent Events
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization');

// Evitar timeout
set_time_limit(0);
ini_set('max_execution_time', 0);

require_once '../config/database.php';
require_once '../includes/jwt_helper.php';

// Verificar JWT
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
    echo "event: error\n";
    echo "data: " . json_encode(['error' => 'Token de autorización requerido']) . "\n\n";
    exit;
}

$token = substr($authHeader, 7);
$decoded = verifyJWT($token);

if (!$decoded) {
    echo "event: error\n";
    echo "data: " . json_encode(['error' => 'Token inválido']) . "\n\n";
    exit;
}

$userId = $decoded->user_id ?? 1;

try {
    $pdo = new PDO($dsn, $username, $password, $options);
    
    // Enviar evento de conexión
    echo "event: connected\n";
    echo "data: " . json_encode(['message' => 'Conectado al stream de notificaciones']) . "\n\n";
    flush();
    
    $lastCheck = time();
    
    while (true) {
        // Verificar nuevas notificaciones cada 5 segundos
        if (time() - $lastCheck >= 5) {
            $notifications = getNewNotifications($pdo, $userId, $lastCheck);
            
            foreach ($notifications as $notification) {
                echo "event: notification\n";
                echo "data: " . json_encode($notification) . "\n\n";
                flush();
            }
            
            $lastCheck = time();
        }
        
        // Enviar heartbeat cada 30 segundos
        if (time() % 30 === 0) {
            echo "event: heartbeat\n";
            echo "data: " . json_encode(['timestamp' => time()]) . "\n\n";
            flush();
        }
        
        // Verificar si la conexión sigue activa
        if (connection_aborted()) {
            break;
        }
        
        sleep(1);
    }
    
} catch (Exception $e) {
    echo "event: error\n";
    echo "data: " . json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]) . "\n\n";
    flush();
}

function getNewNotifications($pdo, $userId, $lastCheck) {
    $stmt = $pdo->prepare("
        SELECT 
            id,
            title,
            message,
            type,
            actions,
            created_at
        FROM notifications 
        WHERE (user_id = ? OR user_id IS NULL)
        AND UNIX_TIMESTAMP(created_at) > ?
        AND (expires_at IS NULL OR expires_at > NOW())
        ORDER BY created_at DESC
    ");
    
    $stmt->execute([$userId, $lastCheck]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Decodificar actions JSON
    foreach ($notifications as &$notification) {
        if ($notification['actions']) {
            $notification['actions'] = json_decode($notification['actions'], true);
        }
    }
    
    return $notifications;
}
?>