<?php
// Script de instalación mínima: crea la tabla 'leads' si no existe
// Ejecutar con: php public/api/install/create_min_leads_table.php

// No usar Composer aquí para compatibilidad con PHP 8.0 en CLI
require_once __DIR__ . '/../database.php';

header('Content-Type: application/json');

try {
    $db = getDbConnection();

    // Crear tabla 'leads' con columnas usadas por los endpoints actuales
    $sql = "
        CREATE TABLE IF NOT EXISTS leads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            first_name VARCHAR(100) NULL,
            last_name VARCHAR(100) NULL,
            email VARCHAR(255) NULL,
            phone VARCHAR(50) NULL,
            country VARCHAR(100) NULL,
            city VARCHAR(100) NULL,
            company VARCHAR(150) NULL,
            job_title VARCHAR(150) NULL,
            source VARCHAR(100) DEFAULT 'manual',
            status VARCHAR(50) DEFAULT 'new',
            priority VARCHAR(50) DEFAULT 'medium',
            value DECIMAL(12,2) NULL,
            notes TEXT NULL,
            desk_id INT NULL,
            assigned_to INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_leads_status (status),
            INDEX idx_leads_desk (desk_id),
            INDEX idx_leads_assigned_to (assigned_to)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    $db->exec($sql);

    echo json_encode([
        'success' => true,
        'message' => "Tabla 'leads' verificada/creada correctamente",
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error creando/verificando la tabla leads: ' . $e->getMessage()
    ]);
}
?>