<?php
// Corrige códigos en blanco en la tabla permissions, derivándolos del campo name

// Cargar .env mínimo
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

    // Verificar existencia de columna code
    $col = $pdo->query("SHOW COLUMNS FROM permissions LIKE 'code'");
    if (!$col || !$col->fetch()) {
        echo "La tabla permissions no tiene columna 'code'. Nada que corregir.\n";
        exit(0);
    }

    // Actualizar códigos en blanco o NULL derivados de name
    $update = $pdo->prepare("UPDATE permissions SET code = REPLACE(LOWER(name), '.', '_') WHERE code IS NULL OR code = ''");
    $update->execute();
    echo "Actualizados códigos en blanco basados en name. Filas afectadas: " . $update->rowCount() . "\n";

    // Mostrar resumen
    $rows = $pdo->query("SELECT id, code, name FROM permissions ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $c = $r['code'] ?? '';
        echo "{$r['id']}\t{$c}\t{$r['name']}\n";
    }
} catch (Exception $e) {
    echo "Error corrigiendo códigos: " . $e->getMessage() . "\n";
}

?>