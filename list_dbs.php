<?php
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

function tryList($host, $port, $user, $pass) {
    $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $stmt = $pdo->query('SHOW DATABASES');
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$host = envc('DB_HOST', 'localhost');
$port = envc('DB_PORT', '3306');
$user = envc('DB_USERNAME', envc('DB_USER', 'root'));
$pass = envc('DB_PASSWORD', envc('DB_PASS', ''));

try {
    $dbs = tryList($host, $port, $user, $pass);
    echo "=== BASES DE DATOS DISPONIBLES (credenciales actuales) ===\n";
    foreach ($dbs as $db) { echo "- {$db}\n"; }
} catch (Throwable $e) {
    // Fallback a root/blank
    try {
        $dbs = tryList($host, $port, 'root', '');
        echo "=== BASES DE DATOS DISPONIBLES (fallback root) ===\n";
        foreach ($dbs as $db) { echo "- {$db}\n"; }
    } catch (Throwable $e2) {
        echo "Error conectando al servidor MySQL: " . $e2->getMessage() . "\n";
    }
}
?>