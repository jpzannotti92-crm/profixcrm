<?php

// Script para debuggear específicamente la estructura de usuarios

$baseUrl = 'http://127.0.0.1:8000';

function login($username, $password) {
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
    curl_close($ch);
    
    $decoded = json_decode($response, true);
    return $decoded['token'] ?? null;
}

function debugUsers($token) {
    global $baseUrl;
    
    $ch = curl_init($baseUrl . '/api/users');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    echo "=== DEBUG USUARIOS ===\n";
    echo "Respuesta cruda: $response\n\n";
    
    $decoded = json_decode($response, true);
    
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "Estructura decodificada:\n";
        echo print_r($decoded, true) . "\n";
        
        if (isset($decoded['data'])) {
            echo "=== ANALIZANDO DATA ===\n";
            echo "Tipo de data: " . gettype($decoded['data']) . "\n";
            
            if (is_array($decoded['data'])) {
                echo "Número de elementos: " . count($decoded['data']) . "\n";
                
                if (count($decoded['data']) > 0) {
                    echo "=== PRIMER USUARIO ===\n";
                    $firstUser = $decoded['data'][0];
                    echo "Tipo del primer elemento: " . gettype($firstUser) . "\n";
                    
                    if (is_array($firstUser)) {
                        echo "Claves del primer usuario:\n";
                        echo print_r(array_keys($firstUser), true) . "\n";
                        
                        echo "Contenido del primer usuario:\n";
                        echo print_r($firstUser, true) . "\n";
                    } else {
                        echo "El primer elemento no es un array:\n";
                        echo var_export($firstUser, true) . "\n";
                    }
                }
            }
        }
    } else {
        echo "Error decodificando JSON: " . json_last_error_msg() . "\n";
    }
    
    return $decoded;
}

echo "=== INICIANDO DEBUG DE USUARIOS ===\n\n";

$token = login('admin', 'password');
if ($token) {
    debugUsers($token);
} else {
    echo "Error en login\n";
}

echo "=== FIN DEBUG DE USUARIOS ===\n";