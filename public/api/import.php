<?php
require_once __DIR__ . '/../../platform_check_bypass.php';
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../src/Database/Connection.php';
require_once __DIR__ . '/database.php'; // Para las funciones errorResponse y successResponse
/**
 * API específica para importación de leads
 */

// require_once 'database.php'; // Functions already included via bootstrap.php

/**
 * Procesar archivo CSV
 */
function processCSV($filePath) {
    $data = [];
    
    if (($handle = fopen($filePath, "r")) !== FALSE) {
        // Detectar delimitador
        $firstLine = fgets($handle);
        rewind($handle);
        
        $delimiter = ',';
        if (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
            $delimiter = ';';
        }
        
        // Leer headers
        $headers = fgetcsv($handle, 1000, $delimiter);
        
        if (!$headers) {
            fclose($handle);
            return [];
        }
        
        // Limpiar headers
        $headers = array_map('trim', $headers);
        
        // Leer datos
        while (($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
            if (count($row) === count($headers)) {
                $rowData = array_combine($headers, array_map('trim', $row));
                
                // Filtrar filas vacías
                if (array_filter($rowData)) {
                    $data[] = $rowData;
                }
            }
        }
        fclose($handle);
    }
    
    return $data;
}

/**
 * Mapear fila a datos de lead
 */
function mapRowToLead($row, $mapping) {
    $leadData = [];
    
    foreach ($mapping as $leadField => $csvColumn) {
        if (isset($row[$csvColumn]) && $row[$csvColumn] !== '') {
            $value = trim($row[$csvColumn]);
            
            // Limpiar y validar datos según el campo
            switch ($leadField) {
                case 'email':
                    $leadData[$leadField] = strtolower($value);
                    break;
                case 'phone':
                    // Limpiar teléfono
                    $leadData[$leadField] = preg_replace('/[^0-9+\-\s\(\)]/', '', $value);
                    break;
                case 'first_name':
                case 'last_name':
                    // Capitalizar nombres
                    $leadData[$leadField] = ucwords(strtolower($value));
                    break;
                case 'country':
                    $leadData[$leadField] = ucwords(strtolower($value));
                    break;
                case 'last_contact':
                    // Intentar convertir fecha
                    $timestamp = strtotime($value);
                    if ($timestamp !== false) {
                        $leadData[$leadField] = date('Y-m-d H:i:s', $timestamp);
                    } else {
                        $leadData[$leadField] = null;
                    }
                    break;
                case 'desk_id':
                case 'assigned_user_id':
                    // Convertir a entero si es numérico
                    if (is_numeric($value)) {
                        $leadData[$leadField] = (int)$value;
                    } else {
                        $leadData[$leadField] = null;
                    }
                    break;
                default:
                    $leadData[$leadField] = $value;
            }
        }
    }
    
    return $leadData;
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Método no permitido', 405);
    exit;
}

// Verificar autenticación JWT
require_once __DIR__ . '/../../src/Database/Connection.php';

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (empty($authHeader) || strpos($authHeader, 'Bearer ') !== 0) {
    errorResponse('Token de autenticación requerido', 401);
    exit;
}

$token = substr($authHeader, 7);

// Verificar JWT
$secret = $_ENV['JWT_SECRET'] ?? 'password';
try {
    if (class_exists('Firebase\JWT\JWT')) {
        $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($secret, 'HS256'));
        $user = (array) $decoded;
    } else {
        // Fallback para JWT manual
        list($header64, $payload64, $signature64) = explode('.', $token);
        $payload = json_decode(base64_decode($payload64), true);
        $user = $payload;
    }
} catch (Exception $e) {
    errorResponse('Token inválido', 401);
    exit;
}

// Obtener conexión a la base de datos
$connection = iaTradeCRM\Database\Connection::getInstance();
$pdo = $connection->getConnection();

try {
    // Verificar si se subió un archivo
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        errorResponse('No se subió ningún archivo válido', 400);
        exit;
    }
    
    $file = $_FILES['file'];
    $fileName = $file['name'];
    $fileTmpName = $file['tmp_name'];
    $fileSize = $file['size'];
    
    // Validar tipo de archivo
    $allowedExtensions = ['csv'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    if (!in_array($fileExtension, $allowedExtensions)) {
        errorResponse('Tipo de archivo no permitido. Solo se permiten archivos CSV', 400);
        exit;
    }
    
    // Validar tamaño (máximo 10MB)
    if ($fileSize > 10 * 1024 * 1024) {
        errorResponse('El archivo es demasiado grande. Máximo 10MB', 400);
        exit;
    }
    
    // Procesar archivo CSV
    $data = processCSV($fileTmpName);
    
    if (empty($data)) {
        errorResponse('El archivo está vacío o no se pudo procesar', 400);
        exit;
    }
    
    // Obtener mapeo de columnas del POST
    $mappingJson = $_POST['mapping'] ?? null;
    
    if (!$mappingJson) {
        // Primera fase: retornar columnas para mapeo
        $columns = array_keys($data[0]);
        successResponse([
            'requires_mapping' => true,
            'columns' => $columns,
            'sample_data' => array_slice($data, 0, 5),
            'total_rows' => count($data)
        ], 'Mapeo de columnas requerido');
        exit;
    }
    
    // Segunda fase: importar con mapeo
    $mapping = json_decode($mappingJson, true);
    
    if (empty($mapping)) {
        errorResponse('Mapeo de columnas inválido', 400);
        exit;
    }
    
    // Importar datos
    $imported = 0;
    $errors = [];
    $duplicates = 0;
    
    foreach ($data as $index => $row) {
        try {
            $leadData = mapRowToLead($row, $mapping);
            
            // Validar datos requeridos
            if (empty($leadData['first_name']) && empty($leadData['last_name'])) {
                $errors[] = "Fila " . ($index + 2) . ": Se requiere al menos nombre o apellido";
                continue;
            }
            
            if (empty($leadData['email'])) {
                $errors[] = "Fila " . ($index + 2) . ": Email es requerido";
                continue;
            }
            
            // Validar formato de email
            if (!filter_var($leadData['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Fila " . ($index + 2) . ": Email inválido: {$leadData['email']}";
                continue;
            }
            
            // Verificar si el email ya existe
            $checkSql = "SELECT COUNT(*) FROM leads WHERE email = :email";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute(['email' => $leadData['email']]);
            
            if ($checkStmt->fetchColumn() > 0) {
                $duplicates++;
                continue;
            }
            
            // Insertar lead
            $sql = "
                INSERT INTO leads (
                    first_name, last_name, phone, country, email, 
                    desk_id, notes, last_contact_date, campaign, assigned_to,
                    status, source, created_at
                ) VALUES (
                    :first_name, :last_name, :phone, :country, :email,
                    :desk_id, :notes, :last_contact_date, :campaign, :assigned_to,
                    :status, :source, NOW()
                )
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'first_name' => $leadData['first_name'] ?? '',
                'last_name' => $leadData['last_name'] ?? '',
                'phone' => $leadData['phone'] ?? null,
                'country' => $leadData['country'] ?? null,
                'email' => $leadData['email'],
                'desk_id' => $leadData['desk_id'] ?? null,
                'notes' => $leadData['last_comment'] ?? null,
                'last_contact_date' => $leadData['last_contact'] ?? null,
                'campaign' => $leadData['campaign'] ?? null,
                'assigned_to' => $leadData['assigned_user_id'] ?? null,
                'status' => 'new',
                'source' => 'import'
            ]);
            
            $imported++;
            
        } catch (Exception $e) {
            $errors[] = "Fila " . ($index + 2) . ": " . $e->getMessage();
        }
    }
    
    successResponse([
        'imported' => $imported,
        'duplicates' => $duplicates,
        'errors_count' => count($errors),
        'total_rows' => count($data),
        'errors' => array_slice($errors, 0, 10), // Solo primeros 10 errores
        'success_rate' => count($data) > 0 ? round(($imported / count($data)) * 100, 2) : 0
    ], "Importación completada: {$imported} leads importados de " . count($data) . " filas");
    
} catch (Exception $e) {
    errorResponse('Error en la importación: ' . $e->getMessage(), 500);
}
?>