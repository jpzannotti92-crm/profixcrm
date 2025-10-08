<?php
// Script para actualizar la tabla de permisos con nuevas funcionalidades
try {
    $pdo = new PDO('mysql:host=localhost;dbname=iatrade_crm', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== ACTUALIZANDO PERMISOS DEL SISTEMA ===\n\n";
    
    // Nuevos permisos a agregar
    $newPermissions = [
        // Actividades de Leads
        ['name' => 'activities.view', 'description' => 'Ver actividades de leads'],
        ['name' => 'activities.create', 'description' => 'Crear actividades de leads'],
        ['name' => 'activities.edit', 'description' => 'Editar actividades de leads'],
        ['name' => 'activities.delete', 'description' => 'Eliminar actividades de leads'],
        
        // Dashboard
        ['name' => 'dashboard.view', 'description' => 'Ver dashboard'],
        ['name' => 'dashboard.stats', 'description' => 'Ver estadísticas del dashboard'],
        
        // Permisos de Usuario
        ['name' => 'user_permissions.view', 'description' => 'Ver permisos de usuarios'],
        ['name' => 'user_permissions.edit', 'description' => 'Editar permisos de usuarios'],
        
        // Instrumentos Financieros
        ['name' => 'instruments.view', 'description' => 'Ver instrumentos financieros'],
        ['name' => 'instruments.create', 'description' => 'Crear instrumentos financieros'],
        ['name' => 'instruments.edit', 'description' => 'Editar instrumentos financieros'],
        ['name' => 'instruments.delete', 'description' => 'Eliminar instrumentos financieros'],
        
        // Importación/Exportación
        ['name' => 'import.leads', 'description' => 'Importar leads desde archivos'],
        ['name' => 'export.leads', 'description' => 'Exportar leads a archivos'],
        
        // Configuración del sistema
        ['name' => 'system.database', 'description' => 'Acceso a configuración de base de datos'],
        ['name' => 'system.maintenance', 'description' => 'Modo de mantenimiento del sistema']
    ];
    
    $insertedCount = 0;
    $existingCount = 0;
    
    foreach ($newPermissions as $permission) {
        // Verificar si el permiso ya existe
        $stmt = $pdo->prepare('SELECT id FROM permissions WHERE name = ?');
        $stmt->execute([$permission['name']]);
        
        if ($stmt->fetch()) {
            echo "✓ Permiso '{$permission['name']}' ya existe\n";
            $existingCount++;
        } else {
            // Insertar nuevo permiso
            $stmt = $pdo->prepare('INSERT INTO permissions (name, description, created_at) VALUES (?, ?, NOW())');
            $stmt->execute([$permission['name'], $permission['description']]);
            echo "✓ Agregado permiso: {$permission['name']} - {$permission['description']}\n";
            $insertedCount++;
        }
    }
    
    echo "\n=== RESUMEN ===\n";
    echo "Permisos nuevos agregados: $insertedCount\n";
    echo "Permisos que ya existían: $existingCount\n";
    echo "Total de permisos procesados: " . ($insertedCount + $existingCount) . "\n\n";
    
    // Mostrar todos los permisos actuales
    echo "=== TODOS LOS PERMISOS ACTUALES ===\n\n";
    $stmt = $pdo->query('SELECT name, description FROM permissions ORDER BY name');
    $allPermissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($allPermissions as $permission) {
        echo "- {$permission['name']}: {$permission['description']}\n";
    }
    
    echo "\nTotal de permisos en el sistema: " . count($allPermissions) . "\n";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>