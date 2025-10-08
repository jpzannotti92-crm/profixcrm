<?php
// Simple Web Deployment Assistant (non-destructive, step-by-step)
// Accessible via URL: /deploy-assistant.php
// Helps verify server readiness and orchestrate minimal deployment steps.

declare(strict_types=1);

$ROOT = dirname(__DIR__);
$LOG_DIR = $ROOT . DIRECTORY_SEPARATOR . 'logs';
$DEPLOY_LOG = $LOG_DIR . DIRECTORY_SEPARATOR . 'deployment.log';
$PUBLIC_DIR = __DIR__;

@date_default_timezone_set('UTC');

function log_line(string $line): void {
    global $DEPLOY_LOG;
    if (!is_dir(dirname($DEPLOY_LOG))) {
        @mkdir(dirname($DEPLOY_LOG), 0775, true);
    }
    @file_put_contents($DEPLOY_LOG, '[' . date('Y-m-d H:i:s') . '] ' . $line . PHP_EOL, FILE_APPEND);
}

function safe_shell(string $cmd): array {
    log_line('CMD: ' . $cmd);
    $output = [];
    $return_var = 0;
    @exec($cmd . ' 2>&1', $output, $return_var);
    return ['ok' => $return_var === 0, 'code' => $return_var, 'out' => implode("\n", $output)];
}

function check_requirements(): array {
    $phpVersion = PHP_VERSION;
    $extensions = ['pdo', 'pdo_mysql', 'openssl', 'mbstring', 'json', 'curl'];
    $extStatus = [];
    foreach ($extensions as $ext) {
        $extStatus[$ext] = extension_loaded($ext);
    }

    $composer = safe_shell('composer --version');
    $composerAvailable = $composer['ok'] || (strpos(strtolower($composer['out']), 'composer') !== false);

    // Basic paths
    $paths = [
        'public_uploads' => __DIR__ . '/uploads',
        'storage_logs' => dirname(__DIR__) . '/storage/logs',
        'storage_cache' => dirname(__DIR__) . '/storage/cache',
        'storage_sessions' => dirname(__DIR__) . '/storage/sessions',
    ];
    $pathsWritable = [];
    foreach ($paths as $k => $p) {
        if (!is_dir($p)) @mkdir($p, 0775, true);
        $pathsWritable[$k] = is_writable($p);
    }

    $envExists = is_file(dirname(__DIR__) . '/.env') || is_file(dirname(__DIR__) . '/.env.production');

    return [
        'php_version' => $phpVersion,
        'php_ok' => version_compare($phpVersion, '8.0.0', '>='), // recomendado 8.2+, mínimo 8.0
        'extensions' => $extStatus,
        'composer_available' => $composerAvailable,
        'paths_writable' => $pathsWritable,
        'env_exists' => $envExists,
        'composer_raw' => $composer,
    ];
}

function write_env(array $env): array {
    $lines = [];
    foreach ($env as $k => $v) {
        $lines[] = $k . '=' . $v;
    }
    $content = implode("\n", $lines) . "\n";
    $target = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
    $ok = @file_put_contents($target, $content) !== false;
    log_line('WRITE .env to ' . $target . ' -> ' . ($ok ? 'OK' : 'FAIL'));
    return ['ok' => $ok, 'path' => $target];
}

function run_composer_install(): array {
    return safe_shell('composer install --no-dev --optimize-autoloader');
}

function run_migrations(): array {
    $root = dirname(__DIR__);
    $results = [];
    if (is_file($root . '/run_migration.php')) {
        $results['run_migration.php'] = safe_shell('php "' . $root . '/run_migration.php"');
    }
    if (is_file($root . '/database/install.php')) {
        $results['database/install.php'] = safe_shell('php "' . $root . '/database/install.php"');
    }
    return $results;
}

function health_check(): array {
    $url = rtrim((($_SERVER['HTTPS'] ?? '') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'], '/') . '/api/health.php';
    $ctx = stream_context_create(['http' => ['timeout' => 10]]);
    $body = @file_get_contents($url, false, $ctx);
    $ok = $body !== false && (strpos($body, 'success') !== false);
    return ['ok' => $ok, 'url' => $url, 'body' => $body ?: ''];
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$message = '';
$data = [];

if ($action === 'save_env') {
    $env = [
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
        'APP_URL' => trim($_POST['app_url'] ?? ''),
        'DB_CONNECTION' => 'mysql',
        'DB_HOST' => trim($_POST['db_host'] ?? 'localhost'),
        'DB_PORT' => trim($_POST['db_port'] ?? '3306'),
        'DB_DATABASE' => trim($_POST['db_name'] ?? ''),
        'DB_USERNAME' => trim($_POST['db_user'] ?? ''),
        'DB_PASSWORD' => trim($_POST['db_pass'] ?? ''),
        'SESSION_LIFETIME' => '180',
        'FORCE_HTTPS' => 'true',
    ];
    $res = write_env($env);
    $message = $res['ok'] ? 'Archivo .env guardado correctamente' : 'No se pudo guardar el archivo .env';
    $data = $res;
}
elseif ($action === 'composer_install') {
    $res = run_composer_install();
    $message = $res['ok'] ? 'Composer install ejecutado correctamente' : 'Fallo al ejecutar composer install';
    $data = $res;
}
elseif ($action === 'run_migrations') {
    $res = run_migrations();
    $ok = true;
    foreach ($res as $r) { if (!$r['ok']) { $ok = false; break; } }
    $message = $ok ? 'Migraciones/instalación ejecutadas' : 'Alguna migración/instalación falló';
    $data = $res;
}
elseif ($action === 'health') {
    $res = health_check();
    $message = $res['ok'] ? 'Health OK' : 'Health con problemas';
    $data = $res;
}

$req = check_requirements();

?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Asistente de Despliegue</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu; margin:0; background:#0f172a; color:#e5e7eb}
    header{padding:16px 24px; background:#111827; border-bottom:1px solid #1f2937}
    h1{font-size:20px; margin:0}
    main{padding:24px; max-width:980px; margin:0 auto}
    .card{background:#111827; border:1px solid #1f2937; border-radius:10px; padding:16px; margin-bottom:16px}
    .row{display:flex; gap:12px; flex-wrap:wrap}
    .col{flex:1 1 300px}
    .ok{color:#22c55e} .warn{color:#f59e0b} .err{color:#ef4444}
    .btn{background:#2563eb; color:white; border:none; padding:10px 14px; border-radius:8px; cursor:pointer}
    .btn.secondary{background:#374151}
    input,textarea{width:100%; padding:8px 10px; border-radius:8px; border:1px solid #374151; background:#0b1220; color:#e5e7eb}
    label{display:block; margin-bottom:6px; font-size:12px; color:#9ca3af}
    .muted{color:#9ca3af; font-size:12px}
    pre{white-space:pre-wrap; background:#0b1220; padding:12px; border-radius:8px; border:1px solid #1f2937}
  </style>
  </head>
<body>
  <header>
    <h1>Asistente de Despliegue</h1>
  </header>
  <main>
    <?php if (!empty($message)): ?>
      <div class="card" style="border-color:#2563eb">
        <div><strong>Resultado:</strong> <?= htmlspecialchars($message) ?></div>
        <?php if (!empty($data)): ?><pre><?= htmlspecialchars(print_r($data, true)) ?></pre><?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="card">
      <h2>1) Verificación del servidor</h2>
      <div class="row">
        <div class="col"><strong>PHP</strong><br />
          Versión: <?= htmlspecialchars($req['php_version']) ?>
          <div class="muted">Mínimo 8.0, recomendado 8.2+</div>
          <div class="<?= $req['php_ok'] ? 'ok' : 'err' ?>"><?= $req['php_ok'] ? 'OK' : 'Actualiza PHP' ?></div>
        </div>
        <div class="col"><strong>Composer</strong><br />
          <div class="<?= $req['composer_available'] ? 'ok' : 'warn' ?>"><?= $req['composer_available'] ? 'Detectado' : 'No detectado' ?></div>
        </div>
        <div class="col"><strong>Extensiones</strong><br />
          <?php foreach ($req['extensions'] as $ext => $ok): ?>
            <div class="<?= $ok ? 'ok' : 'err' ?>"><?= htmlspecialchars($ext) ?>: <?= $ok ? 'OK' : 'Falta' ?></div>
          <?php endforeach; ?>
        </div>
        <div class="col"><strong>Permisos</strong><br />
          <?php foreach ($req['paths_writable'] as $k => $ok): ?>
            <div class="<?= $ok ? 'ok' : 'err' ?>"><?= htmlspecialchars($k) ?>: <?= $ok ? 'escribible' : 'no escribible' ?></div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="card">
      <h2>2) Configurar entorno (.env)</h2>
      <div class="muted">Se guardará en el servidor sin tocar tu repositorio local.</div>
      <form method="post">
        <input type="hidden" name="action" value="save_env" />
        <label>APP_URL<input name="app_url" value="<?= htmlspecialchars( (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']) ?>" /></label>
        <label>DB_HOST<input name="db_host" value="localhost" /></label>
        <label>DB_PORT<input name="db_port" value="3306" /></label>
        <label>DB_NAME<input name="db_name" value="" /></label>
        <label>DB_USER<input name="db_user" value="" /></label>
        <label>DB_PASSWORD<input type="password" name="db_pass" value="" /></label>
        <button class="btn" type="submit">Guardar .env</button>
      </form>
      <div class="muted">Estado: <?= $req['env_exists'] ? '<span class="ok">.env/.env.production encontrado</span>' : '<span class="warn">.env no encontrado</span>' ?></div>
    </div>

    <div class="card">
      <h2>3) Instalar dependencias</h2>
      <form method="post" onsubmit="return confirm('¿Ejecutar composer install en servidor?');">
        <input type="hidden" name="action" value="composer_install" />
        <button class="btn" type="submit" <?= !$req['composer_available'] ? 'disabled' : '' ?>>composer install</button>
        <span class="muted">Requiere Composer en servidor</span>
      </form>
    </div>

    <div class="card">
      <h2>4) Migraciones / Instalación BD</h2>
      <form method="post">
        <input type="hidden" name="action" value="run_migrations" />
        <button class="btn" type="submit">Ejecutar migraciones</button>
        <span class="muted">Usa run_migration.php y/o database/install.php si existen</span>
      </form>
    </div>

    <div class="card">
      <h2>5) Verificar salud</h2>
      <form method="post">
        <input type="hidden" name="action" value="health" />
        <button class="btn" type="submit">Probar /api/health.php</button>
      </form>
    </div>

    <div class="card">
      <h2>Notas</h2>
      <ul>
        <li>Este asistente escribe únicamente archivos de entorno (.env) y ejecuta comandos estándar si están disponibles.</li>
        <li>No modifica el código de la aplicación ni el repositorio local.</li>
        <li>Recomendado ejecutar sobre HTTPS y restringir acceso por IP/rol.</li>
      </ul>
    </div>

  </main>
</body>
</html>