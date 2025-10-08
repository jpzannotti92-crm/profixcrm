<?php
// Crear un nuevo usuario con el rol de leads desde el inicio
$token = file_get_contents('current_token.txt');

echo "=== CREANDO USUARIO CON ROL DE LEADS DESDE INICIO ===\n";

$userData = [
    'username' => 'leadagent',
    'email' => 'leadagent@example.com',
    'password' => 'LeadAgent123!',
    'first_name' => 'Lead',
    'last_name' => 'Agent',
    'role_id' => 5, // Rol test_role con permisos de leads
    'status' => 'active',
    'phone' => '+34987654333'
];

$ch = curl_init('http://127.0.0.1:8001/api/users.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($userData));
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

if ($httpCode === 200) {
    $data = json_decode($response, true);
    $newUserId = $data['user_id'] ?? $data['id'];
    
    echo "\n=== USUARIO CREADO EXITOSAMENTE CON ID: $newUserId ===\n";
    
    // Ahora obtener token para este nuevo usuario
    $ch = curl_init('http://127.0.0.1:8000/api/auth/login.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['username' => 'leadagent', 'password' => 'LeadAgent123!']));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    if (isset($data['token'])) {
        $newToken = $data['token'];
        
        echo "\n=== TOKEN OBTENIDO, PROBANDO CREAR LEAD ===\n";
        
        $leadData = [
            'name' => 'María García',
            'email' => 'maria.garcia@test.com',
            'phone' => '+34987654344',
            'source' => 'web',
            'status' => 'new',
            'notes' => 'Lead de prueba creado por lead agent',
            'company' => 'Test Company Agent',
            'country' => 'España',
            'interest_level' => 'high',
            'budget' => 75000,
            'assigned_to' => $newUserId,
            'desk_id' => 5
        ];
        
        $ch = curl_init('http://127.0.0.1:8001/api/leads.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($leadData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $newToken,
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "Código HTTP: $httpCode\n";
        echo "Respuesta: $response\n";
        
        if ($httpCode === 201) {
            echo "\n=== ¡LEAD CREADO EXITOSAMENTE CON LEAD AGENT! ===\n";
            file_put_contents('lead_agent_token.txt', $newToken);
            file_put_contents('lead_agent_id.txt', $newUserId);
        }
    }
}