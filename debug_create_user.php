<?php
require_once 'config/config.php';
require_once 'src/helpers.php';

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
curl_setopt($ch, CURLOPT_HEADER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$body = substr($response, $headerSize);
curl_close($ch);

echo "=== LOGIN RESPONSE ===\n";
echo "HTTP Code: " . $httpCode . "\n";
echo "Body: " . $body . "\n";

$loginResponse = json_decode($body, true);
$token = $loginResponse['data']['token'] ?? null;

if ($token) {
    echo "\n=== CREATE USER TEST ===\n";
    
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
    curl_close($ch);
    
    echo "HTTP Code: " . $httpCode . "\n";
    echo "Response: " . $response . "\n";
    
    $createResponse = json_decode($response, true);
    echo "\nParsed response:\n";
    var_dump($createResponse);
    
    if (isset($createResponse['data'])) {
        echo "\nData structure:\n";
        var_dump($createResponse['data']);
    }
}