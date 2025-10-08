<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=iatrade_crm', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $sql = "CREATE TABLE IF NOT EXISTS user_targets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        monthly_target INT NOT NULL DEFAULT 50,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_date (user_id, created_at)
    )";
    
    $pdo->exec($sql);
    echo "Tabla user_targets creada exitosamente\n";
    
    // Insertar algunos objetivos de ejemplo para usuarios existentes
    $insertSql = "INSERT IGNORE INTO user_targets (user_id, monthly_target) 
                  SELECT u.id, 50 FROM users u 
                  INNER JOIN user_roles ur ON u.id = ur.user_id 
                  INNER JOIN roles r ON ur.role_id = r.id 
                  WHERE r.name IN ('sales', 'employee')";
    $pdo->exec($insertSql);
    echo "Objetivos por defecto insertados para usuarios de ventas\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>