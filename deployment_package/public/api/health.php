<?php
require_once __DIR__ . '/bootstrap.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verificar que el sistema esté funcionando
$status = [
    'success' => true,
    'message' => 'API funcionando correctamente',
    'timestamp' => date('Y-m-d H:i:s'),
    'server' => $_SERVER['SERVER_NAME'] ?? 'localhost',
    'php_version' => PHP_VERSION,
    'method' => $_SERVER['REQUEST_METHOD']
];

// Verificaciones básicas del sistema
$checks = [
    'database' => false,
    'config' => false,
    'permissions' => false
];

// Verificar configuración
if (file_exists(__DIR__ . '/../../config/config.php')) {
    $checks['config'] = true;
}

// Verificar base de datos (si existe configuración)
try {
    if (file_exists(__DIR__ . '/../../config/database.php')) {
        require_once __DIR__ . '/../../config/database.php';
        $checks['database'] = true;
    }
} catch (Exception $e) {
    $checks['database'] = false;
}

// Verificar permisos básicos
$checks['permissions'] = is_writable(__DIR__ . '/../../logs') || is_writable(__DIR__ . '/../../storage');

$status['checks'] = $checks;
$status['healthy'] = !in_array(false, $checks);

http_response_code(200);
echo json_encode($status, JSON_PRETTY_PRINT);
?>