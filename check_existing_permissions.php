<?php
require_once 'vendor/autoload.php';

// Cargar variables de entorno
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$db = new PDO('mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_DATABASE'], $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD']);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== PERMISOS EXISTENTES EN LA BASE DE DATOS ===\n";

// Ver todos los permisos
$stmt = $db->query('SELECT code, name, description FROM permissions ORDER BY code');
$permisos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$permisos_por_categoria = [
    'leads' => [],
    'users' => [],
    'roles' => [],
    'desks' => [],
    'states' => [],
    'trading' => [],
    'other' => []
];

foreach ($permisos as $permiso) {
    $categoria = 'other';
    if (strpos($permiso['code'], 'leads') !== false) {
        $categoria = 'leads';
    } elseif (strpos($permiso['code'], 'users') !== false) {
        $categoria = 'users';
    } elseif (strpos($permiso['code'], 'roles') !== false) {
        $categoria = 'roles';
    } elseif (strpos($permiso['code'], 'desk') !== false) {
        $categoria = 'desks';
    } elseif (strpos($permiso['code'], 'states') !== false || strpos($permiso['code'], 'manage_states') !== false) {
        $categoria = 'states';
    } elseif (strpos($permiso['code'], 'trading') !== false) {
        $categoria = 'trading';
    }
    
    $permisos_por_categoria[$categoria][] = $permiso;
}

foreach ($permisos_por_categoria as $categoria => $perms) {
    if (!empty($perms)) {
        echo "\n=== PERMISOS DE {$categoria} ===\n";
        foreach ($perms as $perm) {
            echo "- {$perm['code']} - {$perm['name']}\n";
        }
    }
}

echo "\n=== RESUMEN ===\n";
echo "Total de permisos: " . count($permisos) . "\n";

// Verificar qué permisos tiene el rol admin
echo "\n=== PERMISOS DEL ROL ADMIN ===\n";
$stmt = $db->query("SELECT p.code, p.name 
                    FROM permissions p 
                    INNER JOIN role_permissions rp ON p.id = rp.permission_id 
                    INNER JOIN roles r ON rp.role_id = r.id 
                    WHERE r.name = 'admin' 
                    ORDER BY p.code");
$admin_perms = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($admin_perms as $perm) {
    echo "✓ {$perm['code']} - {$perm['name']}\n";
}

echo "\nTotal de permisos del admin: " . count($admin_perms) . "\n";