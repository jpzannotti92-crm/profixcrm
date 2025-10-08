<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=iatrade_crm', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Agregando campos faltantes a la tabla lead_activities...\n";
    
    // Campos que necesitamos agregar
    $fieldsToAdd = [
        "ADD COLUMN description TEXT DEFAULT NULL COMMENT 'Descripción detallada de la actividad'",
        "ADD COLUMN metadata JSON DEFAULT NULL COMMENT 'Metadatos adicionales para eventos automáticos'",
        "ADD COLUMN is_system_generated BOOLEAN DEFAULT FALSE COMMENT 'Indica si fue generada automáticamente'",
        "ADD COLUMN priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium' COMMENT 'Prioridad'",
        "ADD COLUMN visibility ENUM('public', 'private', 'team') DEFAULT 'public' COMMENT 'Visibilidad'"
    ];
    
    foreach ($fieldsToAdd as $field) {
        try {
            $pdo->exec("ALTER TABLE lead_activities $field");
            echo "✓ Campo agregado: $field\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "⚠ Campo ya existe: $field\n";
            } else {
                echo "✗ Error agregando campo: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // Agregar índices
    $indexes = [
        "CREATE INDEX idx_lead_activities_type_status ON lead_activities(type, status)",
        "CREATE INDEX idx_lead_activities_is_system ON lead_activities(is_system_generated)",
        "CREATE INDEX idx_lead_activities_priority ON lead_activities(priority)"
    ];
    
    foreach ($indexes as $index) {
        try {
            $pdo->exec($index);
            echo "✓ Índice creado: $index\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                echo "⚠ Índice ya existe\n";
            } else {
                echo "✗ Error creando índice: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\nVerificando estructura final...\n";
    $stmt = $pdo->query('DESCRIBE lead_activities');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n=== ESTRUCTURA FINAL ===\n";
    foreach ($columns as $column) {
        echo sprintf("%-25s %-40s %-10s\n", 
            $column['Field'], 
            $column['Type'], 
            $column['Null']
        );
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>