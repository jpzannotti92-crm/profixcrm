<?php
// Endpoint seguro para resetear la contraseña del admin
// Uso (HTTP):
//   POST /api/auth/reset_admin.php
//   Body JSON: {"token":"<TOKEN>", "username":"admin", "password":"password"}
//   También acepta GET: /api/auth/reset_admin.php?token=<TOKEN>&username=admin&password=password

// Autoload y carga de entorno (.env / .env.production)
$baseRoot = dirname(__DIR__, 3); // proyecto raíz
$docroot = dirname(__DIR__, 2);  // docroot

// Cargar Composer si existe
$vendorCandidates = [
    $baseRoot . '/vendor/autoload.php',
    $docroot . '/vendor/autoload.php',
];
foreach ($vendorCandidates as $v) { if (is_file($v)) { require_once $v; break; } }

// Cargar .env o .env.production
$envCandidates = [
    $baseRoot . '/.env',
    $baseRoot . '/.env.production',
    $docroot . '/.env',
    $docroot . '/.env.production',
];
foreach ($envCandidates as $envPath) {
    if (is_file($envPath)) {
        try {
            if (class_exists('Dotenv\\Dotenv')) {
                $dotenv = Dotenv\Dotenv::createMutable(dirname($envPath));
                if (method_exists($dotenv, 'overload')) { $dotenv->overload(); } else { $dotenv->load(); }
            } else {
                $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if (is_array($lines)) {
                    foreach ($lines as $line) {
                        if (strpos(ltrim($line), '#') === 0) { continue; }
                        $parts = explode('=', $line, 2);
                        if (count($parts) === 2) { $_ENV[trim($parts[0])] = trim($parts[1]); }
                    }
                }
            }
        } catch (Throwable $e) { /* continuar */ }
        break;
    }
}

// Headers
if (!headers_sent()) {
    header('Content-Type: application/json');
    $origin = $_SERVER['HTTP_ORIGIN'] ?? 'http://localhost:3000';
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

// Obtener parámetros (POST JSON o GET)
$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
$token = $body['token'] ?? ($_GET['token'] ?? '');
$username = ($body['username'] ?? ($_GET['username'] ?? 'admin'));
$newPassword = ($body['password'] ?? ($_GET['password'] ?? 'password'));

// Token esperado: ADMIN_RESET_TOKEN de env o contenido de admin_token.txt
$expectedToken = $_ENV['ADMIN_RESET_TOKEN'] ?? '';
if ($expectedToken === '' && is_file($baseRoot . '/admin_token.txt')) {
    $expectedToken = trim(@file_get_contents($baseRoot . '/admin_token.txt')) ?: '';
}

if ($expectedToken === '' || $token === '' || !hash_equals($expectedToken, $token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token inválido o ausente']);
    exit;
}

// Conexión a BD con soporte de variantes de env
$dbHost = $_ENV['DB_HOST'] ?? 'localhost';
$dbPort = $_ENV['DB_PORT'] ?? '3306';
$dbName = $_ENV['DB_DATABASE'] ?? ($_ENV['DB_NAME'] ?? '');
$dbUser = $_ENV['DB_USERNAME'] ?? ($_ENV['DB_USER'] ?? '');
$dbPass = $_ENV['DB_PASSWORD'] ?? ($_ENV['DB_PASS'] ?? '');

if ($dbName === '' || $dbUser === '') {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Configuración de BD incompleta']);
    exit;
}

try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // Detectar columna de contraseña
    $hasPasswordHash = false; $hasPassword = false;
    try { $st = $pdo->query("SHOW COLUMNS FROM users LIKE 'password_hash'"); $hasPasswordHash = $st && $st->fetch() ? true : false; } catch (Throwable $e) {}
    try { $st = $pdo->query("SHOW COLUMNS FROM users LIKE 'password'"); $hasPassword = $st && $st->fetch() ? true : false; } catch (Throwable $e) {}

    if (!$hasPasswordHash && !$hasPassword) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'No se encontró columna de contraseña en users']);
        exit;
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);

    // Actualizar usando username O email
    $updated = false;
    if ($hasPasswordHash) {
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = ? OR email = ? LIMIT 1");
        $updated = $stmt->execute([$hash, $username, $username]) || $updated;
    }
    if ($hasPassword) {
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = ? OR email = ? LIMIT 1");
        $updated = $stmt->execute([$hash, $username, $username]) || $updated;
    }

    if ($updated) {
        // Activar usuario y actualizar last_login
        try { $pdo->prepare("UPDATE users SET active = 1, last_login = NOW() WHERE username = ? OR email = ?")
                ->execute([$username, $username]); } catch (Throwable $e) {}

        echo json_encode([
            'success' => true,
            'message' => "Contraseña actualizada para '{$username}'",
            'username' => $username,
        ]);
        exit;
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
        exit;
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de BD: ' . $e->getMessage()]);
    exit;
}
?>