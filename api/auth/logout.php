<?php
// Wrapper de logout para el servidor dev (api). Asegura CORS con credenciales y reutiliza la lógica pública.
header('Content-Type: application/json');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) {
    header('Access-Control-Allow-Origin: ' . $origin);
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';
if (file_exists(__DIR__ . '/../../.env')) {
    $dotenv = Dotenv\Dotenv::createMutable(__DIR__ . '/../../');
    if (method_exists($dotenv, 'overload')) { $dotenv->overload(); } else { $dotenv->load(); }
}

// Reutilizar implementación de logout de servidor público
require __DIR__ . '/../../public/api/auth/logout.php';
?>