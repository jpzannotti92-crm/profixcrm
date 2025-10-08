<?php
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/Database/Connection.php';

use iaTradeCRM\Database\Connection;
use Firebase\JWT\JWT;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
        exit();
    }
    
    // Log de datos recibidos para debugging
    error_log("Datos de registro recibidos: " . json_encode($input));
    
    // Validar datos requeridos
    $required = ['email', 'password', 'firstName', 'lastName'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => "El campo {$field} es requerido"
            ]);
            exit;
        }
    }
    
    $db = Connection::getInstance()->getConnection();
    
    // Verificar si el email ya existe
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$input['email']]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'El email ya está registrado'
        ]);
        exit();
    }
    
    // Verificar si el username ya existe (usar email como username por defecto)
    $username = $input['username'] ?? $input['email'];
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        // Si el username existe, generar uno único
        $username = $input['email'];
    }
    
    // Hash de la contraseña
    $passwordHash = password_hash($input['password'], PASSWORD_DEFAULT);
    
    // Insertar nuevo usuario
    $stmt = $db->prepare("
        INSERT INTO users (
            username, email, password_hash, first_name, last_name, 
            phone, status, email_verified, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, 'active', 0, NOW(), NOW())
    ");
    
    $result = $stmt->execute([
        $username,
        $input['email'],
        $passwordHash,
        $input['firstName'],
        $input['lastName'],
        $input['phone'] ?? null
    ]);
    
    if (!$result) {
        throw new Exception('Error al crear el usuario');
    }
    
    $userId = $db->lastInsertId();
    
    // Buscar lead por email para vinculación automática
    $leadId = null;
    if (!empty($input['email'])) {
        $stmt = $db->prepare("SELECT id FROM leads WHERE email = ? LIMIT 1");
        $stmt->execute([$input['email']]);
        $lead = $stmt->fetch();
        
        if ($lead) {
            $leadId = $lead['id'];
            
            // Actualizar el lead con el user_id
            $stmt = $db->prepare("UPDATE leads SET user_id = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$userId, $leadId]);
            
            error_log("Usuario registrado y vinculado automáticamente al lead ID: " . $leadId);
        }
    }
    
    // Generar JWT token
    $secret = $_ENV['JWT_SECRET'] ?? 'your-super-secret-jwt-key-change-in-production-2024';
    $payload = [
        'iss' => $_SERVER['HTTP_HOST'] ?? 'iatrade-crm',
        'aud' => $_SERVER['HTTP_HOST'] ?? 'iatrade-crm',
        'iat' => time(),
        'exp' => time() + (24 * 60 * 60), // 24 horas
        'user_id' => $userId,
        'username' => $username,
        'email' => $input['email']
    ];
    
    $token = JWT::encode($payload, $secret, 'HS256');
    
    // Obtener datos del usuario creado
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'Usuario registrado exitosamente',
        'token' => $token,
        'user' => [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'phone' => $user['phone'],
            'status' => $user['status']
        ],
        'lead_linked' => $leadId !== null,
        'lead_id' => $leadId
    ]);
    
} catch (Exception $e) {
    error_log('Registration error: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor: ' . $e->getMessage()
    ]);
}
?>