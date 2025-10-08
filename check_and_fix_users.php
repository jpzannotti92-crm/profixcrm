<?php
// Script para verificar y actualizar contraseñas de usuarios de prueba

$_ENV['DB_DATABASE'] = 'spin2pay_profixcrm'; // Forzar nombre correcto de BD
$config = require 'config/database.php';
$_ENV['DB_DATABASE'] = 'spin2pay_profixcrm';

try {
    $dbConfig = $config['connections']['mysql'];
    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    
    echo "=== VERIFICACIÓN DE USUARIOS Y CONTRASEÑAS ===" . PHP_EOL;
    
    // Obtener usuarios
    $stmt = $db->query('SELECT id, username, email, first_name, last_name FROM users ORDER BY id');
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $user) {
        echo PHP_EOL . "Usuario: {$user['username']} ({$user['first_name']} {$user['last_name']})" . PHP_EOL;
        
        // Verificar si tiene contraseña válida
        $stmt2 = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt2->execute([$user['id']]);
        $passwordData = $stmt2->fetch(PDO::FETCH_ASSOC);
        
        if ($passwordData && !empty($passwordData['password'])) {
            echo "✓ Tiene contraseña configurada" . PHP_EOL;
            
            // Probar login con contraseña 'password'
            echo "Probando login con contraseña 'password'... ";
            
            $loginData = [
                'username' => $user['username'],
                'password' => 'password'
            ];
            
            $ch = curl_init('http://127.0.0.1:8001/api/auth/login.php');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $data = json_decode($response, true);
                if (isset($data['token'])) {
                    echo "✓ LOGIN EXITOSO" . PHP_EOL;
                } else {
                    echo "✗ Login fallido - No hay token" . PHP_EOL;
                }
            } else {
                echo "✗ Login fallido - HTTP $httpCode" . PHP_EOL;
                $errorData = json_decode($response, true);
                if (isset($errorData['message'])) {
                    echo "  Mensaje: {$errorData['message']}" . PHP_EOL;
                }
            }
        } else {
            echo "✗ NO tiene contraseña configurada" . PHP_EOL;
            echo "  Actualizando contraseña a 'password'... ";
            
            // Generar hash de contraseña
            $passwordHash = password_hash('password', PASSWORD_DEFAULT);
            $stmt3 = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $result = $stmt3->execute([$passwordHash, $user['id']]);
            
            if ($result) {
                echo "✓ Contraseña actualizada" . PHP_EOL;
            } else {
                echo "✗ Error al actualizar contraseña" . PHP_EOL;
            }
        }
        
        // Verificar roles asignados
        echo "Roles asignados: ";
        $stmt4 = $db->prepare('
            SELECT r.name 
            FROM roles r 
            JOIN user_roles ur ON r.id = ur.role_id 
            WHERE ur.user_id = ?
        ');
        $stmt4->execute([$user['id']]);
        $roles = $stmt4->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($roles) > 0) {
            echo implode(', ', $roles) . PHP_EOL;
        } else {
            echo "NINGUNO" . PHP_EOL;
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}