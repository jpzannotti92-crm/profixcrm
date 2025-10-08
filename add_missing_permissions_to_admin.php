<?php
require_once 'vendor/autoload.php';

// Cargar variables de entorno
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$db = new PDO('mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_DATABASE'], $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD']);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== AGREGANDO PERMISOS FALTANTES AL ROL ADMIN ===\n";

// Obtener ID del rol admin
$stmt = $db->query("SELECT id FROM roles WHERE name = 'admin'");
$role = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$role) {
    echo "ERROR: No se encontró el rol 'admin'\n";
    exit;
}

$role_id = $role['id'];
echo "ID del rol admin: " . $role_id . "\n\n";

// Permisos que necesitamos agregar
$permisos_faltantes = [
    'leads.view',
    'users.view',
    'users.view.all',
    'roles.view',
    'roles.view.all',
    'desks.view',
    'desks.view.all',
    'states.view',
    'states.view.all',
    'trading.view.all'
];

$agregados = 0;

foreach ($permisos_faltantes as $permiso_code) {
    // Verificar si el permiso existe
    $stmt = $db->prepare("SELECT id FROM permissions WHERE code = ?");
    $stmt->execute([$permiso_code]);
    $permiso = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$permiso) {
        echo "⚠ El permiso '{$permiso_code}' no existe en la base de datos\n";
        continue;
    }
    
    $permiso_id = $permiso['id'];
    
    // Verificar si ya está asignado
    $stmt = $db->prepare("SELECT id FROM role_permissions WHERE role_id = ? AND permission_id = ?");
    $stmt->execute([$role_id, $permiso_id]);
    $existe = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existe) {
        echo "✓ El permiso '{$permiso_code}' ya está asignado\n";
    } else {
        // Agregar el permiso
        $stmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$role_id, $permiso_id]);
        echo "✓ Agregado permiso '{$permiso_code}'\n";
        $agregados++;
    }
}

echo "\n=== RESULTADO ===\n";
echo "Permisos agregados: " . $agregados . "\n";
echo "\nAhora debes cerrar sesión y volver a iniciar sesión para ver los cambios.\n";