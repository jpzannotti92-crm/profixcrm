<?php
echo "🔧 Creando tabla de notificaciones...\n";

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'iatrade_crm';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Conexión establecida exitosamente\n";
    
    // Crear tabla de notificaciones
    $sql = "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
        user_id INT,
        actions JSON,
        is_read BOOLEAN DEFAULT FALSE,
        read_at TIMESTAMP NULL,
        expires_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_is_read (is_read),
        INDEX idx_created_at (created_at),
        INDEX idx_expires_at (expires_at),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    $pdo->exec($sql);
    echo "✅ Tabla 'notifications' creada exitosamente\n";
    
    // Verificar que la tabla existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'notifications'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Tabla verificada correctamente\n";
        
        // Mostrar estructura de la tabla
        $stmt = $pdo->query("DESCRIBE notifications");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\n📋 Estructura de la tabla notifications:\n";
        foreach ($columns as $column) {
            echo "   ✓ {$column['Field']} ({$column['Type']})\n";
        }
    } else {
        echo "❌ Error: La tabla no se pudo crear\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🎉 Configuración completada!\n";
?>