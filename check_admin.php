<?php
require_once 'config/database.php';

try {
    $db = new PDO('mysql:host=localhost;dbname=spin2pay_profixcrm', 'root', '');
    $stmt = $db->query("SELECT id, username, email, status FROM users WHERE username='admin'");
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "=== USUARIO ADMIN ===\n";
    print_r($user);
    
    if ($user) {
        echo "\n=== VERIFICACIÓN DE CONTRASEÑA ===\n";
        echo "Password hash: " . ($user['password_hash'] ?? 'NULL') . "\n";
        echo "Password: " . ($user['password'] ?? 'NULL') . "\n";
        echo "Verificación de 'password': " . (password_verify('password', $user['password_hash'] ?? '') ? 'CORRECTA' : 'INCORRECTA') . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}