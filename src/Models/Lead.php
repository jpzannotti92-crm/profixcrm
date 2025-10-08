<?php

namespace IaTradeCRM\Models;

/**
 * Modelo Lead
 * Gestiona toda la información y operaciones relacionadas con leads de Forex/CFD
 */
class Lead extends BaseModel
{
    protected $table = 'leads';
    
    protected $fillable = [
        'first_name', 'last_name', 'email', 'phone', 'country_code', 'country', 
        'city', 'timezone', 'language', 'trading_experience', 'capital_range',
        'preferred_instruments', 'risk_tolerance', 'investment_goals', 'status',
        'priority', 'assigned_to', 'desk_id', 'source', 'campaign', 'utm_source',
        'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'referrer_url',
        'landing_page', 'ip_address', 'user_agent', 'first_contact_date',
        'last_contact_date', 'next_followup_date', 'demo_date', 'ftd_date',
        'ftd_amount', 'total_deposits', 'total_withdrawals', 'current_balance',
        'lifetime_value', 'notes', 'tags', 'custom_fields', 'created_by', 'updated_by'
    ];

    protected $hidden = ['ip_address', 'user_agent'];

    /**
     * Estados disponibles para leads
     */
    const STATUS_NEW = 'new';
    const STATUS_CONTACTED = 'contacted';
    const STATUS_INTERESTED = 'interested';
    const STATUS_DEMO_ACCOUNT = 'demo_account';
    const STATUS_NO_ANSWER = 'no_answer';
    const STATUS_CALLBACK = 'callback';
    const STATUS_NOT_INTERESTED = 'not_interested';
    const STATUS_FTD = 'ftd';
    const STATUS_CLIENT = 'client';
    const STATUS_LOST = 'lost';

    /**
     * Prioridades disponibles
     */
    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    /**
     * Niveles de experiencia en trading
     */
    const EXPERIENCE_NONE = 'none';
    const EXPERIENCE_BEGINNER = 'beginner';
    const EXPERIENCE_INTERMEDIATE = 'intermediate';
    const EXPERIENCE_ADVANCED = 'advanced';
    const EXPERIENCE_PROFESSIONAL = 'professional';

    /**
     * Obtiene el nombre completo del lead
     */
    public function getFullName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Obtiene el usuario asignado
     */
    public function getAssignedUser(): ?User
    {
        if (!$this->assigned_to) {
            return null;
        }
        return User::find($this->assigned_to);
    }

    /**
     * Obtiene el desk asignado
     */
    public function getDesk(): ?Desk
    {
        if (!$this->desk_id) {
            return null;
        }
        return Desk::find($this->desk_id);
    }

    /**
     * Obtiene el historial de cambios de estado
     */
    public function getStatusHistory(): array
    {
        return LeadStatusHistory::all(['lead_id' => $this->id], 'changed_at DESC');
    }

    /**
     * Obtiene las actividades del lead
     */
    public function getActivities(int $limit = null): array
    {
        $conditions = ['lead_id' => $this->id];
        return LeadActivity::all($conditions, 'created_at DESC', $limit);
    }

    /**
     * Obtiene los documentos del lead
     */
    public function getDocuments(): array
    {
        return LeadDocument::all(['lead_id' => $this->id], 'uploaded_at DESC');
    }

    /**
     * Cambia el estado del lead
     */
    public function changeStatus(string $newStatus, int $changedBy, string $reason = null): bool
    {
        $oldStatus = $this->status;
        
        if ($oldStatus === $newStatus) {
            return true;
        }

        $this->db->beginTransaction();
        
        try {
            // Actualizar el estado del lead
            $this->status = $newStatus;
            $this->updated_by = $changedBy;
            
            // Actualizar fechas específicas según el estado
            $now = date('Y-m-d H:i:s');
            switch ($newStatus) {
                case self::STATUS_CONTACTED:
                    if (!$this->first_contact_date) {
                        $this->first_contact_date = $now;
                    }
                    $this->last_contact_date = $now;
                    break;
                case self::STATUS_DEMO_ACCOUNT:
                    $this->demo_date = $now;
                    break;
                case self::STATUS_FTD:
                    $this->ftd_date = $now;
                    break;
            }
            
            if (!$this->save()) {
                throw new \Exception('Error al actualizar el estado del lead');
            }

            // Registrar el cambio en el historial
            $historyData = [
                'lead_id' => $this->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'changed_by' => $changedBy,
                'reason' => $reason
            ];
            
            $history = new LeadStatusHistory($historyData);
            if (!$history->save()) {
                throw new \Exception('Error al registrar el historial de cambios');
            }

            $this->db->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->db->rollback();
            return false;
        }
    }

    /**
     * Asigna el lead a un usuario
     */
    public function assignTo(int $userId, int $assignedBy, int $deskId = null, string $reason = null): bool
    {
        $this->db->beginTransaction();
        
        try {
            $oldAssignedTo = $this->assigned_to;
            
            // Actualizar la asignación
            $this->assigned_to = $userId;
            $this->desk_id = $deskId ?: $this->desk_id;
            $this->updated_by = $assignedBy;
            
            if (!$this->save()) {
                throw new \Exception('Error al asignar el lead');
            }

            // Registrar la asignación
            $assignmentData = [
                'lead_id' => $this->id,
                'assigned_from' => $oldAssignedTo,
                'assigned_to' => $userId,
                'desk_id' => $deskId,
                'reason' => $reason,
                'assigned_by' => $assignedBy
            ];
            
            $assignment = new LeadAssignment($assignmentData);
            if (!$assignment->save()) {
                throw new \Exception('Error al registrar la asignación');
            }

            $this->db->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->db->rollback();
            return false;
        }
    }

    /**
     * Agrega una actividad al lead
     */
    public function addActivity(array $activityData): bool
    {
        $activityData['lead_id'] = $this->id;
        $activity = new LeadActivity($activityData);
        return $activity->save();
    }

    /**
     * Agrega una nota al lead
     */
    public function addNote(string $note, int $userId): bool
    {
        return $this->addActivity([
            'user_id' => $userId,
            'activity_type' => 'note',
            'description' => $note,
            'status' => 'completed'
        ]);
    }

    /**
     * Programa una tarea de seguimiento
     */
    public function scheduleFollowup(string $subject, string $description, string $scheduledAt, int $userId): bool
    {
        $this->next_followup_date = $scheduledAt;
        $this->save();
        
        return $this->addActivity([
            'user_id' => $userId,
            'activity_type' => 'task',
            'subject' => $subject,
            'description' => $description,
            'scheduled_at' => $scheduledAt,
            'status' => 'pending'
        ]);
    }

    /**
     * Registra un depósito
     */
    public function recordDeposit(float $amount, int $userId, bool $isFirstDeposit = false): bool
    {
        $this->db->beginTransaction();
        
        try {
            // Actualizar totales
            $this->total_deposits += $amount;
            $this->current_balance += $amount;
            $this->lifetime_value += $amount;
            
            if ($isFirstDeposit) {
                $this->ftd_amount = $amount;
                $this->ftd_date = date('Y-m-d H:i:s');
                $this->changeStatus(self::STATUS_FTD, $userId, 'Primer depósito registrado');
            }
            
            if (!$this->save()) {
                throw new \Exception('Error al actualizar el lead');
            }

            // Registrar la actividad
            $this->addActivity([
                'user_id' => $userId,
                'activity_type' => 'deposit',
                'subject' => $isFirstDeposit ? 'Primer Depósito (FTD)' : 'Depósito',
                'description' => "Depósito de $" . number_format($amount, 2),
                'status' => 'completed'
            ]);

            $this->db->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->db->rollback();
            return false;
        }
    }

    /**
     * Obtiene leads por estado
     */
    public static function getByStatus(string $status, int $limit = null): array
    {
        return static::all(['status' => $status], 'updated_at DESC', $limit);
    }

    /**
     * Obtiene leads asignados a un usuario
     */
    public static function getByAssignedUser(int $userId, int $limit = null): array
    {
        return static::all(['assigned_to' => $userId], 'priority DESC, updated_at DESC', $limit);
    }

    /**
     * Obtiene leads por desk
     */
    public static function getByDesk(int $deskId, int $limit = null): array
    {
        return static::all(['desk_id' => $deskId], 'priority DESC, updated_at DESC', $limit);
    }

    /**
     * Obtiene leads que requieren seguimiento
     */
    public static function getRequiringFollowup(): array
    {
        $sql = "SELECT * FROM leads 
                WHERE next_followup_date <= NOW() 
                AND status NOT IN ('ftd', 'client', 'lost', 'not_interested')
                ORDER BY priority DESC, next_followup_date ASC";
        
        return static::query($sql);
    }

    /**
     * Obtiene estadísticas de conversión
     */
    public static function getConversionStats(array $filters = []): array
    {
        $whereClause = '';
        $params = [];
        
        if (!empty($filters)) {
            $conditions = [];
            foreach ($filters as $field => $value) {
                $conditions[] = "{$field} = ?";
                $params[] = $value;
            }
            $whereClause = 'WHERE ' . implode(' AND ', $conditions);
        }

        $sql = "SELECT 
                    COUNT(*) as total_leads,
                    SUM(CASE WHEN status = 'contacted' THEN 1 ELSE 0 END) as contacted,
                    SUM(CASE WHEN status = 'demo_account' THEN 1 ELSE 0 END) as demos,
                    SUM(CASE WHEN status = 'ftd' OR status = 'client' THEN 1 ELSE 0 END) as conversions,
                    SUM(CASE WHEN status = 'lost' OR status = 'not_interested' THEN 1 ELSE 0 END) as lost,
                    AVG(ftd_amount) as avg_ftd,
                    SUM(total_deposits) as total_deposits
                FROM leads {$whereClause}";
        
        $result = static::queryRaw($sql, $params);
        return $result[0] ?? [];
    }

    /**
     * Busca leads con filtros avanzados
     */
    public static function search(array $filters, int $page = 1, int $perPage = 50): array
    {
        $conditions = [];
        $params = [];
        
        // Filtros básicos
        if (!empty($filters['status'])) {
            $conditions[] = "status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['assigned_to'])) {
            $conditions[] = "assigned_to = ?";
            $params[] = $filters['assigned_to'];
        }
        
        if (!empty($filters['desk_id'])) {
            $conditions[] = "desk_id = ?";
            $params[] = $filters['desk_id'];
        }
        
        if (!empty($filters['source'])) {
            $conditions[] = "source = ?";
            $params[] = $filters['source'];
        }
        
        if (!empty($filters['priority'])) {
            $conditions[] = "priority = ?";
            $params[] = $filters['priority'];
        }
        
        // Filtro de texto (nombre, email, teléfono)
        if (!empty($filters['search'])) {
            $conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }
        
        // Filtro de fechas
        if (!empty($filters['date_from'])) {
            $conditions[] = "created_at >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $conditions[] = "created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT * FROM leads {$whereClause} 
                ORDER BY priority DESC, created_at DESC 
                LIMIT {$perPage} OFFSET {$offset}";
        
        return static::query($sql, $params);
    }

    /**
     * Obtiene el conteo de leads por estado
     */
    public static function getStatusCounts(array $filters = []): array
    {
        $whereClause = '';
        $params = [];
        
        if (!empty($filters)) {
            $conditions = [];
            foreach ($filters as $field => $value) {
                if ($field !== 'status') { // Excluir el filtro de estado para obtener todos los conteos
                    $conditions[] = "{$field} = ?";
                    $params[] = $value;
                }
            }
            if (!empty($conditions)) {
                $whereClause = 'WHERE ' . implode(' AND ', $conditions);
            }
        }

        $sql = "SELECT status, COUNT(*) as count 
                FROM leads {$whereClause} 
                GROUP BY status";
        
        $results = static::queryRaw($sql, $params);
        
        $counts = [];
        foreach ($results as $result) {
            $counts[$result['status']] = (int) $result['count'];
        }
        
        return $counts;
    }

    /**
     * Busca un lead por email
     */
    public static function findByEmail(string $email): ?self
    {
        return static::where(['email' => $email]);
    }
}