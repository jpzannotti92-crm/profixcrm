<?php
require_once __DIR__ . '/../../platform_check_bypass.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/bootstrap.php';

use IaTradeCRM\Models\Lead;

header('Content-Type: application/json');

try {
    // Probar si el modelo Lead está disponible
    $lead = new Lead();
    
    // Probar el método findByEmail
    $existingLead = Lead::findByEmail('test@example.com');
    
    echo json_encode([
        'success' => true,
        'message' => 'Lead model funciona correctamente',
        'data' => [
            'lead_class' => get_class($lead),
            'find_by_email_works' => $existingLead ? 'Sí' : 'No (no encontrado)',
            'existing_lead' => $existingLead ? $existingLead->toArray() : null
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}