<?php
require_once __DIR__ . '/../../platform_check_bypass.php';
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Función para generar CSV
function generateCSV($data, $headers) {
    $output = fopen('php://temp', 'w');
    
    // Escribir headers
    fputcsv($output, $headers);
    
    // Escribir datos
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);
    
    return $csv;
}

// Función para generar Excel (formato simple)
function generateExcel($data, $headers) {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>';
    $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
    $xml .= '<Styles>';
    $xml .= '<Style ss:ID="Default" ss:Name="Normal"><Font ss:FontName="Calibri" ss:Size="11"/></Style>';
    $xml .= '<Style ss:ID="Header"><Font ss:Bold="1"/><Interior ss:Color="#CCCCCC" ss:Pattern="Solid"/></Style>';
    $xml .= '</Styles>';
    $xml .= '<Worksheet ss:Name="Leads">';
    $xml .= '<Table>';
    
    // Headers
    $xml .= '<Row>';
    foreach ($headers as $header) {
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">' . htmlspecialchars($header) . '</Data></Cell>';
    }
    $xml .= '</Row>';
    
    // Datos
    foreach ($data as $row) {
        $xml .= '<Row>';
        foreach ($row as $cell) {
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($cell) . '</Data></Cell>';
        }
        $xml .= '</Row>';
    }
    
    $xml .= '</Table>';
    $xml .= '</Worksheet>';
    $xml .= '</Workbook>';
    
    return $xml;
}

// Función para obtener datos de leads (simulados)
function getLeadsData($filters = []) {
    // Datos de demostración
    $leads = [
        [
            'id' => 1,
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
            'email' => 'juan.perez@email.com',
            'phone' => '+34 600 123 456',
            'country' => 'España',
            'city' => 'Madrid',
            'source' => 'Google',
            'status' => 'new',
            'assigned_to' => 'María García',
            'campaign' => 'Campaña Q1 2024',
            'budget' => '25000',
            'company' => 'Tech Solutions S.L.',
            'position' => 'CEO',
            'interest_level' => 'high',
            'created_at' => '2024-01-15 10:30:00',
            'last_contact' => '2024-01-20 14:30:00'
        ],
        [
            'id' => 2,
            'first_name' => 'María',
            'last_name' => 'López',
            'email' => 'maria.lopez@email.com',
            'phone' => '+34 600 987 654',
            'country' => 'España',
            'city' => 'Barcelona',
            'source' => 'Facebook',
            'status' => 'contacted',
            'assigned_to' => 'Carlos Rodríguez',
            'campaign' => 'Campaña Facebook Dic 2023',
            'budget' => '15000',
            'company' => 'Marketing Pro',
            'position' => 'Marketing Manager',
            'interest_level' => 'medium',
            'created_at' => '2024-01-10 09:15:00',
            'last_contact' => '2024-01-18 16:45:00'
        ],
        [
            'id' => 3,
            'first_name' => 'Carlos',
            'last_name' => 'González',
            'email' => 'carlos.gonzalez@email.com',
            'phone' => '+34 600 555 777',
            'country' => 'México',
            'city' => 'Ciudad de México',
            'source' => 'Referral',
            'status' => 'qualified',
            'assigned_to' => 'Ana Martínez',
            'campaign' => 'Referidos Premium',
            'budget' => '50000',
            'company' => 'Global Corp',
            'position' => 'Director Financiero',
            'interest_level' => 'very_high',
            'created_at' => '2024-01-05 11:20:00',
            'last_contact' => '2024-01-22 10:30:00'
        ],
        [
            'id' => 4,
            'first_name' => 'Laura',
            'last_name' => 'Martínez',
            'email' => 'laura.martinez@email.com',
            'phone' => '+34 600 222 333',
            'country' => 'Argentina',
            'city' => 'Buenos Aires',
            'source' => 'Website',
            'status' => 'proposal',
            'assigned_to' => 'Pedro Sánchez',
            'campaign' => 'Sitio Web Enero 2024',
            'budget' => '35000',
            'company' => 'Innovation Labs',
            'position' => 'CTO',
            'interest_level' => 'high',
            'created_at' => '2024-01-12 15:45:00',
            'last_contact' => '2024-01-21 12:15:00'
        ],
        [
            'id' => 5,
            'first_name' => 'Pedro',
            'last_name' => 'Rodríguez',
            'email' => 'pedro.rodriguez@email.com',
            'phone' => '+34 600 888 999',
            'country' => 'Colombia',
            'city' => 'Bogotá',
            'source' => 'Cold Call',
            'status' => 'negotiation',
            'assigned_to' => 'María García',
            'campaign' => 'Outbound Q1',
            'budget' => '40000',
            'company' => 'Business Solutions',
            'position' => 'Gerente Comercial',
            'interest_level' => 'medium',
            'created_at' => '2024-01-08 14:20:00',
            'last_contact' => '2024-01-19 17:30:00'
        ]
    ];
    
    // Aplicar filtros si existen
    if (!empty($filters['status'])) {
        $leads = array_filter($leads, function($lead) use ($filters) {
            return $lead['status'] === $filters['status'];
        });
    }
    
    if (!empty($filters['source'])) {
        $leads = array_filter($leads, function($lead) use ($filters) {
            return $lead['source'] === $filters['source'];
        });
    }
    
    if (!empty($filters['assigned_to'])) {
        $leads = array_filter($leads, function($lead) use ($filters) {
            return $lead['assigned_to'] === $filters['assigned_to'];
        });
    }
    
    if (!empty($filters['date_from'])) {
        $leads = array_filter($leads, function($lead) use ($filters) {
            return $lead['created_at'] >= $filters['date_from'];
        });
    }
    
    if (!empty($filters['date_to'])) {
        $leads = array_filter($leads, function($lead) use ($filters) {
            return $lead['created_at'] <= $filters['date_to'];
        });
    }
    
    return array_values($leads);
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['fields'])) {
            // Obtener campos disponibles para exportación
            $exportFields = [
                'id' => 'ID',
                'first_name' => 'Nombre',
                'last_name' => 'Apellido',
                'email' => 'Email',
                'phone' => 'Teléfono',
                'country' => 'País',
                'city' => 'Ciudad',
                'source' => 'Fuente',
                'status' => 'Estado',
                'assigned_to' => 'Asignado a',
                'campaign' => 'Campaña',
                'budget' => 'Presupuesto',
                'company' => 'Empresa',
                'position' => 'Cargo',
                'interest_level' => 'Nivel de Interés',
                'created_at' => 'Fecha de Creación',
                'last_contact' => 'Último Contacto'
            ];
            
            echo json_encode([
                'success' => true,
                'message' => 'Campos de exportación disponibles',
                'data' => $exportFields
            ]);
        } else {
            // Obtener datos de leads para exportar
            $filters = [];
            
            if (isset($_GET['status'])) {
                $filters['status'] = $_GET['status'];
            }
            
            if (isset($_GET['source'])) {
                $filters['source'] = $_GET['source'];
            }
            
            if (isset($_GET['assigned_to'])) {
                $filters['assigned_to'] = $_GET['assigned_to'];
            }
            
            if (isset($_GET['date_from'])) {
                $filters['date_from'] = $_GET['date_from'];
            }
            
            if (isset($_GET['date_to'])) {
                $filters['date_to'] = $_GET['date_to'];
            }
            
            $leads = getLeadsData($filters);
            
            echo json_encode([
                'success' => true,
                'message' => 'Datos de leads obtenidos correctamente',
                'data' => $leads,
                'total' => count($leads)
            ]);
        }
        break;
        
    case 'POST':
        // Procesar exportación de leads
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            echo json_encode([
                'success' => false,
                'message' => 'Datos inválidos o formato JSON incorrecto'
            ]);
            exit;
        }
        
        // Validar formato de exportación
        $format = isset($input['format']) ? strtolower($input['format']) : 'csv';
        $selectedFields = isset($input['fields']) ? $input['fields'] : [];
        $filters = isset($input['filters']) ? $input['filters'] : [];
        
        if (!in_array($format, ['csv', 'excel'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Formato de exportación no válido. Use: csv o excel'
            ]);
            exit;
        }
        
        if (empty($selectedFields)) {
            echo json_encode([
                'success' => false,
                'message' => 'Debe seleccionar al menos un campo para exportar'
            ]);
            exit;
        }
        
        // Obtener datos de leads
        $leads = getLeadsData($filters);
        
        if (empty($leads)) {
            echo json_encode([
                'success' => false,
                'message' => 'No hay leads que coincidan con los filtros aplicados'
            ]);
            exit;
        }
        
        // Preparar datos para exportación
        $exportData = [];
        $headers = [];
        
        // Obtener headers
        foreach ($selectedFields as $field) {
            $headers[] = $field; // Usar el nombre del campo como header
        }
        
        // Obtener datos
        foreach ($leads as $lead) {
            $row = [];
            foreach ($selectedFields as $field) {
                if (isset($lead[$field])) {
                    $row[] = $lead[$field];
                } else {
                    $row[] = ''; // Campo vacío si no existe
                }
            }
            $exportData[] = $row;
        }
        
        // Generar archivo según formato
        if ($format === 'csv') {
            $fileContent = generateCSV($exportData, $headers);
            $filename = 'leads_export_' . date('Y-m-d_H-i-s') . '.csv';
            $contentType = 'text/csv';
        } else {
            $fileContent = generateExcel($exportData, $headers);
            $filename = 'leads_export_' . date('Y-m-d_H-i-s') . '.xls';
            $contentType = 'application/vnd.ms-excel';
        }
        
        // Codificar el archivo en base64 para enviarlo en la respuesta JSON
        $base64Content = base64_encode($fileContent);
        
        echo json_encode([
            'success' => true,
            'message' => 'Exportación completada exitosamente',
            'data' => [
                'filename' => $filename,
                'content_type' => $contentType,
                'content' => $base64Content,
                'size' => strlen($fileContent),
                'total_leads' => count($exportData),
                'format' => $format
            ]
        ]);
        break;
        
    default:
        echo json_encode([
            'success' => false,
            'message' => 'Método no permitido'
        ]);
        break;
}