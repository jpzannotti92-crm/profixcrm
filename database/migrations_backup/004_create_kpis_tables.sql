-- Tabla de métricas diarias por usuario
CREATE TABLE daily_user_metrics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    desk_id INT,
    date DATE NOT NULL,
    
    -- Métricas de leads
    leads_assigned INT DEFAULT 0,
    leads_contacted INT DEFAULT 0,
    leads_converted_demo INT DEFAULT 0,
    leads_converted_ftd INT DEFAULT 0,
    leads_lost INT DEFAULT 0,
    
    -- Métricas de actividad
    calls_made INT DEFAULT 0,
    calls_answered INT DEFAULT 0,
    emails_sent INT DEFAULT 0,
    meetings_scheduled INT DEFAULT 0,
    
    -- Métricas financieras
    ftd_amount DECIMAL(15,2) DEFAULT 0,
    total_deposits DECIMAL(15,2) DEFAULT 0,
    commission_earned DECIMAL(10,2) DEFAULT 0,
    
    -- Métricas de tiempo
    working_hours DECIMAL(4,2) DEFAULT 0,
    talk_time_minutes INT DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (desk_id) REFERENCES desks(id),
    UNIQUE KEY unique_user_date (user_id, date),
    INDEX idx_date (date),
    INDEX idx_user_date (user_id, date)
);

-- Tabla de métricas diarias por desk
CREATE TABLE daily_desk_metrics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    desk_id INT NOT NULL,
    date DATE NOT NULL,
    
    -- Métricas de leads
    total_leads INT DEFAULT 0,
    new_leads INT DEFAULT 0,
    contacted_leads INT DEFAULT 0,
    demo_conversions INT DEFAULT 0,
    ftd_conversions INT DEFAULT 0,
    lost_leads INT DEFAULT 0,
    
    -- Métricas de actividad
    total_calls INT DEFAULT 0,
    answered_calls INT DEFAULT 0,
    total_emails INT DEFAULT 0,
    meetings_scheduled INT DEFAULT 0,
    
    -- Métricas financieras
    total_ftd_amount DECIMAL(15,2) DEFAULT 0,
    total_deposits DECIMAL(15,2) DEFAULT 0,
    average_ftd DECIMAL(10,2) DEFAULT 0,
    
    -- Ratios de conversión
    contact_rate DECIMAL(5,2) DEFAULT 0, -- % de leads contactados
    demo_conversion_rate DECIMAL(5,2) DEFAULT 0, -- % de demos
    ftd_conversion_rate DECIMAL(5,2) DEFAULT 0, -- % de FTD
    call_answer_rate DECIMAL(5,2) DEFAULT 0, -- % de llamadas respondidas
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (desk_id) REFERENCES desks(id),
    UNIQUE KEY unique_desk_date (desk_id, date),
    INDEX idx_date (date)
);

-- Tabla de métricas globales diarias
CREATE TABLE daily_global_metrics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    date DATE NOT NULL UNIQUE,
    
    -- Métricas de leads
    total_leads INT DEFAULT 0,
    new_leads INT DEFAULT 0,
    active_leads INT DEFAULT 0,
    converted_leads INT DEFAULT 0,
    lost_leads INT DEFAULT 0,
    
    -- Métricas por fuente
    google_ads_leads INT DEFAULT 0,
    facebook_leads INT DEFAULT 0,
    organic_leads INT DEFAULT 0,
    referral_leads INT DEFAULT 0,
    other_leads INT DEFAULT 0,
    
    -- Métricas financieras
    total_ftd_amount DECIMAL(15,2) DEFAULT 0,
    total_deposits DECIMAL(15,2) DEFAULT 0,
    total_withdrawals DECIMAL(15,2) DEFAULT 0,
    net_deposits DECIMAL(15,2) DEFAULT 0,
    
    -- Métricas de actividad
    total_calls INT DEFAULT 0,
    total_emails INT DEFAULT 0,
    total_meetings INT DEFAULT 0,
    
    -- Ratios globales
    overall_conversion_rate DECIMAL(5,2) DEFAULT 0,
    average_ftd DECIMAL(10,2) DEFAULT 0,
    cost_per_lead DECIMAL(8,2) DEFAULT 0,
    cost_per_acquisition DECIMAL(10,2) DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla de objetivos y metas
CREATE TABLE targets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    target_type ENUM('user', 'desk', 'global') NOT NULL,
    target_id INT NULL, -- user_id o desk_id según el tipo
    period_type ENUM('daily', 'weekly', 'monthly', 'quarterly', 'yearly') NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    
    -- Objetivos de leads
    leads_target INT DEFAULT 0,
    contacts_target INT DEFAULT 0,
    demos_target INT DEFAULT 0,
    ftd_target INT DEFAULT 0,
    
    -- Objetivos financieros
    revenue_target DECIMAL(15,2) DEFAULT 0,
    deposits_target DECIMAL(15,2) DEFAULT 0,
    
    -- Objetivos de actividad
    calls_target INT DEFAULT 0,
    emails_target INT DEFAULT 0,
    
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT,
    
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_target_type (target_type, target_id),
    INDEX idx_period (period_start, period_end)
);

-- Tabla de alertas y notificaciones
CREATE TABLE alerts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    alert_type ENUM('target_missed', 'low_performance', 'high_performance', 'lead_followup', 'system') NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    severity ENUM('info', 'warning', 'error', 'success') DEFAULT 'info',
    
    -- Destinatarios
    user_id INT NULL, -- Para alertas específicas de usuario
    desk_id INT NULL, -- Para alertas de desk
    role_id INT NULL, -- Para alertas por rol
    
    -- Estado
    is_read BOOLEAN DEFAULT FALSE,
    is_dismissed BOOLEAN DEFAULT FALSE,
    
    -- Datos adicionales
    data JSON, -- Datos adicionales de la alerta
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    dismissed_at TIMESTAMP NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (desk_id) REFERENCES desks(id),
    FOREIGN KEY (role_id) REFERENCES roles(id),
    
    INDEX idx_user_alerts (user_id, is_read),
    INDEX idx_desk_alerts (desk_id, is_read),
    INDEX idx_created_at (created_at)
);

-- Tabla de configuración de KPIs
CREATE TABLE kpi_configurations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    kpi_name VARCHAR(100) NOT NULL,
    display_name VARCHAR(150) NOT NULL,
    description TEXT,
    calculation_formula TEXT, -- Fórmula de cálculo
    target_value DECIMAL(10,2),
    warning_threshold DECIMAL(10,2),
    critical_threshold DECIMAL(10,2),
    unit VARCHAR(20), -- %, $, count, etc.
    category VARCHAR(50), -- leads, financial, activity, etc.
    is_active BOOLEAN DEFAULT TRUE,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insertar configuraciones de KPIs básicos
INSERT INTO kpi_configurations (kpi_name, display_name, description, target_value, unit, category, display_order) VALUES
('conversion_rate', 'Tasa de Conversión', 'Porcentaje de leads que se convierten en FTD', 15.00, '%', 'leads', 1),
('average_ftd', 'FTD Promedio', 'Monto promedio del primer depósito', 250.00, '$', 'financial', 2),
('leads_per_day', 'Leads por Día', 'Número de leads generados diariamente', 50.00, 'count', 'leads', 3),
('call_answer_rate', 'Tasa de Respuesta', 'Porcentaje de llamadas respondidas', 35.00, '%', 'activity', 4),
('demo_conversion', 'Conversión a Demo', 'Porcentaje de leads que abren cuenta demo', 25.00, '%', 'leads', 5),
('cost_per_lead', 'Costo por Lead', 'Costo promedio de adquisición por lead', 50.00, '$', 'financial', 6),
('retention_rate', 'Tasa de Retención', 'Porcentaje de clientes que permanecen activos', 70.00, '%', 'retention', 7),
('lifetime_value', 'Valor de Vida', 'Valor promedio de vida del cliente', 1500.00, '$', 'financial', 8);