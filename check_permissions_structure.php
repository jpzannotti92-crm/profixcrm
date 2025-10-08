<?php
// Verificar estructura de permisos y roles en la base de datos
$token = file_get_contents('current_token.txt');

echo "=== VERIFICAR ESTRUCTURA DE PERMISOS Y ROLES ===\n";

// 1. Verificar permisos existentes
$ch = curl_init('http://127.0.0.1:8001/api/permissions.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "1. PERMISOS EXISTENTES:\n";
echo "Código HTTP: $httpCode\n";
echo "Respuesta: $response\n";

// 2. Verificar rol específico con sus permisos
echo "\n2. VERIFICAR ROL TEST_ROLE:\n";
$ch = curl_init('http://127.0.0.1:8001/api/roles.php?id=5');
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

// 3. Verificar usuario leadagent
echo "\n3. VERIFICAR USUARIO LEADAGENT:\n";
$ch = curl_init('http://127.0.0.1:8001/api/users.php?id=7');
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

// 4. Verificar permisos directamente desde la base de datos
echo "\n4. VERIFICAR PERMISOS DIRECTOS DEL USUARIO:\n";
$ch = curl_init('http://127.0.0.1:8001/api/user-permissions.php?action=user-profile&user_id=7');
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