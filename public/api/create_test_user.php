<?php
// Fallback-compatible bootstrap for environments with PHP < 8.2
if (PHP_VERSION_ID >= 80200 && file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
    if (class_exists('Dotenv\\Dotenv')) {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();
    }
} else {
    // Minimal .env loader
    $envFile = __DIR__ . '/../../.env';
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
}

require_once __DIR__ . '/../../src/Database/Connection.php';

use iaTradeCRM\Database\Connection;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $db = Connection::getInstance()->getConnection();
    
    // Verificar si ya existe el usuario admin
    $checkStmt = $db->prepare("SELECT id FROM users WHERE username = 'admin'");
    $checkStmt->execute();
    
    if ($checkStmt->fetch()) {
        echo json_encode([
            'success' => true,
            'message' => 'Usuario admin ya existe'
        ]);
        exit();
    }
    
    // Crear usuario admin
    $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
    
    $stmt = $db->prepare("
        INSERT INTO users (
            username, email, password_hash, first_name, last_name, 
            phone, status, email_verified, created_at, updated_at
        ) VALUES (
            'admin', 'admin@iatrade.com', ?, 'Admin', 'User', 
            '+1234567890', 'active', 1, NOW(), NOW()
        )
    ");
    
    $result = $stmt->execute([$passwordHash]);
    
    if ($result) {
        $userId = $db->lastInsertId();
        
        // Crear rol admin si no existe
        $roleStmt = $db->prepare("
            INSERT IGNORE INTO roles (name, display_name, description, is_system) 
            VALUES ('admin', 'Administrador', 'Administrador del sistema', 1)
        ");
        $roleStmt->execute();
        
        // Obtener ID del rol admin
        $getRoleStmt = $db->prepare("SELECT id FROM roles WHERE name = 'admin'");
        $getRoleStmt->execute();
        $roleId = $getRoleStmt->fetch()['id'];
        
        // Asignar rol admin al usuario
        $userRoleStmt = $db->prepare("
            INSERT IGNORE INTO user_roles (user_id, role_id, assigned_at) 
            VALUES (?, ?, NOW())
        ");
        $userRoleStmt->execute([$userId, $roleId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Usuario admin creado exitosamente',
            'user_id' => $userId,
            'username' => 'admin',
            'password' => 'admin123'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error al crear usuario'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>