<?php
// Verificar roles y crear admin si no existe
require_once 'config/config.php';

// Cargar .env manualmente
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, '"\'');
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv("$key=$value");
        }
    }
}

try {
    $db = new PDO(
        "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_DATABASE']};charset=utf8mb4",
        $_ENV['DB_USERNAME'],
        $_ENV['DB_PASSWORD'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "=== ROLES EXISTENTES ===\n\n";
    
    // Obtener roles
    $stmt = $db->query("SELECT id, name, display_name, description FROM roles ORDER BY id");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($roles)) {
        echo "No hay roles en la base de datos.\n";
    } else {
        echo "Roles encontrados:\n";
        foreach ($roles as $role) {
            echo "- {$role['id']}: {$role['name']} ({$role['display_name']}) - {$role['description']}\n";
        }
    }
    
    echo "\n=== VERIFICAR ADMIN ===\n";
    
    // Buscar usuario admin
    $stmt = $db->prepare("SELECT id, email, username, first_name, last_name FROM users WHERE email = ? OR username = ?");
    $stmt->execute(['admin@profixcrm.com', 'admin']);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        echo "Admin encontrado:\n";
        echo "ID: {$admin['id']}\n";
        echo "Email: {$admin['email']}\n";
        echo "Username: {$admin['username']}\n";
        echo "Nombre: {$admin['first_name']} {$admin['last_name']}\n";
    } else {
        echo "No se encontró usuario admin.\n";
        echo "Creando admin con contraseña 'password'...\n";
        
        // Crear usuario admin
        $hashedPassword = password_hash('password', PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (email, username, first_name, last_name, password, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $result = $stmt->execute(['admin@profixcrm.com', 'admin', 'Administrador', 'Sistema', $hashedPassword, 'active']);
        
        if ($result) {
            $adminId = $db->lastInsertId();
            echo "✅ Admin creado con ID: $adminId\n";
            
            // Asignar rol de administrador
            $stmt = $db->prepare("SELECT id FROM roles WHERE name = ?");
            $stmt->execute(['admin']);
            $adminRole = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($adminRole) {
                $stmt = $db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                $stmt->execute([$adminId, $adminRole['id']]);
                echo "✅ Rol de administrador asignado\n";
            } else {
                echo "⚠️ No se encontró rol 'admin' para asignar\n";
            }
        } else {
            echo "❌ Error al crear admin\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error de base de datos: " . $e->getMessage() . "\n";
}