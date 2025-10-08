<?php
// Script de instalación mínima: crea la tabla 'trading_accounts' si no existe
// Ejecutar con: php public/api/install/create_min_trading_accounts_table.php

require_once __DIR__ . '/../database.php';

header('Content-Type: application/json');

try {
    $db = getDbConnection();

    $sql = "
        CREATE TABLE IF NOT EXISTS trading_accounts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            lead_id INT NULL,
            account_number VARCHAR(100) NULL,
            balance DECIMAL(12,2) NULL,
            currency VARCHAR(10) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_trading_accounts_user (user_id),
            INDEX idx_trading_accounts_lead (lead_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    $db->exec($sql);

    echo json_encode([
        'success' => true,
        'message' => "Tabla 'trading_accounts' verificada/creada correctamente",
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error creando/verificando la tabla trading_accounts: ' . $e->getMessage()
    ]);
}
?>