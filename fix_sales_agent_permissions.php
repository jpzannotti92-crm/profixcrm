<?php
$_ENV['DB_DATABASE'] = 'spin2pay_profixcrm';
$config = require 'config/database.php';

try {
    $dbConfig = $config['connections']['mysql'];
    $dsn = 'mysql:host=' . $dbConfig['host'] . ';port=' . $dbConfig['port'] . ';dbname=' . $dbConfig['database'] . ';charset=' . $dbConfig['charset'];
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    
    echo "=== AGREGANDO PERMISOS A SALES AGENT ===" . PHP_EOL;
    
    // Obtener ID del rol Sales Agent
    $stmt = $db->prepare('SELECT id FROM roles WHERE name = ?');
    $stmt->execute(['Sales Agent']);
    $salesAgentRoleId = $stmt->fetchColumn();
    echo "Sales Agent Role ID: " . $salesAgentRoleId . PHP_EOL;
    
    // Obtener permisos de leads que necesita Sales Agent
    $neededPermissions = [
        'leads.create' => 'Crear leads',
        'leads.edit' => 'Editar leads',
        'leads.delete' => 'Eliminar leads',
        'leads.view' => 'Ver todos los leads'
    ];
    
    echo PHP_EOL . "Verificando permisos necesarios:" . PHP_EOL;
    foreach ($neededPermissions as $permName => $desc) {
        $stmt = $db->prepare('SELECT id FROM permissions WHERE name = ?');
        $stmt->execute([$permName]);
        $permId = $stmt->fetchColumn();
        
        if ($permId) {
            echo "✓ Encontrado: $permName (ID: $permId)" . PHP_EOL;
            
            // Verificar si ya tiene el permiso
            $stmt = $db->prepare('SELECT COUNT(*) FROM role_permissions WHERE role_id = ? AND permission_id = ?');
            $stmt->execute([$salesAgentRoleId, $permId]);
            $hasPermission = $stmt->fetchColumn();
            
            if (!$hasPermission) {
                echo "  → Agregando permiso..." . PHP_EOL;
                $stmt = $db->prepare('INSERT INTO role_permissions (role_id, permission_id, granted_by) VALUES (?, ?, ?)');
                $stmt->execute([$salesAgentRoleId, $permId, 1]); // 1 = admin
                echo "  ✓ Permiso agregado!" . PHP_EOL;
            } else {
                echo "  → Ya tiene este permiso" . PHP_EOL;
            }
        } else {
            echo "✗ No encontrado: $permName" . PHP_EOL;
        }
    }
    
    echo PHP_EOL . "=== PERMISOS ACTUALIZADOS DE SALES AGENT ===" . PHP_EOL;
    $stmt = $db->prepare('
        SELECT p.name, p.description
        FROM role_permissions rp
        JOIN permissions p ON rp.permission_id = p.id
        WHERE rp.role_id = ?
    ');
    $stmt->execute([$salesAgentRoleId]);
    $currentPerms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($currentPerms as $perm) {
        echo "✓ " . $perm['name'] . " - " . $perm['description'] . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}