<?php
// Verificar estructura de tabla leads
try {
    $pdo = new PDO('mysql:host=localhost;dbname=spin2pay_profixcrm;charset=utf8mb4', 'root', '');
    $stmt = $pdo->query('DESCRIBE leads');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "COLUMNAS EN TABLA LEADS:\n";
    echo str_pad("CAMPO", 20) . str_pad("TIPO", 25) . str_pad("NULL", 10) . str_pad("KEY", 10) . "DEFAULT\n";
    echo str_repeat("-", 80) . "\n";
    
    foreach ($columns as $col) {
        echo str_pad($col['Field'], 20) . 
             str_pad($col['Type'], 25) . 
             str_pad($col['Null'], 10) . 
             str_pad($col['Key'], 10) . 
             ($col['Default'] ?? 'NULL') . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>