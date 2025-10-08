<?php

/**
 * Instalador de Base de Datos
 * Maneja la creación y configuración de la base de datos
 */
class DatabaseInstaller
{
    private $connection = null;
    private $errors = [];
    private $warnings = [];
    private $config = [];

    public function __construct()
    {
        // Constructor vacío
    }

    /**
     * Prueba la conexión a la base de datos
     */
    public function testConnection($host, $username, $password, $port = 3306)
    {
        try {
            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $pdo = new PDO($dsn, $username, $password, $options);
            
            // Verificar versión de MySQL/MariaDB
            $version = $pdo->query('SELECT VERSION()')->fetchColumn();
            $this->validateDatabaseVersion($version);
            
            $this->connection = $pdo;
            $this->config = [
                'host' => $host,
                'username' => $username,
                'password' => $password,
                'port' => $port,
                'version' => $version
            ];

            return [
                'success' => true,
                'version' => $version,
                'message' => 'Conexión exitosa a la base de datos'
            ];

        } catch (PDOException $e) {
            $this->errors[] = [
                'type' => 'connection',
                'message' => 'Error de conexión a la base de datos',
                'details' => $e->getMessage(),
                'solutions' => $this->getConnectionSolutions($e->getCode())
            ];

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ];
        }
    }

    /**
     * Valida la versión de la base de datos
     */
    private function validateDatabaseVersion($version)
    {
        $isMariaDB = stripos($version, 'mariadb') !== false;
        
        if ($isMariaDB) {
            // Extraer versión de MariaDB
            preg_match('/(\d+\.\d+\.\d+)/', $version, $matches);
            $versionNumber = $matches[1] ?? '0.0.0';
            
            if (version_compare($versionNumber, '10.3.0', '<')) {
                $this->warnings[] = [
                    'type' => 'version',
                    'message' => "MariaDB {$versionNumber} detectado. Se recomienda MariaDB 10.3 o superior",
                    'solution' => 'Considera actualizar MariaDB para mejor compatibilidad'
                ];
            }
        } else {
            // MySQL
            preg_match('/(\d+\.\d+\.\d+)/', $version, $matches);
            $versionNumber = $matches[1] ?? '0.0.0';
            
            if (version_compare($versionNumber, '5.7.0', '<')) {
                $this->errors[] = [
                    'type' => 'version',
                    'message' => "MySQL {$versionNumber} no es compatible. Se requiere MySQL 5.7 o superior",
                    'solution' => 'Actualiza MySQL a la versión 5.7 o superior'
                ];
            } elseif (version_compare($versionNumber, '8.0.0', '<')) {
                $this->warnings[] = [
                    'type' => 'version',
                    'message' => "MySQL {$versionNumber} detectado. Se recomienda MySQL 8.0 o superior",
                    'solution' => 'Considera actualizar a MySQL 8.0 para mejor rendimiento'
                ];
            }
        }
    }

    /**
     * Obtiene soluciones para errores de conexión
     */
    private function getConnectionSolutions($errorCode)
    {
        $solutions = [
            1045 => [ // Access denied
                'Verifica que el usuario y contraseña sean correctos',
                'Asegúrate de que el usuario tenga permisos para conectarse desde este host',
                'Comando: GRANT ALL PRIVILEGES ON *.* TO \'usuario\'@\'localhost\' IDENTIFIED BY \'contraseña\';'
            ],
            2002 => [ // Can't connect to server
                'Verifica que el servidor MySQL esté ejecutándose',
                'Comprueba que el host y puerto sean correctos',
                'Verifica la configuración del firewall'
            ],
            1049 => [ // Unknown database
                'La base de datos especificada no existe',
                'Se creará automáticamente en el siguiente paso'
            ],
            2005 => [ // Unknown host
                'Verifica que el host de la base de datos sea correcto',
                'Comprueba la conectividad de red al servidor'
            ]
        ];

        return $solutions[$errorCode] ?? [
            'Error desconocido de conexión',
            'Verifica la configuración de la base de datos',
            'Consulta los logs del servidor MySQL para más detalles'
        ];
    }

    /**
     * Crea la base de datos si no existe
     */
    public function createDatabase($databaseName)
    {
        if (!$this->connection) {
            throw new Exception('No hay conexión a la base de datos');
        }

        try {
            // Verificar si la base de datos ya existe
            $stmt = $this->connection->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
            $stmt->execute([$databaseName]);
            
            if ($stmt->fetch()) {
                $this->warnings[] = [
                    'type' => 'database_exists',
                    'message' => "La base de datos '{$databaseName}' ya existe",
                    'solution' => 'Se utilizará la base de datos existente'
                ];
            } else {
                // Crear la base de datos
                $sql = "CREATE DATABASE `{$databaseName}` 
                        CHARACTER SET utf8mb4 
                        COLLATE utf8mb4_unicode_ci";
                
                $this->connection->exec($sql);
            }

            // Conectar a la base de datos específica
            $dsn = "mysql:host={$this->config['host']};port={$this->config['port']};dbname={$databaseName};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $this->connection = new PDO($dsn, $this->config['username'], $this->config['password'], $options);
            $this->config['database'] = $databaseName;

            return [
                'success' => true,
                'message' => "Base de datos '{$databaseName}' lista para usar"
            ];

        } catch (PDOException $e) {
            $this->errors[] = [
                'type' => 'database_creation',
                'message' => "Error al crear la base de datos '{$databaseName}'",
                'details' => $e->getMessage(),
                'solutions' => [
                    'Verifica que el usuario tenga permisos CREATE',
                    'Comando: GRANT CREATE ON *.* TO \'usuario\'@\'localhost\';',
                    'Verifica que no haya caracteres especiales en el nombre'
                ]
            ];

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Ejecuta el script de instalación de la base de datos
     */
    public function installSchema()
    {
        if (!$this->connection) {
            throw new Exception('No hay conexión a la base de datos');
        }

        try {
            // Leer el archivo SQL de instalación
            $sqlFile = __DIR__ . '/../sql/install.sql';
            
            if (!file_exists($sqlFile)) {
                throw new Exception("Archivo de instalación SQL no encontrado: {$sqlFile}");
            }

            $sql = file_get_contents($sqlFile);
            
            if (empty($sql)) {
                throw new Exception("El archivo SQL está vacío");
            }

            // Dividir en statements individuales
            $statements = $this->parseSQLStatements($sql);
            
            $this->connection->beginTransaction();
            
            $executedStatements = 0;
            $errors = [];

            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (empty($statement)) continue;

                try {
                    $this->connection->exec($statement);
                    $executedStatements++;
                } catch (PDOException $e) {
                    $errors[] = [
                        'statement' => substr($statement, 0, 100) . '...',
                        'error' => $e->getMessage()
                    ];
                }
            }

            if (!empty($errors)) {
                $this->connection->rollBack();
                
                foreach ($errors as $error) {
                    $this->errors[] = [
                        'type' => 'sql_execution',
                        'message' => 'Error ejecutando SQL: ' . $error['statement'],
                        'details' => $error['error']
                    ];
                }

                return [
                    'success' => false,
                    'errors' => $errors,
                    'executed' => $executedStatements
                ];
            }

            $this->connection->commit();

            return [
                'success' => true,
                'message' => "Esquema de base de datos instalado correctamente",
                'executed_statements' => $executedStatements
            ];

        } catch (Exception $e) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            $this->errors[] = [
                'type' => 'schema_installation',
                'message' => 'Error instalando el esquema de la base de datos',
                'details' => $e->getMessage()
            ];

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Parsea statements SQL separados por punto y coma
     */
    private function parseSQLStatements($sql)
    {
        // Remover comentarios
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        
        // Dividir por punto y coma, pero no dentro de strings
        $statements = [];
        $current = '';
        $inString = false;
        $stringChar = '';
        
        for ($i = 0; $i < strlen($sql); $i++) {
            $char = $sql[$i];
            
            if (!$inString && ($char === '"' || $char === "'")) {
                $inString = true;
                $stringChar = $char;
            } elseif ($inString && $char === $stringChar) {
                // Verificar si está escapado
                if ($i > 0 && $sql[$i-1] !== '\\') {
                    $inString = false;
                }
            } elseif (!$inString && $char === ';') {
                $statements[] = trim($current);
                $current = '';
                continue;
            }
            
            $current .= $char;
        }
        
        if (!empty(trim($current))) {
            $statements[] = trim($current);
        }
        
        return array_filter($statements);
    }

    /**
     * Verifica la integridad de la instalación
     */
    public function verifyInstallation()
    {
        if (!$this->connection) {
            throw new Exception('No hay conexión a la base de datos');
        }

        try {
            $requiredTables = [
                'users',
                'roles',
                'permissions',
                'user_roles',
                'role_permissions',
                'leads',
                'trading_accounts',
                'deposits_withdrawals',
                'desks',
                'settings'
            ];

            $existingTables = [];
            $missingTables = [];

            // Verificar tablas existentes
            $stmt = $this->connection->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($requiredTables as $table) {
                if (in_array($table, $tables)) {
                    $existingTables[] = $table;
                } else {
                    $missingTables[] = $table;
                }
            }

            // Verificar estructura básica de tablas críticas
            $structureErrors = [];
            foreach (['users', 'roles'] as $criticalTable) {
                if (in_array($criticalTable, $existingTables)) {
                    $result = $this->verifyTableStructure($criticalTable);
                    if (!$result['valid']) {
                        $structureErrors[] = $result;
                    }
                }
            }

            $isValid = empty($missingTables) && empty($structureErrors);

            return [
                'valid' => $isValid,
                'existing_tables' => $existingTables,
                'missing_tables' => $missingTables,
                'structure_errors' => $structureErrors,
                'total_tables' => count($tables)
            ];

        } catch (PDOException $e) {
            $this->errors[] = [
                'type' => 'verification',
                'message' => 'Error verificando la instalación',
                'details' => $e->getMessage()
            ];

            return [
                'valid' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verifica la estructura de una tabla específica
     */
    private function verifyTableStructure($tableName)
    {
        try {
            $stmt = $this->connection->prepare("DESCRIBE {$tableName}");
            $stmt->execute();
            $columns = $stmt->fetchAll();

            $requiredColumns = $this->getRequiredColumns($tableName);
            $existingColumns = array_column($columns, 'Field');
            $missingColumns = array_diff($requiredColumns, $existingColumns);

            return [
                'table' => $tableName,
                'valid' => empty($missingColumns),
                'existing_columns' => $existingColumns,
                'missing_columns' => $missingColumns
            ];

        } catch (PDOException $e) {
            return [
                'table' => $tableName,
                'valid' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Define las columnas requeridas para cada tabla
     */
    private function getRequiredColumns($tableName)
    {
        $columns = [
            'users' => ['id', 'username', 'email', 'password', 'created_at'],
            'roles' => ['id', 'name', 'description', 'created_at'],
            'permissions' => ['id', 'name', 'description'],
            'leads' => ['id', 'name', 'email', 'phone', 'status', 'created_at'],
            'trading_accounts' => ['id', 'user_id', 'account_number', 'balance', 'created_at']
        ];

        return $columns[$tableName] ?? [];
    }

    /**
     * Obtiene errores
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Obtiene advertencias
     */
    public function getWarnings()
    {
        return $this->warnings;
    }

    /**
     * Verifica si hay errores
     */
    public function hasErrors()
    {
        return !empty($this->errors);
    }

    /**
     * Obtiene la configuración actual
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Limpia errores y advertencias
     */
    public function clearMessages()
    {
        $this->errors = [];
        $this->warnings = [];
    }

    /**
     * Cierra la conexión
     */
    public function closeConnection()
    {
        $this->connection = null;
    }
}