<?php
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/Database/Connection.php';

// Cargar variables de entorno priorizando .env local
$dotenv = Dotenv\Dotenv::createMutable(__DIR__ . '/../../../');
if (method_exists($dotenv, 'overload')) {
    $dotenv->overload();
} else {
    $dotenv->load();
}

use iaTradeCRM\Database\Connection;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header('Content-Type: application/json');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
header('Vary: Origin');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

// Obtener token del header Authorization
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token no proporcionado']);
    exit();
}

$token = $matches[1];

try {
    // Configuración JWT
    $secret = $_ENV['JWT_SECRET'] ?? 'your-super-secret-jwt-key-change-in-production-2024';
    
    // Decodificar y validar token
    $decoded = JWT::decode($token, new Key($secret, 'HS256'));
    $userId = $decoded->user_id;
    
    // Opcional: Registrar logout en base de datos
    $db = Connection::getInstance()->getConnection();
    $stmt = $db->prepare("UPDATE users SET updated_at = NOW() WHERE id = ?");
    $stmt->execute([$userId]);
    
    // En un sistema más avanzado, aquí se podría:
    // 1. Agregar el token a una blacklist
    // 2. Registrar la actividad de logout
    // 3. Limpiar sesiones activas
    // 4. Limpiar cookie de autenticación
    if (!headers_sent()) {
        @setcookie('auth_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Logout exitoso'
    ]);
    
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Token inválido o expirado'
    ]);
}
?>