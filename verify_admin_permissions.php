<?php
$pdo = new PDO('mysql:host=localhost;dbname=spin2pay_profixcrm', 'root', '');

// Obtener el ID del rol admin
$stmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'admin'");
$stmt->execute();
$role = $stmt->fetch(PDO::FETCH_ASSOC);

if ($role) {
    $role_id = $role['id'];
    echo "=== PERMISOS DEL ROL 'ADMIN' ===\n\n";
    
    $stmt = $pdo->prepare("
        SELECT p.name, p.description 
        FROM permissions p 
        INNER JOIN role_permissions rp ON p.id = rp.permission_id 
        WHERE rp.role_id = ? 
        ORDER BY p.name
    ");
    $stmt->execute([$role_id]);
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total de permisos: " . count($permissions) . "\n\n";
    
    // Verificar permisos específicos que necesita el frontend
    $required_permissions = ['leads.view', 'users.view', 'roles.view', 'desks.view', 'manage_states', 'trading_accounts.view'];
    
    foreach ($required_permissions as $perm) {
        $found = false;
        foreach ($permissions as $p) {
            if ($p['name'] === $perm) {
                echo "✓ $perm: {$p['description']}\n";
                $found = true;
                break;
            }
        }
        if (!$found) {
            echo "✗ $perm: NO ENCONTRADO\n";
        }
    }
    
    echo "\n=== TODOS LOS PERMISOS ===\n";
    foreach ($permissions as $permission) {
        echo "- {$permission['name']}: {$permission['description']}\n";
    }
} else {
    echo "Rol 'admin' no encontrado.\n";
}
?>