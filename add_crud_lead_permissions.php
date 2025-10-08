<?php
require_once 'src/Database/Connection.php';

echo '=== AGREGANDO PERMISOS CRUD COMPLETO PARA LEADS ===' . PHP_EOL . PHP_EOL;

$pdo = \iaTradeCRM\Database\Connection::getInstance()->getConnection();

// Función para verificar si una columna existe
function tableHasColumn($db, $table, $column) {
    try {
        $stmt = $db->prepare("SHOW COLUMNS FROM `$table` LIKE :column");
        $stmt->execute(['column' => $column]);
        return (bool)$stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

// Permisos que vamos a agregar
$permissionsToAdd = [
    'leads.view.assigned' => ['display_name' => 'Ver Leads Asignados', 'description' => 'Permite ver leads asignados al usuario'],
    'leads.view.desk' => ['display_name' => 'Ver Leads del Desk', 'description' => 'Permite ver leads del desk del usuario'],
    'leads.edit' => ['display_name' => 'Editar Leads', 'description' => 'Permite editar leads existentes'],
    'leads.delete' => ['display_name' => 'Eliminar Leads', 'description' => 'Permite eliminar leads'],
    'leads.view.all' => ['display_name' => 'Ver Todos los Leads', 'description' => 'Permite ver todos los leads sin restricciones']
];

// Verificar y agregar permisos que no existan
echo '1. Verificando permisos existentes...' . PHP_EOL;
$existingPermissions = [];
foreach ($permissionsToAdd as $permName => $permData) {
    $stmt = $pdo->prepare("SELECT id FROM permissions WHERE name = ?");
    $stmt->execute([$permName]);
    $result = $stmt->fetch();
    
    if ($result) {
        $existingPermissions[$permName] = $result['id'];
        echo "   - $permName: YA EXISTE (ID: {$result['id']})" . PHP_EOL;
    } else {
        // Detectar columnas disponibles y preparar INSERT dinámico
        $columns = ['name'];
        $values = [$permName];
        $placeholders = ['?'];

        if (tableHasColumn($pdo, 'permissions', 'display_name')) {
            $columns[] = 'display_name';
            $values[] = $permData['display_name'];
            $placeholders[] = '?';
        }
        if (tableHasColumn($pdo, 'permissions', 'description')) {
            $columns[] = 'description';
            $values[] = $permData['description'];
            $placeholders[] = '?';
        }
        if (tableHasColumn($pdo, 'permissions', 'module')) {
            $columns[] = 'module';
            $values[] = 'leads';
            $placeholders[] = '?';
        }
        if (tableHasColumn($pdo, 'permissions', 'action')) {
            $columns[] = 'action';
            $values[] = 'view';
            $placeholders[] = '?';
        }
        if (tableHasColumn($pdo, 'permissions', 'created_at')) {
            $columns[] = 'created_at';
            $values[] = date('Y-m-d H:i:s');
            $placeholders[] = '?';
        }

        // Agregar el permiso
        $sql = "INSERT INTO permissions (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        $newId = $pdo->lastInsertId();
        $existingPermissions[$permName] = $newId;
        echo "   - $permName: AGREGADO (ID: $newId)" . PHP_EOL;
    }
}

echo PHP_EOL . '2. Obteniendo IDs de roles...' . PHP_EOL;

// Obtener IDs de roles
$roles = [];
$roleNames = ['test_role', 'Sales Agent', 'admin'];
foreach ($roleNames as $roleName) {
    $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = ?");
    $stmt->execute([$roleName]);
    $result = $stmt->fetch();
    if ($result) {
        $roles[$roleName] = $result['id'];
        echo "   - $roleName: ID {$result['id']}" . PHP_EOL;
    } else {
        echo "   - $roleName: NO ENCONTRADO" . PHP_EOL;
    }
}

echo PHP_EOL . '3. Asignando permisos a roles...' . PHP_EOL;

// Asignar permisos a roles específicos
$rolePermissions = [
    'test_role' => ['leads.view.assigned', 'leads.view.desk', 'leads.edit', 'leads.delete'],
    'Sales Agent' => ['leads.view.assigned', 'leads.view.desk', 'leads.edit', 'leads.delete'],
    'admin' => ['leads.view.all', 'leads.edit', 'leads.delete']
];

foreach ($rolePermissions as $roleName => $permNames) {
    if (!isset($roles[$roleName])) continue;
    
    $roleId = $roles[$roleName];
    echo "   Asignando permisos a $roleName:" . PHP_EOL;
    
    foreach ($permNames as $permName) {
        if (!isset($existingPermissions[$permName])) continue;
        
        $permId = $existingPermissions[$permName];
        
        // Verificar si ya existe la asignación
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM role_permissions WHERE role_id = ? AND permission_id = ?");
        $stmt->execute([$roleId, $permId]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            echo "     - $permName: YA ASIGNADO" . PHP_EOL;
        } else {
            // Asignar el permiso al rol
            $stmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
            $stmt->execute([$roleId, $permId]);
            echo "     - $permName: ASIGNADO" . PHP_EOL;
        }
    }
}

echo PHP_EOL . '4. Verificando asignaciones finales...' . PHP_EOL;

// Verificar asignaciones finales
foreach ($roles as $roleName => $roleId) {
    echo "   Permisos de $roleName:" . PHP_EOL;
    
    // Construir query dinámica según columnas disponibles
    $selectColumns = ['p.name'];
    if (tableHasColumn($pdo, 'permissions', 'display_name')) {
        $selectColumns[] = 'p.display_name';
    }
    
    $query = "SELECT " . implode(', ', $selectColumns) . " 
              FROM role_permissions rp 
              JOIN permissions p ON rp.permission_id = p.id 
              WHERE rp.role_id = ? AND p.name LIKE 'leads.%'
              ORDER BY p.name";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$roleId]);
    $permissions = $stmt->fetchAll();
    
    foreach ($permissions as $perm) {
        if (isset($perm['display_name'])) {
            echo "     - {$perm['name']}: {$perm['display_name']}" . PHP_EOL;
        } else {
            echo "     - {$perm['name']}" . PHP_EOL;
        }
    }
    echo PHP_EOL;
}

echo '=== PROCESO COMPLETADO ===' . PHP_EOL;