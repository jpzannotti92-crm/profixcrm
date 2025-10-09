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

// Manejo temprano: intentar extraer token desde mltiples fuentes para evitar falsos 401
function early_extract_token(): ?string {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization'] ?? '';
    $xAuth = $headers['X-Auth-Token'] ?? ($headers['x-auth-token'] ?? '');
    $serverAuth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $redirectAuth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    $rawAuth = $_SERVER['Authorization'] ?? '';

    $candidates = [];
    foreach ([$authHeader, $serverAuth, $redirectAuth, $rawAuth] as $h) {
        if ($h) { $candidates[] = $h; }
    }
    if ($xAuth) { $candidates[] = 'Bearer ' . $xAuth; }

    foreach ($candidates as $h) {
        if (preg_match('/Bearer\s+(.*)$/i', $h, $m)) {
            return $m[1];
        }
    }

    if (isset($_COOKIE['auth_token']) && $_COOKIE['auth_token'] !== '') {
        return $_COOKIE['auth_token'];
    }

    if (!empty($_GET['token'])) {
        return (string)$_GET['token'];
    }

    $raw = file_get_contents('php://input');
    if ($raw) {
        $json = json_decode($raw, true);
        if (is_array($json) && !empty($json['token'])) {
            return (string)$json['token'];
        }
    }
    if (!empty($_POST['token'])) {
        return (string)$_POST['token'];
    }

    return null;
}

$__earlyToken = early_extract_token();
if (!$__earlyToken) {
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
        // Exponer el token extrado para el endpoint pblico si es necesario
        $GLOBALS['__proxied_token'] = $__earlyToken;
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