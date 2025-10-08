<?php
require_once 'config/database.php';

$config = require 'config/database.php';
$dbConfig = $config['connections']['mysql'];

try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']}",
        $dbConfig['username'],
        $dbConfig['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Completando columnas faltantes en permisos...\n\n";
    
    // Definir las actualizaciones para cada permiso
    $updates = [
        // Permisos de estados del desk (57-64)
        57 => [
            'display_name' => 'Ver Estados del Desk',
            'module' => 'desk_states',
            'action' => 'view'
        ],
        58 => [
            'display_name' => 'Crear Estados',
            'module' => 'desk_states',
            'action' => 'create'
        ],
        59 => [
            'display_name' => 'Editar Estados',
            'module' => 'desk_states',
            'action' => 'edit'
        ],
        60 => [
            'display_name' => 'Eliminar Estados',
            'module' => 'desk_states',
            'action' => 'delete'
        ],
        61 => [
            'display_name' => 'Gestionar Transiciones',
            'module' => 'state_transitions',
            'action' => 'manage'
        ],
        62 => [
            'display_name' => 'Cambiar Estado de Leads',
            'module' => 'lead_states',
            'action' => 'change'
        ],
        63 => [
            'display_name' => 'Ver Historial de Estados',
            'module' => 'lead_states',
            'action' => 'history'
        ],
        64 => [
            'display_name' => 'Gestionar Plantillas',
            'module' => 'state_templates',
            'action' => 'manage'
        ],
        // Permiso 65 ya tiene module y action correctos, solo actualizar si es necesario
        // Permiso 66 ya tiene module y action correctos
        // Permiso 68 ya tiene module y action correctos
        
        // Permisos de leads (69-72)
        69 => [
            'display_name' => 'Ver Todos los Leads',
            'module' => 'leads',
            'action' => 'view_all'
        ],
        70 => [
            'display_name' => 'Ver Leads Asignados',
            'module' => 'leads',
            'action' => 'view_assigned'
        ],
        71 => [
            'display_name' => 'Ver Leads del Desk',
            'module' => 'leads',
            'action' => 'view_desk'
        ],
        72 => [
            'display_name' => 'Reasignar Leads',
            'module' => 'leads',
            'action' => 'reassign'
        ]
    ];
    
    $updated = 0;
    
    foreach ($updates as $id => $data) {
        $stmt = $pdo->prepare("
            UPDATE permissions 
            SET display_name = ?, module = ?, action = ? 
            WHERE id = ?
        ");
        
        $result = $stmt->execute([
            $data['display_name'],
            $data['module'],
            $data['action'],
            $id
        ]);
        
        if ($result) {
            echo "✅ Actualizado permiso ID {$id}: {$data['display_name']}\n";
            $updated++;
        } else {
            echo "❌ Error actualizando permiso ID {$id}\n";
        }
    }
    
    echo "\n=== RESUMEN ===\n";
    echo "Permisos actualizados: {$updated}\n";
    
    // Verificar los cambios
    echo "\nVerificando cambios realizados:\n";
    $stmt = $pdo->query('SELECT * FROM permissions WHERE id >= 57 ORDER BY id');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: {$row['id']} | {$row['name']} | Display: {$row['display_name']} | Module: {$row['module']} | Action: {$row['action']}\n";
    }
    
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
?>