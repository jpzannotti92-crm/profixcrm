<?php
// Proxy robusto de verificación bajo /api a /public/api para evitar dependencias fuera del docroot
header('Content-Type: application/json');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token, X-Access-Token, X-Token, X-JWT');
// Blindaje contra errores: nunca devolver 500
ini_set('display_errors', '0');
set_exception_handler(function($e){
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(401);
    }
    echo json_encode(['success'=>false,'message'=>'Verificación no disponible','code'=>'verify_exception_proxy']);
});
register_shutdown_function(function(){
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(401);
        }
        echo json_encode(['success'=>false,'message'=>'Verificación no disponible','code'=>'verify_fatal_proxy']);
    }
});

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
    // Alternativos para evadir filtros/WAF
    $xAccess = $headers['X-Access-Token'] ?? ($headers['x-access-token'] ?? '');
    $xToken = $headers['X-Token'] ?? ($headers['x-token'] ?? '');
    $xJwt = $headers['X-JWT'] ?? ($headers['x-jwt'] ?? '');
    $serverAuth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $redirectAuth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    $rawAuth = $_SERVER['Authorization'] ?? '';

    $candidates = [];
    foreach ([$authHeader, $serverAuth, $redirectAuth, $rawAuth] as $h) {
        if ($h) { $candidates[] = $h; }
    }
    foreach ([$xAuth, $xAccess, $xToken, $xJwt] as $alt) {
        if ($alt) { $candidates[] = 'Bearer ' . $alt; }
    }

    foreach ($candidates as $h) {
        if (preg_match('/Bearer\s+(.*)$/i', $h, $m)) {
            return $m[1];
        }
    }

    $cookieCandidates = [
        $_COOKIE['auth_token'] ?? '',
        $_COOKIE['access_token'] ?? '',
        $_COOKIE['session_token'] ?? '',
        $_COOKIE['jwt'] ?? ''
    ];
    foreach ($cookieCandidates as $cTok) {
        if ($cTok !== '') { return $cTok; }
    }

    $queryCandidates = [
        $_GET['token'] ?? '',
        $_GET['access_token'] ?? '',
        $_GET['t'] ?? '',
        $_GET['k'] ?? '',
        $_GET['jwt'] ?? ''
    ];
    foreach ($queryCandidates as $qTok) {
        if ($qTok !== '') { return (string)$qTok; }
    }

    $raw = file_get_contents('php://input');
    if ($raw) {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $bodyCandidates = [
                $json['token'] ?? '',
                $json['access_token'] ?? '',
                $json['t'] ?? '',
                $json['k'] ?? '',
                $json['jwt'] ?? ''
            ];
            foreach ($bodyCandidates as $bTok) {
                if ($bTok !== '') { return (string)$bTok; }
            }
        }
    }
    $postCandidates = [
        $_POST['token'] ?? '',
        $_POST['access_token'] ?? '',
        $_POST['t'] ?? '',
        $_POST['k'] ?? '',
        $_POST['jwt'] ?? ''
    ];
    foreach ($postCandidates as $pTok) {
        if ($pTok !== '') { return (string)$pTok; }
    }

    return null;
}

$__earlyToken = early_extract_token();
// Delegar verificación al endpoint público aunque no se haya podido extraer token aquí.
// Si se obtuvo un token temprano, exponerlo para el verificador público.
if ($__earlyToken) {
    $GLOBALS['__proxied_token'] = $__earlyToken;
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