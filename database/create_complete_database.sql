-- =====================================================
-- IATRADE CRM - SCRIPT COMPLETO DE BASE DE DATOS
-- =====================================================

-- Crear base de datos
CREATE DATABASE IF NOT EXISTS `iatrade_crm` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `iatrade_crm`;

-- =====================================================
-- TABLA: users (Usuarios del sistema)
-- =====================================================
CREATE TABLE IF NOT EXISTS `users` (
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
CREATE TABLE IF NOT EXISTS `roles` (
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
CREATE TABLE IF NOT EXISTS `permissions` (
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
CREATE TABLE IF NOT EXISTS `user_roles` (
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
CREATE TABLE IF NOT EXISTS `role_permissions` (
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
CREATE TABLE IF NOT EXISTS `desks` (
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
CREATE TABLE IF NOT EXISTS `desk_users` (
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
CREATE TABLE IF NOT EXISTS `leads` (
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
CREATE TABLE IF NOT EXISTS `lead_status_history` (
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
CREATE TABLE IF NOT EXISTS `lead_activities` (
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
CREATE TABLE IF NOT EXISTS `lead_notes` (
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
-- TABLA: trading_accounts (Cuentas de trading)
-- =====================================================
CREATE TABLE IF NOT EXISTS `trading_accounts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `account_number` VARCHAR(50) UNIQUE NOT NULL,
    `lead_id` INT,
    `user_id` INT,
    `account_type` ENUM('demo', 'micro', 'standard', 'real', 'vip') NOT NULL,
    `platform` VARCHAR(50) DEFAULT 'MT4',
    `currency` VARCHAR(3) DEFAULT 'USD',
    `balance` DECIMAL(15,2) DEFAULT 0.00,
    `equity` DECIMAL(15,2) DEFAULT 0.00,
    `margin` DECIMAL(15,2) DEFAULT 0.00,
    `free_margin` DECIMAL(15,2) DEFAULT 0.00,
    `leverage` VARCHAR(10) DEFAULT '1:100',
    `status` ENUM('active', 'inactive', 'suspended', 'closed') DEFAULT 'active',
    `server` VARCHAR(100),
    `password` VARCHAR(255),
    `investor_password` VARCHAR(255),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    
    INDEX `idx_account_number` (`account_number`),
    INDEX `idx_lead_id` (`lead_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_account_type` (`account_type`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: transactions (Transacciones financieras)
-- =====================================================
CREATE TABLE IF NOT EXISTS `transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `account_id` INT,
    `user_id` INT,
    `type` ENUM('deposit', 'withdrawal') NOT NULL,
    `method` VARCHAR(50) NOT NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `currency` VARCHAR(3) DEFAULT 'USD',
    `status` ENUM('pending', 'approved', 'processing', 'completed', 'rejected', 'cancelled') DEFAULT 'pending',
    `reference_number` VARCHAR(100) UNIQUE,
    `external_reference` VARCHAR(255),
    `payment_details` JSON,
    `notes` TEXT,
    `processed_by` INT,
    `processed_at` TIMESTAMP NULL,
    `completed_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`account_id`) REFERENCES `trading_accounts`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`processed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    
    INDEX `idx_account_id` (`account_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_type` (`type`),
    INDEX `idx_status` (`status`),
    INDEX `idx_reference` (`reference_number`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: lead_imports (Importaciones de leads)
-- =====================================================
CREATE TABLE IF NOT EXISTS `lead_imports` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `filename` VARCHAR(255) NOT NULL,
    `original_filename` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500),
    `file_size` BIGINT NOT NULL,
    `total_rows` INT NOT NULL,
    `imported_rows` INT DEFAULT 0,
    `failed_rows` INT DEFAULT 0,
    `duplicate_rows` INT DEFAULT 0,
    `status` ENUM('processing', 'completed', 'failed') DEFAULT 'processing',
    `mapping` JSON,
    `settings` JSON,
    `errors` JSON,
    `warnings` JSON,
    `processing_time` TIME,
    `imported_by` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `completed_at` TIMESTAMP NULL,
    
    FOREIGN KEY (`imported_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    
    INDEX `idx_imported_by` (`imported_by`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB;

-- =====================================================
-- INSERTAR DATOS INICIALES
-- =====================================================

-- Roles del sistema
INSERT IGNORE INTO `roles` (`name`, `display_name`, `description`, `color`, `is_system`) VALUES
('super_admin', 'Super Administrador', 'Acceso completo al sistema', '#dc3545', TRUE),
('admin', 'Administrador', 'Administrador del sistema', '#fd7e14', TRUE),
('manager', 'Gerente', 'Gerente de equipo', '#6f42c1', TRUE),
('agent', 'Agente', 'Agente de ventas', '#20c997', TRUE),
('viewer', 'Visualizador', 'Solo lectura', '#6c757d', TRUE);

-- Permisos del sistema
INSERT IGNORE INTO `permissions` (`name`, `display_name`, `description`, `module`, `action`) VALUES
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

-- Trading
('trading.view', 'Ver Trading', 'Ver cuentas de trading', 'trading', 'view'),
('trading.create', 'Crear Cuentas', 'Crear cuentas de trading', 'trading', 'create'),
('trading.edit', 'Editar Cuentas', 'Editar cuentas de trading', 'trading', 'edit'),
('trading.delete', 'Eliminar Cuentas', 'Eliminar cuentas de trading', 'trading', 'delete'),

-- Transacciones
('transactions.view', 'Ver Transacciones', 'Ver depósitos y retiros', 'transactions', 'view'),
('transactions.approve', 'Aprobar Transacciones', 'Aprobar depósitos y retiros', 'transactions', 'approve'),
('transactions.process', 'Procesar Transacciones', 'Procesar transacciones', 'transactions', 'process'),

-- Reportes
('reports.view', 'Ver Reportes', 'Ver reportes del sistema', 'reports', 'view'),
('reports.create', 'Crear Reportes', 'Crear reportes personalizados', 'reports', 'create'),

-- Sistema
('system.settings', 'Configuraciones', 'Acceso a configuraciones del sistema', 'system', 'settings'),
('system.audit', 'Logs de Auditoría', 'Ver logs de auditoría', 'system', 'audit');

-- Asignar permisos a roles
-- Super Admin: todos los permisos
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p WHERE r.name = 'super_admin';

-- Admin: todos excepto algunos del sistema
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p 
WHERE r.name = 'admin' AND p.name NOT IN ('system.audit');

-- Manager: gestión de leads y usuarios
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p 
WHERE r.name = 'manager' AND p.module IN ('leads', 'users', 'desks', 'reports', 'trading', 'transactions');

-- Agent: operaciones básicas de leads
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p 
WHERE r.name = 'agent' AND p.name IN ('leads.view', 'leads.create', 'leads.edit', 'reports.view', 'trading.view');

-- Viewer: solo lectura
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p 
WHERE r.name = 'viewer' AND p.action = 'view';

-- Usuario administrador por defecto
INSERT IGNORE INTO `users` (`username`, `email`, `password_hash`, `first_name`, `last_name`, `status`, `email_verified`) VALUES
('admin', 'admin@iatrade.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'System', 'active', TRUE);

-- Asignar rol de super admin al usuario admin
INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`)
SELECT u.id, r.id FROM `users` u, `roles` r WHERE u.username = 'admin' AND r.name = 'super_admin';

-- Mesa por defecto
INSERT IGNORE INTO `desks` (`name`, `description`, `color`, `status`) VALUES
('Mesa Principal', 'Mesa de trabajo principal', '#007bff', 'active');

-- Leads de ejemplo
INSERT IGNORE INTO `leads` (`first_name`, `last_name`, `email`, `phone`, `country`, `city`, `source`, `status`, `assigned_to`, `desk_id`, `notes`) VALUES
('Juan', 'Pérez', 'juan.perez@email.com', '+34 123 456 789', 'España', 'Madrid', 'Website', 'new', 1, 1, 'Lead interesado en trading de Forex'),
('María', 'González', 'maria.gonzalez@email.com', '+34 987 654 321', 'España', 'Barcelona', 'Facebook', 'contacted', 1, 1, 'Primera llamada realizada, interesada en CFDs');

-- Cuentas de trading de ejemplo
INSERT IGNORE INTO `trading_accounts` (`account_number`, `lead_id`, `user_id`, `account_type`, `platform`, `currency`, `balance`, `leverage`, `status`) VALUES
('MT4-DEMO-001', 1, 1, 'demo', 'MT4', 'USD', 10000.00, '1:100', 'active'),
('MT4-DEMO-002', 2, 1, 'demo', 'MT4', 'EUR', 5000.00, '1:50', 'active');

SELECT 'Base de datos IATRADE CRM creada exitosamente con todos los módulos' as message;