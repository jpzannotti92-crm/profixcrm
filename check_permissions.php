<?php
// Verificar permisos del usuario admin
$token = file_get_contents('current_token.txt');

echo "=== VERIFICAR PERMISOS DEL ADMIN ===\n";

$ch = curl_init('http://127.0.0.1:8001/api/user-permissions.php?action=user-profile');
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
    echo "\n=== PERMISOS DEL ADMIN ===\n";
    if (isset($data['permissions'])) {
        echo "Total de permisos: " . count($data['permissions']) . "\n";
        echo "Permisos:\n";
        foreach ($data['permissions'] as $permission) {
            echo "- " . $permission . "\n";
        }
    }
}