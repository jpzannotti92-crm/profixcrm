<?php
// Verificar si hay permisos específicos de leads
$token = file_get_contents('current_token.txt');

echo "=== BUSCANDO PERMISOS DE LEADS ===\n";

$ch = curl_init('http://127.0.0.1:8001/api/user-permissions.php?action=user-profile');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
if (isset($data['permissions'])) {
    echo "Permisos relacionados con leads:\n";
    foreach ($data['permissions'] as $permission) {
        if (stripos($permission, 'lead') !== false) {
            echo "- " . $permission . "\n";
        }
    }
}

// También probar con el endpoint de leads para ver si funciona GET
echo "\n=== PROBANDO GET LEADS ===\n";
$ch = curl_init('http://127.0.0.1:8001/api/leads.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Código HTTP GET: $httpCode\n";
echo "Respuesta GET: $response\n";