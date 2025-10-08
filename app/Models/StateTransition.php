<?php

namespace App\Models;

use PDO;
use Exception;

class StateTransition
{
    private $pdo;
    
    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }
    
    /**
     * Obtener todas las transiciones de un desk
     */
    public function getTransitionsByDesk($deskId, $activeOnly = true)
    {
        $sql = "
            SELECT 
                st.*,
                fs.name as from_state_name,
                fs.display_name as from_state_display,
                ts.name as to_state_name,
                ts.display_name as to_state_display
            FROM state_transitions st
            LEFT JOIN desk_states fs ON st.from_state_id = fs.id
            INNER JOIN desk_states ts ON st.to_state_id = ts.id
            WHERE st.desk_id = ?
        ";
        
        $params = [$deskId];
        
        if ($activeOnly) {
            $sql .= " AND st.is_active = 1";
        }
        
        $sql .= " ORDER BY fs.sort_order ASC, ts.sort_order ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener transiciones disponibles desde un estado específico
     */
    public function getAvailableTransitions($fromStateId, $userId = null)
    {
        $sql = "
            SELECT 
                st.*,
                ts.name as to_state_name,
                ts.display_name as to_state_display,
                ts.color as to_state_color,
                ts.icon as to_state_icon
            FROM state_transitions st
            INNER JOIN desk_states ts ON st.to_state_id = ts.id
            WHERE st.from_state_id = ? AND st.is_active = 1 AND ts.is_active = 1
        ";
        
        $params = [$fromStateId];
        
        // TODO: Agregar verificación de permisos si se proporciona userId
        
        $sql .= " ORDER BY ts.sort_order ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener transiciones disponibles desde un estado específico (método estático)
     */
    public static function getAvailableTransitionsStatic($fromStateId, $deskId = null)
    {
        // Obtener la conexión PDO global
        $db = \iaTradeCRM\Database\Connection::getInstance();
        $pdo = $db->getConnection();
        
        $sql = "
            SELECT 
                st.*,
                ts.name as to_state_name,
                ts.display_name as to_state_display,
                ts.color as to_state_color,
                ts.icon as to_state_icon
            FROM state_transitions st
            INNER JOIN desk_states ts ON st.to_state_id = ts.id
            WHERE st.from_state_id = ? AND st.is_active = 1 AND ts.is_active = 1
        ";
        
        $params = [$fromStateId];
        
        $sql .= " ORDER BY ts.sort_order ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    
    /**
     * Obtener transiciones que permiten llegar a cualquier estado (from_state_id = NULL)
     */
    public function getGlobalTransitions($deskId, $toStateId = null)
    {
        $sql = "
            SELECT 
                st.*,
                ts.name as to_state_name,
                ts.display_name as to_state_display
            FROM state_transitions st
            INNER JOIN desk_states ts ON st.to_state_id = ts.id
            WHERE st.desk_id = ? AND st.from_state_id IS NULL AND st.is_active = 1
        ";
        
        $params = [$deskId];
        
        if ($toStateId) {
            $sql .= " AND st.to_state_id = ?";
            $params[] = $toStateId;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Crear una nueva transición
     */
    public function createTransition($data)
    {
        // Validar datos requeridos
        $required = ['desk_id', 'to_state_id'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new Exception("El campo '$field' es requerido");
            }
        }
        
        // Verificar que la transición no exista ya
        if ($this->transitionExists($data['desk_id'], $data['from_state_id'] ?? null, $data['to_state_id'])) {
            throw new Exception("Esta transición ya existe");
        }
        
        // Verificar que los estados pertenezcan al mismo desk
        if ($data['from_state_id']) {
            $this->validateStatesBelongToDesk($data['desk_id'], [$data['from_state_id'], $data['to_state_id']]);
        } else {
            $this->validateStatesBelongToDesk($data['desk_id'], [$data['to_state_id']]);
        }
        
        $sql = "INSERT INTO state_transitions (
            desk_id, from_state_id, to_state_id, is_automatic, conditions,
            required_permission, notification_template, is_active, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['desk_id'],
            $data['from_state_id'] ?? null,
            $data['to_state_id'],
            $data['is_automatic'] ?? false,
            isset($data['conditions']) ? json_encode($data['conditions']) : null,
            $data['required_permission'] ?? null,
            $data['notification_template'] ?? null,
            $data['is_active'] ?? true,
            $data['created_by'] ?? null
        ];
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Actualizar una transición
     */
    public function updateTransition($id, $data)
    {
        $transition = $this->getTransition($id);
        if (!$transition) {
            throw new Exception("Transición no encontrada");
        }
        
        $updateFields = [];
        $params = [];
        
        $allowedFields = [
            'is_automatic', 'conditions', 'required_permission', 
            'notification_template', 'is_active'
        ];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                if ($field === 'conditions' && is_array($data[$field])) {
                    $updateFields[] = "$field = ?";
                    $params[] = json_encode($data[$field]);
                } else {
                    $updateFields[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
        }
        
        if (empty($updateFields)) {
            throw new Exception("No hay campos para actualizar");
        }
        
        $params[] = $id;
        
        $sql = "UPDATE state_transitions SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute($params);
    }
    
    /**
     * Eliminar una transición
     */
    public function deleteTransition($id)
    {
        $stmt = $this->pdo->prepare("DELETE FROM state_transitions WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Obtener una transición específica
     */
    public function getTransition($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM state_transitions WHERE id = ?");
        $stmt->execute([$id]);
        
        $transition = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($transition && $transition['conditions']) {
            $transition['conditions'] = json_decode($transition['conditions'], true);
        }
        
        return $transition;
    }
    
    /**
     * Verificar si una transición es válida
     */
    public function isValidTransition($fromStateId, $toStateId, $userId = null)
    {
        // Obtener información de los estados
        $stmt = $this->pdo->prepare("SELECT desk_id FROM desk_states WHERE id = ?");
        $stmt->execute([$fromStateId]);
        $fromState = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$fromState) {
            return false;
        }
        
        $stmt->execute([$toStateId]);
        $toState = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$toState || $fromState['desk_id'] !== $toState['desk_id']) {
            return false;
        }
        
        // Verificar si existe una transición específica
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM state_transitions 
            WHERE desk_id = ? AND from_state_id = ? AND to_state_id = ? AND is_active = 1
        ");
        $stmt->execute([$fromState['desk_id'], $fromStateId, $toStateId]);
        
        if ($stmt->fetchColumn() > 0) {
            return true;
        }
        
        // Verificar si existe una transición global (desde cualquier estado)
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM state_transitions 
            WHERE desk_id = ? AND from_state_id IS NULL AND to_state_id = ? AND is_active = 1
        ");
        $stmt->execute([$fromState['desk_id'], $toStateId]);
        
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Crear transiciones por defecto para un desk
     */
    public function createDefaultTransitions($deskId)
    {
        // Obtener estados del desk
        $stmt = $this->pdo->prepare("SELECT id, name FROM desk_states WHERE desk_id = ? ORDER BY sort_order");
        $stmt->execute([$deskId]);
        $states = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stateMap = [];
        foreach ($states as $state) {
            $stateMap[$state['name']] = $state['id'];
        }
        
        // Definir transiciones por defecto
        $defaultTransitions = [
            // Desde nuevo
            ['new', 'contacted'],
            
            // Desde contactado
            ['contacted', 'interested'],
            ['contacted', 'not_interested'],
            
            // Desde interesado
            ['interested', 'demo_account'],
            ['interested', 'ftd'],
            
            // Desde demo
            ['demo_account', 'ftd'],
            
            // Desde FTD
            ['ftd', 'client'],
            
            // Transiciones globales (desde cualquier estado)
            [null, 'lost'], // Cualquier estado puede ir a perdido
        ];
        
        foreach ($defaultTransitions as $transition) {
            $fromStateId = $transition[0] ? ($stateMap[$transition[0]] ?? null) : null;
            $toStateId = $stateMap[$transition[1]] ?? null;
            
            if ($toStateId && !$this->transitionExists($deskId, $fromStateId, $toStateId)) {
                try {
                    $this->createTransition([
                        'desk_id' => $deskId,
                        'from_state_id' => $fromStateId,
                        'to_state_id' => $toStateId,
                        'is_active' => true
                    ]);
                } catch (Exception $e) {
                    // Ignorar errores de transiciones duplicadas
                }
            }
        }
    }
    
    /**
     * Obtener matriz de transiciones para visualización
     */
    public function getTransitionMatrix($deskId)
    {
        // Obtener todos los estados
        $stmt = $this->pdo->prepare("SELECT id, name, display_name FROM desk_states WHERE desk_id = ? AND is_active = 1 ORDER BY sort_order");
        $stmt->execute([$deskId]);
        $states = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener todas las transiciones
        $transitions = $this->getTransitionsByDesk($deskId);
        
        // Crear matriz
        $matrix = [];
        foreach ($states as $fromState) {
            $matrix[$fromState['id']] = [];
            foreach ($states as $toState) {
                $matrix[$fromState['id']][$toState['id']] = false;
            }
        }
        
        // Marcar transiciones existentes
        foreach ($transitions as $transition) {
            if ($transition['from_state_id']) {
                $matrix[$transition['from_state_id']][$transition['to_state_id']] = true;
            } else {
                // Transición global - marcar para todos los estados
                foreach ($states as $state) {
                    $matrix[$state['id']][$transition['to_state_id']] = true;
                }
            }
        }
        
        return [
            'states' => $states,
            'matrix' => $matrix,
            'transitions' => $transitions
        ];
    }
    
    /**
     * Verificar si una transición ya existe
     */
    private function transitionExists($deskId, $fromStateId, $toStateId)
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM state_transitions 
            WHERE desk_id = ? AND from_state_id <=> ? AND to_state_id = ?
        ");
        $stmt->execute([$deskId, $fromStateId, $toStateId]);
        
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Validar que los estados pertenezcan al desk
     */
    private function validateStatesBelongToDesk($deskId, $stateIds)
    {
        $placeholders = str_repeat('?,', count($stateIds) - 1) . '?';
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM desk_states WHERE id IN ($placeholders) AND desk_id = ?");
        $stmt->execute(array_merge($stateIds, [$deskId]));
        
        if ($stmt->fetchColumn() !== count($stateIds)) {
            throw new Exception("Uno o más estados no pertenecen al desk especificado");
        }
    }
}
?>