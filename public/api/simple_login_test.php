<?php
// Test simple de login
$url = 'http://localhost:8000/api/api/auth/login.php';
$data = json_encode([
    'username' => 'admin',
    'password' => 'admin123'
]);

$options = [
    'http' => [
        'header' => "Content-Type: application/json\r\n",
        'method' => 'POST',
        'content' => $data
    ]
];

$context = stream_context_create($options);
$result = file_get_contents($url, false, $context);

if ($result === FALSE) {
    echo "Error en la petición\n";
    print_r($http_response_header);
} else {
    echo "Respuesta del servidor:\n";
    echo $result . "\n";
    
    $decoded = json_decode($result, true);
    if ($decoded) {
        echo "\nRespuesta decodificada:\n";
        print_r($decoded);
    }
}
?>