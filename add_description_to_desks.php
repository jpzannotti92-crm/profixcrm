<?php
// Script para agregar la columna 'description' a la tabla desks si falta
// Usa la conexión central del proyecto

require_once __DIR__ . '/src/Database/Connection.php';

use iaTradeCRM\Database\Connection;

 function tableHasColumn(PDO $db, string $table, string $column): bool {
    // Evitar placeholders en SHOW/LIKE, usar INFORMATION_SCHEMA
    $sql = "SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . addslashes($table) . "' 
              AND COLUMN_NAME = '" . addslashes($column) . "'";
    $stmt = $db->query($sql);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int)($row['cnt'] ?? 0) > 0;
 }

try {
    $db = Connection::getInstance()->getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Conexión OK.\n\n";
    echo "Verificando columna 'description' en tabla: desks\n";

    if (tableHasColumn($db, 'desks', 'description')) {
        echo "• Columna 'description' ya existe en desks\n";
    } else {
        echo "• Agregando columna 'description' a desks...\n";
        // Según los esquemas del proyecto, 'description' es TEXT y opcional
        $db->exec("ALTER TABLE `desks` ADD COLUMN `description` TEXT NULL AFTER `name`");
        echo "✓ Columna 'description' agregada correctamente\n";
    }

    echo "\nListo.\n";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

?>