<?php
// Verificar el rol que creamos y sus permisos
$token = file_get_contents('current_token.txt');

echo "=== VERIFICAR ROL CREADO Y SUS PERMISOS ===\n";

// Primero obtener todos los roles
$ch = curl_init('http://127.0.0.1:8001/api/roles.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Código HTTP: $httpCode\n";
echo "Respuesta: $response\n";

if ($httpCode === 200) {
    $data = json_decode($response, true);
    echo "\n=== ROLES DISPONIBLES ===\n";
    if (isset($data['data'])) {
        foreach ($data['data'] as $role) {
            echo "ID: " . $role['id'] . " - Nombre: " . $role['name'] . " - Display: " . $role['display_name'] . "\n";
            if ($role['id'] == 5) { // El rol que creamos
                echo "Permisos del rol Test Role:\n";
                if (isset($role['permissions'])) {
                    foreach ($role['permissions'] as $perm) {
                        echo "  - " . $perm . "\n";
                    }
                }
            }
        }
    }
}