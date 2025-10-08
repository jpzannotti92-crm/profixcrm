<?php
// Verificar usuarios existentes en la base de datos
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
    
    echo "=== USUARIOS EN LA BASE DE DATOS ===\n\n";
    
    // Obtener todos los usuarios
    $stmt = $db->query("SELECT id, name, email, username, status, created_at FROM users ORDER BY id");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($users)) {
        echo "No hay usuarios en la base de datos.\n";
    } else {
        echo "Total de usuarios: " . count($users) . "\n\n";
        foreach ($users as $user) {
            echo "ID: {$user['id']}\n";
            echo "Nombre: {$user['name']}\n";
            echo "Email: {$user['email']}\n";
            echo "Username: {$user['username']}\n";
            echo "Estado: {$user['status']}\n";
            echo "Creado: {$user['created_at']}\n";
            echo "---\n";
        }
    }
    
    // Verificar tabla de configuración
    echo "\n=== CONFIGURACIÓN DEL SISTEMA ===\n";
    $stmt = $db->query("SELECT * FROM settings WHERE setting_key LIKE '%admin%' OR setting_key LIKE '%default%'");
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($settings as $setting) {
        echo "{$setting['setting_key']}: {$setting['setting_value']}\n";
    }
    
} catch (Exception $e) {
    echo "Error de base de datos: " . $e->getMessage() . "\n";
}