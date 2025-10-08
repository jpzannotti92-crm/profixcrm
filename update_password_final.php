<?php
try {
    $pdo = new PDO("mysql:host=localhost;dbname=spin2pay_profixcrm", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Generar hash para 'password'
    $newHash = password_hash('password', PASSWORD_DEFAULT);
    echo "Nuevo hash generado: " . $newHash . "\n";
    
    // Actualizar la contraseña
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = 'admin'");
    $result = $stmt->execute([$newHash]);
    
    if ($result) {
        echo "✓ Contraseña actualizada exitosamente\n";
        
        // Verificar la actualización
        $stmt = $pdo->prepare("SELECT username, password_hash FROM users WHERE username = 'admin'");
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify('password', $user['password_hash'])) {
            echo "✓ Verificación exitosa: La contraseña 'password' funciona correctamente\n";
        } else {
            echo "✗ Error en la verificación\n";
        }
    } else {
        echo "✗ Error al actualizar la contraseña\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>