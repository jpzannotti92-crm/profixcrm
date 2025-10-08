<?php
// Script para agregar columnas faltantes a la tabla leads

try {
    $pdo = new PDO('mysql:host=localhost;dbname=spin2pay_profixcrm;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== AGREGANDO COLUMNAS FALTANTES A TABLA LEADS ===\n\n";
    
    // Verificar columnas actuales
    $stmt = $pdo->query("DESCRIBE leads");
    $currentColumns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
    echo "Columnas actuales: " . implode(', ', $currentColumns) . "\n\n";
    
    // Columnas que necesitamos agregar
    $columnsToAdd = [
        'campaign' => "VARCHAR(100) DEFAULT NULL AFTER source",
        'address' => "VARCHAR(255) DEFAULT NULL AFTER city",
        'postal_code' => "VARCHAR(20) DEFAULT NULL AFTER address",
        'birth_date' => "DATE DEFAULT NULL AFTER postal_code",
        'gender' => "VARCHAR(20) DEFAULT NULL AFTER birth_date",
        'marital_status' => "VARCHAR(50) DEFAULT NULL AFTER gender",
        'children' => "INT DEFAULT NULL AFTER marital_status",
        'education' => "VARCHAR(100) DEFAULT NULL AFTER children",
        'experience' => "VARCHAR(100) DEFAULT NULL AFTER education",
        'skills' => "TEXT DEFAULT NULL AFTER experience",
        'languages' => "VARCHAR(255) DEFAULT NULL AFTER skills",
        'last_contact' => "DATETIME DEFAULT NULL AFTER notes"
    ];
    
    $addedColumns = [];
    
    foreach ($columnsToAdd as $columnName => $definition) {
        if (!in_array($columnName, $currentColumns)) {
            echo "Agregando columna '$columnName'... ";
            $sql = "ALTER TABLE leads ADD COLUMN $columnName $definition";
            $pdo->exec($sql);
            echo "✓ HECHO\n";
            $addedColumns[] = $columnName;
        } else {
            echo "La columna '$columnName' ya existe ✓\n";
        }
    }
    
    echo "\n=== RESULTADO ===\n";
    if (count($addedColumns) > 0) {
        echo "Se agregaron las siguientes columnas: " . implode(', ', $addedColumns) . "\n";
    } else {
        echo "No se agregaron columnas nuevas. Todas las columnas ya existen.\n";
    }
    
    // Verificar estructura final
    echo "\n=== ESTRUCTURA FINAL DE LA TABLA ===\n";
    $stmt = $pdo->query("DESCRIBE leads");
    $finalColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($finalColumns as $col) {
        echo str_pad($col['Field'], 20) . str_pad($col['Type'], 25) . str_pad($col['Null'], 10) . "\n";
    }
    
    echo "\n✓ Proceso completado exitosamente!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>