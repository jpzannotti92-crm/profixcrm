<?php
echo "🚀 Completando configuración de la base de datos...\n";

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'iatrade_crm';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Conexión establecida exitosamente\n";
    
    // Leer y ejecutar el script de esquema completo
    $sql = file_get_contents(__DIR__ . '/complete_schema.sql');
    
    // Dividir en consultas individuales
    $queries = explode(';', $sql);
    $executed = 0;
    
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query) && !preg_match('/^--/', $query)) {
            try {
                $pdo->exec($query);
                $executed++;
            } catch (Exception $e) {
                // Ignorar errores de tablas que ya existen
                if (strpos($e->getMessage(), 'already exists') === false) {
                    echo "⚠️  Advertencia en consulta: " . substr($query, 0, 50) . "...\n";
                    echo "   Error: " . $e->getMessage() . "\n";
                }
            }
        }
    }
    
    echo "✅ $executed consultas ejecutadas exitosamente\n";
    
    // Verificar tablas creadas
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "\n📊 Tablas en la base de datos:\n";
    foreach ($tables as $table) {
        echo "   ✓ $table\n";
    }
    
    // Verificar datos de prueba
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $userCount = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM leads");
    $leadCount = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM trading_accounts");
    $accountCount = $stmt->fetch()['count'];
    
    echo "\n📈 Datos de prueba:\n";
    echo "   👥 Usuarios: $userCount\n";
    echo "   📋 Leads: $leadCount\n";
    echo "   💰 Cuentas de trading: $accountCount\n";
    
    echo "\n🎉 ¡Base de datos configurada completamente!\n";
    echo "\n📋 Información de acceso:\n";
    // Detectar URLs dinámicamente
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    $frontendUrl = $protocol . '://' . $host;
    $backendUrl = $protocol . '://' . $host;
    
    // Si estamos en desarrollo local, usar puertos específicos
    if (strpos($host, 'localhost') !== false && strpos($host, ':') === false) {
        $frontendUrl = $protocol . '://' . $host . ':3000';
        $backendUrl = $protocol . '://' . $host . ':8000';
    }
    
    echo "   🌐 URL Frontend: $frontendUrl\n";
    echo "   🔧 URL Backend: $backendUrl\n";
    echo "   👤 Usuario: admin\n";
    echo "   🔑 Contraseña: admin123\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>