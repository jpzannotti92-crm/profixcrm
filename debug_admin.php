<?php
// Debug de la consulta original

$conn = new mysqli('localhost', 'root', '', 'spin2pay_profixcrm');
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Verificar usuario admin directamente
echo "Verificando usuario 'admin':\n";
$result = $conn->query("SELECT id, username, email FROM users WHERE username = 'admin' LIMIT 1");
if ($result && $result->num_rows > 0) {
    $admin = $result->fetch_assoc();
    echo "✅ Usuario admin encontrado: ID = {$admin['id']}\n";
    
    // Verificar roles del usuario
    $rolesResult = $conn->query("
        SELECT r.name as role_name
        FROM roles r
        INNER JOIN user_roles ur ON r.id = ur.role_id
        WHERE ur.user_id = {$admin['id']}
    ");
    
    if ($rolesResult && $rolesResult->num_rows > 0) {
        echo "Roles encontrados:\n";
        while ($row = $rolesResult->fetch_assoc()) {
            echo "- {$row['role_name']}\n";
        }
    } else {
        echo "⚠️  No se encontraron roles para este usuario\n";
    }
} else {
    echo "❌ Usuario admin no encontrado\n";
}

$conn->close();