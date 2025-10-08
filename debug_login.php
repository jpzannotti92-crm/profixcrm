<?php
require_once 'config/database.php';

// Verificar la contraseña directamente en la base de datos
$username = 'admin';
$password = 'password';

try {
    $pdo = new PDO("mysql:host=localhost;dbname=spin2pay_profixcrm", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Obtener el usuario
    $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "Usuario encontrado:\n";
        echo "ID: " . $user['id'] . "\n";
        echo "Username: " . $user['username'] . "\n";
        echo "Hash almacenado: " . $user['password_hash'] . "\n";
        
        // Verificar la contraseña
        $isValid = password_verify($password, $user['password_hash']);
        echo "Verificación de contraseña: " . ($isValid ? "VÁLIDA" : "INVÁLIDA") . "\n";
        
        // Generar un nuevo hash para comparar
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        echo "Nuevo hash generado: " . $newHash . "\n";
        echo "Verificación del nuevo hash: " . (password_verify($password, $newHash) ? "VÁLIDA" : "INVÁLIDA") . "\n";
        
    } else {
        echo "Usuario no encontrado\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>