<?php

namespace IaTradeCRM\Models;

use IaTradeCRM\Database\Connection;
use PDO;

abstract class BaseModel
{
    protected $table;
    protected $primaryKey = 'id';
    protected $fillable = [];
    protected $attributes = [];

    public function __construct($attributes = [])
    {
        $this->fill($attributes);
    }

    /**
     * Llenar el modelo con atributos
     */
    public function fill($attributes)
    {
        foreach ($attributes as $key => $value) {
            if (in_array($key, $this->fillable) || empty($this->fillable)) {
                $this->attributes[$key] = $value;
            }
        }
        return $this;
    }

    /**
     * Obtener atributo
     */
    public function __get($key)
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Establecer atributo
     */
    public function __set($key, $value)
    {
        if (in_array($key, $this->fillable) || empty($this->fillable)) {
            $this->attributes[$key] = $value;
        }
    }

    /**
     * Verificar si existe atributo
     */
    public function __isset($key)
    {
        return isset($this->attributes[$key]);
    }

    /**
     * Crear instancia desde array
     */
    public static function hydrate($data)
    {
        $instance = new static();
        $instance->attributes = $data;
        return $instance;
    }

    /**
     * Obtener conexión a la base de datos
     */
    protected static function getConnection()
    {
        return Connection::getInstance();
    }

    /**
     * Buscar por ID
     */
    public static function find($id)
    {
        $instance = new static();
        $db = static::getConnection();
        
        $stmt = $db->getConnection()->prepare("SELECT * FROM {$instance->table} WHERE {$instance->primaryKey} = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) {
            return null;
        }
        
        return static::hydrate($data);
    }

    /**
     * Obtener todos los registros
     */
    public static function all($conditions = [], $orderBy = null, $limit = null)
    {
        $instance = new static();
        $db = static::getConnection();
        
        $sql = "SELECT * FROM {$instance->table}";
        $params = [];
        
        if (!empty($conditions)) {
            $whereClause = [];
            foreach ($conditions as $key => $value) {
                $whereClause[] = "{$key} = :{$key}";
                $params[$key] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }
        
        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }
        
        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }
        
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->execute($params);
        
        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = static::hydrate($row);
        }
        
        return $results;
    }

    /**
     * Buscar primer registro que coincida
     */
    public static function where($conditions)
    {
        $results = static::all($conditions, null, 1);
        return !empty($results) ? $results[0] : null;
    }

    /**
     * Crear nuevo registro
     */
    public static function create($data)
    {
        $instance = new static();
        $db = static::getConnection();
        
        // Filtrar solo campos permitidos
        $filteredData = [];
        foreach ($data as $key => $value) {
            if (in_array($key, $instance->fillable) || empty($instance->fillable)) {
                $filteredData[$key] = $value;
            }
        }
        
        $fields = array_keys($filteredData);
        $placeholders = array_map(function($field) { return ":{$field}"; }, $fields);
        
        $sql = "INSERT INTO {$instance->table} (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->execute($filteredData);
        
        $id = $db->getConnection()->lastInsertId();
        return static::find($id);
    }

    /**
     * Actualizar registro
     */
    public static function update($id, $data)
    {
        $instance = new static();
        $db = static::getConnection();
        
        // Filtrar solo campos permitidos
        $filteredData = [];
        foreach ($data as $key => $value) {
            if (in_array($key, $instance->fillable) || empty($instance->fillable)) {
                $filteredData[$key] = $value;
            }
        }
        
        $fields = array_keys($filteredData);
        $setClause = array_map(function($field) { return "{$field} = :{$field}"; }, $fields);
        
        $sql = "UPDATE {$instance->table} SET " . implode(', ', $setClause) . " WHERE {$instance->primaryKey} = :id";
        
        $filteredData['id'] = $id;
        $stmt = $db->getConnection()->prepare($sql);
        
        return $stmt->execute($filteredData);
    }

    /**
     * Eliminar registro
     */
    public static function delete($id)
    {
        $instance = new static();
        $db = static::getConnection();
        
        $stmt = $db->getConnection()->prepare("DELETE FROM {$instance->table} WHERE {$instance->primaryKey} = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Guardar el modelo actual
     */
    public function save()
    {
        $db = static::getConnection();
        
        if (isset($this->attributes[$this->primaryKey])) {
            // Actualizar registro existente
            return static::update($this->attributes[$this->primaryKey], $this->attributes);
        } else {
            // Crear nuevo registro
            $created = static::create($this->attributes);
            if ($created) {
                $this->attributes = $created->attributes;
                return true;
            }
            return false;
        }
    }

    /**
     * Convertir a array
     */
    public function toArray()
    {
        return $this->attributes;
    }

    /**
     * Establecer múltiples atributos
     */
    public function setAttributes(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            if (in_array($key, $this->fillable) || empty($this->fillable)) {
                $this->attributes[$key] = $value;
            }
        }
    }

    /**
     * Convertir a JSON
     */
    public function toJson()
    {
        return json_encode($this->toArray());
    }

    /**
     * Ejecutar query personalizada
     */
    public static function query($sql, $params = [])
    {
        $db = static::getConnection();
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->execute($params);
        
        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = static::hydrate($row);
        }
        
        return $results;
    }

    /**
     * Contar registros
     */
    public static function count($conditions = [])
    {
        $instance = new static();
        $db = static::getConnection();
        
        $sql = "SELECT COUNT(*) FROM {$instance->table}";
        $params = [];
        
        if (!empty($conditions)) {
            $whereClause = [];
            foreach ($conditions as $key => $value) {
                $whereClause[] = "{$key} = :{$key}";
                $params[$key] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }
        
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchColumn();
    }
}