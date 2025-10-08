-- Tabla de usuarios del sistema (empleados)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    phone VARCHAR(20),
    avatar VARCHAR(255),
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    updated_by INT,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

-- Tabla de roles
CREATE TABLE roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    level INT NOT NULL DEFAULT 1, -- Nivel jerárquico
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla de permisos
CREATE TABLE permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    module VARCHAR(50) NOT NULL, -- leads, users, reports, etc.
    action VARCHAR(50) NOT NULL, -- create, read, update, delete, export
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de relación roles-permisos
CREATE TABLE role_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_role_permission (role_id, permission_id)
);

-- Tabla de relación usuarios-roles
CREATE TABLE user_roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id),
    UNIQUE KEY unique_user_role (user_id, role_id)
);

-- Insertar roles básicos
INSERT INTO roles (name, display_name, description, level) VALUES
('super_admin', 'Super Administrador', 'Acceso completo al sistema', 5),
('admin', 'Administrador', 'Administrador del sistema', 4),
('manager', 'Manager', 'Gerente de desk', 3),
('senior_sales', 'Senior Sales', 'Vendedor senior', 2),
('sales', 'Sales', 'Vendedor', 1),
('retention', 'Retention', 'Especialista en retención', 2),
('analyst', 'Analista', 'Analista de datos', 2);

-- Insertar permisos básicos
INSERT INTO permissions (name, display_name, description, module, action) VALUES
-- Leads
('leads.create', 'Crear Leads', 'Crear nuevos leads', 'leads', 'create'),
('leads.read', 'Ver Leads', 'Ver información de leads', 'leads', 'read'),
('leads.update', 'Actualizar Leads', 'Modificar información de leads', 'leads', 'update'),
('leads.delete', 'Eliminar Leads', 'Eliminar leads', 'leads', 'delete'),
('leads.assign', 'Asignar Leads', 'Asignar leads a otros usuarios', 'leads', 'assign'),
('leads.export', 'Exportar Leads', 'Exportar datos de leads', 'leads', 'export'),

-- Usuarios
('users.create', 'Crear Usuarios', 'Crear nuevos usuarios', 'users', 'create'),
('users.read', 'Ver Usuarios', 'Ver información de usuarios', 'users', 'read'),
('users.update', 'Actualizar Usuarios', 'Modificar información de usuarios', 'users', 'update'),
('users.delete', 'Eliminar Usuarios', 'Eliminar usuarios', 'users', 'delete'),

-- Reportes
('reports.view', 'Ver Reportes', 'Acceso a reportes', 'reports', 'read'),
('reports.export', 'Exportar Reportes', 'Exportar reportes', 'reports', 'export'),
('reports.advanced', 'Reportes Avanzados', 'Acceso a reportes avanzados', 'reports', 'advanced'),

-- KPIs
('kpis.view', 'Ver KPIs', 'Ver dashboard de KPIs', 'kpis', 'read'),
('kpis.manage', 'Gestionar KPIs', 'Configurar KPIs', 'kpis', 'manage'),

-- Desks
('desks.create', 'Crear Desks', 'Crear nuevos desks', 'desks', 'create'),
('desks.read', 'Ver Desks', 'Ver información de desks', 'desks', 'read'),
('desks.update', 'Actualizar Desks', 'Modificar desks', 'desks', 'update'),
('desks.delete', 'Eliminar Desks', 'Eliminar desks', 'desks', 'delete');