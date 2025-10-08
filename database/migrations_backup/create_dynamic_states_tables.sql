-- =====================================================
-- Migración: Módulo de Estados Dinámicos por Desk
-- Fecha: 2024
-- Descripción: Crea las tablas necesarias para el sistema
--              de estados personalizables por desk
-- =====================================================

-- Tabla principal de estados por desk
CREATE TABLE `desk_states` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `desk_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL COMMENT 'Nombre interno del estado (snake_case)',
    `display_name` VARCHAR(150) NOT NULL COMMENT 'Nombre visible para el usuario',
    `description` TEXT COMMENT 'Descripción del estado',
    `color` VARCHAR(7) DEFAULT '#6B7280' COMMENT 'Color hex para la UI',
    `icon` VARCHAR(50) DEFAULT 'tag' COMMENT 'Icono para la UI (Heroicons)',
    `is_initial` BOOLEAN DEFAULT FALSE COMMENT 'Estado inicial para nuevos leads',
    `is_final` BOOLEAN DEFAULT FALSE COMMENT 'Estado final (no permite más cambios)',
    `is_active` BOOLEAN DEFAULT TRUE COMMENT 'Estado activo/inactivo',
    `sort_order` INT DEFAULT 0 COMMENT 'Orden de visualización',
    `created_by` INT COMMENT 'Usuario que creó el estado',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`desk_id`) REFERENCES `desks`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    
    UNIQUE KEY `unique_desk_state` (`desk_id`, `name`),
    INDEX `idx_desk_id` (`desk_id`),
    INDEX `idx_is_active` (`is_active`),
    INDEX `idx_sort_order` (`sort_order`),
    INDEX `idx_is_initial` (`is_initial`)
) ENGINE=InnoDB COMMENT='Estados personalizables por desk';

-- Tabla de transiciones permitidas entre estados
CREATE TABLE `state_transitions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `desk_id` INT NOT NULL,
    `from_state_id` INT COMMENT 'Estado origen (NULL = cualquier estado)',
    `to_state_id` INT NOT NULL COMMENT 'Estado destino',
    `is_automatic` BOOLEAN DEFAULT FALSE COMMENT 'Transición automática',
    `conditions` JSON COMMENT 'Condiciones para transición automática',
    `required_permission` VARCHAR(100) COMMENT 'Permiso requerido para esta transición',
    `notification_template` TEXT COMMENT 'Template de notificación',
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
    INDEX `idx_to_state` (`to_state_id`),
    INDEX `idx_is_automatic` (`is_automatic`)
) ENGINE=InnoDB COMMENT='Transiciones permitidas entre estados';

-- Modificar tabla leads para agregar soporte a estados dinámicos
ALTER TABLE `leads` 
ADD COLUMN `desk_state_id` INT NULL COMMENT 'Estado dinámico del lead' AFTER `status`,
ADD FOREIGN KEY (`desk_state_id`) REFERENCES `desk_states`(`id`) ON DELETE SET NULL,
ADD INDEX `idx_desk_state_id` (`desk_state_id`);

-- Mejorar tabla de historial de estados
DROP TABLE IF EXISTS `lead_state_history`;

CREATE TABLE `lead_state_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `lead_id` INT NOT NULL,
    `old_status` VARCHAR(50) COMMENT 'Estado legacy anterior',
    `new_status` VARCHAR(50) COMMENT 'Estado legacy nuevo',
    `old_desk_state_id` INT COMMENT 'Estado dinámico anterior',
    `new_desk_state_id` INT COMMENT 'Estado dinámico nuevo',
    `transition_id` INT COMMENT 'Transición utilizada',
    `changed_by` INT COMMENT 'Usuario que realizó el cambio',
    `reason` TEXT COMMENT 'Razón del cambio',
    `automatic` BOOLEAN DEFAULT FALSE COMMENT 'Cambio automático',
    `metadata` JSON COMMENT 'Datos adicionales del cambio',
    `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`old_desk_state_id`) REFERENCES `desk_states`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`new_desk_state_id`) REFERENCES `desk_states`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`transition_id`) REFERENCES `state_transitions`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    
    INDEX `idx_lead_id` (`lead_id`),
    INDEX `idx_changed_by` (`changed_by`),
    INDEX `idx_changed_at` (`changed_at`),
    INDEX `idx_new_desk_state` (`new_desk_state_id`),
    INDEX `idx_automatic` (`automatic`)
) ENGINE=InnoDB COMMENT='Historial completo de cambios de estado';

-- Tabla para plantillas de estados predefinidas
CREATE TABLE `state_templates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL COMMENT 'Nombre de la plantilla',
    `description` TEXT COMMENT 'Descripción de la plantilla',
    `industry` VARCHAR(50) COMMENT 'Industria objetivo',
    `template_data` JSON NOT NULL COMMENT 'Configuración de estados y transiciones',
    `is_public` BOOLEAN DEFAULT FALSE COMMENT 'Plantilla pública',
    `created_by` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    
    UNIQUE KEY `unique_template_name` (`name`),
    INDEX `idx_industry` (`industry`),
    INDEX `idx_is_public` (`is_public`)
) ENGINE=InnoDB COMMENT='Plantillas predefinidas de estados';

-- Insertar permisos para el módulo de estados
INSERT INTO `permissions` (`name`, `display_name`, `description`) VALUES
('desk_states.view', 'Ver Estados del Desk', 'Permite ver los estados configurados del desk'),
('desk_states.create', 'Crear Estados', 'Permite crear nuevos estados para el desk'),
('desk_states.edit', 'Editar Estados', 'Permite modificar estados existentes'),
('desk_states.delete', 'Eliminar Estados', 'Permite eliminar estados del desk'),
('state_transitions.manage', 'Gestionar Transiciones', 'Permite configurar transiciones entre estados'),
('lead_states.change', 'Cambiar Estado de Leads', 'Permite cambiar el estado de los leads'),
('lead_states.history', 'Ver Historial de Estados', 'Permite ver el historial de cambios de estado'),
('state_templates.manage', 'Gestionar Plantillas', 'Permite crear y gestionar plantillas de estados');

-- Asignar permisos al rol admin (ID 2)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, `id` FROM `permissions` 
WHERE `name` IN (
    'desk_states.view', 'desk_states.create', 'desk_states.edit', 'desk_states.delete',
    'state_transitions.manage', 'lead_states.change', 'lead_states.history', 'state_templates.manage'
);

-- Crear trigger para mantener sincronización entre status legacy y desk_state_id
DELIMITER $$

CREATE TRIGGER `sync_lead_state_on_update` 
BEFORE UPDATE ON `leads`
FOR EACH ROW
BEGIN
    -- Si se actualiza desk_state_id, sincronizar con status legacy
    IF NEW.desk_state_id != OLD.desk_state_id AND NEW.desk_state_id IS NOT NULL THEN
        -- Obtener el nombre del estado dinámico
        SELECT `name` INTO @state_name FROM `desk_states` WHERE `id` = NEW.desk_state_id;
        
        -- Mapear a estados legacy conocidos
        CASE @state_name
            WHEN 'new' THEN SET NEW.status = 'new';
            WHEN 'contacted' THEN SET NEW.status = 'contacted';
            WHEN 'interested' THEN SET NEW.status = 'interested';
            WHEN 'demo_account' THEN SET NEW.status = 'demo_account';
            WHEN 'no_answer' THEN SET NEW.status = 'no_answer';
            WHEN 'callback' THEN SET NEW.status = 'callback';
            WHEN 'not_interested' THEN SET NEW.status = 'not_interested';
            WHEN 'ftd' THEN SET NEW.status = 'ftd';
            WHEN 'client' THEN SET NEW.status = 'client';
            WHEN 'lost' THEN SET NEW.status = 'lost';
            ELSE SET NEW.status = 'new'; -- Default fallback
        END CASE;
    END IF;
    
    -- Si se actualiza status legacy, intentar sincronizar con desk_state_id
    IF NEW.status != OLD.status AND NEW.desk_state_id IS NULL THEN
        -- Buscar estado dinámico correspondiente para el desk del lead
        SELECT ds.id INTO @dynamic_state_id 
        FROM `desk_states` ds 
        INNER JOIN `leads` l ON l.desk_id = ds.desk_id 
        WHERE l.id = NEW.id AND ds.name = NEW.status AND ds.is_active = 1
        LIMIT 1;
        
        IF @dynamic_state_id IS NOT NULL THEN
            SET NEW.desk_state_id = @dynamic_state_id;
        END IF;
    END IF;
END$$

DELIMITER ;

-- Insertar estados por defecto para cada desk existente
INSERT INTO `desk_states` (`desk_id`, `name`, `display_name`, `description`, `color`, `icon`, `is_initial`, `sort_order`)
SELECT 
    d.id as desk_id,
    'new' as name,
    'Nuevo' as display_name,
    'Lead recién ingresado al sistema' as description,
    '#3B82F6' as color,
    'user-plus' as icon,
    TRUE as is_initial,
    1 as sort_order
FROM `desks` d;

INSERT INTO `desk_states` (`desk_id`, `name`, `display_name`, `description`, `color`, `icon`, `sort_order`)
SELECT 
    d.id as desk_id,
    'contacted' as name,
    'Contactado' as display_name,
    'Se ha establecido contacto inicial' as description,
    '#10B981' as color,
    'phone' as icon,
    2 as sort_order
FROM `desks` d;

INSERT INTO `desk_states` (`desk_id`, `name`, `display_name`, `description`, `color`, `icon`, `sort_order`)
SELECT 
    d.id as desk_id,
    'interested' as name,
    'Interesado' as display_name,
    'Muestra interés en los productos' as description,
    '#F59E0B' as color,
    'star' as icon,
    3 as sort_order
FROM `desks` d;

INSERT INTO `desk_states` (`desk_id`, `name`, `display_name`, `description`, `color`, `icon`, `sort_order`)
SELECT 
    d.id as desk_id,
    'demo_account' as name,
    'Cuenta Demo' as display_name,
    'Ha creado una cuenta de demostración' as description,
    '#8B5CF6' as color,
    'play' as icon,
    4 as sort_order
FROM `desks` d;

INSERT INTO `desk_states` (`desk_id`, `name`, `display_name`, `description`, `color`, `icon`, `sort_order`)
SELECT 
    d.id as desk_id,
    'ftd' as name,
    'Primer Depósito' as display_name,
    'Ha realizado su primer depósito' as description,
    '#059669' as color,
    'currency-dollar' as icon,
    5 as sort_order
FROM `desks` d;

INSERT INTO `desk_states` (`desk_id`, `name`, `display_name`, `description`, `color`, `icon`, `sort_order`)
SELECT 
    d.id as desk_id,
    'client' as name,
    'Cliente' as display_name,
    'Cliente activo del sistema' as description,
    '#DC2626' as color,
    'user-check' as icon,
    6 as sort_order
FROM `desks` d;

INSERT INTO `desk_states` (`desk_id`, `name`, `display_name`, `description`, `color`, `icon`, `sort_order`)
SELECT 
    d.id as desk_id,
    'not_interested' as name,
    'No Interesado' as display_name,
    'No muestra interés en los productos' as description,
    '#6B7280' as color,
    'x-circle' as icon,
    7 as sort_order
FROM `desks` d;

INSERT INTO `desk_states` (`desk_id`, `name`, `display_name`, `description`, `color`, `icon`, `is_final`, `sort_order`)
SELECT 
    d.id as desk_id,
    'lost' as name,
    'Perdido' as display_name,
    'Lead perdido definitivamente' as description,
    '#EF4444' as color,
    'trash' as icon,
    TRUE as is_final,
    8 as sort_order
FROM `desks` d;

-- Crear transiciones básicas para todos los desks
-- Desde cualquier estado inicial se puede ir a contactado
INSERT INTO `state_transitions` (`desk_id`, `from_state_id`, `to_state_id`)
SELECT 
    ds1.desk_id,
    ds1.id as from_state_id,
    ds2.id as to_state_id
FROM `desk_states` ds1
INNER JOIN `desk_states` ds2 ON ds1.desk_id = ds2.desk_id
WHERE ds1.name = 'new' AND ds2.name = 'contacted';

-- Desde contactado se puede ir a interesado o no interesado
INSERT INTO `state_transitions` (`desk_id`, `from_state_id`, `to_state_id`)
SELECT 
    ds1.desk_id,
    ds1.id as from_state_id,
    ds2.id as to_state_id
FROM `desk_states` ds1
INNER JOIN `desk_states` ds2 ON ds1.desk_id = ds2.desk_id
WHERE ds1.name = 'contacted' AND ds2.name IN ('interested', 'not_interested');

-- Desde interesado se puede ir a demo_account o ftd
INSERT INTO `state_transitions` (`desk_id`, `from_state_id`, `to_state_id`)
SELECT 
    ds1.desk_id,
    ds1.id as from_state_id,
    ds2.id as to_state_id
FROM `desk_states` ds1
INNER JOIN `desk_states` ds2 ON ds1.desk_id = ds2.desk_id
WHERE ds1.name = 'interested' AND ds2.name IN ('demo_account', 'ftd');

-- Desde demo_account se puede ir a ftd
INSERT INTO `state_transitions` (`desk_id`, `from_state_id`, `to_state_id`)
SELECT 
    ds1.desk_id,
    ds1.id as from_state_id,
    ds2.id as to_state_id
FROM `desk_states` ds1
INNER JOIN `desk_states` ds2 ON ds1.desk_id = ds2.desk_id
WHERE ds1.name = 'demo_account' AND ds2.name = 'ftd';

-- Desde ftd se puede ir a client
INSERT INTO `state_transitions` (`desk_id`, `from_state_id`, `to_state_id`)
SELECT 
    ds1.desk_id,
    ds1.id as from_state_id,
    ds2.id as to_state_id
FROM `desk_states` ds1
INNER JOIN `desk_states` ds2 ON ds1.desk_id = ds2.desk_id
WHERE ds1.name = 'ftd' AND ds2.name = 'client';

-- Desde cualquier estado se puede ir a lost (excepto desde lost mismo)
INSERT INTO `state_transitions` (`desk_id`, `from_state_id`, `to_state_id`)
SELECT 
    ds1.desk_id,
    ds1.id as from_state_id,
    ds2.id as to_state_id
FROM `desk_states` ds1
INNER JOIN `desk_states` ds2 ON ds1.desk_id = ds2.desk_id
WHERE ds1.name != 'lost' AND ds2.name = 'lost';

-- Migrar leads existentes a estados dinámicos
UPDATE `leads` l
INNER JOIN `desk_states` ds ON l.desk_id = ds.desk_id AND l.status = ds.name
SET l.desk_state_id = ds.id
WHERE l.desk_state_id IS NULL AND ds.is_active = 1;

-- Para leads sin estado dinámico correspondiente, asignar estado inicial
UPDATE `leads` l
INNER JOIN `desk_states` ds ON l.desk_id = ds.desk_id AND ds.is_initial = 1
SET l.desk_state_id = ds.id
WHERE l.desk_state_id IS NULL;

COMMIT;