<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Database/Connection.php';

use iaTradeCRM\Database\Connection;

try {
    $db = Connection::getInstance()->getConnection();
    
    $stmt = $db->prepare('SELECT username, password_hash FROM users WHERE username = ?');
    $stmt->execute(['admin']);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "Usuario encontrado: " . $user['username'] . "\n";
        echo "Hash: " . $user['password_hash'] . "\n";
        echo "Verificación 'admin123': " . (password_verify('admin123', $user['password_hash']) ? 'OK' : 'FAIL') . "\n";
        echo "Verificación 'password': " . (password_verify('password', $user['password_hash']) ? 'OK' : 'FAIL') . "\n";
        echo "Verificación 'admin': " . (password_verify('admin', $user['password_hash']) ? 'OK' : 'FAIL') . "\n";
        
        // Crear nuevo hash para admin123
        $newHash = password_hash('admin123', PASSWORD_DEFAULT);
        echo "\nNuevo hash para 'admin123': " . $newHash . "\n";
        echo "Verificación del nuevo hash: " . (password_verify('admin123', $newHash) ? 'OK' : 'FAIL') . "\n";
        
        // Actualizar la contraseña
        $updateStmt = $db->prepare('UPDATE users SET password_hash = ? WHERE username = ?');
        $result = $updateStmt->execute([$newHash, 'admin']);
        
        if ($result) {
            echo "\n✅ Contraseña actualizada exitosamente\n";
        } else {
            echo "\n❌ Error al actualizar contraseña\n";
        }
        
    } else {
        echo "❌ Usuario admin no encontrado\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>