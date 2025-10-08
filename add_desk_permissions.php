<?php
$_ENV['DB_DATABASE'] = 'spin2pay_profixcrm';
$config = require 'config/database.php';

try {
    $dbConfig = $config['connections']['mysql'];
    $dsn = 'mysql:host=' . $dbConfig['host'] . ';port=' . $dbConfig['port'] . ';dbname=' . $dbConfig['database'] . ';charset=' . $dbConfig['charset'];
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    
    echo "=== CREANDO PERMISOS ESPECÍFICOS DE MESA ===" . PHP_EOL . PHP_EOL;
    
    // Crear permisos específicos de mesa
    $deskPermissions = [
        'desk.2.create' => 'Crear leads en Sales Desk',
        'desk.2.assign' => 'Asignar leads a Sales Desk',
        'desk.3.create' => 'Crear leads en Retencion',
        'desk.3.assign' => 'Asignar leads a Retencion',
        'desk.4.create' => 'Crear leads en Test Desk',
        'desk.4.assign' => 'Asignar leads a Test Desk',
        'desk.5.create' => 'Crear leads en Test Desk Pro',
        'desk.5.assign' => 'Asignar leads a Test Desk Pro'
    ];
    
    $permissionIds = [];
    
    foreach ($deskPermissions as $code => $description) {
        // Verificar si ya existe
        $stmt = $db->prepare('SELECT id FROM permissions WHERE code = ?');
        $stmt->execute([$code]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            $permissionIds[$code] = $existing['id'];
            echo "✓ Permiso $code ya existe (ID: {$existing['id']})" . PHP_EOL;
        } else {
            // Crear el permiso
            $stmt = $db->prepare('INSERT INTO permissions (code, name, description) VALUES (?, ?, ?)');
            $stmt->execute([$code, $code, $description]);
            $permissionIds[$code] = $db->lastInsertId();
            echo "+ Creado permiso $code (ID: {$permissionIds[$code]})" . PHP_EOL;
        }
    }
    
    echo PHP_EOL . "=== ASIGNANDO PERMISOS A ROLES ===" . PHP_EOL . PHP_EOL;
    
    // Obtener IDs de roles
    $stmt = $db->prepare('SELECT id FROM roles WHERE name = ?');
    
    // Sales Agent
    $stmt->execute(['Sales Agent']);
    $salesAgentRoleId = $stmt->fetchColumn();
    
    // test_role
    $stmt->execute(['test_role']);
    $testRoleRoleId = $stmt->fetchColumn();
    
    // Asignar permisos a Sales Agent (mesas 2 y 3)
    echo "Asignando a Sales Agent:" . PHP_EOL;
    $salesAgentPermissions = [
        'desk.2.create',
        'desk.2.assign',
        'desk.3.create',
        'desk.3.assign'
    ];
    
    foreach ($salesAgentPermissions as $permCode) {
        if (isset($permissionIds[$permCode])) {
            $permId = $permissionIds[$permCode];
            
            // Verificar si ya está asignado
            $stmt = $db->prepare('SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = ?');
            $stmt->execute([$salesAgentRoleId, $permId]);
            
            if ($stmt->fetch()) {
                echo "  ✓ $permCode ya asignado" . PHP_EOL;
            } else {
                // Asignar el permiso
                $stmt = $db->prepare('INSERT INTO role_permissions (role_id, permission_id, granted_by) VALUES (?, ?, 1)');
                $stmt->execute([$salesAgentRoleId, $permId]);
                echo "  + Asignado $permCode" . PHP_EOL;
            }
        }
    }
    
    // Asignar permisos a test_role (mesas 4 y 5)
    echo PHP_EOL . "Asignando a test_role:" . PHP_EOL;
    $testRolePermissions = [
        'desk.4.create',
        'desk.4.assign',
        'desk.5.create',
        'desk.5.assign'
    ];
    
    foreach ($testRolePermissions as $permCode) {
        if (isset($permissionIds[$permCode])) {
            $permId = $permissionIds[$permCode];
            
            // Verificar si ya está asignado
            $stmt = $db->prepare('SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = ?');
            $stmt->execute([$testRoleRoleId, $permId]);
            
            if ($stmt->fetch()) {
                echo "  ✓ $permCode ya asignado" . PHP_EOL;
            } else {
                // Asignar el permiso
                $stmt = $db->prepare('INSERT INTO role_permissions (role_id, permission_id, granted_by) VALUES (?, ?, 1)');
                $stmt->execute([$testRoleRoleId, $permId]);
                echo "  + Asignado $permCode" . PHP_EOL;
            }
        }
    }
    
    echo PHP_EOL . "=== VERIFICANDO ASIGNACIONES FINALES ===" . PHP_EOL . PHP_EOL;
    
    // Verificar permisos finales de Sales Agent
    echo "Sales Agent permisos actualizados:" . PHP_EOL;
    $stmt = $db->prepare('
        SELECT p.code, p.name, p.description
        FROM role_permissions rp
        JOIN roles r ON rp.role_id = r.id
        JOIN permissions p ON rp.permission_id = p.id
        WHERE r.name = ? AND (p.code LIKE "%desk%" OR p.code LIKE "%lead%")
        ORDER BY p.code
    ');
    $stmt->execute(['Sales Agent']);
    $finalPerms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($finalPerms as $perm) {
        echo "  - {$perm['code']}: {$perm['description']}" . PHP_EOL;
    }
    
    echo PHP_EOL . "test_role permisos actualizados:" . PHP_EOL;
    $stmt->execute(['test_role']);
    $finalPerms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($finalPerms as $perm) {
        echo "  - {$perm['code']}: {$perm['description']}" . PHP_EOL;
    }
    
    echo PHP_EOL . "✓ Proceso completado!" . PHP_EOL;
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}