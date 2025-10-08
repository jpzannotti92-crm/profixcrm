<?php
// Verificar qué permisos faltan para los módulos de navegación
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Models/User.php';
require_once __DIR__ . '/../src/Models/Role.php';
require_once __DIR__ . '/../src/Models/Permission.php';

use IaTradeCRM\Models\User;
use IaTradeCRM\Models\Role;
use IaTradeCRM\Models\Permission;

echo "=== VERIFICANDO PERMISOS PARA NAVEGACIÓN ===\n\n";

// Obtener el usuario admin
$user = User::findByUsername('admin');
if (!$user) {
    echo "❌ Usuario admin no encontrado\n";
    exit;
}

echo "✅ Usuario: {$user->username} (ID: {$user->id})\n\n";

// Obtener roles del usuario
$roles = $user->getRoles();
echo "Roles del usuario:\n";
foreach ($roles as $role) {
    echo "- {$role['name']} (ID: {$role['id']})\n";
}
echo "\n";

// Obtener permisos del usuario
$permissions = $user->getPermissions();
echo "Permisos del usuario (" . count($permissions) . " total):\n";
foreach ($permissions as $permission) {
    echo "- {$permission['name']} ({$permission['slug']})\n";
}
echo "\n";

// Verificar permisos necesarios para la navegación
$requiredPermissions = [
    'view_leads' => 'Ver módulo Leads',
    'view_users' => 'Ver módulo Usuarios', 
    'view_roles' => 'Ver módulo Roles',
    'view_desks' => 'Ver módulo Desks',
    'view_trading_accounts' => 'Ver módulo Trading Accounts',
    'manage_states' => 'Gestionar Estados',
    'view_reports' => 'Ver Reportes',
    'view_deposits_withdrawals' => 'Ver Depósitos/Retiros'
];

echo "=== VERIFICACIÓN DE PERMISOS PARA NAVEGACIÓN ===\n\n";

foreach ($requiredPermissions as $slug => $description) {
    $hasPermission = false;
    foreach ($permissions as $permission) {
        if ($permission['slug'] === $slug) {
            $hasPermission = true;
            break;
        }
    }
    echo "{$description} ({$slug}): " . ($hasPermission ? "✅ SÍ" : "❌ NO") . "\n";
}

echo "\n=== SOLUCIÓN PROPUESTA ===\n";
echo "1. Verificar si el rol 'admin' tiene estos permisos asignados\n";
echo "2. Si no los tiene, agregarlos al rol\n";
echo "3. Si el usuario no tiene el rol correcto, asignarle el rol adecuado\n";