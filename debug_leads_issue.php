<?php
header('Content-Type: text/plain; charset=utf-8');

echo "=== DEBUG LEADS ISSUE ===\n\n";

require_once 'vendor/autoload.php';
require_once 'src/Database/Connection.php';
require_once 'src/Models/BaseModel.php';
require_once 'src/Models/User.php';
require_once 'src/Middleware/RBACMiddleware.php';

use iaTradeCRM\Database\Connection;
use App\Models\User;
use App\Middleware\RBACMiddleware;

try {
    // Cargar variables de entorno
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    
    $db = Connection::getInstance();
    $rbacMiddleware = new RBACMiddleware();
    
    echo "1. VERIFICANDO USUARIO ADMIN:\n";
    
    // Obtener usuario admin
    $stmt = $db->getConnection()->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute(['admin']);
    $adminData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$adminData) {
        echo "❌ Usuario admin no encontrado\n";
        exit();
    }
    
    $admin = new User();
    $admin->id = $adminData['id'];
    $admin->username = $adminData['username'];
    $admin->email = $adminData['email'];
    $admin->first_name = $adminData['first_name'];
    $admin->last_name = $adminData['last_name'];
    $admin->status = $adminData['status'];
    
    echo "✅ Usuario admin encontrado (ID: {$admin->id})\n";
    
    echo "\n2. VERIFICANDO PERMISOS DE LEADS:\n";
    
    $permissions = $admin->getPermissions();
    $leadsPermissions = array_filter($permissions, function($p) {
        return strpos($p['name'], 'leads') === 0;
    });
    
    echo "Permisos de leads encontrados:\n";
    foreach ($leadsPermissions as $perm) {
        echo "- {$perm['name']}\n";
    }
    
    // Verificar permisos específicos
    $hasLeadsView = $admin->hasPermission('leads.view');
    $hasLeadsViewAssigned = $admin->hasPermission('leads.view.assigned');
    $hasLeadsViewAll = $admin->hasPermission('leads.view.all');
    
    echo "\nVerificación de permisos específicos:\n";
    echo "- leads.view: " . ($hasLeadsView ? "✅" : "❌") . "\n";
    echo "- leads.view.assigned: " . ($hasLeadsViewAssigned ? "✅" : "❌") . "\n";
    echo "- leads.view.all: " . ($hasLeadsViewAll ? "✅" : "❌") . "\n";
    
    echo "\n3. VERIFICANDO FILTROS DE LEADS:\n";
    
    $leadsFilters = $rbacMiddleware->getLeadsFilters($admin);
    echo "Filtros aplicados:\n";
    if (empty($leadsFilters)) {
        echo "- Sin filtros (puede ver todos los leads)\n";
    } else {
        foreach ($leadsFilters as $key => $value) {
            if (is_array($value)) {
                echo "- $key: " . implode(', ', $value) . "\n";
            } else {
                echo "- $key: $value\n";
            }
        }
    }
    
    echo "\n4. VERIFICANDO ENDPOINT user-permissions.php:\n";
    
    // Simular llamada al endpoint
    $_GET['action'] = 'leads-filters';
    
    ob_start();
    $filters = $rbacMiddleware->getLeadsFilters($admin);
    $response = [
        'success' => true,
        'data' => [
            'filters' => $filters,
            'user_id' => $admin->id,
            'access_level' => [
                'is_super_admin' => $admin->isSuperAdmin(),
                'is_admin' => $admin->isAdmin(),
                'is_manager' => $admin->isManager(),
                'has_sales_role' => $admin->hasRole('sales')
            ]
        ]
    ];
    ob_end_clean();
    
    echo "Respuesta del endpoint leads-filters:\n";
    echo json_encode($response, JSON_PRETTY_PRINT) . "\n";
    
    echo "\n5. VERIFICANDO ENDPOINT users.php:\n";
    
    // Verificar si el endpoint users existe y funciona
    $usersEndpoint = __DIR__ . '/public/api/users.php';
    if (file_exists($usersEndpoint)) {
        echo "✅ Endpoint users.php existe\n";
        
        // Verificar permisos para users
        $hasUsersView = $admin->hasPermission('users.view');
        echo "- Permiso users.view: " . ($hasUsersView ? "✅" : "❌") . "\n";
    } else {
        echo "❌ Endpoint users.php no existe\n";
    }
    
    echo "\n6. VERIFICANDO ENDPOINT desks.php:\n";
    
    // Verificar si el endpoint desks existe y funciona
    $desksEndpoint = __DIR__ . '/public/api/desks.php';
    if (file_exists($desksEndpoint)) {
        echo "✅ Endpoint desks.php existe\n";
        
        // Verificar permisos para desks
        $hasDesksView = $admin->hasPermission('desks.view');
        echo "- Permiso desks.view: " . ($hasDesksView ? "✅" : "❌") . "\n";
    } else {
        echo "❌ Endpoint desks.php no existe\n";
    }
    
    echo "\n7. VERIFICANDO ROLES DEL USUARIO:\n";
    
    $roles = $admin->getRoles();
    echo "Roles del usuario admin:\n";
    foreach ($roles as $role) {
        echo "- {$role['name']}\n";
    }
    
    echo "\n8. VERIFICANDO DESKS DEL USUARIO:\n";
    
    $desks = $admin->getDesks();
    echo "Desks del usuario admin:\n";
    if (empty($desks)) {
        echo "- Sin desks asignados\n";
    } else {
        foreach ($desks as $desk) {
            echo "- {$desk['name']} (ID: {$desk['id']})\n";
        }
    }
    
    echo "\n=== RESUMEN ===\n";
    
    if (!$hasLeadsView && !$hasLeadsViewAssigned && !$hasLeadsViewAll) {
        echo "❌ PROBLEMA: El usuario admin no tiene permisos para ver leads\n";
        echo "   Esto causará que se desloguee al intentar acceder a la página de leads\n";
    } else {
        echo "✅ El usuario admin tiene permisos para ver leads\n";
    }
    
    if (!$hasUsersView) {
        echo "❌ PROBLEMA: El usuario admin no tiene permisos para ver usuarios\n";
        echo "   Esto causará errores al cargar filtros de usuarios\n";
    } else {
        echo "✅ El usuario admin tiene permisos para ver usuarios\n";
    }
    
    if (!$hasDesksView) {
        echo "❌ PROBLEMA: El usuario admin no tiene permisos para ver desks\n";
        echo "   Esto causará errores al cargar filtros de desks\n";
    } else {
        echo "✅ El usuario admin tiene permisos para ver desks\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}