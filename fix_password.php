<?php
// Cargar variables de entorno
$envFile = __DIR__ . '/.env';
if (is_file($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(ltrim($line), '#') === 0) { continue; }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $k = trim($parts[0]);
            $v = trim($parts[1], " \t\n\r\0\x0B\"'" );
            $_ENV[$k] = $v;
            putenv("$k=$v");
        }
    }
}

require_once 'config/database.php';

try {
    $pdo = new PDO(
        'mysql:host=' . $_ENV['DB_HOST'] . ';port=' . $_ENV['DB_PORT'] . ';dbname=' . $_ENV['DB_DATABASE'], 
        $_ENV['DB_USERNAME'], 
        $_ENV['DB_PASSWORD']
    );
    
    $currentHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
    
    // Probar contraseñas comunes
    $passwords = ['admin123', 'admin', 'password', 'secret', '123456'];
    
    echo "=== VERIFICANDO CONTRASEÑAS ===\n";
    foreach ($passwords as $password) {
        if (password_verify($password, $currentHash)) {
            echo "✓ La contraseña '$password' es CORRECTA\n";
            exit;
        } else {
            echo "✗ La contraseña '$password' es incorrecta\n";
        }
    }
    
    echo "\n=== ACTUALIZANDO CONTRASEÑA A 'admin123' ===\n";
    
    // Crear nuevo hash para admin123
    $newHash = password_hash('admin123', PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = 'admin'");
    $result = $stmt->execute([$newHash]);
    
    if ($result) {
        echo "✓ Contraseña actualizada correctamente\n";
        echo "Nueva contraseña: admin123\n";
        echo "Nuevo hash: $newHash\n";
    } else {
        echo "✗ Error al actualizar la contraseña\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>