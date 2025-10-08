<?php
// Diagnóstico de la tabla permissions usando .env

// Cargar .env sin Composer
$envFile = __DIR__ . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(ltrim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $k = trim($parts[0]);
            $v = trim($parts[1], " \t\n\r\0\x0B\"'" );
            $_ENV[$k] = $v; putenv("$k=$v");
        }
    }
}

$host = $_ENV['DB_HOST'] ?? 'localhost';
$db   = $_ENV['DB_DATABASE'] ?? ($_ENV['DB_NAME'] ?? 'iatrade_crm');
$user = $_ENV['DB_USERNAME'] ?? ($_ENV['DB_USER'] ?? 'root');
$pass = $_ENV['DB_PASSWORD'] ?? '';

try {
    $pdo = new PDO("mysql:host={$host};dbname={$db};charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== DESCRIBE permissions ===\n";
    $desc = $pdo->query("DESCRIBE permissions")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($desc as $row) {
        echo sprintf("%s\t%s\tNULL:%s\tKEY:%s\tDEFAULT:%s\n",
            $row['Field'] ?? '', $row['Type'] ?? '', $row['Null'] ?? '', $row['Key'] ?? '', $row['Default'] ?? ''
        );
    }

    echo "\n=== Contenido permissions (id, code, name) ===\n";
    $rows = $pdo->query("SELECT id, code, name FROM permissions ORDER BY id LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $code = isset($r['code']) ? $r['code'] : '';
        echo "{$r['id']}\t{$code}\t{$r['name']}\n";
    }

    echo "\n=== Recuento por code ===\n";
    // Manejar tablas sin columna code
    try {
        $agg = $pdo->query("SELECT COALESCE(code,'') as code, COUNT(*) as cnt FROM permissions GROUP BY COALESCE(code,'') ORDER BY cnt DESC, code ASC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($agg as $a) {
            $c = $a['code'];
            if ($c === null) { $c = 'NULL'; }
            if ($c === '') { $c = "<vacío>"; }
            echo sprintf("%s => %d\n", $c, (int)$a['cnt']);
        }
    } catch (Exception $e) {
        echo "Tabla permissions no tiene columna 'code' o error: " . $e->getMessage() . "\n";
    }

} catch (Exception $e) {
    echo "Error diagnóstico permissions: " . $e->getMessage() . "\n";
}

?>