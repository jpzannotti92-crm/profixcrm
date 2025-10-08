<?php
// Verificar usuarios con roles usando las tablas correctas

$conn = new mysqli('localhost', 'root', '', 'spin2pay_profixcrm');
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Ver usuarios con roles
echo "Usuarios con roles asignados:\n";
$result = $conn->query("
    SELECT u.username, u.email, r.name as role_name
    FROM users u 
    LEFT JOIN user_roles ur ON u.id = ur.user_id 
    LEFT JOIN roles r ON ur.role_id = r.id 
    WHERE ur.role_id IS NOT NULL
    ORDER BY u.username
");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "- {$row['username']} ({$row['email']}) -> {$row['role_name']}\n";
    }
} else {
    echo "⚠️  No hay usuarios con roles asignados!\n";
    
    // Ver primer usuario disponible
    $userResult = $conn->query("SELECT id, username, email FROM users LIMIT 1");
    if ($userResult && $userResult->num_rows > 0) {
        $user = $userResult->fetch_assoc();
        echo "\nPrimer usuario disponible: {$user['username']} (ID: {$user['id']})\n";
        
        // Asignar rol admin al primer usuario
        echo "Asignando rol 'admin' al usuario...\n";
        if ($conn->query("INSERT INTO user_roles (user_id, role_id) VALUES ({$user['id']}, 1)")) {
            echo "✅ Rol 'admin' asignado!\n";
        } else {
            echo "❌ Error asignando rol: " . $conn->error . "\n";
        }
    }
}

$conn->close();