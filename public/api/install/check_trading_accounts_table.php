<?php
// Script de diagnóstico: verifica existencia y columnas de 'trading_accounts'
// Ejecutar con: php public/api/install/check_trading_accounts_table.php

// Usar conexión mínima compatible
require_once __DIR__ . '/../database.php';

header('Content-Type: application/json');

try {
    $db = getDbConnection();

    // Verificar si la tabla existe
    $stmt = $db->query("SHOW TABLES LIKE 'trading_accounts'");
    $tableExists = $stmt->rowCount() > 0;

    if (!$tableExists) {
        echo json_encode([
            'success' => false,
            'message' => "La tabla 'trading_accounts' no existe",
            'table_exists' => false
        ]);
        exit();
    }

    // Obtener columnas existentes
    $columnsStmt = $db->query("SHOW COLUMNS FROM trading_accounts");
    $columns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);

    $expected = ['id', 'user_id', 'lead_id', 'account_number', 'created_at'];
    $missing = array_values(array_diff($expected, $columns));

    // Contar filas de prueba
    $countStmt = $db->query("SELECT COUNT(*) as cnt FROM trading_accounts");
    $count = (int)($countStmt->fetch()['cnt'] ?? 0);

    echo json_encode([
        'success' => true,
        'message' => "Tabla 'trading_accounts' verificada",
        'table_exists' => true,
        'columns' => $columns,
        'expected_columns' => $expected,
        'missing_columns' => $missing,
        'row_count' => $count
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error verificando trading_accounts: ' . $e->getMessage()
    ]);
}
?>