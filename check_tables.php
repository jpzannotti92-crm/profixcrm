<?php
// Verificar estructura de tablas

$conn = new mysqli('localhost', 'root', '', 'spin2pay_profixcrm');
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Verificar tablas relacionadas con roles
$tables = ['roles', 'permissions', 'role_has_permissions', 'model_has_roles', 'users'];

foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "✅ Tabla $table existe\n";
        
        // Si es model_has_roles, ver su estructura
        if ($table == 'model_has_roles') {
            $desc = $conn->query("DESCRIBE $table");
            echo "   Estructura:\n";
            while ($col = $desc->fetch_assoc()) {
                echo "   - {$col['Field']}: {$col['Type']}\n";
            }
        }
    } else {
        echo "❌ Tabla $table NO existe\n";
    }
}

// Ver primer usuario disponible
$result = $conn->query("SELECT id, username, email FROM users LIMIT 1");
if ($result && $result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo "\nPrimer usuario disponible: {$user['username']} (ID: {$user['id']})\n";
} else {
    echo "\n❌ No hay usuarios en la tabla\n";
}

$conn->close();