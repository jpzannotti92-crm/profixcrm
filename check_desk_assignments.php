<?php
$_ENV['DB_DATABASE'] = 'spin2pay_profixcrm';
$config = require 'config/database.php';

try {
    $dbConfig = $config['connections']['mysql'];
    $dsn = 'mysql:host=' . $dbConfig['host'] . ';port=' . $dbConfig['port'] . ';dbname=' . $dbConfig['database'] . ';charset=' . $dbConfig['charset'];
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    
    echo "=== MESAS DISPONIBLES ===" . PHP_EOL;
    $stmt = $db->query('SELECT id, name, description FROM desks ORDER BY id');
    $desks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($desks as $desk) {
        echo "ID: " . $desk['id'] . " - " . $desk['name'] . " - " . $desk['description'] . PHP_EOL;
    }
    
    echo PHP_EOL . "=== USUARIOS QUE NECESITAN MESAS PARA CREAR LEADS ===" . PHP_EOL;
    
    // Usuarios Sales Agent que necesitan crear leads
    $salesAgents = [
        'jpzannotti92' => ['name' => 'Jean Zannotti', 'current_desk' => 2], // Sales
        'mparedes02' => ['name' => 'Marc Paredes', 'current_desk' => 3],   // Retencion
        'test_front' => ['name' => 'Test Front', 'current_desk' => 3]      // Retencion
    ];
    
    // Usuarios test_role que ya tienen mesas
    $testRoleUsers = [
        'leadmanager' => ['name' => 'Lead Manager Pro', 'current_desk' => 5], // Test Desk Pro
        'leadagent' => ['name' => 'Lead Agent', 'current_desk' => 4]        // Test Desk (ya funciona)
    ];
    
    // Verificar asignaciones actuales
    $stmt = $db->query('
        SELECT u.id, u.username, u.first_name, u.last_name, d.name as desk_name, d.id as desk_id
        FROM users u
        LEFT JOIN desk_users du ON u.id = du.user_id
        LEFT JOIN desks d ON du.desk_id = d.id
        WHERE u.username IN ("jpzannotti92", "mparedes02", "test_front", "leadmanager", "leadagent")
        ORDER BY u.username
    ');
    $userDesks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($userDesks as $user) {
        $desk = $user['desk_name'] ? $user['desk_name'] . " (ID: " . $user['desk_id'] . ")" : "SIN MESA ASIGNADA";
        echo $user['username'] . " (" . $user['first_name'] . " " . $user['last_name'] . ") -> " . $desk . PHP_EOL;
        
        // Si el usuario ya tiene una mesa asignada, no hacer nada
        if ($user['desk_name']) {
            echo "  ✓ Ya tiene mesa asignada" . PHP_EOL;
        } else {
            echo "  ✗ Necesita mesa asignada" . PHP_EOL;
        }
    }
    
    echo PHP_EOL . "=== VERIFICANDO PROBLEMA DE LEADMANAGER ===" . PHP_EOL;
    
    // Verificar si leadmanager tiene acceso a su mesa
    $stmt = $db->prepare('
        SELECT du.user_id, du.desk_id, d.name as desk_name
        FROM desk_users du
        JOIN desks d ON du.desk_id = d.id
        WHERE du.user_id = (SELECT id FROM users WHERE username = ?)
    ');
    $stmt->execute(['leadmanager']);
    $leadmanagerDesk = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($leadmanagerDesk) {
        echo "LeadManager tiene asignada la mesa: " . $leadmanagerDesk['desk_name'] . " (ID: " . $leadmanagerDesk['desk_id'] . ")" . PHP_EOL;
    } else {
        echo "LeadManager NO tiene mesa asignada" . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}