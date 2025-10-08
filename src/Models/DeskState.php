<?php

namespace IaTradeCRM\Models;

use PDO;
use Exception;
use iaTradeCRM\Database\Connection;

class DeskState
{
    private $pdo;
    
    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Obtener el ID de un estado por su nombre dentro de un desk
     * Usado por endpoints para resolver transiciones de estado
     */
    public static function getStateIdByName($name, $deskId)
    {
        try {
            $db = \iaTradeCRM\Database\Connection::getInstance()->getConnection();
            $sql = "SELECT id FROM desk_states WHERE desk_id = ? AND name = ? LIMIT 1";
            $stmt = $db->prepare($sql);
            $stmt->execute([$deskId, $name]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (int)$row['id'] : null;
        } catch (\Throwable $e) {
            error_log("DeskState::getStateIdByName error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Obtener todos los estados de un desk
     */
    public function getStatesByDesk($deskId, $activeOnly = true)
    {
        $sql = "SELECT * FROM desk_states WHERE desk_id = ?";
        $params = [$deskId];
        
        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }
        
        $sql .= " ORDER BY sort_order ASC, display_name ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener un estado específico
     */
    public function getState($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM desk_states WHERE id = ?");
        $stmt->execute([$id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Crear un nuevo estado
     */
    public function createState($data)
    {
        // Validar datos requeridos
        $required = ['desk_id', 'name', 'display_name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("El campo '$field' es requerido");
            }
        }
        
        // Verificar que el nombre sea único en el desk
        if ($this->stateNameExists($data['desk_id'], $data['name'])) {
            throw new Exception("Ya existe un estado con ese nombre en este desk");
        }
        
        // Obtener el siguiente sort_order
        if (!isset($data['sort_order'])) {
            $data['sort_order'] = $this->getNextSortOrder($data['desk_id']);
        }
        
        $sql = "INSERT INTO desk_states (
            desk_id, name, display_name, description, color, icon, 
            is_initial, is_final, is_active, sort_order, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['desk_id'],
            $data['name'],
            $data['display_name'],
            $data['description'] ?? null,
            $data['color'] ?? '#6B7280',
            $data['icon'] ?? 'tag',
            $data['is_initial'] ?? false,
            $data['is_final'] ?? false,
            $data['is_active'] ?? true,
            $data['sort_order'],
            $data['created_by'] ?? null
        ];
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Actualizar un estado
     */
    public function updateState($id, $data)
    {
        $state = $this->getState($id);
        if (!$state) {
            throw new Exception("Estado no encontrado");
        }
        
        // Verificar nombre único si se está cambiando
        if (isset($data['name']) && $data['name'] !== $state['name']) {
            if ($this->stateNameExists($state['desk_id'], $data['name'], $id)) {
                throw new Exception("Ya existe un estado con ese nombre en este desk");
            }
        }
        
        $updateFields = [];
        $params = [];
        
        $allowedFields = [
            'name', 'display_name', 'description', 'color', 'icon',
            'is_initial', 'is_final', 'is_active', 'sort_order'
        ];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateFields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($updateFields)) {
            throw new Exception("No hay campos para actualizar");
        }
        
        $params[] = $id;
        
        $sql = "UPDATE desk_states SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute($params);
    }
    
    /**
     * Eliminar un estado
     */
    public function deleteState($id)
    {
        // Verificar que no haya leads usando este estado
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM leads WHERE desk_state_id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("No se puede eliminar el estado porque hay leads que lo están usando");
        }
        
        // Eliminar transiciones relacionadas
        $stmt = $this->pdo->prepare("DELETE FROM state_transitions WHERE from_state_id = ? OR to_state_id = ?");
        $stmt->execute([$id, $id]);
        
        // Eliminar el estado
        $stmt = $this->pdo->prepare("DELETE FROM desk_states WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Activar/desactivar un estado
     */
    public function toggleState($id, $active = null)
    {
        $state = $this->getState($id);
        if (!$state) {
            throw new Exception("Estado no encontrado");
        }
        
        $newStatus = $active !== null ? $active : !$state['is_active'];
        
        $stmt = $this->pdo->prepare("UPDATE desk_states SET is_active = ? WHERE id = ?");
        return $stmt->execute([$newStatus, $id]);
    }
    
    /**
     * Reordenar estados
     */
    public function reorderStates($deskId, $stateIds)
    {
        $this->pdo->beginTransaction();
        
        try {
            foreach ($stateIds as $index => $stateId) {
                $stmt = $this->pdo->prepare("UPDATE desk_states SET sort_order = ? WHERE id = ? AND desk_id = ?");
                $stmt->execute([$index + 1, $stateId, $deskId]);
            }
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Obtener el estado inicial de un desk
     */
    public function getInitialState($deskId)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM desk_states WHERE desk_id = ? AND is_initial = 1 AND is_active = 1 LIMIT 1");
        $stmt->execute([$deskId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Verificar si un nombre de estado ya existe en el desk
     */
    private function stateNameExists($deskId, $name, $excludeId = null)
    {
        $sql = "SELECT COUNT(*) FROM desk_states WHERE desk_id = ? AND name = ?";
        $params = [$deskId, $name];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Obtener el siguiente sort_order para un desk
     */
    private function getNextSortOrder($deskId)
    {
        $stmt = $this->pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM desk_states WHERE desk_id = ?");
        $stmt->execute([$deskId]);
        
        return $stmt->fetchColumn();
    }
    
    /**
     * Verificar que los estados pertenezcan al desk especificado
     */
    private function validateStatesBelongToDesk($deskId, $stateIds)
    {
        if (empty($stateIds)) {
            return;
        }
        
        $placeholders = str_repeat('?,', count($stateIds) - 1) . '?';
        $sql = "SELECT COUNT(*) FROM desk_states WHERE id IN ($placeholders) AND desk_id = ?";
        
        $params = array_merge($stateIds, [$deskId]);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        if ($stmt->fetchColumn() != count($stateIds)) {
            throw new Exception("Algunos estados no pertenecen al desk especificado");
        }
    }
}