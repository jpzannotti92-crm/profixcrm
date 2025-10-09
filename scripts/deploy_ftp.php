<?php
/**
 * Simple FTP Deploy Script
 *
 * Uso:
 *   php scripts/deploy_ftp.php --config deploy.ftp.json
 *   php scripts/deploy_ftp.php --config deploy.ftp.json --files "api/auth/verify.php,public/api/user-permissions.php"
 *   php scripts/deploy_ftp.php --dry-run
 *
 * Notas:
 * - No commitees credenciales. Usa deploy.ftp.json en local (gitignored).
 * - Soporta SSL (ftps) y modo pasivo.
 */

declare(strict_types=1);

function println(string $msg): void { echo $msg . PHP_EOL; }
function fatal(string $msg, int $code = 1): void { fwrite(STDERR, "Error: $msg\n"); exit($code); }

// Parse CLI args
$argvMap = [];
foreach ($argv as $i => $arg) {
    if ($i === 0) continue;
    if (strpos($arg, '--') === 0) {
        $parts = explode('=', $arg, 2);
        $key = ltrim($parts[0], '-');
        $argvMap[$key] = $parts[1] ?? '1';
    }
}

$configPath = $argvMap['config'] ?? __DIR__ . '/../deploy.ftp.json';
$dryRun = isset($argvMap['dry-run']);
$filesArg = $argvMap['files'] ?? '';

if (!file_exists($configPath)) {
    fatal("No se encontró el archivo de configuración: $configPath. Crea uno basado en deploy.ftp.json.example.");
}

$configJson = file_get_contents($configPath);
if ($configJson === false) fatal('No se pudo leer el archivo de configuración.');
$conf = json_decode($configJson, true);
if (!is_array($conf)) fatal('El archivo de configuración JSON es inválido.');

$host = $conf['host'] ?? null;
$port = (int)($conf['port'] ?? 21);
$user = $conf['username'] ?? null;
$pass = $conf['password'] ?? null;
$ssl = (bool)($conf['ssl'] ?? false);
$passive = (bool)($conf['passive'] ?? true);
$remoteBase = $conf['remote_base_path'] ?? '/public_html';

if (!$host || !$user || !$pass) fatal('Faltan credenciales en la configuración (host, username, password).');

// Build files list
$files = [];
if ($filesArg) {
    $list = array_filter(array_map('trim', explode(',', $filesArg)));
    foreach ($list as $localPath) {
        $files[] = [
            'local' => $localPath,
            'remote' => normalizeRemotePath($remoteBase, mapLocalToRemote($localPath, $remoteBase))
        ];
    }
} else {
    $files = $conf['files'] ?? [];
}

if (empty($files)) fatal('No hay archivos para subir. Usa --files o define files en deploy.ftp.json.');

// Validate local files
foreach ($files as $f) {
    $local = $f['local'] ?? '';
    if (!$local || !file_exists($local)) fatal("Archivo local no existe: $local");
}

println("Conectando a FTP: $host:$port (ssl=" . ($ssl ? 'yes' : 'no') . ", passive=" . ($passive ? 'yes' : 'no') . ")");

if ($dryRun) {
    println("[DRY-RUN] No se establecerá conexión. Mostrando plan de subida:");
    foreach ($files as $f) {
        println("  - " . $f['local'] . " -> " . ($f['remote'] ?? '(calc)'));
    }
    exit(0);
}

// Connect
if ($ssl) {
    $conn = @ftp_ssl_connect($host, $port, 15);
} else {
    $conn = @ftp_connect($host, $port, 15);
}
if (!$conn) fatal('No se pudo conectar al servidor FTP.');

if (!@ftp_login($conn, $user, $pass)) fatal('No se pudo iniciar sesión FTP. Verifica usuario/contraseña.');
@ftp_pasv($conn, $passive);

// Ensure base dir
ensureRemoteDir($conn, $remoteBase);

$okCount = 0;
foreach ($files as $f) {
    $local = $f['local'];
    $remote = $f['remote'] ?? normalizeRemotePath($remoteBase, mapLocalToRemote($local, $remoteBase));

    $remoteDir = dirname($remote);
    ensureRemoteDir($conn, $remoteDir);

    println("Subiendo: $local -> $remote");
    $res = @ftp_put($conn, $remote, $local, FTP_BINARY);
    if (!$res) {
        println("  ✖ Falló subida de $local");
    } else {
        println("  ✔ Subido");
        $okCount++;
    }
}

@ftp_close($conn);
println("Listo. Archivos subidos correctamente: $okCount / " . count($files));
exit($okCount === count($files) ? 0 : 2);

/**
 * Asegura que el directorio remoto exista; crea recursivamente si falta.
 */
function ensureRemoteDir($conn, string $remoteDir): void {
    $remoteDir = rtrim($remoteDir, '/');
    if ($remoteDir === '' || $remoteDir === '/') return;
    $parts = explode('/', ltrim($remoteDir, '/'));
    $path = '';
    foreach ($parts as $part) {
        $path .= '/' . $part;
        if (!@ftp_chdir($conn, $path)) {
            @ftp_mkdir($conn, $path);
        }
    }
}

/**
 * Normaliza ruta remota uniendo base + relativa.
 */
function normalizeRemotePath(string $base, string $relative): string {
    $base = rtrim($base, '/');
    if (strpos($relative, $base) === 0) return $relative; // ya incluye base
    return $base . '/' . ltrim($relative, '/');
}

/**
 * Mapea ruta local a una ruta remota relativa.
 * Reglas:
 * - "api/..." → "/api/..."
 * - "public/api/..." → "/api/..." (producción sirve /api directamente en docroot)
 * - En otro caso, conserva estructura relativa.
 */
function mapLocalToRemote(string $local, string $remoteBase): string {
    $local = str_replace('\\', '/', $local);
    if (strpos($local, 'public/api/') === 0) {
        return '/api/' . substr($local, strlen('public/api/'));
    }
    if (strpos($local, 'api/') === 0) {
        return '/' . $local; // "/api/..."
    }
    // Por defecto, subir bajo la misma estructura
    return '/' . ltrim($local, '/');
}