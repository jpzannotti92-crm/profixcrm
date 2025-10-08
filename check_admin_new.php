<?php
require_once 'config/database.php';

try {
    $db = new PDO('mysql:host=localhost;dbname=spin2pay_profixcrm', 'root', '');
    
    // Verificar usuario admin
    $stmt = $db->query("SELECT id, username, email, status FROM users WHERE username='admin'");
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "=== USUARIO ADMIN ===\n";
    print_r($user);
    
    if ($user) {
        // Verificar columnas de contraseña
        $colStmt = $db->query("SHOW COLUMNS FROM users LIKE 'password_hash'");
        $hasPasswordHash = $colStmt && $colStmt->fetch();
        
        $colStmt2 = $db->query("SHOW COLUMNS FROM users LIKE 'password'");
        $hasPassword = $colStmt2 && $colStmt2->fetch();
        
        echo "\n=== ESTRUCTURA DE CONTRASEÑA ===\n";
        echo "Tiene password_hash: " . ($hasPasswordHash ? 'SÍ' : 'NO') . "\n";
        echo "Tiene password: " . ($hasPassword ? 'SÍ' : 'NO') . "\n";
        
        // Obtener contraseña
        if ($hasPasswordHash) {
            $pwdStmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
            $pwdStmt->execute([$user['id']]);
            $passwordData = $pwdStmt->fetch();
            echo "Password hash: " . ($passwordData['password_hash'] ?? 'NULL') . "\n";
            
            // Verificar contraseñas
            echo "\n=== VERIFICACIÓN DE CONTRASEÑAS ===\n";
            echo "admin12345!: " . (password_verify('admin12345!', $passwordData['password_hash'] ?? '') ? 'CORRECTA' : 'INCORRECTA') . "\n";
            echo "password: " . (password_verify('password', $passwordData['password_hash'] ?? '') ? 'CORRECTA' : 'INCORRECTA') . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}