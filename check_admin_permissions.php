<?php
require_once 'vendor/autoload.php';

// Cargar variables de entorno
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    $db = new PDO('mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_DATABASE'], $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== PERMISOS DEL ROL ADMINISTRADOR ===\n";
    
    // Obtener ID del rol admin
    $stmt = $db->query("SELECT id FROM roles WHERE name = 'admin'");
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$role) {
        echo "ERROR: No se encontró el rol 'admin'\n";
        exit;
    }
    
    echo "ID del rol admin: " . $role['id'] . "\n\n";
    
    // Obtener permisos del rol
    $stmt = $db->prepare("SELECT p.code, p.name, p.description 
                         FROM permissions p 
                         INNER JOIN role_permissions rp ON p.id = rp.permission_id 
                         WHERE rp.role_id = ? 
                         ORDER BY p.code");
    $stmt->execute([$role['id']]);
    $permisos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Permisos asignados:\n";
    foreach ($permisos as $permiso) {
        echo "- " . $permiso['code'] . " (" . $permiso['name'] . ")\n";
    }
    echo "\nTotal: " . count($permisos) . " permisos\n\n";
    
    // Verificar permisos específicos necesarios para los módulos
    echo "=== VERIFICACIÓN DE PERMISOS PARA MÓDULOS ===\n";
    
    $permisos_necesarios = [
        'leads.view' => 'Mi Resumen / Leads',
        'leads.view.all' => 'Ver todos los leads',
        'users.view' => 'Usuarios',
        'users.view.all' => 'Ver todos los usuarios',
        'roles.view' => 'Roles',
        'roles.view.all' => 'Ver todos los roles',
        'desks.view' => 'Mesas',
        'desks.view.all' => 'Ver todas las mesas',
        'states.view' => 'Gestión de Estados',
        'states.view.all' => 'Ver todos los estados',
        'trading.view' => 'Trading',
        'trading.view.all' => 'Ver todo el trading'
    ];
    
    $permisos_faltantes = [];
    
    foreach ($permisos_necesarios as $permiso => $descripcion) {
        $tiene_permiso = false;
        foreach ($permisos as $p) {
            if ($p['code'] === $permiso) {
                $tiene_permiso = true;
                break;
            }
        }
        
        if ($tiene_permiso) {
            echo "✓ " . $permiso . " - " . $descripcion . "\n";
        } else {
            echo "✗ " . $permiso . " - " . $descripcion . "\n";
            $permisos_faltantes[] = $permiso;
        }
    }
    
    if (!empty($permisos_faltantes)) {
        echo "\n=== PERMISOS FALTANTES ===\n";
        foreach ($permisos_faltantes as $permiso) {
            echo "- " . $permiso . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}