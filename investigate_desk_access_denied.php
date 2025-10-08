<?php
$_ENV['DB_DATABASE'] = 'spin2pay_profixcrm';
$config = require 'config/database.php';

try {
    $dbConfig = $config['connections']['mysql'];
    $dsn = 'mysql:host=' . $dbConfig['host'] . ';port=' . $dbConfig['port'] . ';dbname=' . $dbConfig['database'] . ';charset=' . $dbConfig['charset'];
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    
    echo "=== INVESTIGANDO PROBLEMA DESK_ACCESS_DENIED ===" . PHP_EOL . PHP_EOL;
    
    // Verificar qué mesas tienen asignados los usuarios test_role
    echo "1. MESAS ASIGNADAS A USUARIOS TEST_ROLE:" . PHP_EOL;
    $stmt = $db->prepare('
        SELECT u.username, u.id as user_id, d.id as desk_id, d.name as desk_name
        FROM users u
        JOIN user_roles ur ON u.id = ur.user_id
        JOIN roles r ON ur.role_id = r.id
        LEFT JOIN desk_users du ON u.id = du.user_id
        LEFT JOIN desks d ON du.desk_id = d.id
        WHERE r.name = ?
    ');
    $stmt->execute(['test_role']);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $user) {
        $deskName = $user['desk_name'] ?: 'SIN MESA';
        echo "   {$user['username']} -> Mesa {$deskName} (ID: {$user['desk_id']})" . PHP_EOL;
    }
    
    echo PHP_EOL . "2. PERMISOS DE MESA PARA test_role:" . PHP_EOL;
    
    // Verificar permisos de mesa específicos
    foreach ($users as $user) {
        if ($user['desk_id']) {
            echo "   {$user['username']} (Mesa {$user['desk_id']}):" . PHP_EOL;
            
            $stmt = $db->prepare('
                SELECT p.code, p.description
                FROM role_permissions rp
                JOIN roles r ON rp.role_id = r.id
                JOIN permissions p ON rp.permission_id = p.id
                WHERE r.name = ? AND p.code LIKE ?
            ');
            $stmt->execute(['test_role', "desk.{$user['desk_id']}%"]);
            $deskPerms = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($deskPerms) {
                foreach ($deskPerms as $perm) {
                    echo "     - {$perm['code']}: {$perm['description']}" . PHP_EOL;
                }
            } else {
                echo "     - SIN PERMISOS PARA ESTA MESA" . PHP_EOL;
            }
        }
    }
    
    echo PHP_EOL . "3. VERIFICANDO SI HAY PERMISOS ADICIONALES NECESARIOS:" . PHP_EOL;
    
    // Buscar otros permisos de mesa que puedan faltar
    $stmt = $db->query('
        SELECT DISTINCT p.code, p.description
        FROM permissions p
        WHERE p.code LIKE "%desk%" AND p.code NOT LIKE "%create%" AND p.code NOT LIKE "%assign%"
        ORDER BY p.code
    ');
    $otherDeskPerms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($otherDeskPerms as $perm) {
        echo "   - {$perm['code']}: {$perm['description']}" . PHP_EOL;
    }
    
    echo PHP_EOL . "4. POSIBLES SOLUCIONES:" . PHP_EOL;
    echo "   a) Verificar si faltan permisos 'desk.view' o similares" . PHP_EOL;
    echo "   b) Verificar si hay restricciones adicionales por rol" . PHP_EOL;
    echo "   c) Verificar si los usuarios necesitan permisos 'leads.assign' específicos" . PHP_EOL;
    
    // Verificar permisos leads.assign
    echo PHP_EOL . "5. PERMISOS LEADS.ASSIGN:" . PHP_EOL;
    $stmt = $db->prepare('
        SELECT p.code, p.description
        FROM role_permissions rp
        JOIN roles r ON rp.role_id = r.id
        JOIN permissions p ON rp.permission_id = p.id
        WHERE r.name = ? AND p.code LIKE "%assign%"
        ORDER BY p.code
    ');
    $stmt->execute(['test_role']);
    $assignPerms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($assignPerms as $perm) {
        echo "   - {$perm['code']}: {$perm['description']}" . PHP_EOL;
    }
    
    if (empty($assignPerms)) {
        echo "   - NO TIENE PERMISOS DE ASIGNACIÓN" . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}