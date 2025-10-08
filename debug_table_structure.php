<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/config/config.php';

use IaTradeCRM\Models\Lead;
use IaTradeCRM\Database\Connection;

echo "=== VERIFICACIÓN DE ESTRUCTURA DE TABLA LEADS ===\n\n";

try {
    // Obtener conexión directa a través de Connection
    $connection = Connection::getInstance();
    $pdo = $connection->getConnection();
    
    echo "✓ Conexión establecida\n";
    
    // Verificar estructura de la tabla
    $stmt = $pdo->prepare("DESCRIBE leads");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Columnas en la tabla leads:\n";
    foreach ($columns as $column) {
        echo "  - {$column['Field']}: {$column['Type']} ";
        echo ($column['Null'] === 'NO' ? 'NOT NULL' : 'NULL');
        echo ($column['Key'] ? " {$column['Key']}" : '');
        echo ($column['Default'] !== null ? " DEFAULT '{$column['Default']}'" : '');
        echo "\n";
    }
    
    echo "\n✓ Estructura de tabla verificada exitosamente\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
}

echo "\n=== FIN ===\n";