-- Tabla de desks (equipos de trabajo)
CREATE TABLE desks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    manager_id INT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    target_monthly DECIMAL(15,2) DEFAULT 0,
    target_daily DECIMAL(15,2) DEFAULT 0,
    commission_rate DECIMAL(5,2) DEFAULT 0, -- Porcentaje de comisión
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (manager_id) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Tabla de miembros de desk
CREATE TABLE desk_members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    desk_id INT NOT NULL,
    user_id INT NOT NULL,
    role_in_desk ENUM('manager', 'senior_sales', 'sales', 'retention') NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    left_at TIMESTAMP NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    FOREIGN KEY (desk_id) REFERENCES desks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_active_member (desk_id, user_id, status)
);

-- Tabla de configuración de desk
CREATE TABLE desk_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    desk_id INT NOT NULL,
    setting_key VARCHAR(50) NOT NULL,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (desk_id) REFERENCES desks(id) ON DELETE CASCADE,
    UNIQUE KEY unique_desk_setting (desk_id, setting_key)
);

-- Insertar desks de ejemplo
INSERT INTO desks (name, description, target_monthly, target_daily, commission_rate, created_by) VALUES
('Desk Alpha', 'Equipo principal de ventas', 100000.00, 3333.33, 2.5, 1),
('Desk Beta', 'Equipo de retención', 75000.00, 2500.00, 3.0, 1),
('Desk Gamma', 'Equipo de ventas premium', 150000.00, 5000.00, 4.0, 1);