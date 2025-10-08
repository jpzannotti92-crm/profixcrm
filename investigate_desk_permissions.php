<?php
$_ENV['DB_DATABASE'] = 'spin2pay_profixcrm';
$config = require 'config/database.php';

try {
    $dbConfig = $config['connections']['mysql'];
    $dsn = 'mysql:host=' . $dbConfig['host'] . ';port=' . $dbConfig['port'] . ';dbname=' . $dbConfig['database'] . ';charset=' . $dbConfig['charset'];
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    
    echo "=== INVESTIGANDO PERMISOS DE MESA PARA CREAR LEADS ===" . PHP_EOL . PHP_EOL;
    
    // Verificar permisos específicos de mesa
    echo "1. PERMISOS DE MESA DISPONIBLES:" . PHP_EOL;
    $stmt = $db->query('SELECT DISTINCT permission FROM role_permissions WHERE permission LIKE "%desk%" OR permission LIKE "%lead%" ORDER BY permission');
    $deskPermissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($deskPermissions as $perm) {
        echo "   - $perm" . PHP_EOL;
    }
    
    echo PHP_EOL . "2. PERMISOS POR ROL:" . PHP_EOL;
    
    // Sales Agent
    echo PHP_EOL . "SALES AGENT:" . PHP_EOL;
    $stmt = $db->prepare('
        SELECT permission, description 
        FROM role_permissions rp
        JOIN roles r ON rp.role_id = r.id
        WHERE r.name = ?
        ORDER BY permission
    ');
    $stmt->execute(['Sales Agent']);
    $salesAgentPerms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($salesAgentPerms as $perm) {
        echo "   - {$perm['permission']}: {$perm['description']}" . PHP_EOL;
    }
    
    // test_role
    echo PHP_EOL . "TEST_ROLE:" . PHP_EOL;
    $stmt->execute(['test_role']);
    $testRolePerms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($testRolePerms as $perm) {
        echo "   - {$perm['permission']}: {$perm['description']}" . PHP_EOL;
    }
    
    echo PHP_EOL . "3. VERIFICANDO ACCESO A MESAS ESPECÍFICAS:" . PHP_EOL;
    
    // Verificar qué mesas pueden acceder cada rol
    $roles = ['Sales Agent', 'test_role'];
    foreach ($roles as $role) {
        echo PHP_EOL . "$role:" . PHP_EOL;
        
        // Obtener usuarios de este rol
        $stmt = $db->prepare('
            SELECT u.username, du.desk_id, d.name as desk_name
            FROM users u
            JOIN user_roles ur ON u.id = ur.user_id
            JOIN roles r ON ur.role_id = r.id
            LEFT JOIN desk_users du ON u.id = du.user_id
            LEFT JOIN desks d ON du.desk_id = d.id
            WHERE r.name = ?
        ');
        $stmt->execute([$role]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($users as $user) {
            $deskName = $user['desk_name'] ?: 'SIN MESA';
            echo "   {$user['username']} -> {$deskName} (ID: {$user['desk_id']})" . PHP_EOL;
            
            // Verificar permisos de esta mesa específica
            if ($user['desk_id']) {
                $stmt = $db->prepare('
                    SELECT permission 
                    FROM role_permissions rp
                    JOIN roles r ON rp.role_id = r.id
                    WHERE r.name = ? AND permission LIKE ?
                ');
                $stmt->execute([$role, "%desk.{$user['desk_id']}%"]);
                $deskPerms = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                if ($deskPerms) {
                    foreach ($deskPerms as $perm) {
                        echo "     - $perm" . PHP_EOL;
                    }
                } else {
                    echo "     - SIN PERMISOS PARA ESTA MESA" . PHP_EOL;
                }
            }
        }
    }
    
    echo PHP_EOL . "4. PERMISOS QUE PUEDEN FALTAR:" . PHP_EOL;
    echo "Para Sales Agent en Sales (ID: 2):" . PHP_EOL;
    echo "   - desk.2.create (crear en mesa 2)" . PHP_EOL;
    echo "   - desk.2.assign (asignar a mesa 2)" . PHP_EOL;
    
    echo PHP_EOL . "Para test_role en Test Desk (ID: 4) y Test Desk Pro (ID: 5):" . PHP_EOL;
    echo "   - desk.4.create, desk.4.assign" . PHP_EOL;
    echo "   - desk.5.create, desk.5.assign" . PHP_EOL;
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}