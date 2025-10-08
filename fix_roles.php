<?php
// Verificar asignaciones de roles

$conn = new mysqli('localhost', 'root', '', 'spin2pay_profixcrm');
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Verificar total de asignaciones
$result = $conn->query("SELECT COUNT(*) as total FROM model_has_roles");
$row = $result->fetch_assoc();
echo "Total de asignaciones de roles: " . $row['total'] . "\n";

// Ver usuarios con roles
$result = $conn->query("
    SELECT u.username, u.email, r.name as role_name
    FROM users u 
    LEFT JOIN model_has_roles mhr ON u.id = mhr.model_id 
    LEFT JOIN roles r ON mhr.role_id = r.id 
    WHERE mhr.role_id IS NOT NULL
    ORDER BY u.username
");

echo "\nUsuarios con roles asignados:\n";
while ($row = $result->fetch_assoc()) {
    echo "- {$row['username']} ({$row['email']}) -> {$row['role_name']}\n";
}

if ($result->num_rows === 0) {
    echo "\n⚠️  No hay usuarios con roles asignados!\n";
    
    // Ver primer usuario disponible
    $userResult = $conn->query("SELECT id, username, email FROM users LIMIT 1");
    if ($userResult->num_rows > 0) {
        $user = $userResult->fetch_assoc();
        echo "Primer usuario disponible: {$user['username']} (ID: {$user['id']})\n";
        
        // Asignar rol admin al primer usuario
        echo "Asignando rol 'admin' al usuario...\n";
        $conn->query("INSERT INTO model_has_roles (role_id, model_type, model_id) VALUES (1, 'App\\\\Models\\\\User', {$user['id']})");
        echo "✅ Rol asignado!\n";
    }
}

$conn->close();