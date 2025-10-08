<?php

namespace iaTradeCRM\Database;

use PDO;
use PDOException;

class Connection
{
    private static $instance = null;
    private $connection;

    private function __construct()
    {
        try {
            // Cargar constantes del sistema (DB_*), si están disponibles
            $constantsFile = __DIR__ . '/../../config/constants.php';
            if (file_exists($constantsFile)) { @include_once $constantsFile; }

            // Fallback adicional: incluir env.php si existe (evita depender de dotfiles en producción)
            $envPhp = __DIR__ . '/../../env.php';
            if (file_exists($envPhp)) { @include_once $envPhp; }

            // Asegurar carga de .env en contextos CLI o entornos sin Composer
            if ((!isset($_ENV['DB_DATABASE']) && !isset($_ENV['DB_NAME'])) || !isset($_ENV['DB_HOST'])) {
                $envFile = __DIR__ . '/../../.env';
                if (is_file($envFile)) {
                    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    foreach ($lines as $line) {
                        if (strpos(ltrim($line), '#') === 0) { continue; }
                        $parts = explode('=', $line, 2);
                        if (count($parts) === 2) {
                            $k = trim($parts[0]);
                            $v = trim($parts[1], " \t\n\r\0\x0B\"'" );
                            $_ENV[$k] = $v;
                            @putenv("$k=$v");
                        }
                    }
                }
            }
            // Si todavía faltan variables, leer config/config.php directamente
            if ((!isset($_ENV['DB_DATABASE']) && !defined('DB_NAME')) || (!isset($_ENV['DB_HOST']) && !defined('DB_HOST'))) {
                $configFile = __DIR__ . '/../../config/config.php';
                if (file_exists($configFile)) {
                    $cfg = @include $configFile; // devuelve array
                    if (is_array($cfg) && isset($cfg['database'])) {
                        $_ENV['DB_HOST'] = $_ENV['DB_HOST'] ?? ($cfg['database']['host'] ?? null);
                        $_ENV['DB_PORT'] = $_ENV['DB_PORT'] ?? ($cfg['database']['port'] ?? null);
                        $_ENV['DB_DATABASE'] = $_ENV['DB_DATABASE'] ?? ($cfg['database']['name'] ?? null);
                        $_ENV['DB_USERNAME'] = $_ENV['DB_USERNAME'] ?? ($cfg['database']['username'] ?? null);
                        $_ENV['DB_PASSWORD'] = $_ENV['DB_PASSWORD'] ?? ($cfg['database']['password'] ?? null);
                        foreach (['DB_HOST','DB_PORT','DB_DATABASE','DB_USERNAME','DB_PASSWORD'] as $kk) {
                            if (isset($_ENV[$kk]) && $_ENV[$kk] !== null) { @putenv($kk.'='.$_ENV[$kk]); }
                        }
                    }
                }
            }

            $host = (defined('DB_HOST') ? DB_HOST : ($_ENV['DB_HOST'] ?? 'localhost'));
            $port = (defined('DB_PORT') ? DB_PORT : ($_ENV['DB_PORT'] ?? '3306'));
            // Admitir tanto DB_DATABASE como DB_NAME para compatibilidad con distintos .env
            // Fallback actualizado: spin2pay_profixcrm
            $dbname = (defined('DB_NAME') ? DB_NAME : ($_ENV['DB_DATABASE'] ?? ($_ENV['DB_NAME'] ?? 'spin2pay_profixcrm')));
            // Admitir alias DB_USER y constantes
            $username = (defined('DB_USER') ? DB_USER : ($_ENV['DB_USERNAME'] ?? ($_ENV['DB_USER'] ?? 'root')));
            $password = (defined('DB_PASS') ? DB_PASS : ($_ENV['DB_PASSWORD'] ?? ''));
            $charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

            // Opciones base de PDO
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            // Añadir MYSQL_ATTR_INIT_COMMAND solo si existe la constante (pdo_mysql cargado)
            if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
                $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES {$charset}";
            }

            $this->connection = new PDO($dsn, $username, $password, $options);

            // Si no pudimos usar MYSQL_ATTR_INIT_COMMAND (p.ej. pdo_mysql no define la constante),
            // aplicamos manualmente el SET NAMES tras conectar para asegurar el charset
            if (!defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
                try {
                    $this->connection->exec("SET NAMES {$charset}");
                } catch (PDOException $e) {
                    // No es crítico si falla aquí; el charset ya viene en el DSN
                }
            }

            // Mitigar errores por ONLY_FULL_GROUP_BY en MySQL estrictos
            // Algunos endpoints usan GROUP BY con columnas funcionalmente dependientes.
            // En ciertos servidores con sql_mode estricto, esto causa 42000.
            // Removemos ONLY_FULL_GROUP_BY a nivel de sesión para evitar 500.
            try {
                $this->connection->exec("SET SESSION sql_mode = REPLACE(@@SESSION.sql_mode, 'ONLY_FULL_GROUP_BY', '')");
            } catch (PDOException $e) {
                // Silenciar: si no se puede cambiar sql_mode, continuamos.
            }

        } catch (PDOException $e) {
            error_log('Database connection error: ' . $e->getMessage());
            throw new \Exception('Error de conexión a la base de datos: ' . $e->getMessage());
        }
    }

    /**
     * Obtiene la instancia única de la conexión
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Obtiene la conexión PDO
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }

    /**
     * Ejecuta una consulta preparada
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new PDOException("Error en consulta SQL: " . $e->getMessage());
        }
    }

    /**
     * Obtiene un registro por ID
     */
    public function find(string $table, int $id): ?array
    {
        $stmt = $this->query("SELECT * FROM {$table} WHERE id = ?", [$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Obtiene todos los registros de una tabla
     */
    public function findAll(string $table, array $conditions = [], string $orderBy = 'id ASC', int $limit = null): array
    {
        $sql = "SELECT * FROM {$table}";
        $params = [];

        if (!empty($conditions)) {
            $whereClause = [];
            foreach ($conditions as $field => $value) {
                $whereClause[] = "{$field} = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }

        $sql .= " ORDER BY {$orderBy}";

        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }

        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Inserta un nuevo registro
     */
    public function insert(string $table, array $data): int
    {
        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $table,
            implode(', ', $fields),
            implode(', ', $placeholders)
        );

        $this->query($sql, array_values($data));
        return (int) $this->connection->lastInsertId();
    }

    /**
     * Actualiza un registro
     */
    public function update(string $table, int $id, array $data): bool
    {
        $fields = [];
        $params = [];

        foreach ($data as $field => $value) {
            $fields[] = "{$field} = ?";
            $params[] = $value;
        }
        $params[] = $id;

        $sql = sprintf(
            "UPDATE %s SET %s WHERE id = ?",
            $table,
            implode(', ', $fields)
        );

        $stmt = $this->query($sql, $params);
        return $stmt->rowCount() > 0;
    }

    /**
     * Elimina un registro
     */
    public function delete(string $table, int $id): bool
    {
        $stmt = $this->query("DELETE FROM {$table} WHERE id = ?", [$id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Cuenta registros en una tabla
     */
    public function count(string $table, array $conditions = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM {$table}";
        $params = [];

        if (!empty($conditions)) {
            $whereClause = [];
            foreach ($conditions as $field => $value) {
                $whereClause[] = "{$field} = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }

        $stmt = $this->query($sql, $params);
        return (int) $stmt->fetch()['total'];
    }

    /**
     * Inicia una transacción
     */
    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }

    /**
     * Confirma una transacción
     */
    public function commit(): bool
    {
        return $this->connection->commit();
    }

    /**
     * Revierte una transacción
     */
    public function rollback(): bool
    {
        return $this->connection->rollback();
    }

    /**
     * Verifica si hay una transacción activa
     */
    public function inTransaction(): bool
    {
        return $this->connection->inTransaction();
    }

    /**
     * Obtiene el último ID insertado
     */
    public function lastInsertId(): string
    {
        return $this->connection->lastInsertId();
    }

    /**
     * Previene la clonación del objeto
     */
    private function __clone() {}

    /**
     * Previene la deserialización del objeto
     */
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}