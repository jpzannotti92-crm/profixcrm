<?php
$_ENV['DB_DATABASE'] = 'spin2pay_profixcrm';
$config = require 'config/database.php';

try {
    $dbConfig = $config['connections']['mysql'];
    $dsn = 'mysql:host=' . $dbConfig['host'] . ';port=' . $dbConfig['port'] . ';dbname=' . $dbConfig['database'] . ';charset=' . $dbConfig['charset'];
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    
    echo "=== AGREGANDO PERMISOS ADICIONALES PARA test_role ===" . PHP_EOL . PHP_EOL;
    
    // Obtener ID del rol test_role
    $stmt = $db->prepare('SELECT id FROM roles WHERE name = ?');
    $stmt->execute(['test_role']);
    $testRoleId = $stmt->fetchColumn();
    
    // Permisos adicionales que podrían faltar
    $additionalPermissions = [
        'leads.assign' => 87,  // Ya existe este permiso
        'leads.view.desk' => 13,  // Ya existe este permiso
        'desks.view' => 4,  // Ya existe este permiso
        'leads.view.all' => 11  // Ya existe este permiso
    ];
    
    echo "Asignando permisos adicionales a test_role:" . PHP_EOL;
    
    foreach ($additionalPermissions as $permCode => $permId) {
        // Verificar si ya está asignado
        $stmt = $db->prepare('SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = ?');
        $stmt->execute([$testRoleId, $permId]);
        
        if ($stmt->fetch()) {
            echo "  ✓ $permCode ya asignado" . PHP_EOL;
        } else {
            // Asignar el permiso
            $stmt = $db->prepare('INSERT INTO role_permissions (role_id, permission_id, granted_by) VALUES (?, ?, 1)');
            $stmt->execute([$testRoleId, $permId]);
            echo "  + Asignado $permCode" . PHP_EOL;
        }
    }
    
    echo PHP_EOL . "=== TAMBIÉN AGREGANDO A Sales Agent ===" . PHP_EOL . PHP_EOL;
    
    // Obtener ID del rol Sales Agent
    $stmt = $db->prepare('SELECT id FROM roles WHERE name = ?');
    $stmt->execute(['Sales Agent']);
    $salesAgentId = $stmt->fetchColumn();
    
    echo "Asignando permisos adicionales a Sales Agent:" . PHP_EOL;
    
    foreach ($additionalPermissions as $permCode => $permId) {
        // Verificar si ya está asignado
        $stmt = $db->prepare('SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = ?');
        $stmt->execute([$salesAgentId, $permId]);
        
        if ($stmt->fetch()) {
            echo "  ✓ $permCode ya asignado" . PHP_EOL;
        } else {
            // Asignar el permiso
            $stmt = $db->prepare('INSERT INTO role_permissions (role_id, permission_id, granted_by) VALUES (?, ?, 1)');
            $stmt->execute([$salesAgentId, $permId]);
            echo "  + Asignado $permCode" . PHP_EOL;
        }
    }
    
    echo PHP_EOL . "=== VERIFICANDO PERMISOS FINALES ===" . PHP_EOL . PHP_EOL;
    
    // Verificar permisos finales de test_role
    echo "test_role permisos actualizados:" . PHP_EOL;
    $stmt = $db->prepare('
        SELECT p.code, p.description
        FROM role_permissions rp
        JOIN roles r ON rp.role_id = r.id
        JOIN permissions p ON rp.permission_id = p.id
        WHERE r.name = ? AND (p.code LIKE "%desk%" OR p.code LIKE "%lead%" OR p.code LIKE "%assign%")
        ORDER BY p.code
    ');
    $stmt->execute(['test_role']);
    $finalPerms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($finalPerms as $perm) {
        echo "  - {$perm['code']}: {$perm['description']}" . PHP_EOL;
    }
    
    echo PHP_EOL . "✓ Permisos adicionales agregados!" . PHP_EOL;
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}