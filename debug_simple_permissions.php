<?php
// Conexión directa a la base de datos
try {
    $db = new PDO('mysql:host=localhost;dbname=spin2pay_profixcrm', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage() . "\n");
}

$users = ['jpzannotti92', 'mparedes02', 'test_front', 'leadmanager', 'leadagent'];

echo "=== DEBUG DE PERMISOS DE USUARIOS (SIMPLE) ===\n\n";

foreach ($users as $username) {
    echo "--- USUARIO: $username ---\n";
    
    // Obtener usuario
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "Usuario no encontrado\n\n";
        continue;
    }
    
    echo "ID: {$user['id']}\n";
    
    // Obtener roles del usuario (versión simplificada)
    $stmt = $db->prepare("SELECT r.name FROM roles r JOIN user_roles ur ON r.id = ur.role_id WHERE ur.user_id = ?");
    $stmt->execute([$user['id']]);
    $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Roles: " . implode(', ', $roles) . "\n";
    
    // Obtener permisos del usuario (versión simplificada)
    $stmt = $db->prepare("
        SELECT DISTINCT p.code 
        FROM permissions p 
        JOIN role_permissions rp ON p.id = rp.permission_id 
        JOIN user_roles ur ON rp.role_id = ur.role_id 
        WHERE ur.user_id = ?
    ");
    $stmt->execute([$user['id']]);
    $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Permisos totales: " . count($permissions) . "\n";
    $leadPermissions = array_filter($permissions, function($p) {
        return strpos($p, 'leads.') === 0;
    });
    echo "Permisos de leads: " . implode(', ', $leadPermissions) . "\n";
    
    // Obtener mesas del usuario
    $stmt = $db->prepare("SELECT d.name FROM desks d JOIN desk_users du ON d.id = du.desk_id WHERE du.user_id = ?");
    $stmt->execute([$user['id']]);
    $desks = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Mesas asignadas: " . implode(', ', $desks) . "\n";
    
    // Verificar permisos específicos
    echo "Permisos específicos:\n";
    echo "- leads.view.all: " . (in_array('leads.view.all', $permissions) ? 'SÍ' : 'NO') . "\n";
    echo "- leads.view.assigned: " . (in_array('leads.view.assigned', $permissions) ? 'SÍ' : 'NO') . "\n";
    echo "- leads.view.desk: " . (in_array('leads.view.desk', $permissions) ? 'SÍ' : 'NO') . "\n";
    echo "- leads.create: " . (in_array('leads.create', $permissions) ? 'SÍ' : 'NO') . "\n";
    echo "- leads.edit: " . (in_array('leads.edit', $permissions) ? 'SÍ' : 'NO') . "\n";
    echo "- leads.delete: " . (in_array('leads.delete', $permissions) ? 'SÍ' : 'NO') . "\n";
    
    // Verificar últimos leads asignados a este usuario
    $stmt = $db->prepare("SELECT id, first_name, last_name, desk_id FROM leads WHERE assigned_to = ? ORDER BY id DESC LIMIT 3");
    $stmt->execute([$user['id']]);
    $userLeads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($userLeads)) {
        echo "Últimos leads asignados a este usuario:\n";
        foreach ($userLeads as $lead) {
            echo "  - ID: {$lead['id']}, Nombre: {$lead['first_name']} {$lead['last_name']}, Mesa: {$lead['desk_id']}\n";
        }
    } else {
        echo "No tiene leads asignados\n";
    }
    
    echo "\n";
}

echo "=== FIN DEBUG ===\n";