<?php
// Cargar autoloader de Composer
require_once 'vendor/autoload.php';

// Cargar variables de entorno
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Cargar archivos necesarios
require_once 'src/Database/Connection.php';
require_once 'src/Models/BaseModel.php';
require_once 'src/Models/User.php';

use iaTradeCRM\Database\Connection;
use App\Models\User;

try {
    $db = Connection::getInstance();
    
    // Buscar usuario admin
    $user = User::findByUsernameOrEmail('admin');
    
    if (!$user) {
        echo "Usuario admin no encontrado\n";
        exit();
    }
    
    echo "=== DEBUG PERMISOS USUARIO ADMIN ===\n";
    echo "ID: " . $user->id . "\n";
    echo "Username: " . $user->username . "\n";
    echo "Email: " . $user->email . "\n";
    echo "Status: " . $user->status . "\n\n";
    
    // Verificar roles
    echo "=== ROLES ===\n";
    $roles = $user->getRoles();
    foreach ($roles as $role) {
        echo "- " . $role['name'] . " (ID: " . $role['id'] . ")\n";
    }
    echo "\n";
    
    // Verificar permisos
    echo "=== PERMISOS ===\n";
    $permissions = $user->getPermissions();
    foreach ($permissions as $permission) {
        echo "- " . $permission['name'] . " (" . $permission['description'] . ")\n";
    }
    echo "\n";
    
    // Verificar permiso específico 'roles.view'
    echo "=== VERIFICACIÓN PERMISO 'roles.view' ===\n";
    $hasRolesView = $user->hasPermission('roles.view');
    echo "hasPermission('roles.view'): " . ($hasRolesView ? 'SÍ' : 'NO') . "\n";
    
    // Verificar consulta SQL directa
    echo "\n=== CONSULTA SQL DIRECTA ===\n";
    $stmt = $db->getConnection()->prepare("
        SELECT COUNT(*) as count
        FROM permissions p
        INNER JOIN role_permissions rp ON p.id = rp.permission_id
        INNER JOIN user_roles ur ON rp.role_id = ur.role_id
        WHERE ur.user_id = :user_id AND p.name = :permission_name
    ");
    
    $stmt->execute([
        'user_id' => $user->id,
        'permission_name' => 'roles.view'
    ]);
    
    $result = $stmt->fetch();
    echo "Consulta SQL directa - Count: " . $result['count'] . "\n";
    
    // Verificar estructura de tablas
    echo "\n=== VERIFICACIÓN ESTRUCTURA TABLAS ===\n";
    
    // user_roles
    $stmt = $db->getConnection()->prepare("SELECT * FROM user_roles WHERE user_id = ?");
    $stmt->execute([$user->id]);
    $userRoles = $stmt->fetchAll();
    echo "user_roles para usuario {$user->id}:\n";
    foreach ($userRoles as $ur) {
        echo "  - role_id: " . $ur['role_id'] . "\n";
    }
    
    // role_permissions para cada rol
    foreach ($userRoles as $ur) {
        $stmt = $db->getConnection()->prepare("
            SELECT rp.*, p.name as permission_name 
            FROM role_permissions rp 
            JOIN permissions p ON rp.permission_id = p.id 
            WHERE rp.role_id = ?
        ");
        $stmt->execute([$ur['role_id']]);
        $rolePermissions = $stmt->fetchAll();
        echo "role_permissions para role_id {$ur['role_id']}:\n";
        foreach ($rolePermissions as $rp) {
            echo "  - permission_id: " . $rp['permission_id'] . " (" . $rp['permission_name'] . ")\n";
        }
    }
    
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
    echo 'Trace: ' . $e->getTraceAsString() . "\n";
}
?>