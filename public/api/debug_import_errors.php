<?php
/**
 * Debug de errores de importación
 */

require_once __DIR__ . '/../../platform_check_bypass.php';
require_once __DIR__ . '/../../src/Database/Connection.php';

use iaTradeCRM\Database\Connection;

header('Content-Type: application/json; charset=utf-8');

// Simular una importación para ver los errores específicos
try {
    $connection = Connection::getInstance();
    $pdo = $connection->getConnection();
    
    // Leer el archivo CSV de prueba que tienes
    $csvFile = __DIR__ . '/../../500_leads.csv';
    
    if (!file_exists($csvFile)) {
        echo json_encode([
            'success' => false,
            'message' => 'Archivo CSV no encontrado',
            'file' => $csvFile
        ]);
        exit;
    }
    
    $handle = fopen($csvFile, 'r');
    if (!$handle) {
        echo json_encode([
            'success' => false,
            'message' => 'No se pudo abrir el archivo CSV'
        ]);
        exit;
    }
    
    // Leer encabezados
    $headers = fgetcsv($handle);
    if (!$headers) {
        echo json_encode([
            'success' => false,
            'message' => 'No se pudieron leer los encabezados'
        ]);
        exit;
    }
    
    $errors = [];
    $rowNumber = 1;
    $successCount = 0;
    
    // Procesar primeras 10 filas para debug
    while (($row = fgetcsv($handle)) !== false && $rowNumber <= 10) {
        $rowData = array_combine($headers, $row);
        $rowNumber++;
        
        // Simular el mapeo básico
        $leadData = [
            'first_name' => $rowData['first_name'] ?? $rowData['nombre'] ?? '',
            'last_name' => $rowData['last_name'] ?? $rowData['apellido'] ?? '',
            'email' => $rowData['email'] ?? $rowData['correo'] ?? '',
            'phone' => $rowData['phone'] ?? $rowData['telefono'] ?? null,
            'country' => $rowData['country'] ?? $rowData['pais'] ?? null,
            'city' => $rowData['city'] ?? $rowData['ciudad'] ?? null,
            'source' => 'import',
            'status' => 'new',
            'priority' => 'medium'
        ];
        
        // Validar como lo hace el importador
        $rowErrors = [];
        
        if (empty($leadData['first_name']) && empty($leadData['last_name'])) {
            $rowErrors[] = 'Nombre y apellido están vacíos';
        }
        
        if (empty($leadData['email'])) {
            $rowErrors[] = 'Email está vacío';
        } elseif (!filter_var($leadData['email'], FILTER_VALIDATE_EMAIL)) {
            $rowErrors[] = 'Email no válido: ' . $leadData['email'];
        }
        
        // Verificar duplicados
        if (!empty($leadData['email'])) {
            $stmt = $pdo->prepare('SELECT id FROM leads WHERE email = ?');
            $stmt->execute([$leadData['email']]);
            if ($stmt->fetch()) {
                $rowErrors[] = 'Email duplicado: ' . $leadData['email'];
            }
        }
        
        if (!empty($rowErrors)) {
            $errors[] = [
                'row' => $rowNumber,
                'data' => $rowData,
                'errors' => $rowErrors
            ];
        } else {
            $successCount++;
        }
    }
    
    fclose($handle);
    
    echo json_encode([
        'success' => true,
        'message' => 'Debug completado',
        'processed_rows' => $rowNumber - 1,
        'successful_rows' => $successCount,
        'failed_rows' => count($errors),
        'errors' => $errors,
        'headers_found' => $headers,
        'suggested_mapping' => [
            'first_name' => ['first_name', 'nombre', 'name', 'nombres'],
            'last_name' => ['last_name', 'apellido', 'surname', 'apellidos'],
            'email' => ['email', 'correo', 'mail', 'email_address'],
            'phone' => ['phone', 'telefono', 'telephone', 'tel', 'celular'],
            'country' => ['country', 'pais', 'nation'],
            'city' => ['city', 'ciudad', 'town']
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}