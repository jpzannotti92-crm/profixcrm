<?php
// Seed de permisos faltantes del pack 'admin' sin usar Composer
// Inserta en la tabla 'permissions' los permisos esperados si no existen

require_once __DIR__ . '/src/Database/Connection.php';

use iaTradeCRM\Database\Connection;

function tableHasColumn($db, $table, $column) {
    $sql = "SHOW COLUMNS FROM `" . str_replace("`", "``", $table) . "` LIKE " . $db->quote($column);
    $stmt = $db->query($sql);
    return (bool)$stmt->fetch();
}

function ensurePermission($db, $name, $display = null, $desc = null, $module = null, $action = null) {
    $stmt = $db->prepare("SELECT id FROM permissions WHERE name = ? LIMIT 1");
    $stmt->execute([$name]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        echo "✓ Existe: {$name} (ID: {$existing['id']})\n";
        return (int)$existing['id'];
    }

    $columns = ['name'];
    $values = [$name];
    $placeholders = ['?'];

    if (tableHasColumn($db, 'permissions', 'display_name')) { $columns[] = 'display_name'; $values[] = $display ?? $name; $placeholders[] = '?'; }
    if (tableHasColumn($db, 'permissions', 'code')) { $columns[] = 'code'; $values[] = $name; $placeholders[] = '?'; }
    if (tableHasColumn($db, 'permissions', 'description')) { $columns[] = 'description'; $values[] = $desc ?? ('Permiso para ' . $name); $placeholders[] = '?'; }
    if (tableHasColumn($db, 'permissions', 'module')) { $columns[] = 'module'; $values[] = $module ?? (explode('.', $name)[0] ?? 'system'); $placeholders[] = '?'; }
    if (tableHasColumn($db, 'permissions', 'action')) { $columns[] = 'action'; $values[] = $action ?? (explode('.', $name)[1] ?? null); $placeholders[] = '?'; }
    if (tableHasColumn($db, 'permissions', 'created_at')) { $columns[] = 'created_at'; $values[] = date('Y-m-d H:i:s'); $placeholders[] = '?'; }

    $sql = "INSERT INTO permissions (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $ins = $db->prepare($sql);
    $ins->execute($values);
    $id = (int)$db->lastInsertId();
    echo "+ Creado: {$name} (ID: {$id})\n";
    return $id;
}

try {
    $db = Connection::getInstance()->getConnection();

    echo "=== Seed permisos faltantes (pack admin) ===\n\n";

    $expected = [
        // Usuarios y roles
        'users.view', 'users.create', 'users.edit', 'users.delete', 'users.edit_all',
        'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
        // Desks
        'desks.view', 'desks.create', 'desks.edit', 'desks.delete',
        // Leads
        'leads.view', 'leads.create', 'leads.edit', 'leads.delete', 'leads.assign', 'leads.import', 'leads.export',
        // Trading y cuentas
        'trading.view', 'trading.create', 'trading.edit', 'trading.delete',
        'trading_accounts.view', 'trading_accounts.create', 'trading_accounts.edit', 'trading_accounts.delete',
        // Depósitos y retiros
        'deposits_withdrawals.view', 'deposits_withdrawals.create', 'deposits_withdrawals.edit', 'deposits_withdrawals.delete',
        // Transacciones y reportes
        'transactions.view', 'transactions.approve', 'transactions.process',
        'reports.view', 'reports.create',
        // Actividades y dashboard
        'activities.view', 'activities.create', 'activities.edit', 'activities.delete',
        'dashboard.view', 'dashboard.stats',
        // Permisos de usuario, instrumentos y otros
        'user_permissions.view', 'user_permissions.edit',
        'instruments.view', 'instruments.create', 'instruments.edit', 'instruments.delete',
        'webtrader.access',
        // Estados
        'manage_states'
    ];

    $created = 0;
    foreach ($expected as $permName) {
        // Derivar labels
        $parts = explode('.', $permName);
        $module = $parts[0] ?? 'system';
        $action = $parts[1] ?? null;
        $display = ucfirst(str_replace('_', ' ', $permName));
        $desc = "Permite {$action} en {$module}";

        $id = ensurePermission($db, $permName, $display, $desc, $module, $action);
        if ($id) { $created++; }
    }

    echo "\nSeed completado. Permisos revisados: " . count($expected) . "\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}