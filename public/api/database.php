<?php
/**
 * Conexión a base de datos para APIs
 */

// Cargar variables de entorno (.env) para credenciales
if (class_exists('Dotenv\Dotenv')) {
    $dotenv = Dotenv\Dotenv::createMutable(__DIR__ . '/../../');
    if (method_exists($dotenv, 'overload')) {
        $dotenv->overload();
    } else {
        $dotenv->load();
    }
} else {
    // Fallback mínimo si Dotenv no está disponible
    $envFile = __DIR__ . '/../../.env';
    if (is_file($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(ltrim($line), '#') === 0) { continue; }
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $k = trim($parts[0]);
                $v = trim($parts[1], " \t\n\r\0\x0B\"'" );
                $_ENV[$k] = $v;
                putenv("$k=$v");
            }
        }
    }
}

// Configuración de la base de datos utilizando .env
$config = [
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'username' => $_ENV['DB_USERNAME'] ?? ($_ENV['DB_USER'] ?? 'root'),
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    // Fallback actualizado: spin2pay_profixcrm
    'database' => $_ENV['DB_DATABASE'] ?? ($_ENV['DB_NAME'] ?? 'spin2pay_profixcrm'),
    'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4'
];

/**
 * Obtener conexión PDO
 */
function getDbConnection() {
    global $config;
    
    try {
        // Primero intentar conectar sin especificar base de datos
        $pdo = new PDO(
            "mysql:host={$config['host']};charset={$config['charset']}", 
            $config['username'], 
            $config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
            ]
        );
        
        // Crear base de datos si no existe
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$config['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        // Seleccionar la base de datos
        $pdo->exec("USE `{$config['database']}`");
        
        return $pdo;
    } catch (PDOException $e) {
        // Intentar conexión directa si falla la creación
        try {
            $pdo = new PDO(
                "mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}", 
                $config['username'], 
                $config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
                ]
            );
            return $pdo;
        } catch (PDOException $e2) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error de conexión a la base de datos',
                'error' => $e2->getMessage()
            ]);
            exit;
        }
    }
}

/**
 * Verificar token de autenticación
 */
function verifyToken() {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    
    if (empty($authHeader) || strpos($authHeader, 'Bearer ') !== 0) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Token de autenticación requerido'
        ]);
        exit;
    }
    
    $token = substr($authHeader, 7);
    
    // Decodificar token simple (base64)
    $decoded = base64_decode($token);
    $tokenData = json_decode($decoded, true);
    
    if (!$tokenData || !isset($tokenData['user_id']) || !isset($tokenData['exp'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Token inválido'
        ]);
        exit;
    }
    
    // Verificar expiración
    if ($tokenData['exp'] < time()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Token expirado'
        ]);
        exit;
    }
    
    return $tokenData;
}

/**
 * Respuesta exitosa
 */
function successResponse($data = null, $message = 'Operación exitosa') {
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => $data
    ]);
}

/**
 * Respuesta de error
 */
function errorResponse($message, $code = 400) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $message
    ]);
}
?>