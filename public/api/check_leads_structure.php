<?php
/**
 * Verificar estructura de tabla leads
 */

require_once __DIR__ . '/../../platform_check_bypass.php';
require_once __DIR__ . '/../../src/Database/Connection.php';

use iaTradeCRM\Database\Connection;

try {
    $connection = Connection::getInstance();
    $pdo = $connection->getConnection();
    
    $stmt = $pdo->query('DESCRIBE leads');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "=== ESTRUCTURA DE TABLA LEADS ===\n";
    echo str_pad("CAMPO", 20) . str_pad("TIPO", 25) . str_pad("NULL", 10) . str_pad("KEY", 10) . "DEFAULT\n";
    echo str_repeat("-", 80) . "\n";
    
    foreach ($columns as $col) {
        echo str_pad($col['Field'], 20) . 
             str_pad($col['Type'], 25) . 
             str_pad($col['Null'], 10) . 
             str_pad($col['Key'], 10) . 
             ($col['Default'] ?? 'NULL') . "\n";
    }
    
    echo "\n=== CAMPOS DISPONIBLES ===\n";
    foreach ($columns as $col) {
        echo "- {$col['Field']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}