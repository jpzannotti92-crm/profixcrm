<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Database/Connection.php';

use iaTradeCRM\Database\Connection;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $db = Connection::getInstance()->getConnection();
    
    // Contar leads antes de eliminar
    $countStmt = $db->query("SELECT COUNT(*) as total FROM leads");
    $totalBefore = $countStmt->fetch()['total'];
    
    // Eliminar todos los leads
    $deleteStmt = $db->prepare("DELETE FROM leads");
    $result = $deleteStmt->execute();
    
    // Reiniciar el auto_increment
    $resetStmt = $db->prepare("ALTER TABLE leads AUTO_INCREMENT = 1");
    $resetStmt->execute();
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Tabla de leads limpiada exitosamente',
            'leads_deleted' => $totalBefore,
            'current_count' => 0
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error al limpiar la tabla de leads'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>