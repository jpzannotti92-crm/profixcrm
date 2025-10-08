-- =====================================================
-- MIGRACIÓN: Crear tabla lead_activities
-- Descripción: Tabla para almacenar actividades, comentarios y eventos de leads
-- =====================================================

CREATE TABLE IF NOT EXISTS `lead_activities` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `lead_id` INT NOT NULL,
    `user_id` INT,
    `type` ENUM('comment', 'assignment', 'status_change', 'call', 'email', 'meeting', 'note', 'system') NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `metadata` JSON,
    `is_system_generated` BOOLEAN DEFAULT FALSE,
    `is_private` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    
    INDEX `idx_lead_id` (`lead_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_type` (`type`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_system_generated` (`is_system_generated`)
) ENGINE=InnoDB;

-- Insertar permisos para el módulo de actividades
INSERT IGNORE INTO `permissions` (`name`, `display_name`, `description`, `module`, `action`) VALUES
('view_lead_activities', 'Ver Actividades de Leads', 'Permite ver las actividades y comentarios de los leads', 'leads', 'view_activities'),
('create_lead_activities', 'Crear Actividades de Leads', 'Permite crear comentarios y actividades en los leads', 'leads', 'create_activities'),
('edit_lead_activities', 'Editar Actividades de Leads', 'Permite editar comentarios y actividades de los leads', 'leads', 'edit_activities'),
('delete_lead_activities', 'Eliminar Actividades de Leads', 'Permite eliminar comentarios y actividades de los leads', 'leads', 'delete_activities');