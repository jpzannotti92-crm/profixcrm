-- =====================================================
-- TABLA: notifications (Sistema de notificaciones)
-- =====================================================
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    `user_id` INT NULL, -- NULL significa para todos los usuarios
    `actions` JSON NULL, -- Acciones disponibles en formato JSON
    `is_read` BOOLEAN DEFAULT FALSE,
    `read_at` TIMESTAMP NULL,
    `expires_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_type` (`type`),
    INDEX `idx_is_read` (`is_read`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB;

-- =====================================================
-- COMPLETAR ESQUEMA DE BASE DE DATOS IATRADE CRM
-- =====================================================

USE `iatrade_crm`;

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
-- TABLA: webtrader_sessions (Sesiones del WebTrader)
-- =====================================================
CREATE TABLE IF NOT EXISTS `webtrader_sessions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `account_id` INT NOT NULL,
    `user_id` INT,
    `session_token` VARCHAR(255) UNIQUE NOT NULL,
    `ip_address` VARCHAR(45),
    `user_agent` TEXT,
    `started_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `last_activity` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `ended_at` TIMESTAMP NULL,
    `status` ENUM('active', 'expired', 'terminated') DEFAULT 'active',
    
    FOREIGN KEY (`account_id`) REFERENCES `trading_accounts`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    
    INDEX `idx_account_id` (`account_id`),
    INDEX `idx_session_token` (`session_token`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: trading_orders (Órdenes de trading)
-- =====================================================
CREATE TABLE IF NOT EXISTS `trading_orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `account_id` INT NOT NULL,
    `symbol` VARCHAR(20) NOT NULL,
    `order_type` ENUM('market', 'limit', 'stop', 'stop_limit') NOT NULL,
    `side` ENUM('buy', 'sell') NOT NULL,
    `volume` DECIMAL(10,2) NOT NULL,
    `open_price` DECIMAL(10,5),
    `close_price` DECIMAL(10,5),
    `stop_loss` DECIMAL(10,5),
    `take_profit` DECIMAL(10,5),
    `status` ENUM('pending', 'open', 'closed', 'cancelled') DEFAULT 'pending',
    `profit` DECIMAL(15,2) DEFAULT 0.00,
    `commission` DECIMAL(10,2) DEFAULT 0.00,
    `swap` DECIMAL(10,2) DEFAULT 0.00,
    `opened_at` TIMESTAMP NULL,
    `closed_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`account_id`) REFERENCES `trading_accounts`(`id`) ON DELETE CASCADE,
    
    INDEX `idx_account_id` (`account_id`),
    INDEX `idx_symbol` (`symbol`),
    INDEX `idx_status` (`status`),
    INDEX `idx_opened_at` (`opened_at`)
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
-- INSERTAR DATOS DE DEMO PARA TRADING
-- =====================================================

-- Cuentas de trading de demo
INSERT INTO `trading_accounts` (`account_number`, `lead_id`, `user_id`, `account_type`, `platform`, `currency`, `balance`, `leverage`, `status`) VALUES
('MT4-DEMO-001', 1, 2, 'demo', 'MT4', 'USD', 10000.00, '1:100', 'active'),
('MT4-DEMO-002', 2, 3, 'demo', 'MT4', 'EUR', 5000.00, '1:50', 'active'),
('MT4-REAL-001', 3, 2, 'real', 'MT4', 'USD', 25000.00, '1:200', 'active'),
('MT4-VIP-001', 6, 2, 'vip', 'MT4', 'USD', 100000.00, '1:500', 'active');

-- Transacciones de demo
INSERT INTO `transactions` (`account_id`, `user_id`, `type`, `method`, `amount`, `currency`, `status`, `reference_number`, `notes`) VALUES
(3, 2, 'deposit', 'bank_transfer', 25000.00, 'USD', 'completed', 'DEP-2024-001', 'Depósito inicial cuenta real'),
(4, 2, 'deposit', 'credit_card', 100000.00, 'USD', 'completed', 'DEP-2024-002', 'Depósito cuenta VIP'),
(1, 2, 'deposit', 'demo_credit', 10000.00, 'USD', 'completed', 'DEMO-001', 'Crédito cuenta demo'),
(2, 3, 'deposit', 'demo_credit', 5000.00, 'EUR', 'completed', 'DEMO-002', 'Crédito cuenta demo EUR');

-- Órdenes de trading de demo
INSERT INTO `trading_orders` (`account_id`, `symbol`, `order_type`, `side`, `volume`, `open_price`, `status`, `profit`, `opened_at`) VALUES
(3, 'EURUSD', 'market', 'buy', 1.00, 1.0850, 'open', 150.00, '2024-01-20 10:30:00'),
(3, 'GBPUSD', 'market', 'sell', 0.50, 1.2650, 'closed', -75.50, '2024-01-19 14:15:00'),
(4, 'XAUUSD', 'limit', 'buy', 2.00, 2025.50, 'open', 500.00, '2024-01-20 09:45:00'),
(1, 'EURUSD', 'market', 'buy', 0.10, 1.0845, 'open', 5.00, '2024-01-20 15:20:00');

-- =====================================================
-- ACTUALIZAR CONFIGURACIONES DEL SISTEMA
-- =====================================================

-- Configuraciones para trading
INSERT INTO `system_settings` (`key`, `value`, `type`, `description`, `is_public`) VALUES
('trading_enabled', 'true', 'boolean', 'Trading habilitado en el sistema', TRUE),
('webtrader_enabled', 'true', 'boolean', 'WebTrader habilitado', TRUE),
('demo_accounts_enabled', 'true', 'boolean', 'Cuentas demo habilitadas', TRUE),
('max_demo_balance', '50000', 'number', 'Balance máximo para cuentas demo', FALSE),
('default_leverage', '1:100', 'string', 'Apalancamiento por defecto', TRUE),
('supported_platforms', '["MT4", "MT5", "WebTrader"]', 'json', 'Plataformas soportadas', TRUE),
('supported_currencies', '["USD", "EUR", "GBP"]', 'json', 'Monedas soportadas', TRUE),
('min_deposit_amount', '100', 'number', 'Monto mínimo de depósito', TRUE),
('max_withdrawal_amount', '50000', 'number', 'Monto máximo de retiro diario', FALSE),
('transaction_approval_required', 'true', 'boolean', 'Aprobación manual de transacciones', FALSE),
('lead_import_enabled', 'true', 'boolean', 'Importación de leads habilitada', TRUE),
('max_import_file_size', '10485760', 'number', 'Tamaño máximo de archivo de importación (bytes)', FALSE);

-- =====================================================
-- VISTAS ADICIONALES PARA TRADING
-- =====================================================

-- Vista de cuentas con información completa
CREATE OR REPLACE VIEW `trading_accounts_view` AS
SELECT 
    ta.*,
    CONCAT(l.first_name, ' ', l.last_name) as lead_name,
    l.email as lead_email,
    CONCAT(u.first_name, ' ', u.last_name) as user_name,
    (SELECT COUNT(*) FROM trading_orders WHERE account_id = ta.id AND status = 'open') as open_orders,
    (SELECT SUM(amount) FROM transactions WHERE account_id = ta.id AND type = 'deposit' AND status = 'completed') as total_deposits,
    (SELECT SUM(amount) FROM transactions WHERE account_id = ta.id AND type = 'withdrawal' AND status = 'completed') as total_withdrawals
FROM trading_accounts ta
LEFT JOIN leads l ON ta.lead_id = l.id
LEFT JOIN users u ON ta.user_id = u.id;

-- Vista de transacciones con información completa
CREATE OR REPLACE VIEW `transactions_view` AS
SELECT 
    t.*,
    ta.account_number,
    ta.account_type,
    CONCAT(l.first_name, ' ', l.last_name) as account_holder,
    CONCAT(u.first_name, ' ', u.last_name) as user_name,
    CONCAT(p.first_name, ' ', p.last_name) as processed_by_name
FROM transactions t
LEFT JOIN trading_accounts ta ON t.account_id = ta.id
LEFT JOIN leads l ON ta.lead_id = l.id
LEFT JOIN users u ON t.user_id = u.id
LEFT JOIN users p ON t.processed_by = p.id;

-- =====================================================
-- PROCEDIMIENTOS PARA TRADING
-- =====================================================

DELIMITER $$

-- Procedimiento para obtener estadísticas de trading
CREATE PROCEDURE `GetTradingStats`(IN user_id INT, IN date_from DATE, IN date_to DATE)
BEGIN
    SELECT 
        COUNT(DISTINCT ta.id) as total_accounts,
        COUNT(CASE WHEN ta.account_type = 'demo' THEN 1 END) as demo_accounts,
        COUNT(CASE WHEN ta.account_type IN ('real', 'vip') THEN 1 END) as live_accounts,
        COUNT(CASE WHEN ta.status = 'active' THEN 1 END) as active_accounts,
        COALESCE(SUM(ta.balance), 0) as total_balance,
        COUNT(DISTINCT to1.id) as total_orders,
        COUNT(CASE WHEN to1.status = 'open' THEN 1 END) as open_orders,
        COALESCE(SUM(CASE WHEN to1.profit > 0 THEN to1.profit END), 0) as total_profit,
        COALESCE(SUM(CASE WHEN to1.profit < 0 THEN ABS(to1.profit) END), 0) as total_loss
    FROM trading_accounts ta
    LEFT JOIN trading_orders to1 ON ta.id = to1.account_id
    WHERE (user_id IS NULL OR ta.user_id = user_id)
    AND ta.created_at BETWEEN date_from AND date_to;
END$$

DELIMITER ;

-- =====================================================
-- ÍNDICES ADICIONALES PARA RENDIMIENTO
-- =====================================================

-- Índices para trading_accounts
CREATE INDEX `idx_accounts_type_status` ON `trading_accounts` (`account_type`, `status`);
CREATE INDEX `idx_accounts_created` ON `trading_accounts` (`created_at`);

-- Índices para transactions
CREATE INDEX `idx_transactions_type_status` ON `transactions` (`type`, `status`);
CREATE INDEX `idx_transactions_amount` ON `transactions` (`amount`);

-- Índices para trading_orders
CREATE INDEX `idx_orders_symbol_status` ON `trading_orders` (`symbol`, `status`);
CREATE INDEX `idx_orders_profit` ON `trading_orders` (`profit`);

SELECT 'Esquema completo de base de datos actualizado exitosamente' as message;