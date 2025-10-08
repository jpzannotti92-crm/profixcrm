<?php
// Conectar logger global para robustecer manejo de errores
try {
    require_once __DIR__ . '/../enhanced_error_logger.php';
} catch (Throwable $e) {
    // Fallback silencioso
}

// Configuración de errores coherente
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
$errorLogPath = __DIR__ . '/../storage/logs/php_errors.log';
if (!is_dir(dirname($errorLogPath))) {
    @mkdir(dirname($errorLogPath), 0755, true);
}
ini_set('error_log', $errorLogPath);
// Force dev skip of Composer platform check when using PHP built-in server
if (!getenv('DEV_SKIP_PLATFORM_CHECK') && PHP_SAPI === 'cli-server') {
    putenv('DEV_SKIP_PLATFORM_CHECK=1');
    $_ENV['DEV_SKIP_PLATFORM_CHECK'] = '1';
    $_SERVER['DEV_SKIP_PLATFORM_CHECK'] = '1';
}
// Router for PHP built-in server to support SPA client-side routing while preserving /api/* and static assets

// Enable dev overrides to skip Composer platform check if configured
if (!getenv('DEV_SKIP_PLATFORM_CHECK')) {
    // allow override via query param for quick testing
    if (isset($_GET['dev_skip_platform_check']) && $_GET['dev_skip_platform_check'] === '1') {
        putenv('DEV_SKIP_PLATFORM_CHECK=1');
        $_ENV['DEV_SKIP_PLATFORM_CHECK'] = '1';
        $_SERVER['DEV_SKIP_PLATFORM_CHECK'] = '1';
    }
}

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$isApi = (strpos($uri, '/api/') === 0);

// === Robust error handling to avoid white screens ===
$logFile = __DIR__ . '/../storage/logs/php_errors.log';
function robust_log($msg, $context = []) {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    if (!empty($context)) {
        $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
    }
    $target = __DIR__ . '/../storage/logs/php_errors.log';
    @mkdir(dirname($target), 0777, true);
    @file_put_contents($target, $line . "\n", FILE_APPEND);
}

function send_api_error($status, $message, $extra = []) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($status);
    echo json_encode(array_merge([
        'ok' => false,
        'error' => $message,
        'status' => $status,
        'path' => urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)),
    ], $extra), JSON_UNESCAPED_UNICODE);
}

function send_spa_error($status) {
    http_response_code($status);
    // Prefer custom error handler if present
    $handler = __DIR__ . '/error_handler.php';
    if (is_file($handler)) {
        require $handler;
        return;
    }
    // Fallback to serving SPA index if available
    $index = __DIR__ . '/index.html';
    if (is_file($index)) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile($index);
        return;
    }
    header('Content-Type: text/plain; charset=UTF-8');
    echo ($status === 403 ? '403 Forbidden' : '404/500 Error') . "\n";
}

// Identify asset requests to avoid serving SPA fallback for missing static files
function is_asset_request($uri) {
    $ext = strtolower(pathinfo($uri, PATHINFO_EXTENSION));
    $assetExts = ['js','css','map','ico','png','jpg','jpeg','gif','svg','webp','json','woff','woff2','ttf','otf'];
    if (in_array($ext, $assetExts)) {
        return true;
    }
    return (strpos($uri, '/assets/') === 0)
        || (strpos($uri, '/uploads/') === 0)
        || (strpos($uri, '/js/') === 0)
        || (strpos($uri, '/css/') === 0)
        || (strpos($uri, '/images/') === 0)
        || (strpos($uri, '/fonts/') === 0);
}

set_exception_handler(function($e) use ($isApi) {
    robust_log('Uncaught exception', ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
    if ($isApi) {
        send_api_error(500, 'Server exception', ['detail' => $e->getMessage()]);
    } else {
        send_spa_error(500);
    }
    exit;
});

set_error_handler(function($severity, $message, $file, $line) use ($isApi) {
    // Log and continue for non-fatal errors; shutdown handler will catch fatals
    robust_log('PHP error', ['severity' => $severity, 'message' => $message, 'file' => $file, 'line' => $line]);
});

register_shutdown_function(function() use ($isApi) {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        robust_log('Fatal shutdown error', $error);
        if ($isApi) {
            send_api_error(500, 'Fatal error', ['detail' => $error['message']]);
        } else {
            send_spa_error(500);
        }
    }
});

$fullPath = __DIR__ . $uri;

// If the requested path is a real file (asset, php, etc.), let the server handle it
if ($uri !== '/' && file_exists($fullPath) && !is_dir($fullPath)) {
    return false; // Serve the requested resource as-is
}

// Harden asset handling: if it's an asset request but the file doesn't exist, return 404 instead of SPA fallback
if (is_asset_request($uri)) {
    if (!file_exists($fullPath) || is_dir($fullPath)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Asset not found: ' . $uri;
        return true;
    }
}

// Friendly API endpoints without .php extension -> map to actual scripts
// This allows requests like /api/config, /api/auth/login, etc. to work in dev
if (preg_match('#^/api/#', $uri)) {
    $friendlyApiMap = [
        '/api/config' => '/api/config.php',
        '/api/health' => '/api/health.php',
        '/api/health-deep' => '/api/health-deep.php',
        '/api/dashboard' => '/api/dashboard.php',
        '/api/leads' => '/api/leads.php',
        '/api/users' => '/api/users.php',
        '/api/roles' => '/api/roles.php',
        '/api/desks' => '/api/desks.php',
        '/api/states' => '/api/states.php',
        '/api/state-transitions' => '/api/state-transitions.php',
        '/api/lead-state-change' => '/api/lead-state-change.php',
        '/api/import' => '/api/import-simple.php',
        '/api/auth/login' => '/api/auth/login.php',
        '/api/auth/verify' => '/api/auth/verify.php',
        '/api/auth/logout' => '/api/auth/logout.php',
    ];
    if (isset($friendlyApiMap[$uri])) {
        $target = __DIR__ . $friendlyApiMap[$uri];
        if (file_exists($target)) {
            // Ensure JSON for API endpoints
            header('Content-Type: application/json; charset=utf-8');
            require $target;
            return true;
        } else {
            send_api_error(404, 'Friendly API endpoint mapped but target not found', ['mapped_to' => $friendlyApiMap[$uri]]);
            return true;
        }
    }
}

// If request targets /api/* and file does not exist, return 404 explicitly
if (preg_match('#^/api/.*#', $uri)) {
    // If an API file exists, let the server serve it (handled by the block above)
    send_api_error(404, 'API endpoint not found');
    return true;
}

// Legacy endpoints without /api prefix compatibility
$compatMap = [
    '/leads_simple.php' => '/api/leads_simple.php',
    '/leads.php' => '/api/leads.php',
    '/trading-accounts.php' => '/api/trading-accounts.php',
    '/dashboard.php' => '/api/dashboard.php',
    '/lead-trading-link.php' => '/api/lead-trading-link.php',
    '/deposits-withdrawals.php' => '/api/deposits-withdrawals.php',
    '/employee-stats.php' => '/api/employee-stats.php',
    '/employee-activities.php' => '/api/employee-activities.php',
    '/users.php' => '/api/users.php',
    '/roles.php' => '/api/roles.php',
    '/desks.php' => '/api/desks.php',
    '/lead-import.php' => '/api/lead-import.php',
    '/user-permissions.php' => '/api/user-permissions.php',
];
if (isset($compatMap[$uri])) {
    $target = __DIR__ . $compatMap[$uri];
    if (file_exists($target)) {
        // Execute the API script directly
        require $target;
        return true;
    } else {
        send_api_error(404, 'Legacy endpoint mapped but target not found', ['mapped_to' => $compatMap[$uri]]);
        return true;
    }
}

// Otherwise, serve the SPA entry point so React Router can handle the route
$index = __DIR__ . '/index.html';
if (file_exists($index)) {
    // Ensure HTML content type
    header('Content-Type: text/html; charset=UTF-8');
    readfile($index);
    return true;
}

// Fallback to index.php if index.html is missing
$phpIndex = __DIR__ . '/index.php';
if (file_exists($phpIndex)) {
    require $phpIndex;
    return true;
}

http_response_code(500);
send_spa_error(500);
return true;