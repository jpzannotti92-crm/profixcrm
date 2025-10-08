<?php
/**
 * API de Importación de Leads - Versión Simplificada
 * Este archivo maneja la importación de leads desde archivos CSV y Excel
 */

require_once __DIR__ . '/../../platform_check_bypass.php';
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../src/Database/Connection.php';
require_once __DIR__ . '/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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
 * Procesar archivo Excel básico (XLSX)
 * Implementación simplificada sin librerías externas
 */
function processExcel($filePath) {
    $data = [];
    
    // Verificar si es un archivo ZIP válido (XLSX es un ZIP)
    $zip = new ZipArchive;
    if ($zip->open($filePath) !== TRUE) {
        return [];
    }
    
    // Leer el archivo sharedStrings.xml si existe
    $sharedStrings = [];
    if ($zip->locateName('xl/sharedStrings.xml') !== false) {
        $xmlStrings = simplexml_load_string($zip->getFromName('xl/sharedStrings.xml'));
        if ($xmlStrings) {
            foreach ($xmlStrings->si as $si) {
                $sharedStrings[] = (string)$si->t;
            }
        }
    }
    
    // Leer el archivo sheet1.xml
    $sheetData = $zip->getFromName('xl/worksheets/sheet1.xml');
    if (!$sheetData) {
        $zip->close();
        return [];
    }
    
    $xml = simplexml_load_string($sheetData);
    if (!$xml) {
        $zip->close();
        return [];
    }
    
    $headers = [];
    $firstRow = true;
    
    foreach ($xml->sheetData->row as $row) {
        $rowData = [];
        $colIndex = 0;
        
        foreach ($row->c as $cell) {
            $value = '';
            
            // Determinar el tipo de celda
            if (isset($cell['t'])) {
                switch ((string)$cell['t']) {
                    case 's': // String compartido
                        $idx = (int)$cell->v;
                        $value = isset($sharedStrings[$idx]) ? $sharedStrings[$idx] : '';
                        break;
                    case 'b': // Boolean
                        $value = ((int)$cell->v) ? 'TRUE' : 'FALSE';
                        break;
                    case 'e': // Error
                        $value = '#ERROR';
                        break;
                    default: // Numérico o fecha
                        $value = isset($cell->v) ? (string)$cell->v : '';
                }
            } else {
                $value = isset($cell->v) ? (string)$cell->v : '';
            }
            
            // Manejar fechas (convertir serial de Excel a fecha)
            if (isset($cell['s']) && $value !== '') {
                // Simple heurística para detectar fechas
                if ((float)$value > 40000 && (float)$value < 50000) {
                    $unixDate = ((int)$value - 25569) * 86400;
                    $value = date('Y-m-d', $unixDate);
                }
            }
            
            $rowData[$colIndex] = trim($value);
            $colIndex++;
        }
        
        // Si es la primera fila, usarla como headers
        if ($firstRow) {
            $headers = $rowData;
            $firstRow = false;
        } else {
            // Combinar headers con datos
            if (count($rowData) === count($headers)) {
                $combined = array_combine($headers, $rowData);
                if (array_filter($combined)) { // Filtrar filas vacías
                    $data[] = $combined;
                }
            }
        }
    }
    
    $zip->close();
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
                case 'city':
                case 'company':
                case 'job_title':
                    $leadData[$leadField] = ucwords(strtolower($value));
                    break;
                case 'source':
                    $leadData[$leadField] = strtolower($value);
                    break;
                case 'campaign':
                case 'status':
                case 'priority':
                case 'notes':
                case 'source':
                case 'campaign':
                case 'status':
                    $leadData[$leadField] = $value;
                    break;
                
                case 'desk_id':
                case 'assigned_to':
                    // Convertir a entero si es numérico
                    if (is_numeric($value)) {
                        $leadData[$leadField] = (int)$value;
                    } else {
                        $leadData[$leadField] = null;
                    }
                    break;
                case 'value':
                    // Convertir a decimal
                    if (is_numeric($value)) {
                        $leadData[$leadField] = (float)$value;
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

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Método no permitido', 405);
    exit;
}

try {
    // Obtener conexión a la base de datos
    $connection = iaTradeCRM\Database\Connection::getInstance();
    $pdo = $connection->getConnection();
    
    // Verificar archivo
    if (!isset($_FILES['file'])) {
        errorResponse('No se proporcionó archivo', 400);
        exit;
    }
    
    $file = $_FILES['file'];
    
    // Validar archivo
    if ($file['error'] !== UPLOAD_ERR_OK) {
        errorResponse('Error al subir archivo: ' . $file['error'], 400);
        exit;
    }
    
    // Validar tipos de archivo permitidos (CSV y Excel)
    $allowedTypes = ['text/csv', 'application/csv', 'application/vnd.ms-excel', 'text/plain'];
    $allowedExtensions = ['csv', 'xlsx', 'xls'];
    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($fileInfo, $file['tmp_name']);
    finfo_close($fileInfo);
    
    // Validar por tipo MIME o por extensión
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($mimeType, $allowedTypes) && !in_array($fileExtension, $allowedExtensions)) {
        errorResponse('Tipo de archivo no permitido. Solo CSV y Excel (.xlsx, .xls) son permitidos.', 400);
        exit;
    }
    
    // Validar tamaño (máximo 10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        errorResponse('Archivo demasiado grande. Máximo 10MB permitido.', 400);
        exit;
    }
    
    // Procesar archivo según tipo
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if ($fileExtension === 'xlsx' || $fileExtension === 'xls') {
        // Procesar Excel
        $data = processExcel($file['tmp_name']);
    } else {
        // Procesar CSV
        $data = processCSV($file['tmp_name']);
    }
    
    if (empty($data)) {
        errorResponse('El archivo está vacío o tiene formato inválido', 400);
        exit;
    }
    
    // Obtener mapeo de columnas
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
            if (empty($leadData['first_name']) || empty($leadData['last_name'])) {
                $errors[] = "Fila " . ($index + 2) . ": Se requiere nombre y apellido";
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
            
            // Insertar lead con campos que existen en la tabla
            $sql = "
                INSERT INTO leads (
                    first_name, last_name, email, phone, country, city, 
                    address, postal_code, company, job_title, 
                    source, campaign, status, priority, 
                    assigned_to, desk_id, notes, value, last_contact,
                    created_by, updated_by, created_at, updated_at
                ) VALUES (
                    :first_name, :last_name, :email, :phone, :country, :city,
                    :address, :postal_code, :company, :job_title,
                    :source, :campaign, :status, :priority,
                    :assigned_to, :desk_id, :notes, :value, :last_contact,
                    :created_by, :updated_by, NOW(), NOW()
                )
            ";
            
            $stmt = $pdo->prepare($sql);
            
            // Mapear solo los campos que existen en la tabla
            $stmt->execute([
                'first_name' => $leadData['first_name'] ?? '',
                'last_name' => $leadData['last_name'] ?? '',
                'email' => $leadData['email'] ?? '',
                'phone' => $leadData['phone'] ?? null,
                'country' => $leadData['country'] ?? null,
                'city' => $leadData['city'] ?? null,
                'address' => $leadData['address'] ?? null,
                'postal_code' => $leadData['postal_code'] ?? null,
                'company' => $leadData['company'] ?? null,
                'job_title' => $leadData['job_title'] ?? null,
                'source' => $leadData['source'] ?? 'import',
                'campaign' => $leadData['campaign'] ?? null,
                'status' => $leadData['status'] ?? 'new',
                'priority' => $leadData['priority'] ?? 'medium',
                'assigned_to' => $leadData['assigned_to'] ?? null,
                'desk_id' => $leadData['desk_id'] ?? null,
                'notes' => $leadData['notes'] ?? null,
                'value' => $leadData['value'] ?? null,
                'last_contact' => $leadData['last_contact'] ?? null,
                'created_by' => $leadData['created_by'] ?? 1,
                'updated_by' => $leadData['updated_by'] ?? 1
            ]);
            
            $imported++;
            
        } catch (Exception $e) {
            $errors[] = "Fila " . ($index + 2) . ": Error al insertar - " . $e->getMessage();
        }
    }
    
    // Retornar resumen de importación
    successResponse([
        'imported' => $imported,
        'duplicates' => $duplicates,
        'errors' => count($errors),
        'errors_list' => array_slice($errors, 0, 10) // Limitar a 10 errores
    ], 'Importación completada');
    
} catch (Exception $e) {
    errorResponse('Error en la importación: ' . $e->getMessage(), 500);
}