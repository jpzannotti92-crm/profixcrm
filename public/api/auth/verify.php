<?php
// Verificación JWT desde Authorization y cookie para servidor público
header('Content-Type: application/json');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Vary: Origin');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit;
}

// Extraer token ANTES de cargar dependencias para evitar 500 en solicitudes sin token
function extract_token(): ?string {
    // Token pasado por rewrite desde el path /api/auth/verify/<token>
    $envToken = $_SERVER['REDIRECT_PROXY_TOKEN'] ?? ($_SERVER['PROXY_TOKEN'] ?? null);
    if ($envToken) { return (string)$envToken; }
    // Si el proxy nos pasf un token ya extrafdo, usarlo primero
    if (isset($GLOBALS['__proxied_token']) && $GLOBALS['__proxied_token']) {
        return (string)$GLOBALS['__proxied_token'];
    }
    // Encabezados en distintas variantes segfn el servidor/proxy
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization'] ?? '';
    $xAuthHeader = $headers['X-Auth-Token'] ?? ($headers['x-auth-token'] ?? '');
    // Alternativos para evadir filtros/WAF que bloquean 'Authorization' o 'token'
    $xAccessHeader = $headers['X-Access-Token'] ?? ($headers['x-access-token'] ?? '');
    $xTokenHeader = $headers['X-Token'] ?? ($headers['x-token'] ?? '');
    $xJwtHeader = $headers['X-JWT'] ?? ($headers['x-jwt'] ?? '');
    $serverAuth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $redirectAuth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    $rawAuth = $_SERVER['Authorization'] ?? '';

    $candidates = [];
    foreach ([$authHeader, $serverAuth, $redirectAuth, $rawAuth] as $h) {
        if ($h) { $candidates[] = $h; }
    }
    // Añadir variantes equivalentes como Bearer
    foreach ([$xAuthHeader, $xAccessHeader, $xTokenHeader, $xJwtHeader] as $alt) {
        if ($alt) { $candidates[] = 'Bearer ' . $alt; }
    }

    foreach ($candidates as $h) {
        if (preg_match('/Bearer\s+(.*)$/i', $h, $m)) {
            return $m[1];
        }
    }

    // Cookie establecida por login (y variantes tolerantes)
    $cookieCandidates = [
        $_COOKIE['auth_token'] ?? '',
        $_COOKIE['access_token'] ?? '',
        $_COOKIE['session_token'] ?? '',
        $_COOKIE['jwt'] ?? ''
    ];
    foreach ($cookieCandidates as $cTok) {
        if ($cTok !== '') { return $cTok; }
    }

    // Query param tolerante
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

    // Body JSON { token: "..." } o form token=...
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

$token = extract_token();

if (!$token) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token no proporcionado']);
    exit;
}

// Autoload de Composer opcional (evitar 500 si no existe)
if (file_exists(__DIR__ . '/../../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../../vendor/autoload.php';
    if (file_exists(__DIR__ . '/../../../.env')) {
        $dotenv = Dotenv\Dotenv::createMutable(__DIR__ . '/../../..');
        if (method_exists($dotenv, 'overload')) { $dotenv->overload(); } else { $dotenv->load(); }
    }
} else {
    error_log('public/api/auth/verify.php: vendor/autoload.php no encontrado, continuando sin Composer');
}

// Cargar dependencias del proyecto (evitar fallo de autoload con App\Models)
// Intentar múltiples rutas para entornos donde /src no está bajo /public
$srcCandidates = [
    __DIR__ . '/../../../src',            // public/src (puede no existir)
    (rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/') . '/src') ?: '', // /public_html/src
    __DIR__ . '/../../../../src',         // raíz del proyecto
];
foreach ($srcCandidates as $srcBase) {
    if ($srcBase && is_dir($srcBase)) {
        @require_once $srcBase . '/Database/Connection.php';
        @require_once $srcBase . '/Models/BaseModel.php';
        @require_once $srcBase . '/Models/User.php';
        break;
    }
}

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use IaTradeCRM\Models\User;

try {
    // El token ya fue validado como presente arriba

    // Cargar secreto JWT de forma tolerante
    $secret = $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET') ?? 'your-super-secret-jwt-key-change-in-production-2024';

    try {
        $decoded = JWT::decode($token, new Key($secret, 'HS256'));
        
        try {
            $user = User::find($decoded->user_id);
        } catch (\Throwable $dbEx) {
            // Evitar 500: responder 401 y mensaje estable
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'code' => 'db_error',
                'message' => 'No fue posible validar el usuario actualmente'
            ]);
            exit;
        }

        if (!$user || ($user->status ?? 'inactive') !== 'active') {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Usuario no válido o inactivo']);
            exit;
        }

        // Roles y permisos pueden ser objetos; normalizar a strings si es posible
        $rolesRaw = method_exists($user, 'getRoles') ? $user->getRoles() : [];
        $permissionsRaw = method_exists($user, 'getPermissions') ? $user->getPermissions() : [];
        $roles = array_map(function($r) { return is_array($r) ? ($r['name'] ?? ($r['display_name'] ?? '')) : (is_string($r) ? $r : ''); }, $rolesRaw);
        $roles = array_values(array_filter($roles, fn($v) => $v !== ''));
        $permissions = array_map(function($p) { return is_array($p) ? ($p['name'] ?? ($p['display_name'] ?? '')) : (is_string($p) ? $p : ''); }, $permissionsRaw);
        $permissions = array_values(array_filter($permissions, fn($v) => $v !== ''));

        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'first_name' => $user->first_name ?? null,
                'last_name' => $user->last_name ?? null,
                'roles' => $roles,
                'permissions' => $permissions,
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Token inválido o expirado'
        ]);
        exit;
    }
} catch (\Throwable $fatal) {
    // Fallback general: nunca devolver 500, mantener JSON
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'No fue posible verificar el token en este momento',
        'code' => 'verify_unavailable'
    ]);
}
?>