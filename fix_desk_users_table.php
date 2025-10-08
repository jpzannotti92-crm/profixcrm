<?php
// Script para agregar la columna is_primary a la tabla desk_users
$dbConfig = include __DIR__ . '/config/database.php';
$mysql = $dbConfig['connections']['mysql'];

try {
    $pdo = new PDO(
        "mysql:host={$mysql['host']};port={$mysql['port']};dbname={$mysql['database']}", 
        $mysql['username'], 
        $mysql['password'],
        $mysql['options']
    );
    
    echo "<h2>Corrección de la tabla desk_users</h2>\n";
    
    // Verificar si la columna ya existe
    $stmt = $pdo->query("SHOW COLUMNS FROM desk_users LIKE 'is_primary'");
    $hasPrimaryColumn = $stmt->fetch();
    
    if ($hasPrimaryColumn) {
        echo "<p style='color: green;'>✅ La columna 'is_primary' ya existe en la tabla desk_users</p>\n";
    } else {
        echo "<p>Agregando la columna 'is_primary' a la tabla desk_users...</p>\n";
        
        // Agregar la columna is_primary
        $alterQuery = "ALTER TABLE desk_users ADD COLUMN is_primary TINYINT(1) DEFAULT 0";
        $pdo->exec($alterQuery);
        
        echo "<p style='color: green;'>✅ Columna 'is_primary' agregada exitosamente</p>\n";
        
        // Verificar que se agregó correctamente
        $stmt = $pdo->query("SHOW COLUMNS FROM desk_users LIKE 'is_primary'");
        $newColumn = $stmt->fetch();
        
        if ($newColumn) {
            echo "<p>Detalles de la nueva columna:</p>\n";
            echo "<ul>\n";
            echo "<li>Campo: {$newColumn['Field']}</li>\n";
            echo "<li>Tipo: {$newColumn['Type']}</li>\n";
            echo "<li>Null: {$newColumn['Null']}</li>\n";
            echo "<li>Default: {$newColumn['Default']}</li>\n";
            echo "</ul>\n";
            
            // Opcional: Establecer el primer registro de cada usuario como primario
            echo "<h3>Estableciendo registros primarios por defecto</h3>\n";
            
            $updateQuery = "
                UPDATE desk_users du1 
                SET is_primary = 1 
                WHERE du1.id = (
                    SELECT MIN(du2.id) 
                    FROM (SELECT * FROM desk_users) du2 
                    WHERE du2.user_id = du1.user_id
                )
            ";
            
            $affectedRows = $pdo->exec($updateQuery);
            echo "<p>Se establecieron {$affectedRows} registros como primarios</p>\n";
            
            echo "<p style='color: green;'>🎉 Corrección completada exitosamente!</p>\n";
        } else {
            echo "<p style='color: red;'>❌ Error: No se pudo verificar la nueva columna</p>\n";
        }
    }
    
    // Mostrar la estructura actualizada
    echo "<h3>Estructura actualizada de desk_users:</h3>\n";
    $stmt = $pdo->query('DESCRIBE desk_users');
    echo "<table border='1'>\n";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>\n";
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $highlight = ($row['Field'] === 'is_primary') ? " style='background-color: #90EE90;'" : "";
        echo "<tr{$highlight}>";
        echo "<td>{$row['Field']}</td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "<td>{$row['Default']}</td>";
        echo "<td>{$row['Extra']}</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>\n";
    echo "<p>Código de error: " . $e->getCode() . "</p>\n";
}
?>