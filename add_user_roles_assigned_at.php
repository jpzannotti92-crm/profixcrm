<?php
// Asegura la columna 'assigned_at' en la tabla user_roles para evitar errores 42S22
// Uso: C:\xampp\php\php.exe add_user_roles_assigned_at.php

require_once __DIR__ . '/src/Database/Connection.php';

use iaTradeCRM\Database\Connection;

try {
    $pdo = Connection::getInstance()->getConnection();

    // Verificar si existe la tabla user_roles
    $hasTable = false;
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'user_roles'");
        $hasTable = (bool)$stmt->fetchColumn();
    } catch (Exception $e) { $hasTable = false; }
    if (!$hasTable) { throw new Exception("La tabla 'user_roles' no existe"); }

    // Comprobar columna assigned_at
    $hasAssignedAt = false;
    try {
        $col = $pdo->query("SHOW COLUMNS FROM user_roles LIKE 'assigned_at'");
        $hasAssignedAt = $col && $col->fetch() ? true : false;
    } catch (Exception $e) { $hasAssignedAt = false; }

    if (!$hasAssignedAt) {
        $pdo->exec("ALTER TABLE user_roles ADD COLUMN assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
        echo "✓ Columna 'assigned_at' añadida a 'user_roles'\n";
    } else {
        echo "• Columna 'assigned_at' ya existe en 'user_roles'\n";
    }

    echo "Hecho.\n";
    exit(0);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

?>