<?php
// Obtener token JWT para pruebas
$ch = curl_init('http://127.0.0.1:8000/api/auth/login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['username' => 'admin', 'password' => 'admin12345!']));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
if (isset($data['token'])) {
    file_put_contents('current_token.txt', $data['token']);
    echo "Token obtenido exitosamente\n";
    echo "Token: " . substr($data['token'], 0, 20) . "...\n";
} else {
    echo "Error al obtener token\n";
    echo "Respuesta: " . $response . "\n";
}