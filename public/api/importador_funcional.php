<?php
/**
 * Importador de leads que realmente guarda en la base de datos
 * Versión funcional y confiable
 */

require_once __DIR__ . '/../../platform_check_bypass.php';
require_once __DIR__ . '/../../src/Database/Connection.php';

use iaTradeCRM\Database\Connection;

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $connection = Connection::getInstance();
    $pdo = $connection->getConnection();
    
    // Verificar archivo
    if (!isset($_FILES['file'])) {
        echo json_encode(['success' => false, 'message' => 'No se proporcionó archivo']);
        exit;
    }
    
    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Error al subir archivo']);
        exit;
    }
    
    // Validar tipo de archivo
    $allowedExtensions = ['csv', 'xlsx', 'xls'];
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($fileExtension, $allowedExtensions)) {
        echo json_encode(['success' => false, 'message' => 'Tipo de archivo no permitido. Use CSV, XLSX o XLS']);
        exit;
    }
    
    // Obtener mapeo de columnas si existe
    $columnMapping = [];
    if (isset($_POST['mapping']) && !empty($_POST['mapping'])) {
        $columnMapping = json_decode($_POST['mapping'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['success' => false, 'message' => 'Mapeo de columnas inválido']);
            exit;
        }
    }
    
    // Procesar archivo
    $result = processAndSaveFile($file['tmp_name'], $fileExtension, $columnMapping, $pdo);
    
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($result);
    
} catch (Exception $e) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function processAndSaveFile($filePath, $extension, $columnMapping, $pdo) {
    $data = [];
    
    if ($extension === 'csv') {
        $data = processCSVSimple($filePath);
    } elseif (in_array($extension, ['xlsx', 'xls'])) {
        $data = processExcelSimple($filePath);
    }
    
    return importAndSaveLeads($data, $pdo);
}

function processCSVSimple($filePath) {
    $handle = fopen($filePath, 'r');
    if (!$handle) {
        throw new Exception('No se pudo abrir el archivo CSV');
    }
    
    $headers = fgetcsv($handle);
    if (!$headers) {
        fclose($handle);
        throw new Exception('No se pudieron leer los encabezados del CSV');
    }
    
    // Limpiar encabezados
    $headers = array_map('trim', $headers);
    $headers = array_map(function($h) {
        return strtolower(trim(str_replace(['á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú', 'ñ', 'Ñ'], 
                          ['a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U', 'n', 'N'], $h)));
    }, $headers);
    
    $data = [];
    $rowNum = 1;
    
    while (($row = fgetcsv($handle)) !== false) {
        $rowNum++;
        if (count($row) !== count($headers)) {
            continue;
        }
        
        $rowData = array_combine($headers, $row);
        
        // Limpiar datos
        $cleanedRow = [];
        foreach ($rowData as $key => $value) {
            $cleanedRow[$key] = trim($value);
        }
        
        $data[] = $cleanedRow;
    }
    
    fclose($handle);
    return $data;
}

function processExcelSimple($filePath) {
    // Por ahora solo CSV, podemos agregar Excel después
    throw new Exception('Excel no implementado aún, use CSV');
}

function importAndSaveLeads($data, $pdo) {
    $imported = 0;
    $duplicates = 0;
    $errors = 0;
    $errors_list = [];
    
    // Preparar consultas
    $checkStmt = $pdo->prepare('SELECT id FROM leads WHERE email = ?');
    $insertStmt = $pdo->prepare('
        INSERT INTO leads (
            first_name, last_name, email, phone, country, city, 
            company, job_title, source, campaign, status, priority, 
            notes, value, last_contact, created_at, updated_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, NOW(), NOW()
        )
    ');
    
    $rowNumber = 1;
    
    foreach ($data as $row) {
        $rowNumber++;
        $rowErrors = [];
        
        try {
            // Mapear datos básicos
            $firstName = $row['first_name'] ?? $row['nombre'] ?? $row['name'] ?? '';
            $lastName = $row['last_name'] ?? $row['apellido'] ?? $row['surname'] ?? '';
            $email = $row['email'] ?? $row['correo'] ?? $row['mail'] ?? '';
            $phone = $row['phone'] ?? $row['telefono'] ?? $row['telephone'] ?? '';
            $country = $row['country'] ?? $row['pais'] ?? '';
            $city = $row['city'] ?? $row['ciudad'] ?? '';
            $company = $row['company'] ?? $row['empresa'] ?? '';
            $jobTitle = $row['job_title'] ?? $row['puesto'] ?? $row['position'] ?? '';
            
            // Validaciones básicas
            if (empty($firstName) && empty($lastName)) {
                $rowErrors[] = 'Nombre y apellido están vacíos';
            }
            
            if (empty($email)) {
                $rowErrors[] = 'Email está vacío';
            } else {
                // Limpiar email
                $email = strtolower(trim($email));
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $rowErrors[] = 'Email no válido: ' . $email;
                }
            }
            
            if (!empty($rowErrors)) {
                $errors++;
                $errors_list[] = [
                    'row' => $rowNumber,
                    'errors' => $rowErrors,
                    'data' => $row
                ];
                continue;
            }
            
            // Verificar duplicados
            $checkStmt->execute([$email]);
            if ($checkStmt->fetch()) {
                $duplicates++;
                continue;
            }
            
            // Valores por defecto
            $source = 'import';
            $status = 'new';
            $priority = 'medium';
            $notes = null;
            $value = null;
            $lastContact = null;
            
            // Ejecutar inserción con parámetros correctos
            $insertStmt->execute([
                $firstName, $lastName, $email, $phone, $country, $city,
                $company, $jobTitle, $source, null, $status, $priority,
                $notes, $value, $lastContact
            ]);
            
            $imported++;
            
        } catch (Exception $e) {
            $errors++;
            $errors_list[] = [
                'row' => $rowNumber,
                'errors' => ['Error SQL: ' . $e->getMessage()],
                'data' => $row
            ];
        }
    }
    
    return [
        'success' => true,
        'message' => 'Importación completada',
        'data' => [
            'total_rows' => count($data),
            'imported_rows' => $imported,
            'failed_rows' => $errors,
            'duplicate_rows' => $duplicates,
            'errors' => array_slice($errors_list, 0, 10)
        ]
    ];
}