-- =====================================================
-- Migración Simplificada: Módulo de Estados Dinámicos
-- =====================================================

-- Tabla principal de estados por desk
CREATE TABLE IF NOT EXISTS `desk_states` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `desk_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL COMMENT 'Nombre interno del estado',
    `display_name` VARCHAR(150) NOT NULL COMMENT 'Nombre visible',
    `description` TEXT COMMENT 'Descripción del estado',
    `color` VARCHAR(7) DEFAULT '#6B7280' COMMENT 'Color hex',
    `icon` VARCHAR(50) DEFAULT 'tag' COMMENT 'Icono',
    `is_initial` BOOLEAN DEFAULT FALSE COMMENT 'Estado inicial',
    `is_final` BOOLEAN DEFAULT FALSE COMMENT 'Estado final',
    `is_active` BOOLEAN DEFAULT TRUE COMMENT 'Estado activo',
    `sort_order` INT DEFAULT 0 COMMENT 'Orden',
    `created_by` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`desk_id`) REFERENCES `desks`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    
    UNIQUE KEY `unique_desk_state` (`desk_id`, `name`),
    INDEX `idx_desk_id` (`desk_id`),
    INDEX `idx_is_active` (`is_active`),
    INDEX `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB;

-- Tabla de transiciones
CREATE TABLE IF NOT EXISTS `state_transitions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `desk_id` INT NOT NULL,
    `from_state_id` INT,
    `to_state_id` INT NOT NULL,
    `is_automatic` BOOLEAN DEFAULT FALSE,
    `conditions` JSON,
    `required_permission` VARCHAR(100),
    `notification_template` TEXT,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_by` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`desk_id`) REFERENCES `desks`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`from_state_id`) REFERENCES `desk_states`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`to_state_id`) REFERENCES `desk_states`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    
    UNIQUE KEY `unique_transition` (`desk_id`, `from_state_id`, `to_state_id`),
    INDEX `idx_desk_id` (`desk_id`),
    INDEX `idx_from_state` (`from_state_id`),
    INDEX `idx_to_state` (`to_state_id`)
) ENGINE=InnoDB;

-- Tabla de plantillas
CREATE TABLE IF NOT EXISTS `state_templates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `industry` VARCHAR(50),
    `template_data` JSON NOT NULL,
    `is_public` BOOLEAN DEFAULT FALSE,
    `created_by` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    
    UNIQUE KEY `unique_template_name` (`name`),
    INDEX `idx_industry` (`industry`)
) ENGINE=InnoDB;

-- Agregar columna a leads si no existe
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name = 'leads' 
     AND table_schema = 'iatrade_crm' 
     AND column_name = 'desk_state_id') = 0,
    'ALTER TABLE `leads` ADD COLUMN `desk_state_id` INT NULL AFTER `status`, ADD INDEX `idx_desk_state_id` (`desk_state_id`)',
    'SELECT "Column already exists"'
));

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Recrear tabla de historial mejorada
DROP TABLE IF EXISTS `lead_state_history`;

CREATE TABLE `lead_state_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `lead_id` INT NOT NULL,
    `old_status` VARCHAR(50),
    `new_status` VARCHAR(50),
    `old_desk_state_id` INT,
    `new_desk_state_id` INT,
    `transition_id` INT,
    `changed_by` INT,
    `reason` TEXT,
    `automatic` BOOLEAN DEFAULT FALSE,
    `metadata` JSON,
    `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`old_desk_state_id`) REFERENCES `desk_states`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`new_desk_state_id`) REFERENCES `desk_states`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`transition_id`) REFERENCES `state_transitions`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    
    INDEX `idx_lead_id` (`lead_id`),
    INDEX `idx_changed_by` (`changed_by`),
    INDEX `idx_changed_at` (`changed_at`)
) ENGINE=InnoDB;