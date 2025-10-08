<?php
// Diagnóstico profundo de entorno de producción
// Requiere cabecera X-Health-Secret si HEALTH_SECRET está definido en .env

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Health-Secret');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$startTs = microtime(true);

// Cargar entorno de forma robusta (.env y fallback .env.production)
try {
    // Fallback sin dotfiles: incluir env.php si existe
    $envPhp = __DIR__ . '/../../env.php';
    if (file_exists($envPhp)) { @include_once $envPhp; }

    // Intentar vendor autoload cuando esté disponible
    if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
        require_once __DIR__ . '/../../vendor/autoload.php';
        if (class_exists('Dotenv\\Dotenv')) {
            $rootPath = __DIR__ . '/../../';
            $dotenv = Dotenv\Dotenv::createMutable($rootPath);
            $dotenv->load();
            // Si no hay variables cargadas clave, intentar .env.production
            $hasDbEnv = (getenv('DB_HOST') !== false) || isset($_ENV['DB_HOST']);
            if (!$hasDbEnv && file_exists($rootPath . '.env.production')) {
                try {
                    $dotenvProd = Dotenv\Dotenv::createMutable($rootPath, '.env.production');
                    $dotenvProd->load();
                } catch (Throwable $e) {
                    // Continuar incluso si falla
                }
            }
        }
    } else {
        // Fallback mínimo: cargar .env o .env.production manualmente
        $rootPath = __DIR__ . '/../../';
        $envFiles = [];
        if (is_file($rootPath . '.env')) $envFiles[] = $rootPath . '.env';
        if (is_file($rootPath . '.env.production')) $envFiles[] = $rootPath . '.env.production';
        foreach ($envFiles as $envFile) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (strpos(ltrim($line), '#') === 0) continue;
                $parts = explode('=', $line, 2);
                if (count($parts) === 2) {
                    $k = trim($parts[0]);
                    $v = trim($parts[1]);
                    $_ENV[$k] = $v;
                    @putenv($k . '=' . $v);
                }
            }
        }
    }
} catch (Throwable $e) {
    // Continuar incluso si Dotenv falla
}

// Seguridad: si HEALTH_SECRET está definido, exigir cabecera coincidente
$healthSecret = getenv('HEALTH_SECRET') ?: ($_ENV['HEALTH_SECRET'] ?? null);
if ($healthSecret) {
    $clientSecret = $_SERVER['HTTP_X_HEALTH_SECRET'] ?? '';
    if (!hash_equals($healthSecret, $clientSecret)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized: invalid health secret']);
        exit();
    }
}

$checks = [
    'env_loaded' => false,
    'paths' => [
        'storage_writable' => false,
        'logs_writable' => false,
        'uploads_writable' => false,
    ],
    'php_extensions' => [],
    'db' => [
        'connected' => false,
        'select1' => false,
        'tables' => [
            'users' => false,
            'roles' => false,
            'leads' => false,
            'desks' => false,
            'desk_states' => false,
            'lead_state_history' => false
        ],
        'counts' => [
            'users' => null,
            'leads' => null
        ]
    ],
];

// Verificar variables esenciales
// Permitir alias DB_NAME para DB_DATABASE y detectar variables mínimas
$requiredEnv = ['DB_HOST','DB_PORT','DB_DATABASE','DB_USERNAME'];
$envPresent = 0;
foreach ($requiredEnv as $k) {
    if ($k === 'DB_DATABASE') {
        $hasDbName = (getenv('DB_DATABASE') !== false) || isset($_ENV['DB_DATABASE'])
            || (getenv('DB_NAME') !== false) || isset($_ENV['DB_NAME']);
        if ($hasDbName) { $envPresent++; }
    } else {
        if (getenv($k) !== false || isset($_ENV[$k])) { $envPresent++; }
    }
}
$checks['env_loaded'] = ($envPresent >= 3);

// Si no cargó el entorno, intentar poblar desde constantes de config.php
if (!$checks['env_loaded']) {
    if (defined('DB_HOST')) { $_ENV['DB_HOST'] = DB_HOST; @putenv('DB_HOST=' . DB_HOST); }
    if (defined('DB_PORT')) { $_ENV['DB_PORT'] = DB_PORT; @putenv('DB_PORT=' . DB_PORT); }
    if (defined('DB_NAME')) { $_ENV['DB_DATABASE'] = DB_NAME; $_ENV['DB_NAME'] = DB_NAME; @putenv('DB_DATABASE=' . DB_NAME); @putenv('DB_NAME=' . DB_NAME); }
    if (defined('DB_USER')) { $_ENV['DB_USERNAME'] = DB_USER; $_ENV['DB_USER'] = DB_USER; @putenv('DB_USERNAME=' . DB_USER); @putenv('DB_USER=' . DB_USER); }
    if (defined('DB_PASS')) { $_ENV['DB_PASSWORD'] = DB_PASS; $_ENV['DB_PASS'] = DB_PASS; @putenv('DB_PASSWORD=' . DB_PASS); @putenv('DB_PASS=' . DB_PASS); }
    // Recalcular
    $envPresent = 0;
    foreach ($requiredEnv as $k) {
        if ($k === 'DB_DATABASE') {
            $hasDbName = (getenv('DB_DATABASE') !== false) || isset($_ENV['DB_DATABASE'])
                || (getenv('DB_NAME') !== false) || isset($_ENV['DB_NAME']);
            if ($hasDbName) { $envPresent++; }
        } else {
            if (getenv($k) !== false || isset($_ENV[$k])) { $envPresent++; }
        }
    }
    $checks['env_loaded'] = ($envPresent >= 3);
}

// Paths y permisos relativos al root del proyecto
$root = realpath(__DIR__ . '/../../');
$storage = $root . '/storage';
$logs = $root . '/storage/logs';
$uploads = $root . '/uploads';
$checks['paths']['storage_writable'] = is_dir($storage) && is_writable($storage);
$checks['paths']['logs_writable'] = is_dir($logs) && is_writable($logs);
$checks['paths']['uploads_writable'] = is_dir($uploads) && is_writable($uploads);

// Extensiones PHP recomendadas
$extensions = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'curl', 'openssl'];
foreach ($extensions as $ext) {
    $checks['php_extensions'][$ext] = extension_loaded($ext);
}

// Prueba de base de datos usando Connection
$issues = [];
$dbInfo = [
    'dsn' => null,
    'sql_mode' => null
];

try {
    require_once __DIR__ . '/../../src/Database/Connection.php';
    $conn = \iaTradeCRM\Database\Connection::getInstance();
    $pdo = $conn->getConnection();
    $checks['db']['connected'] = true;

    // SELECT 1
    try {
        $pdo->query('SELECT 1');
        $checks['db']['select1'] = true;
    } catch (\PDOException $e) {
        $issues[] = 'SELECT 1 failed: ' . $e->getMessage();
    }

    // Detectar sql_mode
    try {
        $modeStmt = $pdo->query("SELECT @@SESSION.sql_mode as sql_mode");
        $dbInfo['sql_mode'] = $modeStmt->fetchColumn();
    } catch (\PDOException $e) {}

    // Comprobar tablas clave
    $tableChecks = array_keys($checks['db']['tables']);
    foreach ($tableChecks as $tbl) {
        try {
            $existsStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
            $existsStmt->execute([$tbl]);
            $checks['db']['tables'][$tbl] = ((int)$existsStmt->fetchColumn() > 0);
        } catch (\PDOException $e) {
            $checks['db']['tables'][$tbl] = false;
        }
    }

    // Conteos básicos si tablas existen
    if ($checks['db']['tables']['users']) {
        try { $checks['db']['counts']['users'] = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(); } catch (\PDOException $e) {}
    }
    if ($checks['db']['tables']['leads']) {
        try { $checks['db']['counts']['leads'] = (int)$pdo->query('SELECT COUNT(*) FROM leads')->fetchColumn(); } catch (\PDOException $e) {}
    }

} catch (\Throwable $e) {
    $issues[] = 'DB connection error: ' . $e->getMessage();
}

$durationMs = (int)((microtime(true) - $startTs) * 1000);

$status = [
    'success' => !in_array(false, [
        $checks['env_loaded'],
        $checks['paths']['storage_writable'],
        $checks['paths']['logs_writable'],
        $checks['paths']['uploads_writable'],
        $checks['db']['connected']
    ], true),
    'message' => 'Deep health diagnostics',
    'timestamp' => date('Y-m-d H:i:s'),
    'server' => $_SERVER['SERVER_NAME'] ?? 'unknown',
    'php_version' => PHP_VERSION,
    'duration_ms' => $durationMs,
    'checks' => $checks,
    'db_info' => $dbInfo,
    'issues' => $issues
];

// Log a storage/logs/health.log si es posible
try {
    if (is_dir($logs) && is_writable($logs)) {
        @file_put_contents($logs . '/health.log', json_encode($status, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    }
} catch (\Throwable $e) {}

http_response_code($status['success'] ? 200 : 500);
echo json_encode($status, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>