<?php
// Bootstrap opcional
$bootstrap = __DIR__ . '/../bootstrap.php';
if (file_exists($bootstrap)) { require_once $bootstrap; }

// Autoload Composer con rutas robustas
$vendorCandidates = [
    __DIR__ . '/../../../vendor/autoload.php', // proyecto raíz
    __DIR__ . '/../../vendor/autoload.php',    // vendor dentro de docroot
];
foreach ($vendorCandidates as $vendorAutoload) {
    if (file_exists($vendorAutoload)) {
        // Bypass de platform check
        $bypass = dirname($vendorAutoload, 2) . '/platform_check_bypass.php';
        if (file_exists($bypass)) { require_once $bypass; }
        require_once $vendorAutoload;
        break;
    }
}

// Cargar .env / .env.production desde rutas posibles
$envRootCandidates = [
    __DIR__ . '/../../../', // proyecto raíz
    __DIR__ . '/../../',    // docroot
];
$envLoaded = false;
foreach ($envRootCandidates as $envRoot) {
    $envPath = $envRoot . '/.env';
    $envProdPath = $envRoot . '/.env.production';
    if (file_exists($envPath) || file_exists($envProdPath)) {
        if (class_exists('Dotenv\\Dotenv')) {
            $dotenv = Dotenv\Dotenv::createMutable($envRoot);
            if (file_exists($envPath)) {
                if (method_exists($dotenv, 'overload')) { $dotenv->overload(); } else { $dotenv->load(); }
                $envLoaded = true;
            } elseif (file_exists($envProdPath)) {
                // Cargar manualmente .env.production
                $lines = @file($envProdPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if (is_array($lines)) {
                    foreach ($lines as $line) {
                        if (strpos(ltrim($line), '#') === 0) { continue; }
                        $parts = explode('=', $line, 2);
                        if (count($parts) === 2) {
                            $k = trim($parts[0]);
                            $v = trim($parts[1], " \t\n\r\0\x0B\"'" );
                            $_ENV[$k] = $v; putenv("$k=$v");
                        }
                    }
                }
                $envLoaded = true;
            }
        } else {
            // Minimal loader
            $fileToLoad = file_exists($envPath) ? $envPath : $envProdPath;
            if ($fileToLoad && is_file($fileToLoad)) {
                $lines = file($fileToLoad, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos(ltrim($line), '#') === 0) { continue; }
                    $parts = explode('=', $line, 2);
                    if (count($parts) === 2) {
                        $k = trim($parts[0]);
                        $v = trim($parts[1], " \t\n\r\0\x0B\"'" );
                        $_ENV[$k] = $v; putenv("$k=$v");
                    }
                }
                $envLoaded = true;
            }
        }
        if ($envLoaded) { break; }
    }
}

// Fallback: cargar .env.production si JWT_SECRET no está definido
if (!isset($_ENV['JWT_SECRET']) || $_ENV['JWT_SECRET'] === '') {
    $envProd = __DIR__ . '/../../../.env.production';
    if (is_file($envProd)) {
        $lines = @file($envProd, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (is_array($lines)) {
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
}

// JWT fallback helpers if firebase/php-jwt is unavailable
if (!class_exists('Firebase\\JWT\\JWT')) {
    if (!function_exists('base64url_encode')) {
        function base64url_encode($data) {
            return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
        }
    }
    if (!function_exists('jwt_encode')) {
        function jwt_encode(array $payload, string $secret): string {
            $header = ['alg' => 'HS256', 'typ' => 'JWT'];
            $segments = [
                base64url_encode(json_encode($header, JSON_UNESCAPED_UNICODE)),
                base64url_encode(json_encode($payload, JSON_UNESCAPED_UNICODE)),
            ];
            $signingInput = implode('.', $segments);
            $signature = hash_hmac('sha256', $signingInput, $secret, true);
            $segments[] = base64url_encode($signature);
            return implode('.', $segments);
        }
    }
}

// Cargar capa de acceso a datos si existe; si no, usar PDO directo
$useLegacyPDO = false;
$connCandidates = [
    __DIR__ . '/../../../src/Database/Connection.php',
    __DIR__ . '/../../src/Database/Connection.php',
];
$compatCandidates = [
    __DIR__ . '/../../../src/Database/MySQLCompatibility.php',
    __DIR__ . '/../../src/Database/MySQLCompatibility.php',
];
$connLoaded = false; $compatLoaded = false;
foreach ($connCandidates as $c) { if (file_exists($c)) { require_once $c; $connLoaded = true; break; } }
foreach ($compatCandidates as $c) { if (file_exists($c)) { require_once $c; $compatLoaded = true; break; } }
if (!$connLoaded || !$compatLoaded) { $useLegacyPDO = true; }

use iaTradeCRM\Database\Connection;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use iaTradeCRM\Database\MySQLCompatibility;

// Evitar warnings de headers cuando se ejecuta vía CLI o los headers ya se enviaron
if (!headers_sent()) {
    header('Content-Type: application/json');
    // CORS dinámico para permitir credenciales (cookies) correctamente
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : 'http://localhost:3000';
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    // Permitir envío de cookies cuando se use CORS/proxy
    header('Access-Control-Allow-Credentials: true');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

// Obtener datos del request (acepta username o email)
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

$identifier = null;
if (is_array($input)) {
    // Permitir tanto 'username' como 'email'
    if (isset($input['username']) && $input['username'] !== '') {
        $identifier = trim($input['username']);
    } elseif (isset($input['email']) && $input['email'] !== '') {
        $identifier = trim($input['email']);
    }
}

if (!$input || $identifier === null || !isset($input['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Usuario (username o email) y contraseña requeridos']);
    exit();
}

$username = $identifier; // El query soporta (username OR email)
$password = trim($input['password']);
$remember = $input['remember'] ?? false;

if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Usuario y contraseña no pueden estar vacíos']);
    exit();
}

try {
    // Obtener conexión
    if (!$useLegacyPDO) {
        $db = Connection::getInstance()->getConnection();
        $mysqlCompat = new MySQLCompatibility($db);
    } else {
        // Fallback PDO usando variables de entorno o config.php
        $dbConf = null;
        $configCandidates = [
            __DIR__ . '/../../../config/config.php',
            __DIR__ . '/../../config/config.php',
        ];
        foreach ($configCandidates as $conf) {
            if (file_exists($conf)) { $cfg = include $conf; $dbConf = $cfg['database'] ?? null; break; }
        }
        $host = $dbConf['host'] ?? ($_ENV['DB_HOST'] ?? 'localhost');
        $name = $dbConf['name'] ?? ($_ENV['DB_NAME'] ?? '');
        // Soportar tanto DB_USER como DB_USERNAME
        $user = $dbConf['username'] ?? ($_ENV['DB_USER'] ?? ($_ENV['DB_USERNAME'] ?? ''));
        // Soportar tanto DB_PASS como DB_PASSWORD
        $pass = $dbConf['password'] ?? ($_ENV['DB_PASS'] ?? ($_ENV['DB_PASSWORD'] ?? ''));
        if (empty($name) || $user === '') {
            throw new Exception('Configuración de base de datos no disponible');
        }
        $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
        $db = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $mysqlCompat = null;
    }
    
    // Verificar si existe la columna is_primary en desk_users para construir el JOIN de forma compatible
    $hasIsPrimary = false;
    try {
        $colStmt = $db->query("SHOW COLUMNS FROM desk_users LIKE 'is_primary'");
        $hasIsPrimary = $colStmt && $colStmt->fetch() ? true : false;
    } catch (Exception $e) {
        $hasIsPrimary = false;
    }

    $joinCondition = $hasIsPrimary ? "u.id = du.user_id AND du.is_primary = 1" : "u.id = du.user_id";

    // Obtener consulta compatible con la configuración actual de MySQL
    if ($mysqlCompat) {
        $sql = $mysqlCompat->getCompatibleLoginQuery($joinCondition);
    } else {
        // Consulta por defecto si no hay helper de compatibilidad
        $sql = "SELECT u.id, u.username, u.email, u.active,
                GROUP_CONCAT(DISTINCT r.name) AS roles,
                GROUP_CONCAT(DISTINCT p.name) AS permissions,
                du.desk_id
            FROM users u
            LEFT JOIN user_roles ur ON ur.user_id = u.id
            LEFT JOIN roles r ON r.id = ur.role_id
            LEFT JOIN role_permissions rp ON rp.role_id = r.id
            LEFT JOIN permissions p ON p.id = rp.permission_id
            LEFT JOIN desk_users du ON du.user_id = u.id
            WHERE (u.username = ? OR u.email = ?) AND u.active = 1
            GROUP BY u.id
            LIMIT 1";
    }

    $stmt = $db->prepare($sql);
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado o inactivo']);
        exit();
    }
    
    // Compatibilidad de contraseña: detectar columna existente y verificar correctamente
    $passwordField = null;
    try {
        $colStmt = $db->query("SHOW COLUMNS FROM users LIKE 'password_hash'");
        $hasHash = $colStmt && $colStmt->fetch();
        if ($hasHash) {
            $passwordField = 'password_hash';
        } else {
            $colStmt2 = $db->query("SHOW COLUMNS FROM users LIKE 'password'");
            $hasPassword = $colStmt2 && $colStmt2->fetch();
            if ($hasPassword) {
                $passwordField = 'password';
            }
        }
    } catch (Exception $e) {
        $passwordField = null;
    }

    // Obtener el valor de la contraseña directamente de la tabla para evitar problemas de SELECT
    $storedPassword = null;
    if ($passwordField) {
        $pwdStmt = $db->prepare("SELECT {$passwordField} AS pwd FROM users WHERE id = ?");
        $pwdStmt->execute([$user['id']]);
        $pwdRow = $pwdStmt->fetch();
        $storedPassword = $pwdRow['pwd'] ?? null;
    }

    $isValid = false;
    if ($storedPassword) {
        if (password_verify($password, $storedPassword)) {
            $isValid = true;
        } else {
            // Compatibilidad: si estaba en texto plano y coincide, migrar a hash seguro
            if ($storedPassword === $password) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                try {
                    $updStmt = $db->prepare("UPDATE users SET {$passwordField} = ? WHERE id = ?");
                    $updStmt->execute([$newHash, $user['id']]);
                } catch (Exception $e) { /* ignorar errores de migración silenciosamente */ }
                $isValid = true;
            }
        }
    }

    if (!$isValid) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta']);
        exit();
    }
    
    // Actualizar último login
    $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $updateStmt->execute([$user['id']]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
    exit();
}

// Generar JWT
$secret = $_ENV['JWT_SECRET'] ?? 'your-super-secret-jwt-key-change-in-production-2024';
$expiration = $remember ? (7 * 24 * 60 * 60) : (24 * 60 * 60);

$payload = [
    'iss' => 'iatrade-crm',
    'aud' => 'iatrade-crm',
    'iat' => time(),
    'exp' => time() + $expiration,
    'user_id' => (int)$user['id'],
    'username' => $user['username'],
    'email' => $user['email'],
    'roles' => $user['roles'] ? explode(',', $user['roles']) : [],
    'permissions' => $user['permissions'] ? array_unique(explode(',', $user['permissions'])) : [],
    'desk_id' => $user['desk_id'] ? (int)$user['desk_id'] : null
];

$jwt = class_exists('Firebase\\JWT\\JWT') ? JWT::encode($payload, $secret, 'HS256') : jwt_encode($payload, $secret);

// Preparar datos del usuario
$userData = [
    'id' => (int)$user['id'],
    'username' => $user['username'],
    'email' => $user['email'],
    'first_name' => $user['first_name'],
    'last_name' => $user['last_name'],
    'phone' => $user['phone'],
    'avatar' => $user['avatar'],
    'department' => $user['department'] ?? null,
    'position' => $user['position'] ?? null,
    'desk' => [
        'id' => $user['desk_id'] ? (int)$user['desk_id'] : null,
        'name' => $user['desk_name']
    ],
    'supervisor' => $user['supervisor_first_name'] ? [
        'name' => $user['supervisor_first_name'] . ' ' . $user['supervisor_last_name']
    ] : null,
    'roles' => $user['roles'] ? explode(',', $user['roles']) : [],
    'role_names' => $user['role_names'] ? explode(',', $user['role_names']) : [],
    'permissions' => $user['permissions'] ? array_unique(explode(',', $user['permissions'])) : [],
    'status' => $user['status'],
    'last_login' => date('Y-m-d H:i:s'),
    'settings' => json_decode($user['settings'] ?? '{}', true)
];

// Respuesta exitosa
// Guardar también el token en cookie HttpOnly para máxima compatibilidad (fallback si falta el header)
// Ajuste de producción: detectar HTTPS, dominio y SameSite apropiados
if (!headers_sent()) {
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                (($_SERVER['SERVER_PORT'] ?? null) == 443) ||
                (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    // Derivar dominio desde APP_URL/ORIGIN/HTTP_HOST
    $appUrl = $_ENV['APP_URL'] ?? '';
    $derivedHost = '';
    if ($appUrl) { $derivedHost = parse_url($appUrl, PHP_URL_HOST) ?: ''; }
    if (!$derivedHost && !empty($_SERVER['HTTP_ORIGIN'])) {
        $derivedHost = parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST) ?: '';
    }
    if (!$derivedHost && !empty($_SERVER['HTTP_HOST'])) {
        $derivedHost = $_SERVER['HTTP_HOST'];
    }

    // SameSite debe ser 'None' si se va a enviar por contexto cross-site con Secure
    $sameSite = $isSecure ? 'None' : 'Lax';

    @setcookie('auth_token', $jwt, [
        'expires' => time() + $expiration,
        'path' => '/',
        'domain' => $derivedHost,
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => $sameSite
    ]);
}

echo json_encode([
    'success' => true,
    'message' => 'Login exitoso',
    'token' => $jwt,
    'user' => $userData,
    'expires_in' => $expiration
]);
?>