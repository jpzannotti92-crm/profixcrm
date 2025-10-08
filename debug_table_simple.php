<?php

echo "=== VERIFICACIÓN DE ESTRUCTURA DE TABLA LEADS (SIMPLE) ===\n\n";

try {
    // Conexión directa a MySQL
    $host = 'localhost';
    $dbname = 'spin2pay_profixcrm';
    $username = 'root';
    $password = '';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
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
    
    // También verificar si hay datos
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM leads");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "\n✓ Total de leads en la base de datos: " . $result['total'] . "\n";
    
    // Verificar algunos leads de ejemplo
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, status FROM leads LIMIT 3");
    $stmt->execute();
    $sampleLeads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nEjemplos de leads:\n";
    foreach ($sampleLeads as $lead) {
        echo "  - ID: {$lead['id']}, Nombre: {$lead['first_name']} {$lead['last_name']}, Email: {$lead['email']}, Estado: {$lead['status']}\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
}

echo "\n=== FIN ===\n";