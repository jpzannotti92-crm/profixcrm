<?php
require_once 'vendor/autoload.php';

use Dotenv\Dotenv;

// Cargar variables de entorno
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Configuración de la base de datos
$dbHost = $_ENV['DB_HOST'] ?? 'localhost';
$dbName = $_ENV['DB_DATABASE'] ?? '';
$dbUser = $_ENV['DB_USERNAME'] ?? '';
$dbPass = $_ENV['DB_PASSWORD'] ?? '';

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Conexión exitosa a la base de datos.\n\n";
    
    // Permisos que necesitamos crear
    $permissions_to_create = [
        'leads.view' => 'Ver leads',
        'users.view' => 'Ver usuarios',
        'roles.view' => 'Ver roles',
        'desks.view' => 'Ver mesas',
        'states.view' => 'Ver estados',
        'trading_accounts.view' => 'Ver cuentas de trading',
        'manage_states' => 'Gestionar estados'
    ];
    
    echo "=== CREANDO PERMISOS FALTANTES ===\n\n";
    
    foreach ($permissions_to_create as $permission_name => $description) {
        // Verificar si el permiso ya existe
        $stmt = $pdo->prepare("SELECT id FROM permissions WHERE name = ?");
        $stmt->execute([$permission_name]);
        
        if ($stmt->fetch()) {
            echo "✓ El permiso '$permission_name' ya existe.\n";
        } else {
            // Crear el permiso
            $stmt = $pdo->prepare("INSERT INTO permissions (name, description, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
            $stmt->execute([$permission_name, $description]);
            echo "✓ Permiso '$permission_name' creado exitosamente.\n";
        }
    }
    
    echo "\n=== ASIGNANDO PERMISOS AL ROL 'admin' ===\n\n";
    
    // Obtener el ID del rol admin
    $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'admin'");
    $stmt->execute();
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$role) {
        echo "✗ Error: No se encontró el rol 'admin'.\n";
        exit;
    }
    
    $role_id = $role['id'];
    echo "Rol 'admin' encontrado (ID: $role_id)\n\n";
    
    $permissions_added = 0;
    
    foreach ($permissions_to_create as $permission_name => $description) {
        // Obtener el ID del permiso
        $stmt = $pdo->prepare("SELECT id FROM permissions WHERE name = ?");
        $stmt->execute([$permission_name]);
        $permission = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($permission) {
            $permission_id = $permission['id'];
            
            // Verificar si ya está asignado
            $stmt = $pdo->prepare("SELECT id FROM role_permissions WHERE role_id = ? AND permission_id = ?");
            $stmt->execute([$role_id, $permission_id]);
            
            if ($stmt->fetch()) {
                echo "✓ El permiso '$permission_name' ya está asignado al rol admin.\n";
            } else {
                // Asignar el permiso al rol
                $stmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
                $stmt->execute([$role_id, $permission_id]);
                echo "✓ Permiso '$permission_name' asignado al rol admin.\n";
                $permissions_added++;
            }
        }
    }
    
    echo "\n=== RESUMEN ===\n";
    echo "Permisos nuevos añadidos al rol admin: $permissions_added\n";
    
    // Verificar todos los permisos del admin
    echo "\n=== PERMISOS TOTALES DEL ROL 'ADMIN' ===\n";
    $stmt = $pdo->prepare("
        SELECT p.name, p.description 
        FROM permissions p 
        INNER JOIN role_permissions rp ON p.id = rp.permission_id 
        WHERE rp.role_id = ? 
        ORDER BY p.name
    ");
    $stmt->execute([$role_id]);
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total de permisos: " . count($permissions) . "\n\n";
    
    foreach ($permissions as $permission) {
        echo "- {$permission['name']}: {$permission['description']}\n";
    }
    
} catch (PDOException $e) {
    echo "Error de conexión: " . $e->getMessage() . "\n";
}
?>