<?php
// Script de instalación mínima: crea la tabla 'lead_activities' si no existe
// Ejecutar con: php public/api/install/create_min_lead_activities_table.php

// No usar Composer aquí para compatibilidad con PHP 8.0 en CLI
require_once __DIR__ . '/../database.php';

header('Content-Type: application/json');

try {
    $db = getDbConnection();

    // Crear tabla 'lead_activities' con columnas usados por el backend actual
    $sql = "
        CREATE TABLE IF NOT EXISTS lead_activities (
            id INT AUTO_INCREMENT PRIMARY KEY,
            lead_id INT NOT NULL,
            user_id INT NULL,
            type ENUM('note','call','email','meeting','task','reminder') DEFAULT 'note',
            status ENUM('planned','in_progress','completed','cancelled') DEFAULT 'completed',
            priority ENUM('low','medium','high') DEFAULT 'medium',
            visibility ENUM('private','team','public') DEFAULT 'team',
            description TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_lead_activities_lead (lead_id),
            INDEX idx_lead_activities_user (user_id),
            INDEX idx_lead_activities_type (type),
            INDEX idx_lead_activities_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    $db->exec($sql);

    echo json_encode([
        'success' => true,
        'message' => "Tabla 'lead_activities' verificada/creada correctamente",
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error creando/verificando la tabla lead_activities: ' . $e->getMessage()
    ]);
}
?>