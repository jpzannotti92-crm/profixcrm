<?php
/**
 * Script simple para verificar usuarios y roles
 */

// Configurar el entorno para simular una petición HTTP
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost:8080';

// Simular una sesión de usuario admin
session_start();
$_SESSION = [];
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';

echo "=== VERIFICACIÓN DE USUARIOS Y ROLES ===\n\n";

// 1. Verificar usuarios
echo "1. VERIFICANDO USUARIOS...\n";
$_GET = [];

try {
    ob_start();
    include 'public/api/users.php';
    $usersOutput = ob_get_clean();
    
    echo "Respuesta cruda de users.php:\n";
    echo $usersOutput . "\n\n";
    
    $usersData = json_decode($usersOutput, true);
    if ($usersData && isset($usersData['data'])) {
        $users = is_array($usersData['data']) ? $usersData['data'] : [];
        if (isset($usersData['data']['users'])) {
            $users = $usersData['data']['users'];
        }
        
        echo "Usuarios encontrados: " . count($users) . "\n";
        if (count($users) > 0) {
            foreach ($users as $index => $user) {
                if (is_array($user)) {
                    $username = $user['username'] ?? 'N/A';
                    $email = $user['email'] ?? 'N/A';
                    $firstName = $user['first_name'] ?? 'N/A';
                    $lastName = $user['last_name'] ?? 'N/A';
                    echo ($index + 1) . ". {$username} - {$firstName} {$lastName} ({$email})\n";
                }
            }
        }
    } else {
        echo "Error al decodificar JSON o sin datos\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    $output = ob_get_clean();
    echo "Output capturado: " . $output . "\n";
}

echo "\n";

// 2. Verificar roles
echo "2. VERIFICANDO ROLES...\n";
try {
    ob_start();
    include 'public/api/roles.php';
    $rolesOutput = ob_get_clean();
    
    echo "Respuesta cruda de roles.php:\n";
    echo $rolesOutput . "\n\n";
    
    $rolesData = json_decode($rolesOutput, true);
    if ($rolesData && isset($rolesData['data'])) {
        $roles = is_array($rolesData['data']) ? $rolesData['data'] : [];
        if (isset($rolesData['data']['roles'])) {
            $roles = $rolesData['data']['roles'];
        }
        
        echo "Roles encontrados: " . count($roles) . "\n";
        if (count($roles) > 0) {
            foreach ($roles as $index => $role) {
                if (is_array($role)) {
                    $name = $role['display_name'] ?? $role['name'] ?? $role['code'] ?? 'N/A';
                    $description = $role['description'] ?? $role['name'] ?? 'N/A';
                    $id = $role['id'] ?? 'N/A';
                    echo ($index + 1) . ". {$name} (ID: {$id}) - {$description}\n";
                }
            }
        }
    } else {
        echo "Error al decodificar JSON o sin datos\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    $output = ob_get_clean();
    echo "Output capturado: " . $output . "\n";
}

echo "\n=== FIN VERIFICACIÓN ===\n";