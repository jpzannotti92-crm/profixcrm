<?php
// Actualizar usuario para asignarle el rol con permisos de leads
$token = file_get_contents('current_token.txt');

echo "=== ASIGNANDO ROL AL LEAD MANAGER ===\n";

$updateData = [
    'role_id' => 5, // El rol test_role con permisos de leads
    'first_name' => 'Lead',
    'last_name' => 'Manager Pro',
    'status' => 'active'
];

$ch = curl_init('http://127.0.0.1:8001/api/users.php?id=6'); // ID del leadmanager
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
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
    echo "\n=== USUARIO ACTUALIZADO EXITOSAMENTE ===\n";
    
    // Ahora probar crear lead con el lead manager actualizado
    echo "\n=== PROBANDO CREAR LEAD CON LEAD MANAGER ACTUALIZADO ===\n";
    
    // Obtener nuevo token
    $ch = curl_init('http://127.0.0.1:8000/api/auth/login.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['username' => 'leadmanager', 'password' => 'Lead12345!']));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    if (isset($data['token'])) {
        $newToken = $data['token'];
        
        $leadData = [
            'name' => 'Carlos Rodríguez',
            'email' => 'carlos.rodriguez@test.com',
            'phone' => '+34987654322',
            'source' => 'web',
            'status' => 'new',
            'notes' => 'Lead de prueba creado por lead manager actualizado',
            'company' => 'Test Company Pro',
            'country' => 'España',
            'interest_level' => 'high',
            'budget' => 85000,
            'assigned_to' => 6,
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
            echo "\n=== ¡LEAD CREADO EXITOSAMENTE! ===\n";
        }
    }
} else {
    echo "\n=== ERROR AL ACTUALIZAR USUARIO ===\n";
}