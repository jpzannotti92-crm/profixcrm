<?php

echo "=== AGREGANDO COLUMNAS created_by Y updated_by A TABLA LEADS ===\n\n";

try {
    // Conexión directa a MySQL
    $host = 'localhost';
    $dbname = 'spin2pay_profixcrm';
    $username = 'root';
    $password = '';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✓ Conexión establecida\n";
    
    // Verificar si las columnas ya existen
    $stmt = $pdo->prepare("SHOW COLUMNS FROM leads LIKE 'created_by'");
    $stmt->execute();
    $createdByExists = $stmt->fetch();
    
    $stmt = $pdo->prepare("SHOW COLUMNS FROM leads LIKE 'updated_by'");
    $stmt->execute();
    $updatedByExists = $stmt->fetch();
    
    // Agregar columna created_by si no existe
    if (!$createdByExists) {
        echo "Agregando columna created_by...\n";
        $pdo->exec("ALTER TABLE leads ADD COLUMN created_by INT(11) NULL AFTER updated_at");
        echo "✓ Columna created_by agregada\n";
    } else {
        echo "✓ Columna created_by ya existe\n";
    }
    
    // Agregar columna updated_by si no existe
    if (!$updatedByExists) {
        echo "Agregando columna updated_by...\n";
        $pdo->exec("ALTER TABLE leads ADD COLUMN updated_by INT(11) NULL AFTER created_by");
        echo "✓ Columna updated_by agregada\n";
    } else {
        echo "✓ Columna updated_by ya existe\n";
    }
    
    // Verificar la estructura actualizada
    echo "\n✓ Verificando estructura actualizada:\n";
    $stmt = $pdo->prepare("DESCRIBE leads");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        echo "  - {$column['Field']}: {$column['Type']} ";
        echo ($column['Null'] === 'NO' ? 'NOT NULL' : 'NULL');
        echo ($column['Key'] ? " {$column['Key']}" : '');
        echo ($column['Default'] !== null ? " DEFAULT '{$column['Default']}'" : '');
        echo "\n";
    }
    
    echo "\n✓ Columnas agregadas exitosamente!\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
}

echo "\n=== FIN ===\n";