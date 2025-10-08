-- =====================================================
-- IATRADE CRM - SCRIPT DE CREACIÓN DE BASE DE DATOS
-- =====================================================

-- Crear base de datos
CREATE DATABASE IF NOT EXISTS `iatrade_crm` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `iatrade_crm`;

-- =====================================================
-- TABLA: users (Usuarios del sistema)
-- =====================================================
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) UNIQUE NOT NULL,
    `email` VARCHAR(100) UNIQUE NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `phone` VARCHAR(20),
    `avatar` VARCHAR(255),
    `status` ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    `last_login` TIMESTAMP NULL,
    `login_attempts` INT DEFAULT 0,
    `locked_until` TIMESTAMP NULL,
    `email_verified` BOOLEAN DEFAULT FALSE,
    `email_verification_token` VARCHAR(255),
    `password_reset_token` VARCHAR(255),
    `password_reset_expires` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX `idx_username` (`username`),
    INDEX `idx_email` (`email`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: roles (Roles del sistema)
-- =====================================================
CREATE TABLE `roles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) UNIQUE NOT NULL,
    `display_name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `color` VARCHAR(7) DEFAULT '#007bff',
    `is_system` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX `idx_name` (`name`)
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: permissions (Permisos del sistema)
-- =====================================================
CREATE TABLE `permissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) UNIQUE NOT NULL,
    `display_name` VARCHAR(150) NOT NULL,
    `description` TEXT,
    `module` VARCHAR(50) NOT NULL,
    `action` VARCHAR(50) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX `idx_module` (`module`),
    INDEX `idx_action` (`action`)
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: user_roles (Relación usuarios-roles)
-- =====================================================
CREATE TABLE `user_roles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `role_id` INT NOT NULL,
    `assigned_by` INT,
    `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    
    UNIQUE KEY `unique_user_role` (`user_id`, `role_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_role_id` (`role_id`)
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: role_permissions (Relación roles-permisos)
-- =====================================================
CREATE TABLE `role_permissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `role_id` INT NOT NULL,
    `permission_id` INT NOT NULL,
    `granted_by` INT,
    `granted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`granted_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    
    UNIQUE KEY `unique_role_permission` (`role_id`, `permission_id`),
    INDEX `idx_role_id` (`role_id`),
    INDEX `idx_permission_id` (`permission_id`)
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: desks (Mesas de trabajo)
-- =====================================================
CREATE TABLE `desks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `color` VARCHAR(7) DEFAULT '#007bff',
    `manager_id` INT,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `max_leads` INT DEFAULT 1000,
    `auto_assign` BOOLEAN DEFAULT FALSE,
    `working_hours_start` TIME DEFAULT '09:00:00',
    `working_hours_end` TIME DEFAULT '18:00:00',
    `timezone` VARCHAR(50) DEFAULT 'UTC',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`manager_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_manager_id` (`manager_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: desk_users (Relación usuarios-mesas)
-- =====================================================
CREATE TABLE `desk_users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `desk_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `assigned_by` INT,
    `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `is_primary` BOOLEAN DEFAULT FALSE,
    
    FOREIGN KEY (`desk_id`) REFERENCES `desks`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    
    UNIQUE KEY `unique_desk_user` (`desk_id`, `user_id`),
    INDEX `idx_desk_id` (`desk_id`),
    INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: leads (Leads principales)
-- =====================================================
CREATE TABLE `leads` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) UNIQUE,
    `phone` VARCHAR(20),
    `country` VARCHAR(100),
    `city` VARCHAR(100),
    `address` TEXT,
    `postal_code` VARCHAR(20),
    `company` VARCHAR(150),
    `job_title` VARCHAR(100),
    `source` VARCHAR(100),
    `campaign` VARCHAR(150),
    `status` ENUM('new', 'contacted', 'qualified', 'proposal', 'negotiation', 'closed_won', 'closed_lost', 'on_hold') DEFAULT 'new',
    `priority` ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    `score` INT DEFAULT 0,
    `value` DECIMAL(15,2) DEFAULT 0.00,
    `currency` VARCHAR(3) DEFAULT 'USD',
    `assigned_to` INT,
    `desk_id` INT,
    `last_contact_date` TIMESTAMP NULL,
    `next_follow_up` TIMESTAMP NULL,
    `conversion_date` TIMESTAMP NULL,
    `notes` TEXT,
    `tags` JSON,
    `custom_fields` JSON,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`desk_id`) REFERENCES `desks`(`id`) ON DELETE SET NULL,
    
    INDEX `idx_email` (`email`),
    INDEX `idx_phone` (`phone`),
    INDEX `idx_status` (`status`),
    INDEX `idx_priority` (`priority`),
    INDEX `idx_assigned_to` (`assigned_to`),
    INDEX `idx_desk_id` (`desk_id`),
    INDEX `idx_source` (`source`),
    INDEX `idx_campaign` (`campaign`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_last_contact` (`last_contact_date`)
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: lead_status_history (Historial de estados)
-- =====================================================
CREATE TABLE `lead_status_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `lead_id` INT NOT NULL,
    `old_status` VARCHAR(50),
    `new_status` VARCHAR(50) NOT NULL,
    `changed_by` INT,
    `reason` TEXT,
    `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    
    INDEX `idx_lead_id` (`lead_id`),
    INDEX `idx_changed_by` (`changed_by`),
    INDEX `idx_changed_at` (`changed_at`)
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: lead_activities (Actividades de leads)
-- =====================================================
CREATE TABLE `lead_activities` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `lead_id` INT NOT NULL,
    `user_id` INT,
    `type` ENUM('call', 'email', 'meeting', 'note', 'task', 'sms', 'other') NOT NULL,
    `subject` VARCHAR(255),
    `description` TEXT,
    `status` ENUM('pending', 'completed', 'cancelled') DEFAULT 'completed',
    `scheduled_at` TIMESTAMP NULL,
    `completed_at` TIMESTAMP NULL,
    `duration_minutes` INT,
    `outcome` ENUM('positive', 'negative', 'neutral') DEFAULT 'neutral',
    `next_action` TEXT,
    `attachments` JSON,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    
    INDEX `idx_lead_id` (`lead_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_type` (`type`),
    INDEX `idx_status` (`status`),
    INDEX `idx_scheduled_at` (`scheduled_at`)
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: lead_notes (Notas de leads)
-- =====================================================
CREATE TABLE `lead_notes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `lead_id` INT NOT NULL,
    `user_id` INT,
    `note` TEXT NOT NULL,
    `is_private` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    
    INDEX `idx_lead_id` (`lead_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: lead_documents (Documentos de leads)
-- =====================================================
CREATE TABLE `lead_documents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `lead_id` INT NOT NULL,
    `user_id` INT,
    `filename` VARCHAR(255) NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `file_size` BIGINT NOT NULL,
    `mime_type` VARCHAR(100),
    `description` TEXT,
    `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    
    INDEX `idx_lead_id` (`lead_id`),
    INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: campaigns (Campañas de marketing)
-- =====================================================
CREATE TABLE `campaigns` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT,
    `type` ENUM('email', 'sms', 'social', 'ppc', 'organic', 'referral', 'other') NOT NULL,
    `status` ENUM('draft', 'active', 'paused', 'completed', 'cancelled') DEFAULT 'draft',
    `budget` DECIMAL(15,2) DEFAULT 0.00,
    `spent` DECIMAL(15,2) DEFAULT 0.00,
    `currency` VARCHAR(3) DEFAULT 'USD',
    `start_date` DATE,
    `end_date` DATE,
    `target_audience` JSON,
    `goals` JSON,
    `created_by` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    
    INDEX `idx_name` (`name`),
    INDEX `idx_type` (`type`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_by` (`created_by`)
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: daily_user_metrics (Métricas diarias de usuarios)
-- =====================================================
CREATE TABLE `daily_user_metrics` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `date` DATE NOT NULL,
    `leads_assigned` INT DEFAULT 0,
    `leads_contacted` INT DEFAULT 0,
    `leads_qualified` INT DEFAULT 0,
    `leads_converted` INT DEFAULT 0,
    `calls_made` INT DEFAULT 0,
    `emails_sent` INT DEFAULT 0,
    `meetings_held` INT DEFAULT 0,
    `revenue_generated` DECIMAL(15,2) DEFAULT 0.00,
    `working_hours` DECIMAL(4,2) DEFAULT 0.00,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    
    UNIQUE KEY `unique_user_date` (`user_id`, `date`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_date` (`date`)
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: system_settings (Configuraciones del sistema)
-- =====================================================
CREATE TABLE `system_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) UNIQUE NOT NULL,
    `value` TEXT,
    `type` ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    `description` TEXT,
    `is_public` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX `idx_key` (`key`)
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: audit_logs (Logs de auditoría)
-- =====================================================
CREATE TABLE `audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT,
    `action` VARCHAR(100) NOT NULL,
    `table_name` VARCHAR(100),
    `record_id` INT,
    `old_values` JSON,
    `new_values` JSON,
    `ip_address` VARCHAR(45),
    `user_agent` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_table_name` (`table_name`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB;

-- =====================================================
-- INSERTAR DATOS INICIALES
-- =====================================================

-- Roles del sistema
INSERT INTO `roles` (`name`, `display_name`, `description`, `color`, `is_system`) VALUES
('super_admin', 'Super Administrador', 'Acceso completo al sistema', '#dc3545', TRUE),
('admin', 'Administrador', 'Administrador del sistema', '#fd7e14', TRUE),
('manager', 'Gerente', 'Gerente de equipo', '#6f42c1', TRUE),
('agent', 'Agente', 'Agente de ventas', '#20c997', TRUE),
('viewer', 'Visualizador', 'Solo lectura', '#6c757d', TRUE);

-- Permisos del sistema
INSERT INTO `permissions` (`name`, `display_name`, `description`, `module`, `action`) VALUES
-- Usuarios
('users.view', 'Ver Usuarios', 'Ver lista de usuarios', 'users', 'view'),
('users.create', 'Crear Usuarios', 'Crear nuevos usuarios', 'users', 'create'),
('users.edit', 'Editar Usuarios', 'Editar usuarios existentes', 'users', 'edit'),
('users.delete', 'Eliminar Usuarios', 'Eliminar usuarios', 'users', 'delete'),

-- Leads
('leads.view', 'Ver Leads', 'Ver lista de leads', 'leads', 'view'),
('leads.create', 'Crear Leads', 'Crear nuevos leads', 'leads', 'create'),
('leads.edit', 'Editar Leads', 'Editar leads existentes', 'leads', 'edit'),
('leads.delete', 'Eliminar Leads', 'Eliminar leads', 'leads', 'delete'),
('leads.assign', 'Asignar Leads', 'Asignar leads a usuarios', 'leads', 'assign'),
('leads.import', 'Importar Leads', 'Importar leads desde archivos', 'leads', 'import'),
('leads.export', 'Exportar Leads', 'Exportar leads a archivos', 'leads', 'export'),

-- Mesas
('desks.view', 'Ver Mesas', 'Ver lista de mesas', 'desks', 'view'),
('desks.create', 'Crear Mesas', 'Crear nuevas mesas', 'desks', 'create'),
('desks.edit', 'Editar Mesas', 'Editar mesas existentes', 'desks', 'edit'),
('desks.delete', 'Eliminar Mesas', 'Eliminar mesas', 'desks', 'delete'),

-- Roles
('roles.view', 'Ver Roles', 'Ver lista de roles', 'roles', 'view'),
('roles.create', 'Crear Roles', 'Crear nuevos roles', 'roles', 'create'),
('roles.edit', 'Editar Roles', 'Editar roles existentes', 'roles', 'edit'),
('roles.delete', 'Eliminar Roles', 'Eliminar roles', 'roles', 'delete'),

-- Reportes
('reports.view', 'Ver Reportes', 'Ver reportes del sistema', 'reports', 'view'),
('reports.create', 'Crear Reportes', 'Crear reportes personalizados', 'reports', 'create'),

-- Sistema
('system.settings', 'Configuraciones', 'Acceso a configuraciones del sistema', 'system', 'settings'),
('system.audit', 'Logs de Auditoría', 'Ver logs de auditoría', 'system', 'audit');

-- Asignar permisos a roles
-- Super Admin: todos los permisos
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p WHERE r.name = 'super_admin';

-- Admin: todos excepto algunos del sistema
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p 
WHERE r.name = 'admin' AND p.name NOT IN ('system.audit');

-- Manager: gestión de leads y usuarios
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p 
WHERE r.name = 'manager' AND p.module IN ('leads', 'users', 'desks', 'reports');

-- Agent: operaciones básicas de leads
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p 
WHERE r.name = 'agent' AND p.name IN ('leads.view', 'leads.create', 'leads.edit', 'reports.view');

-- Viewer: solo lectura
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p 
WHERE r.name = 'viewer' AND p.action = 'view';

-- Usuario administrador por defecto
INSERT INTO `users` (`username`, `email`, `password_hash`, `first_name`, `last_name`, `status`, `email_verified`) VALUES
('admin', 'admin@iatrade.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'System', 'active', TRUE);

-- Asignar rol de super admin al usuario admin
INSERT INTO `user_roles` (`user_id`, `role_id`)
SELECT u.id, r.id FROM `users` u, `roles` r WHERE u.username = 'admin' AND r.name = 'super_admin';

-- Mesa por defecto
INSERT INTO `desks` (`name`, `description`, `color`, `status`) VALUES
('Mesa Principal', 'Mesa de trabajo principal', '#007bff', 'active');

-- Configuraciones del sistema
INSERT INTO `system_settings` (`key`, `value`, `type`, `description`, `is_public`) VALUES
('app_name', 'IATRADE CRM', 'string', 'Nombre de la aplicación', TRUE),
('app_version', '1.0.0', 'string', 'Versión de la aplicación', TRUE),
('default_theme', 'light', 'string', 'Tema por defecto', TRUE),
('timezone', 'UTC', 'string', 'Zona horaria del sistema', FALSE),
('date_format', 'Y-m-d', 'string', 'Formato de fecha', TRUE),
('time_format', 'H:i:s', 'string', 'Formato de hora', TRUE),
('currency', 'USD', 'string', 'Moneda por defecto', TRUE),
('leads_per_page', '25', 'number', 'Leads por página', TRUE),
('auto_assign_leads', 'false', 'boolean', 'Asignación automática de leads', FALSE),
('email_notifications', 'true', 'boolean', 'Notificaciones por email', TRUE);

-- =====================================================
-- VISTAS ÚTILES
-- =====================================================

-- Vista de usuarios con roles
CREATE VIEW `user_roles_view` AS
SELECT 
    u.id,
    u.username,
    u.email,
    u.first_name,
    u.last_name,
    u.status,
    GROUP_CONCAT(r.display_name) as roles,
    u.created_at
FROM users u
LEFT JOIN user_roles ur ON u.id = ur.user_id
LEFT JOIN roles r ON ur.role_id = r.id
GROUP BY u.id;

-- Vista de leads con información completa
CREATE VIEW `leads_complete_view` AS
SELECT 
    l.*,
    CONCAT(l.first_name, ' ', l.last_name) as full_name,
    CONCAT(u.first_name, ' ', u.last_name) as assigned_to_name,
    d.name as desk_name,
    DATEDIFF(NOW(), l.created_at) as days_since_created,
    DATEDIFF(NOW(), l.last_contact_date) as days_since_contact
FROM leads l
LEFT JOIN users u ON l.assigned_to = u.id
LEFT JOIN desks d ON l.desk_id = d.id;

-- =====================================================
-- ÍNDICES ADICIONALES PARA RENDIMIENTO
-- =====================================================

-- Índices compuestos para consultas frecuentes
CREATE INDEX `idx_leads_status_assigned` ON `leads` (`status`, `assigned_to`);
CREATE INDEX `idx_leads_desk_status` ON `leads` (`desk_id`, `status`);
CREATE INDEX `idx_activities_lead_type` ON `lead_activities` (`lead_id`, `type`);
CREATE INDEX `idx_activities_user_date` ON `lead_activities` (`user_id`, `created_at`);

-- =====================================================
-- TRIGGERS PARA AUDITORÍA
-- =====================================================

DELIMITER $$

-- Trigger para auditar cambios en leads
CREATE TRIGGER `leads_audit_update` 
AFTER UPDATE ON `leads`
FOR EACH ROW
BEGIN
    INSERT INTO `audit_logs` (`user_id`, `action`, `table_name`, `record_id`, `old_values`, `new_values`, `created_at`)
    VALUES (
        @current_user_id,
        'UPDATE',
        'leads',
        NEW.id,
        JSON_OBJECT(
            'status', OLD.status,
            'assigned_to', OLD.assigned_to,
            'priority', OLD.priority
        ),
        JSON_OBJECT(
            'status', NEW.status,
            'assigned_to', NEW.assigned_to,
            'priority', NEW.priority
        ),
        NOW()
    );
END$$

DELIMITER ;

-- =====================================================
-- PROCEDIMIENTOS ALMACENADOS ÚTILES
-- =====================================================

DELIMITER $$

-- Procedimiento para obtener estadísticas de leads
CREATE PROCEDURE `GetLeadStats`(IN user_id INT, IN date_from DATE, IN date_to DATE)
BEGIN
    SELECT 
        COUNT(*) as total_leads,
        COUNT(CASE WHEN status = 'new' THEN 1 END) as new_leads,
        COUNT(CASE WHEN status = 'contacted' THEN 1 END) as contacted_leads,
        COUNT(CASE WHEN status = 'qualified' THEN 1 END) as qualified_leads,
        COUNT(CASE WHEN status = 'closed_won' THEN 1 END) as won_leads,
        COUNT(CASE WHEN status = 'closed_lost' THEN 1 END) as lost_leads,
        AVG(score) as avg_score,
        SUM(value) as total_value
    FROM leads 
    WHERE (user_id IS NULL OR assigned_to = user_id)
    AND created_at BETWEEN date_from AND date_to;
END$$

DELIMITER ;

-- =====================================================
-- COMENTARIOS FINALES
-- =====================================================

-- Base de datos creada exitosamente
-- Incluye:
-- - Sistema completo de usuarios, roles y permisos
-- - Gestión de leads con historial y actividades
-- - Mesas de trabajo y asignaciones
-- - Auditoría y logs del sistema
-- - Configuraciones flexibles
-- - Vistas y procedimientos útiles
-- - Índices optimizados para rendimiento

SELECT 'Base de datos IATRADE CRM creada exitosamente' as message;