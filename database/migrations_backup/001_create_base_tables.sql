-- Migración 001: Crear tablas base del sistema
-- iaTrade CRM - Base de datos de producción

-- Tabla de roles
CREATE TABLE IF NOT EXISTS roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    permissions JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla de usuarios
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20),
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    email_verified_at TIMESTAMP NULL,
    remember_token VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT
);

-- Tabla de leads
CREATE TABLE IF NOT EXISTS leads (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(20),
    company VARCHAR(255),
    position VARCHAR(255),
    source VARCHAR(100),
    status VARCHAR(50) DEFAULT 'new',
    assigned_to INT,
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_assigned_to (assigned_to),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Tabla de actividades
CREATE TABLE IF NOT EXISTS activities (
    id INT PRIMARY KEY AUTO_INCREMENT,
    lead_id INT NOT NULL,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    scheduled_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_lead_id (lead_id),
    INDEX idx_user_id (user_id),
    INDEX idx_scheduled_at (scheduled_at)
);

-- Tabla de configuración del sistema
CREATE TABLE IF NOT EXISTS system_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    config_key VARCHAR(100) NOT NULL UNIQUE,
    config_value TEXT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla de logs del sistema
CREATE TABLE IF NOT EXISTS system_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    level VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    context JSON,
    user_id INT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_level (level),
    INDEX idx_created_at (created_at),
    INDEX idx_user_id (user_id)
);

-- Tabla de sesiones
CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(255) PRIMARY KEY,
    user_id INT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    payload LONGTEXT NOT NULL,
    last_activity INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_last_activity (last_activity)
);

-- Insertar roles por defecto
INSERT IGNORE INTO roles (id, name, display_name, description, permissions) VALUES
(1, 'superadmin', 'Super Administrador', 'Acceso completo al sistema', JSON_ARRAY(
    'users.view', 'users.create', 'users.edit', 'users.delete',
    'leads.view', 'leads.create', 'leads.edit', 'leads.delete', 'leads.assign',
    'activities.view', 'activities.create', 'activities.edit', 'activities.delete',
    'reports.view', 'reports.export',
    'system.config', 'system.logs', 'system.backup'
)),
(2, 'admin', 'Administrador', 'Administrador del sistema', JSON_ARRAY(
    'users.view', 'users.create', 'users.edit',
    'leads.view', 'leads.create', 'leads.edit', 'leads.assign',
    'activities.view', 'activities.create', 'activities.edit',
    'reports.view', 'reports.export'
)),
(3, 'manager', 'Gerente', 'Gerente de ventas', JSON_ARRAY(
    'users.view',
    'leads.view', 'leads.create', 'leads.edit', 'leads.assign',
    'activities.view', 'activities.create', 'activities.edit',
    'reports.view'
)),
(4, 'sales_agent', 'Agente de Ventas', 'Agente de ventas', JSON_ARRAY(
    'leads.view.assigned', 'leads.edit.assigned',
    'activities.view.own', 'activities.create', 'activities.edit.own'
));

-- Insertar configuraciones por defecto
INSERT IGNORE INTO system_config (config_key, config_value, description) VALUES
('app_name', 'iaTrade CRM', 'Nombre de la aplicación'),
('app_version', '1.0.0', 'Versión de la aplicación'),
('timezone', 'America/Mexico_City', 'Zona horaria del sistema'),
('date_format', 'Y-m-d', 'Formato de fecha'),
('datetime_format', 'Y-m-d H:i:s', 'Formato de fecha y hora'),
('pagination_limit', '25', 'Límite de paginación por defecto'),
('session_lifetime', '7200', 'Duración de sesión en segundos'),
('password_min_length', '8', 'Longitud mínima de contraseña'),
('max_login_attempts', '5', 'Máximo de intentos de login'),
('lockout_duration', '900', 'Duración de bloqueo en segundos');

-- Crear índices adicionales para optimización
CREATE INDEX idx_users_role_status ON users(role_id, status);
CREATE INDEX idx_leads_assigned_status ON leads(assigned_to, status);
CREATE INDEX idx_activities_user_status ON activities(user_id, status);
CREATE INDEX idx_system_logs_level_date ON system_logs(level, created_at);

-- Crear vistas útiles
CREATE OR REPLACE VIEW active_users AS
SELECT u.*, r.display_name as role_name
FROM users u
JOIN roles r ON u.role_id = r.id
WHERE u.status = 'active';

CREATE OR REPLACE VIEW leads_summary AS
SELECT 
    l.*,
    u.name as assigned_user_name,
    c.name as created_by_name,
    COUNT(a.id) as activities_count
FROM leads l
LEFT JOIN users u ON l.assigned_to = u.id
LEFT JOIN users c ON l.created_by = c.id
LEFT JOIN activities a ON l.id = a.lead_id
GROUP BY l.id;

-- Triggers para auditoría
DELIMITER $$

CREATE TRIGGER users_updated_at 
    BEFORE UPDATE ON users 
    FOR EACH ROW 
BEGIN 
    SET NEW.updated_at = CURRENT_TIMESTAMP; 
END$$

CREATE TRIGGER leads_updated_at 
    BEFORE UPDATE ON leads 
    FOR EACH ROW 
BEGIN 
    SET NEW.updated_at = CURRENT_TIMESTAMP; 
END$$

CREATE TRIGGER activities_updated_at 
    BEFORE UPDATE ON activities 
    FOR EACH ROW 
BEGIN 
    SET NEW.updated_at = CURRENT_TIMESTAMP; 
END$$

DELIMITER ;

-- Comentarios en las tablas
ALTER TABLE roles COMMENT = 'Roles y permisos del sistema';
ALTER TABLE users COMMENT = 'Usuarios del sistema';
ALTER TABLE leads COMMENT = 'Leads y prospectos';
ALTER TABLE activities COMMENT = 'Actividades y tareas';
ALTER TABLE system_config COMMENT = 'Configuración del sistema';
ALTER TABLE system_logs COMMENT = 'Logs del sistema';
ALTER TABLE sessions COMMENT = 'Sesiones de usuario';