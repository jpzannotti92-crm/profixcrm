<?php
require_once __DIR__ . '/../../vendor/autoload.php';

header('Content-Type: application/json');

try {
    // Probar que podemos cargar el modelo Lead
    if (class_exists('IaTradeCRM\Models\Lead')) {
        echo json_encode([
            'success' => true,
            'message' => 'Clase Lead encontrada',
            'namespace' => 'IaTradeCRM\Models\Lead'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Clase Lead no encontrada',
            'available_classes' => get_declared_classes()
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}