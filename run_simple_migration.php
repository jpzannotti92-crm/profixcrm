<?php
/**
 * Script simplificado de migración para Estados dinámicos
 */

$host = 'localhost';
$dbname = 'iatrade_crm';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Conectado a la base de datos exitosamente.\n";
    
    // Ejecutar migración simplificada
    $sql = file_get_contents('database/migrations/create_states_tables_simple.sql');
    
    if ($sql === false) {
        throw new Exception('No se pudo leer el archivo de migración');
    }
    
    echo "Ejecutando migración simplificada...\n";
    
    // Ejecutar todo el SQL de una vez
    $pdo->exec($sql);
    
    echo "✅ Tablas creadas exitosamente.\n";
    
    // Insertar permisos
    echo "Insertando permisos...\n";
    
    $permissions = [
        ['desk_states.view', 'Ver Estados del Desk', 'Permite ver los estados configurados del desk'],
        ['desk_states.create', 'Crear Estados', 'Permite crear nuevos estados para el desk'],
        ['desk_states.edit', 'Editar Estados', 'Permite modificar estados existentes'],
        ['desk_states.delete', 'Eliminar Estados', 'Permite eliminar estados del desk'],
        ['state_transitions.manage', 'Gestionar Transiciones', 'Permite configurar transiciones entre estados'],
        ['lead_states.change', 'Cambiar Estado de Leads', 'Permite cambiar el estado de los leads'],
        ['lead_states.history', 'Ver Historial de Estados', 'Permite ver el historial de cambios de estado'],
        ['state_templates.manage', 'Gestionar Plantillas', 'Permite crear y gestionar plantillas de estados']
    ];
    
    foreach ($permissions as $perm) {
        try {
            $stmt = $pdo->prepare("INSERT IGNORE INTO permissions (name, display_name, description) VALUES (?, ?, ?)");
            $stmt->execute($perm);
        } catch (PDOException $e) {
            echo "Warning: " . $e->getMessage() . "\n";
        }
    }
    
    // Asignar permisos al admin
    echo "Asignando permisos al admin...\n";
    
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO role_permissions (role_id, permission_id)
        SELECT 2, id FROM permissions 
        WHERE name IN ('desk_states.view', 'desk_states.create', 'desk_states.edit', 'desk_states.delete',
                       'state_transitions.manage', 'lead_states.change', 'lead_states.history', 'state_templates.manage')
    ");
    $stmt->execute();
    
    // Insertar estados por defecto para cada desk
    echo "Creando estados por defecto...\n";
    
    $defaultStates = [
        ['new', 'Nuevo', 'Lead recién ingresado', '#3B82F6', 'user-plus', 1, 1],
        ['contacted', 'Contactado', 'Se estableció contacto', '#10B981', 'phone', 0, 2],
        ['interested', 'Interesado', 'Muestra interés', '#F59E0B', 'star', 0, 3],
        ['demo_account', 'Cuenta Demo', 'Creó cuenta demo', '#8B5CF6', 'play', 0, 4],
        ['ftd', 'Primer Depósito', 'Realizó primer depósito', '#059669', 'currency-dollar', 0, 5],
        ['client', 'Cliente', 'Cliente activo', '#DC2626', 'user-check', 0, 6],
        ['not_interested', 'No Interesado', 'Sin interés', '#6B7280', 'x-circle', 0, 7],
        ['lost', 'Perdido', 'Lead perdido', '#EF4444', 'trash', 0, 8]
    ];
    
    // Obtener todos los desks
    $stmt = $pdo->query("SELECT id FROM desks");
    $desks = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($desks as $deskId) {
        foreach ($defaultStates as $state) {
            try {
                $stmt = $pdo->prepare("
                    INSERT IGNORE INTO desk_states 
                    (desk_id, name, display_name, description, color, icon, is_initial, sort_order) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute(array_merge([$deskId], $state));
            } catch (PDOException $e) {
                echo "Warning: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // Verificar resultados
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM desk_states');
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "📊 Estados creados: " . $result['count'] . "\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM permissions WHERE name LIKE 'desk_states.%' OR name LIKE 'state_%'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "🔐 Permisos creados: " . $result['count'] . "\n";
    
    echo "\n🎉 Migración completada exitosamente!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>