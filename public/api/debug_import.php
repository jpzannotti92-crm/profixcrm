<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Simular el proceso de importación con datos de prueba similares a los reales
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['leads'])) {
        echo json_encode([
            'success' => false,
            'message' => 'No se recibieron datos de leads',
            'received_data' => $input
        ]);
        exit();
    }
    
    $leads = $input['leads'];
    $skipDuplicates = $input['skipDuplicates'] ?? true;
    
    // Análisis detallado de los datos recibidos
    $analysis = [
        'total_leads_received' => count($leads),
        'skip_duplicates' => $skipDuplicates,
        'sample_leads' => array_slice($leads, 0, 3), // Primeros 3 leads para análisis
        'field_analysis' => [],
        'validation_results' => []
    ];
    
    // Analizar campos en los primeros 10 leads
    $sampleSize = min(10, count($leads));
    for ($i = 0; $i < $sampleSize; $i++) {
        $lead = $leads[$i];
        $leadNum = $i + 1;
        
        // Verificar campos requeridos
        $hasFirstName = !empty($lead['first_name']);
        $hasLastName = !empty($lead['last_name']);
        $hasEmail = !empty($lead['email']);
        
        $analysis['validation_results'][] = [
            'lead_number' => $leadNum,
            'has_first_name' => $hasFirstName,
            'has_last_name' => $hasLastName,
            'has_email' => $hasEmail,
            'first_name_value' => $lead['first_name'] ?? 'VACÍO',
            'last_name_value' => $lead['last_name'] ?? 'VACÍO',
            'email_value' => $lead['email'] ?? 'VACÍO',
            'all_fields' => array_keys($lead),
            'passes_validation' => $hasFirstName && $hasLastName
        ];
    }
    
    // Análisis de campos disponibles
    if (!empty($leads)) {
        $firstLead = $leads[0];
        $analysis['field_analysis'] = [
            'available_fields' => array_keys($firstLead),
            'field_values_sample' => $firstLead
        ];
    }
    
    // Contar cuántos leads pasarían la validación
    $validLeads = 0;
    $invalidLeads = 0;
    $emptyFirstName = 0;
    $emptyLastName = 0;
    $emptyEmail = 0;
    
    foreach ($leads as $lead) {
        $hasFirstName = !empty($lead['first_name']);
        $hasLastName = !empty($lead['last_name']);
        
        if (!$hasFirstName) $emptyFirstName++;
        if (!$hasLastName) $emptyLastName++;
        if (empty($lead['email'])) $emptyEmail++;
        
        if ($hasFirstName && $hasLastName) {
            $validLeads++;
        } else {
            $invalidLeads++;
        }
    }
    
    $analysis['summary'] = [
        'valid_leads' => $validLeads,
        'invalid_leads' => $invalidLeads,
        'empty_first_name' => $emptyFirstName,
        'empty_last_name' => $emptyLastName,
        'empty_email' => $emptyEmail
    ];
    
    echo json_encode([
        'success' => true,
        'message' => 'Análisis de importación completado',
        'analysis' => $analysis
    ], JSON_PRETTY_PRINT);
    
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido. Use POST.'
    ]);
}
?>