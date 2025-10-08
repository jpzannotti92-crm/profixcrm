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
    
    echo "Verificando permiso ID 62...\n";
    
    // Verificar si existe el permiso ID 62
    $stmt = $pdo->prepare('SELECT * FROM permissions WHERE id = 62');
    $stmt->execute();
    $permission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($permission) {
        echo "Permiso encontrado:\n";
        echo "ID: {$permission['id']}\n";
        echo "Name: {$permission['name']}\n";
        echo "Display: " . ($permission['display_name'] ?? 'NULL') . "\n";
        echo "Module: " . ($permission['module'] ?? 'NULL') . "\n";
        echo "Action: " . ($permission['action'] ?? 'NULL') . "\n\n";
        
        // Si las columnas están vacías, actualizarlas
        if (empty($permission['display_name']) || empty($permission['module']) || empty($permission['action'])) {
            echo "Actualizando permiso ID 62...\n";
            $stmt = $pdo->prepare("
                UPDATE permissions 
                SET display_name = ?, module = ?, action = ? 
                WHERE id = 62
            ");
            
            $result = $stmt->execute([
                'Cambiar Estado de Leads',
                'lead_states',
                'change'
            ]);
            
            if ($result) {
                echo "✅ Permiso ID 62 actualizado correctamente\n";
            } else {
                echo "❌ Error actualizando permiso ID 62\n";
            }
        } else {
            echo "El permiso ID 62 ya está completo\n";
        }
    } else {
        echo "❌ Permiso ID 62 no encontrado\n";
    }
    
    // Verificar todos los permisos desde ID 57 para asegurar que están completos
    echo "\nVerificando todos los permisos desde ID 57:\n";
    $stmt = $pdo->query('SELECT * FROM permissions WHERE id >= 57 ORDER BY id');
    $incomplete = 0;
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $missing = [];
        if (empty($row['display_name'])) $missing[] = 'display_name';
        if (empty($row['module'])) $missing[] = 'module';
        if (empty($row['action'])) $missing[] = 'action';
        
        if (!empty($missing)) {
            echo "❌ ID {$row['id']} ({$row['name']}) - Faltan: " . implode(', ', $missing) . "\n";
            $incomplete++;
        } else {
            echo "✅ ID {$row['id']} ({$row['name']}) - Completo\n";
        }
    }
    
    echo "\nResumen: ";
    if ($incomplete == 0) {
        echo "Todos los permisos están completos ✅\n";
    } else {
        echo "{$incomplete} permisos incompletos ❌\n";
    }
    
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
?>