-- =====================================================
-- ESQUEMA COMPLETO PARA OPERACIONES DE TRADING
-- Sistema iaTrade CRM - Gestión de Trading Avanzada
-- =====================================================

USE `iatrade_crm`;

-- =====================================================
-- TABLA: trading_symbols (Símbolos de trading)
-- =====================================================
CREATE TABLE IF NOT EXISTS `trading_symbols` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `symbol` VARCHAR(20) UNIQUE NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `category` ENUM('forex', 'crypto', 'indices', 'commodities', 'stocks') NOT NULL,
    `base_currency` VARCHAR(3),
    `quote_currency` VARCHAR(3),
    `digits` INT DEFAULT 5,
    `pip_size` DECIMAL(10,8) DEFAULT 0.0001,
    `min_lot` DECIMAL(10,2) DEFAULT 0.01,
    `max_lot` DECIMAL(10,2) DEFAULT 100.00,
    `lot_step` DECIMAL(10,2) DEFAULT 0.01,
    `contract_size` DECIMAL(15,2) DEFAULT 100000,
    `spread_type` ENUM('fixed', 'floating') DEFAULT 'floating',
    `min_spread` DECIMAL(5,2) DEFAULT 0.0,
    `typical_spread` DECIMAL(5,2) DEFAULT 1.0,
    `swap_long` DECIMAL(10,2) DEFAULT 0.0,
    `swap_short` DECIMAL(10,2) DEFAULT 0.0,
    `margin_requirement` DECIMAL(5,2) DEFAULT 1.0,
    `is_active` BOOLEAN DEFAULT TRUE,
    `trading_hours` JSON,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX `idx_symbol` (`symbol`),
    INDEX `idx_category` (`category`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: market_prices (Precios de mercado en tiempo real)
-- =====================================================
CREATE TABLE IF NOT EXISTS `market_prices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `symbol_id` INT NOT NULL,
    `bid` DECIMAL(10,5) NOT NULL,
    `ask` DECIMAL(10,5) NOT NULL,
    `spread` DECIMAL(5,2) GENERATED ALWAYS AS (ask - bid) STORED,
    `high` DECIMAL(10,5),
    `low` DECIMAL(10,5),
    `volume` BIGINT DEFAULT 0,
    `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`symbol_id`) REFERENCES `trading_symbols`(`id`) ON DELETE CASCADE,
    
    INDEX `idx_symbol_timestamp` (`symbol_id`, `timestamp`),
    INDEX `idx_timestamp` (`timestamp`)
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: trading_positions (Posiciones de trading)
-- =====================================================
CREATE TABLE IF NOT EXISTS `trading_positions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `account_id` INT NOT NULL,
    `symbol_id` INT NOT NULL,
    `order_id` INT,
    `ticket` VARCHAR(50) UNIQUE NOT NULL,
    `type` ENUM('buy', 'sell') NOT NULL,
    `volume` DECIMAL(10,2) NOT NULL,
    `open_price` DECIMAL(10,5) NOT NULL,
    `current_price` DECIMAL(10,5),
    `close_price` DECIMAL(10,5),
    `stop_loss` DECIMAL(10,5),
    `take_profit` DECIMAL(10,5),
    `commission` DECIMAL(10,2) DEFAULT 0.00,
    `swap` DECIMAL(10,2) DEFAULT 0.00,
    `profit` DECIMAL(15,2) DEFAULT 0.00,
    `margin_used` DECIMAL(15,2) DEFAULT 0.00,
    `status` ENUM('open', 'closed', 'cancelled') DEFAULT 'open',
    `opened_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `closed_at` TIMESTAMP NULL,
    `close_reason` ENUM('manual', 'stop_loss', 'take_profit', 'margin_call', 'admin') NULL,
    `comment` TEXT,
    `magic_number` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`account_id`) REFERENCES `trading_accounts`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`symbol_id`) REFERENCES `trading_symbols`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`order_id`) REFERENCES `trading_orders`(`id`) ON DELETE SET NULL,
    
    INDEX `idx_account_status` (`account_id`, `status`),
    INDEX `idx_ticket` (`ticket`),
    INDEX `idx_symbol` (`symbol_id`),
    INDEX `idx_opened_at` (`opened_at`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: position_history (Historial de cambios en posiciones)
-- =====================================================
CREATE TABLE IF NOT EXISTS `position_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `position_id` INT NOT NULL,
    `action` ENUM('open', 'modify', 'close', 'partial_close') NOT NULL,
    `old_values` JSON,
    `new_values` JSON,
    `profit_change` DECIMAL(15,2) DEFAULT 0.00,
    `volume_change` DECIMAL(10,2) DEFAULT 0.00,
    `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `user_id` INT,
    `comment` TEXT,
    
    FOREIGN KEY (`position_id`) REFERENCES `trading_positions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    
    INDEX `idx_position_timestamp` (`position_id`, `timestamp`),
    INDEX `idx_action` (`action`)
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: account_equity_history (Historial de equity de cuentas)
-- =====================================================
CREATE TABLE IF NOT EXISTS `account_equity_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `account_id` INT NOT NULL,
    `balance` DECIMAL(15,2) NOT NULL,
    `equity` DECIMAL(15,2) NOT NULL,
    `margin` DECIMAL(15,2) NOT NULL,
    `free_margin` DECIMAL(15,2) NOT NULL,
    `margin_level` DECIMAL(8,2) DEFAULT 0.00,
    `floating_profit` DECIMAL(15,2) DEFAULT 0.00,
    `open_positions` INT DEFAULT 0,
    `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`account_id`) REFERENCES `trading_accounts`(`id`) ON DELETE CASCADE,
    
    INDEX `idx_account_timestamp` (`account_id`, `timestamp`),
    INDEX `idx_timestamp` (`timestamp`)
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: trading_signals (Señales de trading)
-- =====================================================
CREATE TABLE IF NOT EXISTS `trading_signals` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `symbol_id` INT NOT NULL,
    `signal_type` ENUM('buy', 'sell', 'close') NOT NULL,
    `entry_price` DECIMAL(10,5),
    `stop_loss` DECIMAL(10,5),
    `take_profit` DECIMAL(10,5),
    `confidence` DECIMAL(3,2) DEFAULT 0.50,
    `timeframe` ENUM('M1', 'M5', 'M15', 'M30', 'H1', 'H4', 'D1', 'W1', 'MN1') NOT NULL,
    `strategy` VARCHAR(100),
    `description` TEXT,
    `status` ENUM('active', 'triggered', 'expired', 'cancelled') DEFAULT 'active',
    `created_by` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NULL,
    `triggered_at` TIMESTAMP NULL,
    
    FOREIGN KEY (`symbol_id`) REFERENCES `trading_symbols`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    
    INDEX `idx_symbol_status` (`symbol_id`, `status`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: risk_management (Gestión de riesgo)
-- =====================================================
CREATE TABLE IF NOT EXISTS `risk_management` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `account_id` INT NOT NULL,
    `max_risk_per_trade` DECIMAL(5,2) DEFAULT 2.00,
    `max_daily_loss` DECIMAL(15,2) DEFAULT 1000.00,
    `max_drawdown` DECIMAL(5,2) DEFAULT 20.00,
    `max_open_positions` INT DEFAULT 10,
    `max_lot_size` DECIMAL(10,2) DEFAULT 10.00,
    `allowed_symbols` JSON,
    `forbidden_symbols` JSON,
    `trading_hours` JSON,
    `auto_close_on_margin` BOOLEAN DEFAULT TRUE,
    `margin_call_level` DECIMAL(5,2) DEFAULT 100.00,
    `stop_out_level` DECIMAL(5,2) DEFAULT 50.00,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`account_id`) REFERENCES `trading_accounts`(`id`) ON DELETE CASCADE,
    
    UNIQUE KEY `unique_account_risk` (`account_id`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: trading_statistics (Estadísticas de trading)
-- =====================================================
CREATE TABLE IF NOT EXISTS `trading_statistics` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `account_id` INT NOT NULL,
    `period_start` DATE NOT NULL,
    `period_end` DATE NOT NULL,
    `total_trades` INT DEFAULT 0,
    `winning_trades` INT DEFAULT 0,
    `losing_trades` INT DEFAULT 0,
    `win_rate` DECIMAL(5,2) DEFAULT 0.00,
    `total_profit` DECIMAL(15,2) DEFAULT 0.00,
    `total_loss` DECIMAL(15,2) DEFAULT 0.00,
    `net_profit` DECIMAL(15,2) DEFAULT 0.00,
    `gross_profit` DECIMAL(15,2) DEFAULT 0.00,
    `gross_loss` DECIMAL(15,2) DEFAULT 0.00,
    `profit_factor` DECIMAL(8,2) DEFAULT 0.00,
    `average_win` DECIMAL(15,2) DEFAULT 0.00,
    `average_loss` DECIMAL(15,2) DEFAULT 0.00,
    `largest_win` DECIMAL(15,2) DEFAULT 0.00,
    `largest_loss` DECIMAL(15,2) DEFAULT 0.00,
    `max_consecutive_wins` INT DEFAULT 0,
    `max_consecutive_losses` INT DEFAULT 0,
    `max_drawdown` DECIMAL(15,2) DEFAULT 0.00,
    `max_drawdown_percent` DECIMAL(5,2) DEFAULT 0.00,
    `sharpe_ratio` DECIMAL(8,4) DEFAULT 0.00,
    `total_volume` DECIMAL(15,2) DEFAULT 0.00,
    `total_commission` DECIMAL(15,2) DEFAULT 0.00,
    `total_swap` DECIMAL(15,2) DEFAULT 0.00,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`account_id`) REFERENCES `trading_accounts`(`id`) ON DELETE CASCADE,
    
    INDEX `idx_account_period` (`account_id`, `period_start`, `period_end`),
    INDEX `idx_period` (`period_start`, `period_end`)
) ENGINE=InnoDB;

-- =====================================================
-- INSERTAR DATOS DE DEMO PARA TRADING
-- =====================================================

-- Símbolos de trading populares
INSERT INTO `trading_symbols` (`symbol`, `name`, `category`, `base_currency`, `quote_currency`, `digits`, `pip_size`, `typical_spread`) VALUES
('EURUSD', 'Euro vs US Dollar', 'forex', 'EUR', 'USD', 5, 0.00001, 1.2),
('GBPUSD', 'British Pound vs US Dollar', 'forex', 'GBP', 'USD', 5, 0.00001, 1.5),
('USDJPY', 'US Dollar vs Japanese Yen', 'forex', 'USD', 'JPY', 3, 0.001, 1.0),
('USDCHF', 'US Dollar vs Swiss Franc', 'forex', 'USD', 'CHF', 5, 0.00001, 1.3),
('AUDUSD', 'Australian Dollar vs US Dollar', 'forex', 'AUD', 'USD', 5, 0.00001, 1.4),
('USDCAD', 'US Dollar vs Canadian Dollar', 'forex', 'USD', 'CAD', 5, 0.00001, 1.6),
('NZDUSD', 'New Zealand Dollar vs US Dollar', 'forex', 'NZD', 'USD', 5, 0.00001, 1.8),
('EURGBP', 'Euro vs British Pound', 'forex', 'EUR', 'GBP', 5, 0.00001, 1.1),
('EURJPY', 'Euro vs Japanese Yen', 'forex', 'EUR', 'JPY', 3, 0.001, 1.4),
('GBPJPY', 'British Pound vs Japanese Yen', 'forex', 'GBP', 'JPY', 3, 0.001, 2.0),
('XAUUSD', 'Gold vs US Dollar', 'commodities', 'XAU', 'USD', 2, 0.01, 0.5),
('XAGUSD', 'Silver vs US Dollar', 'commodities', 'XAG', 'USD', 3, 0.001, 0.03),
('BTCUSD', 'Bitcoin vs US Dollar', 'crypto', 'BTC', 'USD', 2, 0.01, 50.0),
('ETHUSD', 'Ethereum vs US Dollar', 'crypto', 'ETH', 'USD', 2, 0.01, 5.0),
('US30', 'Dow Jones Industrial Average', 'indices', NULL, 'USD', 1, 0.1, 2.0),
('SPX500', 'S&P 500 Index', 'indices', NULL, 'USD', 1, 0.1, 0.7),
('NAS100', 'NASDAQ 100 Index', 'indices', NULL, 'USD', 1, 0.1, 1.0),
('UK100', 'FTSE 100 Index', 'indices', NULL, 'GBP', 1, 0.1, 1.5),
('GER30', 'DAX 30 Index', 'indices', NULL, 'EUR', 1, 0.1, 1.2),
('USOIL', 'US Crude Oil', 'commodities', 'USO', 'USD', 2, 0.01, 0.05);

-- Precios de mercado actuales (simulados)
INSERT INTO `market_prices` (`symbol_id`, `bid`, `ask`, `high`, `low`, `volume`) VALUES
(1, 1.08450, 1.08462, 1.08520, 1.08380, 125000),
(2, 1.26340, 1.26355, 1.26420, 1.26280, 98000),
(3, 149.825, 149.835, 149.950, 149.720, 87000),
(4, 0.91230, 0.91243, 0.91280, 0.91180, 76000),
(5, 0.67890, 0.67904, 0.67950, 0.67820, 65000),
(6, 1.35670, 1.35684, 1.35720, 1.35620, 54000),
(7, 0.62340, 0.62354, 0.62390, 0.62290, 43000),
(8, 0.86120, 0.86131, 0.86170, 0.86080, 32000),
(9, 162.450, 162.464, 162.520, 162.380, 28000),
(10, 189.230, 189.250, 189.320, 189.150, 21000),
(11, 2025.50, 2026.00, 2028.00, 2023.00, 15000),
(12, 24.850, 24.880, 24.920, 24.800, 8000),
(13, 43250.00, 43300.00, 43500.00, 43000.00, 1200),
(14, 2680.50, 2685.00, 2695.00, 2675.00, 3400),
(15, 37850.5, 37852.5, 37890.0, 37820.0, 890),
(16, 4785.2, 4785.9, 4792.1, 4778.5, 1250),
(17, 16890.3, 16891.0, 16905.2, 16875.8, 980),
(18, 7650.8, 7652.3, 7665.4, 7642.1, 760),
(19, 17250.6, 17251.8, 17268.9, 17235.2, 650),
(20, 78.45, 78.50, 78.85, 78.20, 5600);

-- Posiciones de trading de ejemplo
INSERT INTO `trading_positions` (`account_id`, `symbol_id`, `ticket`, `type`, `volume`, `open_price`, `current_price`, `stop_loss`, `take_profit`, `commission`, `swap`, `profit`, `margin_used`, `status`, `opened_at`) VALUES
(1, 1, 'T001001', 'buy', 1.00, 1.08420, 1.08450, 1.08320, 1.08620, -8.50, -2.30, 30.00, 1084.20, 'open', '2024-01-20 09:15:00'),
(1, 2, 'T001002', 'sell', 0.50, 1.26380, 1.26340, 1.26480, 1.26280, -6.30, 1.20, 20.00, 631.90, 'open', '2024-01-20 10:30:00'),
(2, 3, 'T002001', 'buy', 2.00, 149.800, 149.825, 149.700, 149.900, -15.00, -3.50, 50.00, 2996.00, 'open', '2024-01-20 11:45:00'),
(3, 11, 'T003001', 'buy', 0.10, 2024.00, 2025.50, 2020.00, 2030.00, -2.00, 0.00, 15.00, 202.40, 'open', '2024-01-20 14:20:00'),
(1, 1, 'T001003', 'sell', 0.75, 1.08500, 1.08450, NULL, NULL, -6.40, 0.80, 37.50, 813.75, 'closed', '2024-01-19 15:30:00');

-- Historial de posiciones
INSERT INTO `position_history` (`position_id`, `action`, `old_values`, `new_values`, `profit_change`, `timestamp`) VALUES
(1, 'open', NULL, '{"volume": 1.00, "open_price": 1.08420}', 0.00, '2024-01-20 09:15:00'),
(2, 'open', NULL, '{"volume": 0.50, "open_price": 1.26380}', 0.00, '2024-01-20 10:30:00'),
(3, 'open', NULL, '{"volume": 2.00, "open_price": 149.800}', 0.00, '2024-01-20 11:45:00'),
(5, 'open', NULL, '{"volume": 0.75, "open_price": 1.08500}', 0.00, '2024-01-19 15:30:00'),
(5, 'close', '{"status": "open"}', '{"status": "closed", "close_price": 1.08450}', 37.50, '2024-01-19 16:45:00');

-- Gestión de riesgo por defecto
INSERT INTO `risk_management` (`account_id`, `max_risk_per_trade`, `max_daily_loss`, `max_drawdown`, `max_open_positions`, `max_lot_size`) VALUES
(1, 2.00, 500.00, 15.00, 5, 5.00),
(2, 1.50, 300.00, 10.00, 3, 2.00),
(3, 3.00, 1000.00, 20.00, 10, 10.00),
(4, 5.00, 5000.00, 25.00, 20, 50.00);

-- =====================================================
-- VISTAS PARA CONSULTAS COMPLEJAS
-- =====================================================

-- Vista de posiciones con información completa
CREATE OR REPLACE VIEW `positions_complete_view` AS
SELECT 
    p.*,
    ta.account_number,
    ta.account_type,
    ts.symbol,
    ts.name as symbol_name,
    ts.category as symbol_category,
    CONCAT(l.first_name, ' ', l.last_name) as account_holder,
    mp.bid as current_bid,
    mp.ask as current_ask,
    CASE 
        WHEN p.type = 'buy' THEN (mp.bid - p.open_price) * p.volume * ts.contract_size
        WHEN p.type = 'sell' THEN (p.open_price - mp.ask) * p.volume * ts.contract_size
    END as unrealized_profit,
    DATEDIFF(NOW(), p.opened_at) as days_open,
    TIMESTAMPDIFF(HOUR, p.opened_at, NOW()) as hours_open
FROM trading_positions p
LEFT JOIN trading_accounts ta ON p.account_id = ta.id
LEFT JOIN trading_symbols ts ON p.symbol_id = ts.id
LEFT JOIN leads l ON ta.lead_id = l.id
LEFT JOIN market_prices mp ON ts.id = mp.symbol_id
WHERE mp.timestamp = (
    SELECT MAX(timestamp) 
    FROM market_prices mp2 
    WHERE mp2.symbol_id = ts.id
);

-- Vista de estadísticas de cuenta en tiempo real
CREATE OR REPLACE VIEW `account_realtime_stats` AS
SELECT 
    ta.id as account_id,
    ta.account_number,
    ta.balance,
    ta.equity,
    COALESCE(SUM(CASE WHEN p.status = 'open' THEN p.margin_used END), 0) as used_margin,
    ta.equity - COALESCE(SUM(CASE WHEN p.status = 'open' THEN p.margin_used END), 0) as free_margin,
    CASE 
        WHEN COALESCE(SUM(CASE WHEN p.status = 'open' THEN p.margin_used END), 0) > 0 
        THEN (ta.equity / COALESCE(SUM(CASE WHEN p.status = 'open' THEN p.margin_used END), 1)) * 100
        ELSE 0 
    END as margin_level,
    COUNT(CASE WHEN p.status = 'open' THEN 1 END) as open_positions,
    COALESCE(SUM(CASE WHEN p.status = 'open' THEN p.profit END), 0) as floating_profit
FROM trading_accounts ta
LEFT JOIN trading_positions p ON ta.id = p.account_id
GROUP BY ta.id, ta.account_number, ta.balance, ta.equity;

-- =====================================================
-- PROCEDIMIENTOS ALMACENADOS PARA TRADING
-- =====================================================

DELIMITER $$

-- Procedimiento para calcular estadísticas de trading
CREATE PROCEDURE `CalculateTradingStats`(IN account_id INT, IN start_date DATE, IN end_date DATE)
BEGIN
    DECLARE total_trades INT DEFAULT 0;
    DECLARE winning_trades INT DEFAULT 0;
    DECLARE total_profit DECIMAL(15,2) DEFAULT 0.00;
    DECLARE total_loss DECIMAL(15,2) DEFAULT 0.00;
    
    SELECT 
        COUNT(*) INTO total_trades,
        COUNT(CASE WHEN profit > 0 THEN 1 END) INTO winning_trades,
        SUM(CASE WHEN profit > 0 THEN profit ELSE 0 END) INTO total_profit,
        ABS(SUM(CASE WHEN profit < 0 THEN profit ELSE 0 END)) INTO total_loss
    FROM trading_positions 
    WHERE account_id = account_id 
    AND status = 'closed'
    AND DATE(closed_at) BETWEEN start_date AND end_date;
    
    INSERT INTO trading_statistics (
        account_id, period_start, period_end, total_trades, winning_trades,
        losing_trades, win_rate, total_profit, total_loss, net_profit,
        profit_factor
    ) VALUES (
        account_id, start_date, end_date, total_trades, winning_trades,
        total_trades - winning_trades,
        CASE WHEN total_trades > 0 THEN (winning_trades / total_trades) * 100 ELSE 0 END,
        total_profit, total_loss, total_profit - total_loss,
        CASE WHEN total_loss > 0 THEN total_profit / total_loss ELSE 0 END
    ) ON DUPLICATE KEY UPDATE
        total_trades = VALUES(total_trades),
        winning_trades = VALUES(winning_trades),
        losing_trades = VALUES(losing_trades),
        win_rate = VALUES(win_rate),
        total_profit = VALUES(total_profit),
        total_loss = VALUES(total_loss),
        net_profit = VALUES(net_profit),
        profit_factor = VALUES(profit_factor),
        updated_at = NOW();
END$$

DELIMITER ;

-- =====================================================
-- TRIGGERS PARA AUTOMATIZACIÓN
-- =====================================================

DELIMITER $$

-- Trigger para actualizar profit en tiempo real
CREATE TRIGGER `update_position_profit` 
BEFORE UPDATE ON `trading_positions`
FOR EACH ROW
BEGIN
    IF NEW.current_price IS NOT NULL AND NEW.status = 'open' THEN
        IF NEW.type = 'buy' THEN
            SET NEW.profit = (NEW.current_price - NEW.open_price) * NEW.volume * 
                (SELECT contract_size FROM trading_symbols WHERE id = NEW.symbol_id) - NEW.commission - NEW.swap;
        ELSEIF NEW.type = 'sell' THEN
            SET NEW.profit = (NEW.open_price - NEW.current_price) * NEW.volume * 
                (SELECT contract_size FROM trading_symbols WHERE id = NEW.symbol_id) - NEW.commission - NEW.swap;
        END IF;
    END IF;
END$$

-- Trigger para registrar historial de posiciones
CREATE TRIGGER `position_history_log` 
AFTER UPDATE ON `trading_positions`
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status OR OLD.profit != NEW.profit THEN
        INSERT INTO position_history (
            position_id, action, old_values, new_values, profit_change, timestamp
        ) VALUES (
            NEW.id,
            CASE 
                WHEN OLD.status = 'pending' AND NEW.status = 'open' THEN 'open'
                WHEN OLD.status = 'open' AND NEW.status = 'closed' THEN 'close'
                ELSE 'modify'
            END,
            JSON_OBJECT('status', OLD.status, 'profit', OLD.profit),
            JSON_OBJECT('status', NEW.status, 'profit', NEW.profit),
            NEW.profit - OLD.profit,
            NOW()
        );
    END IF;
END$$

DELIMITER ;

SELECT 'Esquema completo de operaciones de trading creado exitosamente' as message;