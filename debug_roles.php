<?php
/**
 * Script de debugging para roles
 */

class RoleDebugger {
    private $baseUrl = 'http://localhost:8080/api';
    
    private function makeRequest($endpoint, $method = 'GET', $data = null, $token = null) {
        $ch = curl_init();
        
        $headers = ['Content-Type: application/json'];
        if ($token) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $data ? json_encode($data) : null,
            CURLOPT_HEADER => true,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        
        $body = substr($response, $headerSize);
        
        // Intentar extraer JSON de la respuesta
        $jsonStart = strpos($body, '{');
        $jsonEnd = strrpos($body, '}');
        
        if ($jsonStart !== false && $jsonEnd !== false) {
            $jsonString = substr($body, $jsonStart, $jsonEnd - $jsonStart + 1);
            $decoded = json_decode($jsonString, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                return [
                    'success' => ($httpCode >= 200 && $httpCode < 300),
                    'data' => $decoded,
                    'http_code' => $httpCode
                ];
            }
        }
        
        return [
            'success' => false,
            'data' => $body,
            'http_code' => $httpCode,
            'error' => 'JSON decode error: ' . json_last_error_msg()
        ];
    }
    
    private function login($username, $password) {
        $response = $this->makeRequest('/auth/login', 'POST', [
            'username' => $username,
            'password' => $password
        ]);
        
        echo "Login response: " . json_encode($response) . "\n";
        
        if ($response['success'] && isset($response['data']['token'])) {
            return $response['data']['token'];
        }
        
        return null;
    }
    
    public function debugRoles() {
        echo "=== DEBUGGING ROLES ===\n\n";
        
        // Login como admin
        echo "1. Login como administrador...\n";
        $adminToken = $this->login('admin', 'password');
        
        if (!$adminToken) {
            echo "❌ Error: No se pudo hacer login como admin\n";
            return;
        }
        echo "✓ Login exitoso\n\n";
        
        // Obtener roles
        echo "2. Obtener roles...\n";
        $response = $this->makeRequest('/roles', 'GET', null, $adminToken);
        
        echo "HTTP Code: " . $response['http_code'] . "\n";
        echo "Success: " . ($response['success'] ? 'true' : 'false') . "\n";
        
        if ($response['success'] && isset($response['data'])) {
            echo "\n--- Respuesta completa ---\n";
            print_r($response['data']);
            
            echo "\n--- Datos de roles ---\n";
            $rolesData = $response['data'];
            $roles = isset($rolesData['roles']) ? $rolesData['roles'] : $rolesData;
            
            echo "Número de roles: " . count($roles) . "\n";
            
            if (count($roles) > 0) {
                echo "\n--- Primer rol detallado ---\n";
                $firstRole = $roles[0];
                print_r($firstRole);
                
                echo "\n--- Todos los roles ---\n";
                foreach ($roles as $index => $role) {
                    if (is_array($role)) {
                        $roleName = $role['display_name'] ?? $role['name'] ?? $role['code'] ?? 'N/A';
                        $roleDesc = $role['description'] ?? $role['name'] ?? 'N/A';
                        echo ($index + 1) . ". {$roleName} ({$roleDesc})\n";
                        echo "   - ID: " . ($role['id'] ?? 'N/A') . "\n";
                        echo "   - Status: " . ($role['status'] ?? 'N/A') . "\n";
                        echo "   - Users count: " . ($role['users_count'] ?? 'N/A') . "\n";
                    }
                }
            }
        } else {
            echo "Error: " . json_encode($response['data'] ?? 'Sin datos') . "\n";
        }
        
        echo "\n=== FIN DEBUGGING ROLES ===\n";
    }
}

// Ejecutar debugging
$debugger = new RoleDebugger();
$debugger->debugRoles();