<?php
// Script para añadir columnas faltantes en la tabla 'leads'
// Añade company, job_title y city si no existen actualmente.

try {
    $pdo = new PDO('mysql:host=localhost;dbname=iatrade_crm', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Verificando columnas de la tabla 'leads'...\n";

    // Verificar que la tabla exista
    $tableStmt = $pdo->query("SHOW TABLES LIKE 'leads'");
    if ($tableStmt->rowCount() === 0) {
        throw new Exception("La tabla 'leads' no existe en la base de datos.");
    }

    // Obtener columnas existentes
    $stmt = $pdo->query("SHOW COLUMNS FROM leads");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    $existing = array_flip($columns);

    $addParts = [];

    if (!isset($existing['company'])) {
        $addParts[] = "ADD COLUMN company VARCHAR(150) NULL COMMENT 'Empresa del lead'";
    }
    if (!isset($existing['job_title'])) {
        $addParts[] = "ADD COLUMN job_title VARCHAR(100) NULL COMMENT 'Cargo del lead'";
    }
    if (!isset($existing['city'])) {
        $addParts[] = "ADD COLUMN city VARCHAR(100) NULL COMMENT 'Ciudad del lead'";
    }

    if (empty($addParts)) {
        echo "No hay columnas faltantes que agregar.\n";
    } else {
        $sql = "ALTER TABLE leads " . implode(', ', $addParts);
        try {
            $pdo->exec($sql);
            echo "✓ Columnas agregadas correctamente: \n";
            foreach ($addParts as $part) {
                echo "   - $part\n";
            }
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            // En caso de correr múltiples veces, manejar duplicados con mensaje amigable
            if (strpos($msg, 'Duplicate column name') !== false) {
                echo "⚠ Algunas columnas ya existen: $msg\n";
            } else {
                throw $e;
            }
        }

        // Mostrar estructura final
        echo "\nEstructura actualizada de 'leads':\n";
        $desc = $pdo->query('DESCRIBE leads')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($desc as $col) {
            printf("%-20s %-25s %-10s\n", $col['Field'], $col['Type'], $col['Null']);
        }
    }

    echo "\nFinalizado.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>