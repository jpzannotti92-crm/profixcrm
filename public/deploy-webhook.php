<?php
// Webhook de Autodespliegue Seguro para ProfixCRM
// URL: /public/deploy-webhook.php
// Requiere cabecera X-Deploy-Secret (definida en .env o .env.production)

declare(strict_types=1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Deploy-Secret');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); echo json_encode(['ok' => true]); exit;
}

$ROOT = dirname(__DIR__);
$PUBLIC = __DIR__;
$start = microtime(true);

function log_line(string $line): void {
    global $ROOT;
    $logDir = $ROOT . '/logs';
    if (!is_dir($logDir)) { @mkdir($logDir, 0775, true); }
    @file_put_contents($logDir . '/deploy_webhook.log', '[' . date('Y-m-d H:i:s') . '] ' . $line . "\n", FILE_APPEND);
}

function safe_exec(string $cmd): array {
    log_line('CMD: ' . $cmd);
    $out = [];
    $code = 0;
    @exec($cmd . ' 2>&1', $out, $code);
    return ['ok' => $code === 0, 'code' => $code, 'out' => implode("\n", $out)];
}

function load_env(): array {
    global $ROOT;
    $vars = [];
    $files = [];
    if (is_file($ROOT . '/.env')) $files[] = $ROOT . '/.env';
    if (is_file($ROOT . '/.env.production')) $files[] = $ROOT . '/.env.production';
    foreach ($files as $f) {
        foreach (@file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (strpos(ltrim($line), '#') === 0) continue;
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) { $vars[trim($parts[0])] = trim($parts[1]); }
        }
    }
    return $vars;
}

function save_env_var(string $key, string $value): bool {
    global $ROOT;
    $file = $ROOT . '/.env.production';
    $content = '';
    if (is_file($file)) { $content = (string)@file_get_contents($file); }
    if (preg_match('/^' . preg_quote($key, '/') . '=.*/m', $content)) {
        $content = preg_replace('/^' . preg_quote($key, '/') . '=.*/m', $key . '=' . $value, $content);
    } else {
        $content .= (substr($content, -1) === "\n" ? '' : "\n") . $key . '=' . $value . "\n";
    }
    return @file_put_contents($file, $content) !== false;
}

// Seguridad del webhook
$env = load_env();
$secret = $env['DEPLOY_SECRET'] ?? ($env['HEALTH_SECRET'] ?? null);
$generated = false;
if (!$secret) {
    // Generar uno y guardarlo para futuros usos (solo si falta)
    $secret = bin2hex(random_bytes(24));
    save_env_var('DEPLOY_SECRET', $secret);
    $generated = true;
    log_line('DEPLOY_SECRET generado');
}

$clientSecret = $_SERVER['HTTP_X_DEPLOY_SECRET'] ?? '';
if (!$generated) {
    if (!hash_equals($secret, $clientSecret)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized: invalid deploy secret']);
        exit;
    }
} else {
    // Primera ejecución sin secreto previo: permitir SOLO esta vez
    log_line('Primera ejecución sin secreto previo: permitido');
}

// Detectar binarios
$gitBins = ['/usr/bin/git', '/usr/local/bin/git', '/usr/local/cpanel/3rdparty/bin/git'];
$composerBins = ['/usr/local/bin/composer', '/usr/bin/composer'];
$git = null; $composer = null;
foreach ($gitBins as $b) { if (is_executable($b)) { $git = $b; break; } }
foreach ($composerBins as $b) { if (is_executable($b)) { $composer = $b; break; } }
if (!$git) { $git = 'git'; }
if (!$composer) { $composer = 'composer'; }

$results = [];

// 1) Limpiar y actualizar repo desde origin/main
$results['git_fetch'] = safe_exec('cd "' . $ROOT . '" && ' . $git . ' fetch --all');
$results['git_reset'] = safe_exec('cd "' . $ROOT . '" && ' . $git . ' reset --hard origin/main');
$results['git_clean'] = safe_exec('cd "' . $ROOT . '" && ' . $git . ' clean -fd');

// 2) Composer install (no-dev)
$results['composer_install'] = safe_exec('cd "' . $ROOT . '" && ' . $composer . ' install --no-dev --prefer-dist');

// 3) Copiar .env y .htaccess si existen plantillas
if (is_file($ROOT . '/.env.production')) {
    @copy($ROOT . '/.env.production', $ROOT . '/.env');
    $results['env_copy'] = ['ok' => is_file($ROOT . '/.env'), 'src' => '.env.production', 'dst' => '.env'];
}
if (is_file($ROOT . '/.htaccess.production')) {
    @copy($ROOT . '/.htaccess.production', $ROOT . '/.htaccess');
    $results['htaccess_copy'] = ['ok' => is_file($ROOT . '/.htaccess'), 'src' => '.htaccess.production', 'dst' => '.htaccess'];
}

// 4) Asegurar permisos en storage/logs y uploads
@mkdir($ROOT . '/storage/logs', 0775, true);
@mkdir($PUBLIC . '/uploads', 0775, true);
$results['chmod'] = safe_exec('chmod -R 775 "' . $ROOT . '/storage" "' . $ROOT . '/storage/logs" "' . $PUBLIC . '/uploads"');

// 5) Opcional: limpiar index.html y assets viejos (ya ignorados por git)
if (is_file($ROOT . '/index.html')) { @unlink($ROOT . '/index.html'); }
if (is_dir($ROOT . '/assets')) { @array_map('unlink', glob($ROOT . '/assets/*')); }
if (is_file($PUBLIC . '/index.html')) { @unlink($PUBLIC . '/index.html'); }
if (is_dir($PUBLIC . '/assets')) { @array_map('unlink', glob($PUBLIC . '/assets/*')); }

// 6) Health checks
$healthUrl = rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost'), '/') . '/api/health';
$healthDeepUrl = rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost'), '/') . '/api/health-deep';

function http_get_json(string $url): array {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HEADER => false,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $ctype = curl_getinfo($ch, CURLINFO_CONTENT_TYPE); curl_close($ch);
    $json = (stripos((string)$ctype, 'application/json') !== false) ? (json_decode($body, true) ?: []) : [];
    return ['status' => $code, 'ctype' => $ctype, 'json' => $json, 'raw' => $body];
}

$results['health'] = http_get_json($healthUrl);
$results['health_deep'] = http_get_json($healthDeepUrl);

$ok = ($results['git_reset']['ok'] && $results['composer_install']['ok']);
$duration = (int)((microtime(true) - $start) * 1000);

http_response_code($ok ? 200 : 500);
echo json_encode([
    'success' => $ok,
    'message' => $ok ? 'Autodespliegue completado' : 'Autodespliegue con errores',
    'duration_ms' => $duration,
    'results' => $results,
]);
?>