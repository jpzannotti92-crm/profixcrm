<?php
// Script para actualizar los roles existentes con los nuevos permisos
try {
    $pdo = new PDO('mysql:host=localhost;dbname=iatrade_crm', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== ACTUALIZANDO ROLES CON NUEVOS PERMISOS ===\n\n";
    
    // Definir los nuevos permisos por rol
    $rolePermissions = [
        'super_admin' => [
            // Todos los permisos existentes + nuevos
            'activities.view', 'activities.create', 'activities.edit', 'activities.delete',
            'dashboard.view', 'dashboard.stats',
            'user_permissions.view', 'user_permissions.edit',
            'instruments.view', 'instruments.create', 'instruments.edit', 'instruments.delete',
            'import.leads', 'export.leads',
            'system.database', 'system.maintenance'
        ],
        
        'admin' => [
            // Permisos administrativos sin acceso a configuración crítica del sistema
            'activities.view', 'activities.create', 'activities.edit', 'activities.delete',
            'dashboard.view', 'dashboard.stats',
            'user_permissions.view', 'user_permissions.edit',
            'instruments.view', 'instruments.create', 'instruments.edit', 'instruments.delete',
            'import.leads', 'export.leads'
        ],
        
        'manager' => [
            // Permisos de gestión de equipo y operaciones
            'activities.view', 'activities.create', 'activities.edit',
            'dashboard.view', 'dashboard.stats',
            'user_permissions.view',
            'instruments.view', 'instruments.create', 'instruments.edit',
            'import.leads', 'export.leads'
        ],
        
        'agent' => [
            // Permisos básicos para agentes
            'activities.view', 'activities.create', 'activities.edit',
            'dashboard.view',
            'instruments.view'
        ],
        
        'Sales Agent' => [
            // Permisos básicos para agentes de ventas
            'activities.view', 'activities.create',
            'dashboard.view',
            'instruments.view'
        ],
        
        'viewer' => [
            // Solo permisos de lectura
            'activities.view',
            'dashboard.view',
            'instruments.view'
        ]
    ];
    
    foreach ($rolePermissions as $roleName => $permissions) {
        echo "Actualizando rol: $roleName\n";
        
        // Obtener ID del rol
        $stmt = $pdo->prepare('SELECT id FROM roles WHERE name = ?');
        $stmt->execute([$roleName]);
        $role = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$role) {
            echo "  ⚠️  Rol '$roleName' no encontrado\n";
            continue;
        }
        
        $roleId = $role['id'];
        $addedCount = 0;
        
        foreach ($permissions as $permissionName) {
            // Obtener ID del permiso
            $stmt = $pdo->prepare('SELECT id FROM permissions WHERE name = ?');
            $stmt->execute([$permissionName]);
            $permission = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$permission) {
                echo "  ⚠️  Permiso '$permissionName' no encontrado\n";
                continue;
            }
            
            $permissionId = $permission['id'];
            
            // Verificar si la relación ya existe
            $stmt = $pdo->prepare('SELECT id FROM role_permissions WHERE role_id = ? AND permission_id = ?');
            $stmt->execute([$roleId, $permissionId]);
            
            if (!$stmt->fetch()) {
                // Agregar la relación
                $stmt = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
                $stmt->execute([$roleId, $permissionId]);
                echo "  ✓ Agregado permiso: $permissionName\n";
                $addedCount++;
            }
        }
        
        echo "  → Permisos nuevos agregados: $addedCount\n\n";
    }
    
    echo "=== VERIFICACIÓN FINAL ===\n\n";
    
    // Mostrar resumen de permisos por rol
    $stmt = $pdo->query('
        SELECT r.name as role_name, COUNT(rp.permission_id) as permission_count
        FROM roles r 
        LEFT JOIN role_permissions rp ON r.id = rp.role_id 
        GROUP BY r.id, r.name 
        ORDER BY r.name
    ');
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Rol '{$row['role_name']}': {$row['permission_count']} permisos\n";
    }
    
    echo "\n✅ Actualización de roles completada exitosamente!\n";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>