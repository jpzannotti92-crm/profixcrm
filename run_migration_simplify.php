<?php
$host = 'localhost';
$dbname = 'iatrade_crm';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Ejecutando migración para simplificar tabla lead_activities...\n";
    
    $sql = file_get_contents('database/migrations/simplify_lead_activities_table.sql');
    $pdo->exec($sql);
    
    echo "Migración ejecutada exitosamente.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>