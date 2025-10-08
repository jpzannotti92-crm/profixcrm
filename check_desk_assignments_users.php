<?php
require_once 'src/Database/Connection.php';

// Obtener la conexión de la base de datos
$db = \iaTradeCRM\Database\Connection::getInstance();
$pdo = $db->getConnection();

echo '=== ASIGNACIONES DE MESAS A USUARIOS ===' . PHP_EOL;
$stmt = $pdo->prepare('
    SELECT u.username, u.id as user_id, d.name as desk_name, d.id as desk_id 
    FROM users u 
    LEFT JOIN desk_users du ON u.id = du.user_id 
    LEFT JOIN desks d ON du.desk_id = d.id 
    WHERE u.username IN (?, ?, ?, ?, ?)
    ORDER BY u.username
');
$stmt->execute([
    'jpzannotti92',
    'mparedes02', 
    'test_front',
    'leadmanager',
    'leadagent'
]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($results as $row) {
    echo $row['username'] . ': ' . ($row['desk_name'] ?: 'SIN MESA ASIGNADA') . ' (ID: ' . ($row['desk_id'] ?: 'NULL') . ')' . PHP_EOL;
}

echo PHP_EOL . '=== MESAS DISPONIBLES ===' . PHP_EOL;
$stmt2 = $pdo->prepare('SELECT id, name FROM desks ORDER BY id');
$stmt2->execute();
$desks = $stmt2->fetchAll(PDO::FETCH_ASSOC);
foreach ($desks as $desk) {
    echo 'Mesa ID ' . $desk['id'] . ': ' . $desk['name'] . PHP_EOL;
}