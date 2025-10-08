<?php
/**
 * Importador mejorado con manejo de duplicados y validación flexible
 */

require_once __DIR__ . '/../../platform_check_bypass.php';
require_once __DIR__ . '/../../src/Database/Connection.php';

use iaTradeCRM\Database\Connection;

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

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
    $result = processFile($file['tmp_name'], $fileExtension, $columnMapping, $pdo);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function processFile($filePath, $extension, $columnMapping, $pdo) {
    $data = [];
    
    if ($extension === 'csv') {
        $data = processCSV($filePath, $columnMapping);
    } elseif (in_array($extension, ['xlsx', 'xls'])) {
        $data = processExcel($filePath, $extension, $columnMapping);
    }
    
    return importLeads($data, $pdo);
}

function processCSV($filePath, $columnMapping) {
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
        return str_replace(['á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú', 'ñ', 'Ñ'], 
                          ['a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U', 'n', 'N'], $h);
    }, $headers);
    
    $data = [];
    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) !== count($headers)) {
            continue; // Saltar filas con número incorrecto de columnas
        }
        
        $rowData = array_combine($headers, $row);
        $cleanedRow = [];
        
        // Limpiar datos
        foreach ($rowData as $key => $value) {
            $cleanedKey = strtolower(trim($key));
            $cleanedValue = trim($value);
            $cleanedRow[$cleanedKey] = $cleanedValue;
        }
        
        $data[] = $cleanedRow;
    }
    
    fclose($handle);
    return $data;
}

function processExcel($filePath, $extension, $columnMapping) {
    if (!class_exists('ZipArchive')) {
        throw new Exception('La extensión ZipArchive no está disponible');
    }
    
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        throw new Exception('No se pudo abrir el archivo Excel');
    }
    
    // Leer shared strings
    $sharedStrings = [];
    $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedStringsXml) {
        $xml = simplexml_load_string($sharedStringsXml);
        foreach ($xml->si as $si) {
            $sharedStrings[] = (string)$si->t;
        }
    }
    
    // Leer datos de la hoja
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if (!$sheetXml) {
        $zip->close();
        throw new Exception('No se pudo leer la hoja de cálculo');
    }
    
    $xml = simplexml_load_string($sheetXml);
    $data = [];
    $headers = [];
    $currentRow = 0;
    
    foreach ($xml->sheetData->row as $row) {
        $rowData = [];
        $colIndex = 0;
        
        foreach ($row->c as $cell) {
            $value = '';
            
            if (isset($cell->v)) {
                if (isset($cell['t']) && $cell['t'] == 's') {
                    // Shared string
                    $value = $sharedStrings[(int)$cell->v] ?? '';
                } elseif (isset($cell['s']) && (int)$cell['s'] == 1) {
                    // Fecha
                    $excelDate = (int)$cell->v;
                    $unixDate = ($excelDate - 25569) * 86400;
                    $value = date('Y-m-d', $unixDate);
                } else {
                    $value = (string)$cell->v;
                }
            }
            
            $rowData[$colIndex] = trim($value);
            $colIndex++;
        }
        
        if ($currentRow === 0) {
            // Limpiar encabezados
            $headers = array_map(function($h) {
                return str_replace(['á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú', 'ñ', 'Ñ'], 
                                  ['a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U', 'n', 'N'], 
                                  strtolower(trim($h)));
            }, $rowData);
        } else {
            if (count($rowData) === count($headers)) {
                $combinedRow = array_combine($headers, $rowData);
                $data[] = $combinedRow;
            }
        }
        
        $currentRow++;
    }
    
    $zip->close();
    return $data;
}

function importLeads($data, $pdo) {
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
            :first_name, :last_name, :email, :phone, :country, :city,
            :company, :job_title, :source, :campaign, :status, :priority,
            :notes, :value, :last_contact, NOW(), NOW()
        )
    ');
    
    $rowNumber = 1;
    
    foreach ($data as $row) {
        $rowNumber++;
        $rowErrors = [];
        
        try {
            // Mapear datos con validación flexible
            $leadData = mapRowToLeadFlexible($row);
            
            if (empty($leadData['first_name']) && empty($leadData['last_name'])) {
                $rowErrors[] = 'Nombre y apellido están vacíos';
            }
            
            if (empty($leadData['email'])) {
                $rowErrors[] = 'Email está vacío';
            } else {
                // Limpiar email - quitar acentos y validar
                $cleanEmail = cleanEmail($leadData['email']);
                if (!filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
                    $rowErrors[] = 'Email no válido después de limpiar: ' . $leadData['email'];
                } else {
                    $leadData['email'] = $cleanEmail;
                }
            }
            
            if (!empty($rowErrors)) {
                $errors++;
                $errors_list[] = [
                    'row' => $rowNumber,
                    'errors' => $rowErrors,
                    'data' => $leadData
                ];
                continue;
            }
            
            // Verificar duplicados
            $checkStmt->execute([$leadData['email']]);
            if ($checkStmt->fetch()) {
                $duplicates++;
                continue;
            }
            
            // Preparar datos para insert (solo los campos que necesitamos)
            $insertData = [
                'first_name' => $leadData['first_name'] ?? null,
                'last_name' => $leadData['last_name'] ?? null,
                'email' => $leadData['email'] ?? null,
                'phone' => $leadData['phone'] ?? null,
                'country' => $leadData['country'] ?? null,
                'city' => $leadData['city'] ?? null,
                'company' => $leadData['company'] ?? null,
                'job_title' => $leadData['job_title'] ?? null,
                'source' => $leadData['source'] ?? null,
                'campaign' => $leadData['campaign'] ?? null,
                'status' => $leadData['status'] ?? null,
                'priority' => $leadData['priority'] ?? null,
                'notes' => $leadData['notes'] ?? null,
                'value' => $leadData['value'] ?? null,
                'last_contact' => $leadData['last_contact'] ?? null
            ];
            
            // Insertar lead
            $insertStmt->execute($insertData);
            $imported++;
            
        } catch (Exception $e) {
            $errors++;
            $errors_list[] = [
                'row' => $rowNumber,
                'errors' => ['Error al procesar fila: ' . $e->getMessage()],
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
            'errors' => array_slice($errors_list, 0, 10) // Limitar errores en respuesta
        ]
    ];
}

function mapRowToLeadFlexible($row) {
    $leadData = [];
    
    // Mapeo flexible de campos comunes
    $fieldMappings = [
        'first_name' => ['first_name', 'nombre', 'name', 'nombres', 'firstName'],
        'last_name' => ['last_name', 'apellido', 'surname', 'apellidos', 'lastName'],
        'email' => ['email', 'correo', 'mail', 'email_address', 'emailAddress'],
        'phone' => ['phone', 'telefono', 'telephone', 'tel', 'celular', 'mobile'],
        'country' => ['country', 'pais', 'nation'],
        'city' => ['city', 'ciudad', 'town'],
        'company' => ['company', 'empresa', 'organization'],
        'job_title' => ['job_title', 'puesto', 'position', 'title'],
        'source' => ['source', 'origen', 'procedencia'],
        'campaign' => ['campaign', 'campana', 'campania'],
        'status' => ['status', 'estado'],
        'priority' => ['priority', 'prioridad'],
        'notes' => ['notes', 'notas', 'observaciones'],
        'value' => ['value', 'valor', 'amount'],
        'last_contact' => ['last_contact', 'ultimo_contacto', 'lastContact']
    ];
    
    // Convertir row a minúsculas para búsqueda insensible
    $lowercaseRow = [];
    foreach ($row as $key => $value) {
        $lowercaseRow[strtolower(trim($key))] = trim($value);
    }
    
    // Buscar cada campo
    foreach ($fieldMappings as $leadField => $possibleFields) {
        foreach ($possibleFields as $possibleField) {
            if (isset($lowercaseRow[$possibleField]) && !empty($lowercaseRow[$possibleField])) {
                $leadData[$leadField] = $lowercaseRow[$possibleField];
                break;
            }
        }
    }
    
    // Valores por defecto
    $defaults = [
        'source' => 'import',
        'status' => 'new',
        'priority' => 'medium',
        'notes' => null,
        'value' => null,
        'last_contact' => null,
        'phone' => null,
        'country' => null,
        'city' => null,
        'company' => null,
        'job_title' => null,
        'campaign' => null
    ];
    
    foreach ($defaults as $field => $defaultValue) {
        if (!isset($leadData[$field]) || empty($leadData[$field])) {
            $leadData[$field] = $defaultValue;
        }
    }
    
    return $leadData;
}

function cleanEmail($email) {
    // Quitar espacios
    $email = trim($email);
    
    // Convertir a minúsculas
    $email = strtolower($email);
    
    // Quitar acentos del dominio (parte después de @)
    if (strpos($email, '@') !== false) {
        list($local, $domain) = explode('@', $email, 2);
        
        // Quitar acentos del dominio
        $domain = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú', 'ñ', 'Ñ'],
            ['a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U', 'n', 'N'],
            $domain
        );
        
        // Quitar caracteres especiales del local (antes de @), pero permitir caracteres válidos
        $local = preg_replace('/[^a-zA-Z0-9._+-]/', '', $local);
        
        // Verificar que no haya doble punto en el dominio
        $domain = str_replace('..', '.', $domain);
        
        $email = $local . '@' . $domain;
    }
    
    return $email;
}