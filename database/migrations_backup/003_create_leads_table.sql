-- Tabla principal de leads
CREATE TABLE leads (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    -- Información básica
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    country_code VARCHAR(3),
    country VARCHAR(50),
    city VARCHAR(50),
    timezone VARCHAR(50),
    language VARCHAR(10) DEFAULT 'en',
    
    -- Información de trading
    trading_experience ENUM('none', 'beginner', 'intermediate', 'advanced', 'professional') DEFAULT 'none',
    capital_range ENUM('under_1k', '1k_5k', '5k_10k', '10k_25k', '25k_50k', '50k_100k', 'over_100k'),
    preferred_instruments SET('forex', 'indices', 'commodities', 'crypto', 'stocks'),
    risk_tolerance ENUM('low', 'medium', 'high'),
    investment_goals TEXT,
    
    -- Estado y asignación
    status ENUM('new', 'contacted', 'interested', 'demo_account', 'no_answer', 'callback', 'not_interested', 'ftd', 'client', 'lost') DEFAULT 'new',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    assigned_to INT NULL,
    desk_id INT NULL,
    
    -- Fuente y tracking
    source VARCHAR(50), -- google_ads, facebook, organic, referral, etc.
    campaign VARCHAR(100),
    utm_source VARCHAR(100),
    utm_medium VARCHAR(100),
    utm_campaign VARCHAR(100),
    utm_content VARCHAR(100),
    utm_term VARCHAR(100),
    referrer_url TEXT,
    landing_page TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    
    -- Fechas importantes
    first_contact_date TIMESTAMP NULL,
    last_contact_date TIMESTAMP NULL,
    next_followup_date TIMESTAMP NULL,
    demo_date TIMESTAMP NULL,
    ftd_date TIMESTAMP NULL, -- First Time Deposit
    
    -- Métricas financieras
    ftd_amount DECIMAL(10,2) DEFAULT 0,
    total_deposits DECIMAL(15,2) DEFAULT 0,
    total_withdrawals DECIMAL(15,2) DEFAULT 0,
    current_balance DECIMAL(15,2) DEFAULT 0,
    lifetime_value DECIMAL(15,2) DEFAULT 0,
    
    -- Información adicional
    notes TEXT,
    tags JSON, -- Para etiquetas dinámicas
    custom_fields JSON, -- Para campos personalizados
    
    -- Auditoría
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    updated_by INT,
    
    -- Índices y relaciones
    FOREIGN KEY (assigned_to) REFERENCES users(id),
    FOREIGN KEY (desk_id) REFERENCES desks(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id),
    
    INDEX idx_status (status),
    INDEX idx_assigned_to (assigned_to),
    INDEX idx_desk_id (desk_id),
    INDEX idx_source (source),
    INDEX idx_created_at (created_at),
    INDEX idx_email (email),
    INDEX idx_phone (phone),
    INDEX idx_priority (priority),
    INDEX idx_next_followup (next_followup_date)
);

-- Tabla de historial de cambios de estado
CREATE TABLE lead_status_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    lead_id INT NOT NULL,
    old_status ENUM('new', 'contacted', 'interested', 'demo_account', 'no_answer', 'callback', 'not_interested', 'ftd', 'client', 'lost'),
    new_status ENUM('new', 'contacted', 'interested', 'demo_account', 'no_answer', 'callback', 'not_interested', 'ftd', 'client', 'lost'),
    changed_by INT NOT NULL,
    reason TEXT,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id)
);

-- Tabla de actividades/interacciones
CREATE TABLE lead_activities (
    id INT PRIMARY KEY AUTO_INCREMENT,
    lead_id INT NOT NULL,
    user_id INT NOT NULL,
    activity_type ENUM('call', 'email', 'sms', 'meeting', 'demo', 'deposit', 'withdrawal', 'note', 'task') NOT NULL,
    subject VARCHAR(200),
    description TEXT,
    outcome ENUM('positive', 'neutral', 'negative'),
    duration_minutes INT, -- Para llamadas y reuniones
    scheduled_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    status ENUM('pending', 'completed', 'cancelled') DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_lead_activity (lead_id, created_at),
    INDEX idx_user_activity (user_id, created_at),
    INDEX idx_activity_type (activity_type)
);

-- Tabla de documentos del lead
CREATE TABLE lead_documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    lead_id INT NOT NULL,
    document_type ENUM('id', 'proof_of_address', 'bank_statement', 'contract', 'other') NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT,
    mime_type VARCHAR(100),
    uploaded_by INT NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verified BOOLEAN DEFAULT FALSE,
    verified_by INT NULL,
    verified_at TIMESTAMP NULL,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id),
    FOREIGN KEY (verified_by) REFERENCES users(id)
);

-- Tabla de asignaciones de leads
CREATE TABLE lead_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    lead_id INT NOT NULL,
    assigned_from INT NULL,
    assigned_to INT NOT NULL,
    desk_id INT NULL,
    reason TEXT,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT NOT NULL,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_from) REFERENCES users(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id),
    FOREIGN KEY (desk_id) REFERENCES desks(id),
    FOREIGN KEY (assigned_by) REFERENCES users(id)
);

-- Tabla de campañas
CREATE TABLE campaigns (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    source VARCHAR(50) NOT NULL,
    medium VARCHAR(50),
    budget DECIMAL(10,2),
    start_date DATE,
    end_date DATE,
    status ENUM('active', 'paused', 'completed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Tabla de configuración de auto-asignación
CREATE TABLE auto_assignment_rules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    conditions JSON, -- Condiciones para la asignación
    desk_id INT,
    user_id INT,
    priority INT DEFAULT 1,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (desk_id) REFERENCES desks(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);