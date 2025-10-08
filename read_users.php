<?php
// Lectura segura de la tabla users con fallback de conexión
$root = __DIR__;

$parseEnv = function($file) {
    if (!file_exists($file)) return;
    $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $val = trim($parts[1], " \t\n\r\0\x0B\"'" );
            putenv("{$key}={$val}");
            $_ENV[$key] = $val;
        }
    }
};
$parseEnv($root.'/.env');
$parseEnv($root.'/.env.production');

function envc($key, $default = null) {
    $val = getenv($key);
    if ($val === false && isset($_ENV[$key])) $val = $_ENV[$key];
    return ($val !== false && $val !== null && $val !== '') ? $val : $default;
}

function connect($root) {
    $envHost = envc('DB_HOST', 'localhost');
    $envPort = envc('DB_PORT', '3306');
    $envName = envc('DB_DATABASE', envc('DB_NAME', 'spin2pay_profixcrm'));
    $envUser = envc('DB_USERNAME', envc('DB_USER', 'root'));
    $envPass = envc('DB_PASSWORD', envc('DB_PASS', ''));
    $envDsn = "mysql:host={$envHost};port={$envPort};dbname={$envName};charset=utf8mb4";
    try {
        return new PDO($envDsn, $envUser, $envPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (Throwable $e) {
        $msg = (string)$e->getMessage();
        $configPath = $root . '/config/config.php';
        $conf = file_exists($configPath) ? require $configPath : [];
        $cfgHost = $conf['database']['host'] ?? 'localhost';
        $cfgPort = $conf['database']['port'] ?? '3306';
        $cfgName = $conf['database']['name'] ?? 'iatrade_crm';
        $cfgUser = $conf['database']['username'] ?? 'root';
        $cfgPass = $conf['database']['password'] ?? '';
        $cfgDsn = "mysql:host={$cfgHost};port={$cfgPort};dbname={$cfgName};charset=utf8mb4";
        return new PDO($cfgDsn, $cfgUser, $cfgPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }
}

try {
    $pdo = connect($root);
    echo "=== ESTRUCTURA USERS ===\n";
    $cols = $pdo->query('DESCRIBE users')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        echo $c['Field'] . " | " . $c['Type'] . "\n";
    }
    echo "\n=== ADMIN (si existe) ===\n";
    $st = $pdo->prepare("SELECT id, username, email, status, password_hash, password FROM users WHERE username = ? OR email = ? LIMIT 1");
    $st->execute(['admin', 'admin@iatrade.com']);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        foreach ($row as $k => $v) {
            echo $k . ': ' . ($v ?? 'NULL') . "\n";
        }
    } else {
        echo "No se encontró admin\n";
    }
    echo "\n=== TOTAL USUARIOS ===\n";
    $cnt = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    echo "Total: " . (int)$cnt . "\n";
} catch (Throwable $e) {
    echo "Error leyendo users: " . $e->getMessage() . "\n";
}
?>