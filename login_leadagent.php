<?php
// Login con usuario leadagent para obtener su token
$loginData = [
    'username' => 'leadagent',
    'password' => 'password'
];

$ch = curl_init('http://127.0.0.1:8001/api/auth/login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "=== LOGIN LEADAGENT ===\n";
echo "Código HTTP: $httpCode\n";
echo "Respuesta: $response\n\n";

$data = json_decode($response, true);
if ($httpCode === 200 && isset($data['success']) && $data['success'] === true) {
    $token = $data['token'];
    file_put_contents('leadagent_token.txt', $token);
    echo "✓ Token guardado en leadagent_token.txt\n";
} else {
    echo "✗ Error en login\n";
}