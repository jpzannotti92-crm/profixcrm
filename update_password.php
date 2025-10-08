<?php
// Script para actualizar la contraseña del usuario admin a 'password'
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Database/Connection.php';

use iaTradeCRM\Database\Connection;

try {
    // Generar hash para la contraseña 'password'
    $password = 'password';
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    echo "Generando hash para contraseña: $password\n";
    echo "Hash generado: $hash\n";
    
    // Conectar a la base de datos
    $db = Connection::getInstance()->getConnection();
    
    // Actualizar la contraseña del usuario admin
    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE username = 'admin'");
    $result = $stmt->execute([$hash]);
    
    if ($result) {
        echo "✓ Contraseña actualizada exitosamente para el usuario admin\n";
        
        // Verificar que la actualización fue correcta
        $stmt = $db->prepare("SELECT username, password_hash FROM users WHERE username = 'admin'");
        $stmt->execute();
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            echo "✓ Verificación exitosa: La contraseña '$password' funciona correctamente\n";
        } else {
            echo "✗ Error: La verificación de contraseña falló\n";
        }
    } else {
        echo "✗ Error al actualizar la contraseña\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>