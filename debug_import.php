<?php
// Debug script para verificar el funcionamiento del endpoint

$token = trim(file_get_contents('admin_token.txt'));
echo "Token length: " . strlen($token) . "\n";
echo "First 50 chars: " . substr($token, 0, 50) . "\n\n";

// Test 1: Simple GET request
echo "=== TEST 1: Simple GET ===\n";
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => "Authorization: Bearer {$token}\r\n"
    ]
]);

$result = file_get_contents('http://localhost:8000/api/lead-import.php?fields=1', false, $context);
echo "Response length: " . strlen($result) . "\n";
echo "Response: " . $result . "\n\n";

// Test 2: POST request with valid data
echo "=== TEST 2: POST with valid data ===\n";
$postData = [
    'data' => [
        ['A' => 'Test', 'B' => 'User', 'C' => 'test@example.com']
    ],
    'mapping' => [
        'A' => 'first_name',
        'B' => 'last_name',
        'C' => 'email'
    ]
];

$postContext = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nAuthorization: Bearer {$token}\r\n",
        'content' => json_encode($postData)
    ]
]);

$postResult = file_get_contents('http://localhost:8000/api/lead-import.php', false, $postContext);
echo "POST Response length: " . strlen($postResult) . "\n";
echo "POST Response: " . $postResult . "\n\n";

// Test 3: POST request with invalid data (missing required fields)
echo "=== TEST 3: POST with invalid data ===\n";
$invalidData = [
    'data' => [
        ['X' => 'Test', 'Y' => 'User']
    ],
    'mapping' => [
        'X' => 'phone',
        'Y' => 'country'
    ]
];

$invalidContext = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nAuthorization: Bearer {$token}\r\n",
        'content' => json_encode($invalidData)
    ]
]);

$invalidResult = file_get_contents('http://localhost:8000/api/lead-import.php', false, $invalidContext);
echo "Invalid POST Response: " . $invalidResult . "\n";