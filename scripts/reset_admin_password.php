<?php
// Reset de contraseña admin robusto para producción
// Uso (en servidor):
//   php scripts/reset_admin_password.php --username admin --password "admin123"

// Cargar .env / .env.production desde docroot (directorio padre)
$docroot = dirname(__DIR__);
$envCandidates = [
    $docroot . '/.env',
    $docroot . '/.env.production',
];
foreach ($envCandidates as $envPath) {
    if (is_file($envPath)) {
        $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (is_array($lines)) {
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || strpos($line, '#') === 0) { continue; }
                $parts = explode('=', $line, 2);
                if (count($parts) === 2) {
                    $k = trim($parts[0]);
                    $v = trim($parts[1], " \t\n\r\0\x0B\"'" );
                    if ($k !== '') { $_ENV[$k] = $v; putenv("$k=$v"); }
                }
            }
        }
        break;
    }
}

// Parseo simple de args
$username = 'admin';
$newPassword = null;
foreach ($argv as $i => $arg) {
    if (substr($arg, 0, 11) === '--username=') { $username = substr($arg, 11); }
    elseif ($arg === '--username' && isset($argv[$i+1])) { $username = $argv[$i+1]; }
    elseif (substr($arg, 0, 11) === '--password=') { $newPassword = substr($arg, 11); }
    elseif ($arg === '--password' && isset($argv[$i+1])) { $newPassword = $argv[$i+1]; }
}

if (!$newPassword) {
    fwrite(STDERR, "Error: debes indicar --password\n");
    exit(2);
}

// Config DB robusta: soporta DB_USERNAME/DB_USER y DB_PASSWORD/DB_PASS y DB_DATABASE/DB_NAME
$dbHost = $_ENV['DB_HOST'] ?? 'localhost';
$dbPort = $_ENV['DB_PORT'] ?? '3306';
$dbName = $_ENV['DB_DATABASE'] ?? ($_ENV['DB_NAME'] ?? '');
$dbUser = $_ENV['DB_USERNAME'] ?? ($_ENV['DB_USER'] ?? '');
$dbPass = $_ENV['DB_PASSWORD'] ?? ($_ENV['DB_PASS'] ?? '');

if ($dbName === '' || $dbUser === '') {
    fwrite(STDERR, "Error: configuración DB incompleta (DB_NAME/DB_DATABASE y DB_USER/DB_USERNAME requeridos)\n");
    exit(3);
}

try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // Detectar columna de contraseña
    $hasPasswordHash = false; $hasPassword = false;
    try {
        $st = $pdo->query("SHOW COLUMNS FROM users LIKE 'password_hash'");
        $hasPasswordHash = $st && $st->fetch() ? true : false;
    } catch (Throwable $e) {}
    try {
        $st = $pdo->query("SHOW COLUMNS FROM users LIKE 'password'");
        $hasPassword = $st && $st->fetch() ? true : false;
    } catch (Throwable $e) {}

    if (!$hasPasswordHash && !$hasPassword) {
        fwrite(STDERR, "Error: no se encontró columna de contraseña en tabla users\n");
        exit(4);
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);

    // Actualizar en la(s) columna(s) disponible(s)
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
        echo "✓ Contraseña actualizada para usuario '{$username}'.\n";
        echo "Nueva contraseña: {$newPassword}\n";
    } else {
        echo "⚠️ No se actualizó ninguna fila. Verifica que el usuario exista.\n";
        exit(5);
    }

    // Marcar usuario activo por si estaba inactivo
    try {
        $pdo->prepare("UPDATE users SET active = 1 WHERE username = ? OR email = ?")
            ->execute([$username, $username]);
    } catch (Throwable $e) {}

} catch (Throwable $e) {
    fwrite(STDERR, "Error de DB: " . $e->getMessage() . "\n");
    exit(6);
}
?>