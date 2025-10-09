<?php
/**
 * 🗄️ PROFIXCRM - INSTALADOR DE BASE DE DATOS
 * Clase completa para instalación y configuración de base de datos
 * 
 * @version 1.0.0
 * @author ProfixCRM Team
 */

class DatabaseInstaller {
    private $logger;
    private $connection = null;
    private $config = [];
    private $results = [];
    
    // Configuración por defecto
    private $defaultConfig = [
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'engine' => 'InnoDB',
        'timeout' => 30,
        'retry_attempts' => 3,
        'create_database' => true,
        'create_user' => false,
        'backup_existing' => true,
        'test_data' => false
    ];
    
    // Estructura de la base de datos
    private $databaseSchema = [
        'users' => [
            'sql' => "CREATE TABLE IF NOT EXISTS `users` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `username` varchar(50) NOT NULL UNIQUE,
                `email` varchar(100) NOT NULL UNIQUE,
                `password` varchar(255) NOT NULL,
                `first_name` varchar(50) DEFAULT NULL,
                `last_name` varchar(50) DEFAULT NULL,
                `role` enum('admin','user','manager') DEFAULT 'user',
                `status` enum('active','inactive','suspended') DEFAULT 'active',
                `last_login` datetime DEFAULT NULL,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_username` (`username`),
                KEY `idx_email` (`email`),
                KEY `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'Tabla de usuarios del sistema'
        ],
        'leads' => [
            'sql' => "CREATE TABLE IF NOT EXISTS `leads` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(100) NOT NULL,
                `email` varchar(100) DEFAULT NULL,
                `phone` varchar(20) DEFAULT NULL,
                `company` varchar(100) DEFAULT NULL,
                `source` varchar(50) DEFAULT NULL,
                `status` enum('new','contacted','qualified','proposal','won','lost') DEFAULT 'new',
                `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
                'assigned_to' int(11) DEFAULT NULL,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_status` (`status`),
                KEY `idx_priority` (`priority`),
                KEY `idx_assigned_to` (`assigned_to`),
                FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'Tabla de leads/prospectos'
        ],
        'customers' => [
            'sql' => "CREATE TABLE IF NOT EXISTS `customers` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `lead_id` int(11) DEFAULT NULL,
                `name` varchar(100) NOT NULL,
                `email` varchar(100) DEFAULT NULL,
                `phone` varchar(20) DEFAULT NULL,
                `company` varchar(100) DEFAULT NULL,
                `address` text DEFAULT NULL,
                `city` varchar(50) DEFAULT NULL,
                `state` varchar(50) DEFAULT NULL,
                `country` varchar(50) DEFAULT NULL,
                `postal_code` varchar(20) DEFAULT NULL,
                `tax_id` varchar(50) DEFAULT NULL,
                `status` enum('active','inactive','suspended') DEFAULT 'active',
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_lead_id` (`lead_id`),
                KEY `idx_status` (`status`),
                FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'Tabla de clientes'
        ],
        'products' => [
            'sql' => "CREATE TABLE IF NOT EXISTS `products` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `sku` varchar(50) NOT NULL UNIQUE,
                `name` varchar(200) NOT NULL,
                `description` text DEFAULT NULL,
                `price` decimal(10,2) NOT NULL DEFAULT 0.00,
                `cost` decimal(10,2) DEFAULT 0.00,
                `stock` int(11) DEFAULT 0,
                `category` varchar(100) DEFAULT NULL,
                `status` enum('active','inactive','discontinued') DEFAULT 'active',
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_sku` (`sku`),
                KEY `idx_category` (`category`),
                KEY `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'Tabla de productos'
        ],
        'invoices' => [
            'sql' => "CREATE TABLE IF NOT EXISTS `invoices` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `invoice_number` varchar(50) NOT NULL UNIQUE,
                `customer_id` int(11) NOT NULL,
                `date` date NOT NULL,
                `due_date` date DEFAULT NULL,
                `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
                `tax` decimal(10,2) DEFAULT 0.00,
                `total` decimal(10,2) NOT NULL DEFAULT 0.00,
                `status` enum('draft','sent','paid','overdue','cancelled') DEFAULT 'draft',
                `notes` text DEFAULT NULL,
                `created_by` int(11) DEFAULT NULL,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_invoice_number` (`invoice_number`),
                KEY `idx_customer_id` (`customer_id`),
                KEY `idx_status` (`status`),
                KEY `idx_date` (`date`),
                FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'Tabla de facturas'
        ],
        'invoice_items' => [
            'sql' => "CREATE TABLE IF NOT EXISTS `invoice_items` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `invoice_id` int(11) NOT NULL,
                `product_id` int(11) DEFAULT NULL,
                `description` text NOT NULL,
                `quantity` int(11) NOT NULL DEFAULT 1,
                `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
                `total_price` decimal(10,2) NOT NULL DEFAULT 0.00,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_invoice_id` (`invoice_id`),
                KEY `idx_product_id` (`product_id`),
                FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'Items de facturas'
        ],
        'tasks' => [
            'sql' => "CREATE TABLE IF NOT EXISTS `tasks` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `title` varchar(200) NOT NULL,
                `description` text DEFAULT NULL,
                `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
                `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
                'assigned_to' int(11) DEFAULT NULL,
                'related_to' enum('lead','customer','invoice','general') DEFAULT 'general',
                'related_id' int(11) DEFAULT NULL,
                `due_date` date DEFAULT NULL,
                `completed_at` datetime DEFAULT NULL,
                `created_by` int(11) DEFAULT NULL,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_assigned_to` (`assigned_to`),
                KEY `idx_status` (`status`),
                KEY `idx_priority` (`priority`),
                KEY `idx_due_date` (`due_date`),
                FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL,
                FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'Tabla de tareas'
        ],
        'api_tokens' => [
            'sql' => "CREATE TABLE IF NOT EXISTS `api_tokens` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `user_id` int(11) NOT NULL,
                `token` varchar(255) NOT NULL UNIQUE,
                `name` varchar(100) DEFAULT NULL,
                `last_used` datetime DEFAULT NULL,
                `expires_at` datetime DEFAULT NULL,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_token` (`token`),
                KEY `idx_user_id` (`user_id`),
                KEY `idx_expires_at` (`expires_at`),
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'Tokens de API'
        ],
        'settings' => [
            'sql' => "CREATE TABLE IF NOT EXISTS `settings` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `key` varchar(100) NOT NULL UNIQUE,
                `value` text DEFAULT NULL,
                `type` enum('string','integer','boolean','json') DEFAULT 'string',
                `description` text DEFAULT NULL,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_key` (`key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'Configuraciones del sistema'
        ],
        'activity_log' => [
            'sql' => "CREATE TABLE IF NOT EXISTS `activity_log` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `user_id` int(11) DEFAULT NULL,
                `action` varchar(100) NOT NULL,
                `entity_type` varchar(50) DEFAULT NULL,
                `entity_id` int(11) DEFAULT NULL,
                `old_values` text DEFAULT NULL,
                `new_values` text DEFAULT NULL,
                `ip_address` varchar(45) DEFAULT NULL,
                `user_agent` text DEFAULT NULL,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_user_id` (`user_id`),
                KEY `idx_action` (`action`),
                KEY `idx_entity` (`entity_type`,`entity_id`),
                KEY `idx_created_at` (`created_at`),
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            'description' => 'Registro de actividad'
        ]
    ];
    
    public function __construct($logger) {
        $this->logger = $logger;
    }
    
    /**
     * Instalar base de datos completa
     */
    public function installDatabase($config) {
        $this->logger->info('Iniciando instalación de base de datos...');
        
        $this->config = array_merge($this->defaultConfig, $config);
        $this->results = [
            'success' => false,
            'database_created' => false,
            'tables_created' => [],
            'data_inserted' => false,
            'errors' => [],
            'warnings' => [],
            'connection_info' => []
        ];
        
        try {
            // Fase 1: Conectar al servidor MySQL
            if (!$this->connectToServer()) {
                return $this->results;
            }
            
            // Fase 2: Crear base de datos (si es necesario)
            if ($this->config['create_database']) {
                if (!$this->createDatabase()) {
                    return $this->results;
                }
            }
            
            // Fase 3: Conectar a la base de datos específica
            if (!$this->connectToDatabase()) {
                return $this->results;
            }
            
            // Fase 4: Crear tablas
            if (!$this->createTables()) {
                return $this->results;
            }
            
            // Fase 5: Crear índices adicionales
            if (!$this->createIndexes()) {
                return $this->results;
            }
            
            // Fase 6: Insertar datos iniciales
            if (!$this->insertInitialData()) {
                return $this->results;
            }
            
            // Fase 7: Crear procedimientos almacenados (si es necesario)
            if (!$this->createStoredProcedures()) {
                return $this->results;
            }
            
            // Fase 8: Verificar integridad
            if (!$this->verifyDatabaseIntegrity()) {
                return $this->results;
            }
            
            // Fase 9: Crear backups y configurar seguridad
            if (!$this->setupDatabaseSecurity()) {
                return $this->results;
            }
            
            // Fase 10: Probar conexión final
            if (!$this->testFinalConnection()) {
                return $this->results;
            }
            
            $this->results['success'] = true;
            $this->logger->info('Instalación de base de datos completada exitosamente');
            
            return $this->results;
            
        } catch (Exception $e) {
            $this->logger->error('Error en instalación de base de datos: ' . $e->getMessage());
            $this->results['errors'][] = $e->getMessage();
            return $this->results;
        }
    }
    
    /**
     * Conectar al servidor MySQL
     */
    private function connectToServer() {
        $this->logger->info('Conectando al servidor MySQL...');
        
        $host = $this->config['host'] ?? 'localhost';
        $port = $this->config['port'] ?? 3306;
        $username = $this->config['username'];
        $password = $this->config['password'];
        
        try {
            $dsn = "mysql:host=$host;port=$port;charset={$this->config['charset']}";
            $this->connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->config['charset']} COLLATE {$this->config['collation']}"
            ]);
            
            // Verificar versión de MySQL
            $version = $this->connection->getAttribute(PDO::ATTR_SERVER_VERSION);
            $this->results['connection_info']['mysql_version'] = $version;
            
            if (version_compare($version, '5.7.0', '<')) {
                $this->results['warnings'][] = "Versión de MySQL ($version) es antigua, se recomienda 5.7+";
            }
            
            $this->logger->info("Conectado exitosamente a MySQL versión $version");
            return true;
            
        } catch (PDOException $e) {
            $this->results['errors'][] = "Error al conectar al servidor MySQL: " . $e->getMessage();
            $this->logger->error("Error de conexión MySQL: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Crear base de datos
     */
    private function createDatabase() {
        $databaseName = $this->config['database'];
        $this->logger->info("Creando base de datos: $databaseName");
        
        try {
            // Verificar si la base de datos ya existe
            $stmt = $this->connection->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
            $stmt->execute([$databaseName]);
            
            if ($stmt->fetch()) {
                if ($this->config['backup_existing']) {
                    $this->logger->info("Base de datos existe, creando backup...");
                    if (!$this->backupDatabase($databaseName)) {
                        return false;
                    }
                }
                
                // Eliminar base de datos existente
                $this->logger->info("Eliminando base de datos existente...");
                $this->connection->exec("DROP DATABASE IF EXISTS `$databaseName`");
            }
            
            // Crear nueva base de datos
            $createSql = "CREATE DATABASE `$databaseName` 
                         CHARACTER SET {$this->config['charset']} 
                         COLLATE {$this->config['collation']}";
            
            $this->connection->exec($createSql);
            $this->results['database_created'] = true;
            $this->logger->info("Base de datos $databaseName creada exitosamente");
            
            return true;
            
        } catch (PDOException $e) {
            $this->results['errors'][] = "Error al crear base de datos: " . $e->getMessage();
            $this->logger->error("Error creando base de datos: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Conectar a la base de datos específica
     */
    private function connectToDatabase() {
        $databaseName = $this->config['database'];
        $this->logger->info("Conectando a la base de datos: $databaseName");
        
        try {
            $host = $this->config['host'] ?? 'localhost';
            $port = $this->config['port'] ?? 3306;
            $username = $this->config['username'];
            $password = $this->config['password'];
            
            $dsn = "mysql:host=$host;port=$port;dbname=$databaseName;charset={$this->config['charset']}";
            $this->connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->config['charset']} COLLATE {$this->config['collation']}"
            ]);
            
            $this->logger->info("Conectado exitosamente a la base de datos $databaseName");
            return true;
            
        } catch (PDOException $e) {
            $this->results['errors'][] = "Error al conectar a la base de datos: " . $e->getMessage();
            $this->logger->error("Error conectando a base de datos: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Crear tablas
     */
    private function createTables() {
        $this->logger->info('Creando tablas de la base de datos...');
        
        try {
            // Deshabilitar foreign key checks temporalmente
            $this->connection->exec("SET FOREIGN_KEY_CHECKS = 0");
            
            foreach ($this->databaseSchema as $tableName => $tableData) {
                $this->logger->info("Creando tabla: $tableName");
                
                try {
                    $this->connection->exec($tableData['sql']);
                    $this->results['tables_created'][$tableName] = true;
                    $this->logger->info("Tabla $tableName creada exitosamente");
                    
                } catch (PDOException $e) {
                    $this->results['tables_created'][$tableName] = false;
                    $this->results['errors'][] = "Error al crear tabla $tableName: " . $e->getMessage();
                    $this->logger->error("Error creando tabla $tableName: " . $e->getMessage());
                    
                    // Continuar con las demás tablas
                    continue;
                }
            }
            
            // Habilitar foreign key checks
            $this->connection->exec("SET FOREIGN_KEY_CHECKS = 1");
            
            $this->logger->info('Tablas creadas exitosamente');
            return true;
            
        } catch (PDOException $e) {
            $this->results['errors'][] = "Error general creando tablas: " . $e->getMessage();
            $this->logger->error("Error creando tablas: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Crear índices adicionales
     */
    private function createIndexes() {
        $this->logger->info('Creando índices adicionales...');
        
        $additionalIndexes = [
            "CREATE INDEX idx_leads_created ON leads(created_at)",
            "CREATE INDEX idx_customers_company ON customers(company)",
            "CREATE INDEX idx_invoices_total ON invoices(total)",
            "CREATE INDEX idx_products_price ON products(price)",
            "CREATE INDEX idx_tasks_due_date ON tasks(due_date)",
            "CREATE INDEX idx_activity_log_created ON activity_log(created_at)"
        ];
        
        try {
            foreach ($additionalIndexes as $indexSql) {
                $this->connection->exec($indexSql);
            }
            
            $this->logger->info('Índices adicionales creados exitosamente');
            return true;
            
        } catch (PDOException $e) {
            $this->results['warnings'][] = "Error creando índices adicionales: " . $e->getMessage();
            $this->logger->warning("Error creando índices: " . $e->getMessage());
            return true; // No es crítico, continuar
        }
    }
    
    /**
     * Insertar datos iniciales
     */
    private function insertInitialData() {
        $this->logger->info('Insertando datos iniciales...');
        
        try {
            // Datos de usuario administrador
            $adminPassword = $this->config['admin_password'] ?? $this->generateSecurePassword();
            $adminUsername = $this->config['admin_username'] ?? 'admin';
            $adminEmail = $this->config['admin_email'] ?? 'admin@profixcrm.com';
            
            $hashedPassword = password_hash($adminPassword, PASSWORD_BCRYPT, ['cost' => 12]);
            
            $stmt = $this->connection->prepare("
                INSERT INTO users (username, email, password, first_name, last_name, role, status) 
                VALUES (?, ?, ?, 'Administrador', 'Principal', 'admin', 'active')
            ");
            $stmt->execute([$adminUsername, $adminEmail, $hashedPassword]);
            
            $this->results['admin_user'] = [
                'username' => $adminUsername,
                'email' => $adminEmail,
                'password' => $adminPassword
            ];
            
            // Configuraciones iniciales
            $settings = [
                ['key' => 'app_name', 'value' => 'ProfixCRM', 'type' => 'string', 'description' => 'Nombre de la aplicación'],
                ['key' => 'app_version', 'value' => '8.0.0', 'type' => 'string', 'description' => 'Versión de la aplicación'],
                ['key' => 'timezone', 'value' => 'America/New_York', 'type' => 'string', 'description' => 'Zona horaria'],
                ['key' => 'currency', 'value' => 'USD', 'type' => 'string', 'description' => 'Moneda por defecto'],
                ['key' => 'date_format', 'value' => 'Y-m-d', 'type' => 'string', 'description' => 'Formato de fecha'],
                ['key' => 'items_per_page', 'value' => '20', 'type' => 'integer', 'description' => 'Items por página'],
                ['key' => 'enable_registration', 'value' => 'false', 'type' => 'boolean', 'description' => 'Habilitar registro de usuarios'],
                ['key' => 'require_email_verification', 'value' => 'true', 'type' => 'boolean', 'description' => 'Requerir verificación de email'],
                ['key' => 'session_lifetime', 'value' => '120', 'type' => 'integer', 'description' => 'Vida de sesión en minutos'],
                ['key' => 'api_rate_limit', 'value' => '60', 'type' => 'integer', 'description' => 'Límite de peticiones API por minuto']
            ];
            
            $stmt = $this->connection->prepare("
                INSERT INTO settings (key, value, type, description) 
                VALUES (?, ?, ?, ?)
            ");
            
            foreach ($settings as $setting) {
                $stmt->execute([$setting['key'], $setting['value'], $setting['type'], $setting['description']]);
            }
            
            // Insertar datos de prueba si está habilitado
            if ($this->config['test_data']) {
                $this->insertTestData();
            }
            
            $this->results['data_inserted'] = true;
            $this->logger->info('Datos iniciales insertados exitosamente');
            
            return true;
            
        } catch (PDOException $e) {
            $this->results['errors'][] = "Error insertando datos iniciales: " . $e->getMessage();
            $this->logger->error("Error insertando datos: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Insertar datos de prueba
     */
    private function insertTestData() {
        $this->logger->info('Insertando datos de prueba...');
        
        try {
            // Leads de prueba
            $testLeads = [
                ['John Doe', 'john@example.com', '555-0101', 'Tech Corp', 'Website', 'new'],
                ['Jane Smith', 'jane@example.com', '555-0102', 'Marketing Inc', 'Referral', 'contacted'],
                ['Bob Johnson', 'bob@example.com', '555-0103', 'Sales Co', 'Social Media', 'qualified']
            ];
            
            $stmt = $this->connection->prepare("
                INSERT INTO leads (name, email, phone, company, source, status) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($testLeads as $lead) {
                $stmt->execute($lead);
            }
            
            // Clientes de prueba
            $stmt = $this->connection->prepare("
                INSERT INTO customers (name, email, phone, company, address, city, state, country) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $testCustomers = [
                ['ABC Corporation', 'info@abc.com', '555-1001', 'ABC Corp', '123 Main St', 'New York', 'NY', 'USA'],
                ['XYZ Industries', 'contact@xyz.com', '555-1002', 'XYZ Industries', '456 Oak Ave', 'Los Angeles', 'CA', 'USA']
            ];
            
            foreach ($testCustomers as $customer) {
                $stmt->execute($customer);
            }
            
            // Productos de prueba
            $stmt = $this->connection->prepare("
                INSERT INTO products (sku, name, description, price, cost, stock, category) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $testProducts = [
                ['PROD-001', 'CRM Basic Plan', 'Plan básico de CRM', 29.99, 15.00, 100, 'Software'],
                ['PROD-002', 'CRM Pro Plan', 'Plan profesional de CRM', 79.99, 40.00, 50, 'Software'],
                ['PROD-003', 'Consulting Hours', 'Horas de consultoría', 150.00, 75.00, 200, 'Services']
            ];
            
            foreach ($testProducts as $product) {
                $stmt->execute($product);
            }
            
            $this->logger->info('Datos de prueba insertados exitosamente');
            
        } catch (PDOException $e) {
            $this->results['warnings'][] = "Error insertando datos de prueba: " . $e->getMessage();
            $this->logger->warning("Error con datos de prueba: " . $e->getMessage());
        }
    }
    
    /**
     * Crear procedimientos almacenados
     */
    private function createStoredProcedures() {
        $this->logger->info('Creando procedimientos almacenados...');
        
        $procedures = [
            "CREATE PROCEDURE IF NOT EXISTS GetCustomerInvoices(IN customerId INT)
            BEGIN
                SELECT i.*, COUNT(ii.id) as item_count 
                FROM invoices i 
                LEFT JOIN invoice_items ii ON i.id = ii.invoice_id 
                WHERE i.customer_id = customerId 
                GROUP BY i.id 
                ORDER BY i.date DESC;
            END",
            
            "CREATE PROCEDURE IF NOT EXISTS GetMonthlyRevenue(IN year INT, IN month INT)
            BEGIN
                SELECT SUM(total) as monthly_revenue, COUNT(*) as invoice_count
                FROM invoices 
                WHERE YEAR(date) = year AND MONTH(date) = month AND status = 'paid';
            END",
            
            "CREATE PROCEDURE IF NOT EXISTS GetLeadConversionRate()
            BEGIN
                SELECT 
                    (SELECT COUNT(*) FROM customers) as total_customers,
                    (SELECT COUNT(*) FROM leads) as total_leads,
                    ROUND((SELECT COUNT(*) FROM customers) / (SELECT COUNT(*) FROM leads) * 100, 2) as conversion_rate;
            END"
        ];
        
        try {
            foreach ($procedures as $procedure) {
                $this->connection->exec($procedure);
            }
            
            $this->logger->info('Procedimientos almacenados creados exitosamente');
            return true;
            
        } catch (PDOException $e) {
            $this->results['warnings'][] = "Error creando procedimientos: " . $e->getMessage();
            $this->logger->warning("Error con procedimientos: " . $e->getMessage());
            return true; // No es crítico
        }
    }
    
    /**
     * Verificar integridad de la base de datos
     */
    private function verifyDatabaseIntegrity() {
        $this->logger->info('Verificando integridad de la base de datos...');
        
        try {
            // Verificar que todas las tablas existen
            $stmt = $this->connection->query("SHOW TABLES");
            $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $requiredTables = array_keys($this->databaseSchema);
            $missingTables = array_diff($requiredTables, $existingTables);
            
            if (!empty($missingTables)) {
                $this->results['errors'][] = "Tablas faltantes: " . implode(', ', $missingTables);
                return false;
            }
            
            // Verificar integridad de foreign keys
            $stmt = $this->connection->query("
                SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL
            ");
            
            $foreignKeys = $stmt->fetchAll();
            $this->results['foreign_keys'] = count($foreignKeys);
            
            // Verificar índices
            $stmt = $this->connection->query("
                SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME
                FROM INFORMATION_SCHEMA.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
            ");
            
            $indexes = $stmt->fetchAll();
            $this->results['indexes'] = count($indexes);
            
            $this->logger->info('Integridad de base de datos verificada exitosamente');
            return true;
            
        } catch (PDOException $e) {
            $this->results['errors'][] = "Error verificando integridad: " . $e->getMessage();
            $this->logger->error("Error verificando integridad: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Configurar seguridad de la base de datos
     */
    private function setupDatabaseSecurity() {
        $this->logger->info('Configurando seguridad de la base de datos...');
        
        try {
            // Crear usuario de aplicación si está habilitado
            if ($this->config['create_user']) {
                $appUsername = $this->config['app_username'] ?? 'profix_app_' . substr(md5(uniqid()), 0, 8);
                $appPassword = $this->config['app_password'] ?? $this->generateSecurePassword();
                
                // Crear usuario con permisos limitados
                $this->connection->exec("CREATE USER IF NOT EXISTS '$appUsername'@'localhost' IDENTIFIED BY '$appPassword'");
                $this->connection->exec("GRANT SELECT, INSERT, UPDATE, DELETE ON {$this->config['database']}.* TO '$appUsername'@'localhost'");
                $this->connection->exec("FLUSH PRIVILEGES");
                
                $this->results['app_user'] = [
                    'username' => $appUsername,
                    'password' => $appPassword
                ];
                
                $this->logger->info("Usuario de aplicación creado: $appUsername");
            }
            
            // Crear backup inicial
            if ($this->config['backup_existing']) {
                $this->createInitialBackup();
            }
            
            $this->logger->info('Seguridad de base de datos configurada exitosamente');
            return true;
            
        } catch (PDOException $e) {
            $this->results['warnings'][] = "Error configurando seguridad: " . $e->getMessage();
            $this->logger->warning("Error con seguridad: " . $e->getMessage());
            return true; // No es crítico
        }
    }
    
    /**
     * Probar conexión final
     */
    private function testFinalConnection() {
        $this->logger->info('Probando conexión final a la base de datos...');
        
        try {
            // Verificar conexión
            $stmt = $this->connection->query("SELECT 1");
            $result = $stmt->fetch();
            
            if (!$result) {
                throw new Exception("Conexión a base de datos no funciona");
            }
            
            // Verificar tabla de usuarios
            $stmt = $this->connection->query("SELECT COUNT(*) as user_count FROM users");
            $userCount = $stmt->fetch();
            
            if ($userCount['user_count'] == 0) {
                throw new Exception("No hay usuarios en la base de datos");
            }
            
            // Verificar configuraciones
            $stmt = $this->connection->query("SELECT COUNT(*) as setting_count FROM settings");
            $settingCount = $stmt->fetch();
            
            if ($settingCount['setting_count'] == 0) {
                throw new Exception("No hay configuraciones en la base de datos");
            }
            
            $this->results['final_test'] = [
                'connection' => 'OK',
                'user_count' => $userCount['user_count'],
                'setting_count' => $settingCount['setting_count']
            ];
            
            $this->logger->info('Prueba de conexión final exitosa');
            return true;
            
        } catch (Exception $e) {
            $this->results['errors'][] = "Error en prueba final: " . $e->getMessage();
            $this->logger->error("Error en prueba final: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Métodos auxiliares
     */
    
    private function backupDatabase($databaseName) {
        $this->logger->info("Creando backup de base de datos: $databaseName");
        
        try {
            $backupDir = dirname(__DIR__, 2) . '/backups';
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }
            
            $timestamp = date('Y-m-d_H-i-s');
            $backupFile = "$backupDir/{$databaseName}_backup_$timestamp.sql";
            
            // Usar mysqldump si está disponible
            $host = $this->config['host'] ?? 'localhost';
            $username = $this->config['username'];
            $password = $this->config['password'];
            
            $command = "mysqldump -h $host -u $username -p$password $databaseName > $backupFile";
            exec($command, $output, $returnCode);
            
            if ($returnCode === 0 && file_exists($backupFile)) {
                $this->results['backup_file'] = $backupFile;
                $this->logger->info("Backup creado exitosamente: $backupFile");
                return true;
            } else {
                $this->results['warnings'][] = "No se pudo crear backup automático";
                $this->logger->warning("Error creando backup con mysqldump");
                return true; // No es crítico
            }
            
        } catch (Exception $e) {
            $this->results['warnings'][] = "Error en backup: " . $e->getMessage();
            $this->logger->warning("Error en backup: " . $e->getMessage());
            return true; // No es crítico
        }
    }
    
    private function createInitialBackup() {
        $this->logger->info('Creando backup inicial...');
        
        try {
            $backupDir = dirname(__DIR__, 2) . '/backups';
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }
            
            $timestamp = date('Y-m-d_H-i-s');
            $databaseName = $this->config['database'];
            $backupFile = "$backupDir/{$databaseName}_initial_$timestamp.sql";
            
            // Backup con mysqldump
            $host = $this->config['host'] ?? 'localhost';
            $username = $this->config['username'];
            $password = $this->config['password'];
            
            $command = "mysqldump -h $host -u $username -p$password $databaseName > $backupFile";
            exec($command, $output, $returnCode);
            
            if ($returnCode === 0 && file_exists($backupFile)) {
                $this->results['initial_backup'] = $backupFile;
                $this->logger->info("Backup inicial creado: $backupFile");
            }
            
        } catch (Exception $e) {
            $this->logger->warning("Error creando backup inicial: " . $e->getMessage());
        }
    }
    
    private function generateSecurePassword($length = 16) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        return $password;
    }
    
    /**
     * Métodos públicos adicionales
     */
    
    public function testConnection($config) {
        try {
            $host = $config['host'] ?? 'localhost';
            $port = $config['port'] ?? 3306;
            $username = $config['username'];
            $password = $config['password'];
            $database = $config['database'] ?? null;
            
            $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
            if ($database) {
                $dsn .= ";dbname=$database";
            }
            
            $testConnection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
            
            // Verificar versión
            $version = $testConnection->getAttribute(PDO::ATTR_SERVER_VERSION);
            
            return [
                'success' => true,
                'version' => $version,
                'message' => 'Conexión exitosa'
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function getDatabaseInfo() {
        if (!$this->connection) {
            return null;
        }
        
        try {
            // Información de la base de datos
            $stmt = $this->connection->query("SELECT DATABASE() as database_name");
            $databaseName = $stmt->fetchColumn();
            
            $stmt = $this->connection->query("SELECT VERSION() as version");
            $version = $stmt->fetchColumn();
            
            // Contar tablas
            $stmt = $this->connection->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Tamaño de la base de datos
            $stmt = $this->connection->query("
                SELECT SUM(data_length + index_length) as size
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
            ");
            $size = $stmt->fetchColumn();
            
            return [
                'database' => $databaseName,
                'version' => $version,
                'tables_count' => count($tables),
                'tables' => $tables,
                'size_bytes' => $size,
                'size_formatted' => $this->formatBytes($size)
            ];
            
        } catch (PDOException $e) {
            return null;
        }
    }
    
    public function generateDatabaseReport() {
        if (!$this->connection) {
            return null;
        }
        
        try {
            $report = [
                'timestamp' => date('Y-m-d H:i:s'),
                'database_info' => $this->getDatabaseInfo(),
                'table_stats' => [],
                'performance_stats' => []
            ];
            
            // Estadísticas por tabla
            $tables = $this->getDatabaseInfo()['tables'];
            foreach ($tables as $table) {
                try {
                    $stmt = $this->connection->query("SELECT COUNT(*) as count FROM `$table`");
                    $count = $stmt->fetchColumn();
                    
                    $stmt = $this->connection->query("
                        SELECT AVG_ROW_LENGTH, DATA_LENGTH, INDEX_LENGTH, AUTO_INCREMENT
                        FROM information_schema.tables 
                        WHERE table_schema = DATABASE() AND table_name = '$table'
                    ");
                    $tableInfo = $stmt->fetch();
                    
                    $report['table_stats'][$table] = [
                        'rows' => $count,
                        'avg_row_length' => $tableInfo['AVG_ROW_LENGTH'],
                        'data_size' => $tableInfo['DATA_LENGTH'],
                        'index_size' => $tableInfo['INDEX_LENGTH'],
                        'auto_increment' => $tableInfo['AUTO_INCREMENT']
                    ];
                    
                } catch (Exception $e) {
                    $report['table_stats'][$table] = ['error' => $e->getMessage()];
                }
            }
            
            return $report;
            
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}