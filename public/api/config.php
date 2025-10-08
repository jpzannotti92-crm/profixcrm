<?php
require_once __DIR__ . '/bootstrap.php';

// Intentar cargar Composer de forma tolerante (si existe)
$autoloadCandidates = [
    __DIR__ . '/../../vendor/autoload.php',            // proyecto raíz/vendor
    __DIR__ . '/../../../vendor/autoload.php',         // si public_html == public
    __DIR__ . '/../../deploy/vendor/autoload.php',     // vendor empaquetado en deploy
];
foreach ($autoloadCandidates as $autoload) {
    if (is_file($autoload)) {
        require_once $autoload;
        break;
    }
}

// Cargar UrlHelper si está disponible, pero no hacer fatal si falta
$helperCandidates = [
    __DIR__ . '/../../src/helpers/UrlHelper.php',
    __DIR__ . '/../../../src/helpers/UrlHelper.php',
    __DIR__ . '/../helpers/UrlHelper.php',
];
foreach ($helperCandidates as $helper) {
    if (is_file($helper)) {
        require_once $helper;
        break;
    }
}

// Definir un helper mínimo si la clase real no existe
if (!class_exists('IaTradeCRM\\Helpers\\UrlHelper')) {
    class UrlHelperFallback {
        public static function detectBaseUrl(): string {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
            $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/';
            $dir = rtrim(str_replace(basename($scriptName), '', $scriptName), '/');
            // Si está sirviendo desde raíz, usar solo host
            return $scheme . '://' . $host;
        }
        public static function getFrontendUrl(): string {
            return self::detectBaseUrl();
        }
        public static function getUrlConfig(): array {
            $base = self::detectBaseUrl();
            return [
                'base' => $base,
                'api' => $base . '/api',
                'frontend' => $base,
                'login' => $base . '/auth/login',
                'dashboard' => $base . '/dashboard',
                'is_local' => (stripos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false)
            ];
        }
    }
    $UrlHelperClass = 'UrlHelperFallback';
} else {
    $UrlHelperClass = 'IaTradeCRM\\Helpers\\UrlHelper';
}

// Cargar variables de entorno si Dotenv está disponible (sin fatal si no)
if (class_exists('Dotenv\\Dotenv')) {
    try {
        $dotenv = Dotenv\Dotenv::createMutable(__DIR__ . '/../../');
        $dotenv->load();
    } catch (Throwable $e) {
        error_log('Error cargando variables de entorno: ' . $e->getMessage());
    }
} elseif (is_file(__DIR__ . '/../../.env') || is_file(__DIR__ . '/../../.env.production')) {
    // Cargar variables básicas de .env manualmente si existe
    $envPath = is_file(__DIR__ . '/../../.env') ? __DIR__ . '/../../.env' : __DIR__ . '/../../.env.production';
    foreach (@file($envPath) ?: [] as $line) {
        if (preg_match('/^\s*([A-Z0-9_]+)\s*=\s*(.*)\s*$/i', $line, $m)) {
            $key = $m[1];
            $val = trim($m[2], "\"' ");
            $_ENV[$key] = $_ENV[$key] ?? $val;
        }
    }
}

header('Content-Type: application/json');

// Configurar CORS dinámicamente según el origen de la solicitud
$origin = $_SERVER['HTTP_ORIGIN'] ?? null;
if ($origin) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: ' . $UrlHelperClass::getFrontendUrl());
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    // Obtener configuración dinámica de URLs
    $urlConfig = $UrlHelperClass::getUrlConfig();

    $config = [
        'app' => [
            'name' => $_ENV['APP_NAME'] ?? 'iaTrade CRM',
            'version' => '1.0.0',
            'environment' => $_ENV['APP_ENV'] ?? 'production'
        ],
        'urls' => $urlConfig,
        'features' => [
            'trading' => true,
            'reports' => true,
            'notifications' => true,
            'cache' => true,
            'minification' => true
        ],
        'limits' => [
            'max_upload_size' => '10MB',
            'session_timeout' => 3600,
            'max_requests_per_minute' => 100
        ],
        'security' => [
            'csrf_protection' => true,
            'rate_limiting' => true,
            'ssl_required' => true
        ]
    ];

    echo json_encode([
        'success' => true,
        'data' => $config
    ], JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    // Respuesta tolerante: devolver una configuración mínima en caso de error
    $baseUrl = $UrlHelperClass::detectBaseUrl();
    echo json_encode([
        'success' => true,
        'warning' => 'Configuración mínima servida por error interno: ' . $e->getMessage(),
        'data' => [
            'app' => [
                'name' => 'iaTrade CRM',
                'version' => '1.0.0',
                'environment' => 'production'
            ],
            'urls' => [
                'base' => $baseUrl,
                'api' => $baseUrl . '/api',
                'frontend' => $baseUrl,
                'login' => $baseUrl . '/auth/login',
                'dashboard' => $baseUrl . '/dashboard',
                'is_local' => true
            ],
            'features' => [
                'trading' => true,
                'reports' => true,
                'notifications' => true,
                'cache' => false,
                'minification' => false
            ],
            'limits' => [
                'max_upload_size' => '10MB',
                'session_timeout' => 3600,
                'max_requests_per_minute' => 100
            ],
            'security' => [
                'csrf_protection' => true,
                'rate_limiting' => false,
                'ssl_required' => false
            ]
        ]
    ], JSON_PRETTY_PRINT);
}
?>