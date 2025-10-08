-- Migración para mejorar la tabla lead_activities existente
-- Agregando campos adicionales para metadatos y eventos automáticos

ALTER TABLE lead_activities 
ADD COLUMN metadata JSON DEFAULT NULL COMMENT 'Metadatos adicionales para eventos automáticos (asignaciones, etc.)',
ADD COLUMN is_system_generated BOOLEAN DEFAULT FALSE COMMENT 'Indica si la actividad fue generada automáticamente por el sistema',
ADD COLUMN priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium' COMMENT 'Prioridad de la actividad',
ADD COLUMN visibility ENUM('public', 'private', 'team') DEFAULT 'public' COMMENT 'Visibilidad de la actividad';

-- Agregar índices para mejorar el rendimiento
CREATE INDEX idx_lead_activities_type_status ON lead_activities(type, status);
CREATE INDEX idx_lead_activities_scheduled_at ON lead_activities(scheduled_at);
CREATE INDEX idx_lead_activities_is_system ON lead_activities(is_system_generated);
CREATE INDEX idx_lead_activities_priority ON lead_activities(priority);

-- Insertar permisos relacionados con actividades de leads si no existen
INSERT IGNORE INTO permissions (name, description, created_at, updated_at) VALUES
('view_lead_activities', 'Ver actividades de leads', NOW(), NOW()),
('create_lead_activities', 'Crear actividades de leads', NOW(), NOW()),
('edit_lead_activities', 'Editar actividades de leads', NOW(), NOW()),
('delete_lead_activities', 'Eliminar actividades de leads', NOW(), NOW()),
('view_all_lead_activities', 'Ver todas las actividades de leads (incluso privadas)', NOW(), NOW());

-- Asignar permisos básicos al rol de administrador (asumiendo que existe un rol con id=1)
INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at, updated_at)
SELECT 1, p.id, NOW(), NOW()
FROM permissions p 
WHERE p.name IN ('view_lead_activities', 'create_lead_activities', 'edit_lead_activities', 'delete_lead_activities', 'view_all_lead_activities');