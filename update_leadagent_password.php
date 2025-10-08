<?php
// Actualizar contraseña del usuario leadagent a 'password'
try {
    $host = 'localhost';
    $dbname = 'spin2pay_profixcrm';
    $username = 'root';
    $password = '';
    
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Generar hash de contraseña
    $newPassword = 'password';
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    
    echo "Nuevo hash de contraseña: " . substr($passwordHash, 0, 30) . "...\n";
    
    // Actualizar contraseña del usuario leadagent
    $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = 7");
    $stmt->execute([$passwordHash]);
    
    if ($stmt->rowCount() > 0) {
        echo "✓ Contraseña actualizada exitosamente\n";
        echo "El usuario leadagent ahora puede usar la contraseña: $newPassword\n";
    } else {
        echo "⚠️  No se encontró el usuario o la contraseña ya era esa\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}