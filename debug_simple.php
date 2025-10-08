<?php
// Login como admin
$loginData = [
    'username' => 'admin',
    'password' => 'password'
];

$ch = curl_init('http://127.0.0.1:8000/api/auth/login');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$body = substr($response, $headerSize);
curl_close($ch);

echo "Login HTTP Code: " . $httpCode . "\n";
echo "Body length: " . strlen($body) . "\n";
echo "Body starts with: " . substr($body, 0, 100) . "\n";

// Check if we have a complete JSON
$jsonStart = strpos($body, '{');
$jsonEnd = strrpos($body, '}');
if ($jsonStart !== false && $jsonEnd !== false) {
    $jsonString = substr($body, $jsonStart, $jsonEnd - $jsonStart + 1);
    echo "Extracted JSON length: " . strlen($jsonString) . "\n";
    $loginResponse = json_decode($jsonString, true);
    echo "Login Response Array: ";
    var_dump($loginResponse);
    $token = $loginResponse['data']['token'] ?? null;
} else {
    echo "No complete JSON found\n";
    $token = null;
}

if ($token) {
    echo "Login exitoso\n";
    
    // Crear usuario
    $timestamp = time();
    $newUserData = [
        'username' => 'debug_user_' . $timestamp,
        'email' => 'debug_' . $timestamp . '@example.com',
        'password' => 'test123',
        'first_name' => 'Debug',
        'last_name' => 'User',
        'role_id' => 2,
        'desk_id' => 2,
        'phone' => '1234567890',
        'status' => 'active'
    ];
    
    $ch = curl_init('http://127.0.0.1:8000/api/users');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($newUserData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $body = substr($response, $headerSize);
    curl_close($ch);
    
    echo "Create user response:\n";
    echo $body . "\n";
    
    $createResponse = json_decode($body, true);
    echo "\nParsed data:\n";
    var_dump($createResponse);
    
    if (isset($createResponse['data'])) {
        echo "\nData field:\n";
        var_dump($createResponse['data']);
    }
} else {
    echo "Login fallido\n";
}