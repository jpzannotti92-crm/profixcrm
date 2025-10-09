<?php
// Proxy robusto de verificación bajo /api a /public/api para evitar dependencias fuera del docroot
header('Content-Type: application/json');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit;
}

// Manejo temprano: si no hay Authorization/X-Auth-Token, devolver 401 sin delegar
$headers = function_exists('getallheaders') ? getallheaders() : [];
$authHeader = $headers['Authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
$xAuth = $headers['X-Auth-Token'] ?? ($headers['x-auth-token'] ?? '');
if (!($authHeader || $xAuth || isset($_COOKIE['auth_token']))) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token no proporcionado']);
    exit;
}

// Intentar resolver ruta absoluta hacia public/api/auth/verify.php
$docroot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
$candidates = [];
if ($docroot !== '') {
    $candidates[] = $docroot . '/public/api/auth/verify.php';
}
// Fallback relativo desde la estructura del proyecto
$candidates[] = __DIR__ . '/../../public/api/auth/verify.php';

foreach ($candidates as $path) {
    if (is_file($path)) {
        require $path;
        exit;
    }
}

// Si no se encontró el endpoint público, responder 401 estable sin romper frontend
http_response_code(401);
echo json_encode([
    'success' => false,
    'message' => 'Verificación no disponible en este entorno',
    'code' => 'verify_proxy_missing'
]);
?>