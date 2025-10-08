<?php
$host = 'localhost';
$dbname = 'spin2pay_profixcrm';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== ESTRUCTURA DE LA TABLA PERMISSIONS ===\n";
    $stmt = $pdo->query("DESCRIBE permissions");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Campo: {$row['Field']} | Tipo: {$row['Type']} | Key: {$row['Key']}\n";
    }
    
    echo "\n=== PRIMEROS 5 PERMISOS ===\n";
    $stmt = $pdo->query("SELECT * FROM permissions LIMIT 5");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: {$row['id']} | Nombre: {$row['name']} | Código: {$row['code']}\n";
    }
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>