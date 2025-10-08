<?php

// Script para debuggear la estructura de respuesta de las APIs

$baseUrl = 'http://127.0.0.1:8000';

function debugLogin($username, $password) {
    global $baseUrl;
    
    $ch = curl_init($baseUrl . '/api/auth/login');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'username' => $username,
        'password' => $password
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "=== LOGIN DEBUG ===\n";
    echo "HTTP Code: $httpCode\n";
    echo "Response: $response\n";
    echo "Decoded: " . print_r(json_decode($response, true), true) . "\n\n";
    
    $decoded = json_decode($response, true);
    return $decoded['token'] ?? null;
}

function debugEndpoint($endpoint, $token) {
    global $baseUrl;
    
    $ch = curl_init($baseUrl . $endpoint);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "=== DEBUG $endpoint ===\n";
    echo "HTTP Code: $httpCode\n";
    echo "Response: $response\n";
    
    $decoded = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "Structure:\n";
        if (isset($decoded['data'])) {
            echo "- Has 'data' key\n";
            echo "- Type of data: " . gettype($decoded['data']) . "\n";
            if (is_array($decoded['data']) && count($decoded['data']) > 0) {
                echo "- First item structure:\n";
                echo print_r(array_keys($decoded['data'][0]), true) . "\n";
            }
        } else {
            echo "- No 'data' key found\n";
            echo "- Full structure:\n";
            echo print_r($decoded, true) . "\n";
        }
    } else {
        echo "- Invalid JSON response\n";
    }
    echo "\n";
    
    return $decoded;
}

echo "=== INICIANDO DEBUG ===\n\n";

$token = debugLogin('admin', 'password');
if ($token) {
    debugEndpoint('/api/users', $token);
    debugEndpoint('/api/roles', $token);
}

echo "=== FIN DEBUG ===\n";