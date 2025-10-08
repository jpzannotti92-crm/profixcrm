<?php

namespace IaTradeCRM\Models;

/**
 * Modelo Desk
 * Gestiona equipos de trabajo y sus métricas
 */
class Desk extends BaseModel
{
    protected $table = 'desks';
    
    protected $fillable = [
        'name', 'description', 'manager_id', 'status', 'target_monthly',
        'target_daily', 'commission_rate', 'created_by'
    ];

    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';

    /**
     * Obtiene el manager del desk
     */
    public function getManager(): ?User
    {
        if (!$this->manager_id) {
            return null;
        }
        return User::find($this->manager_id);
    }

    /**
     * Obtiene todos los miembros del desk
     */
    public function getMembers(bool $activeOnly = true): array
    {
        $conditions = ['desk_id' => $this->id];
        if ($activeOnly) {
            $conditions['status'] = 'active';
        }
        
        $sql = "SELECT u.*, dm.role_in_desk, dm.joined_at, dm.status as member_status
                FROM users u
                INNER JOIN desk_members dm ON u.id = dm.user_id
                WHERE dm.desk_id = ?";
        
        $params = [$this->id];
        
        if ($activeOnly) {
            $sql .= " AND dm.status = 'active'";
        }
        
        $sql .= " ORDER BY dm.joined_at ASC";
        
        return $this->db->query($sql, $params)->fetchAll();
    }

    /**
     * Obtiene miembros por rol
     */
    public function getMembersByRole(string $role): array
    {
        $sql = "SELECT u.*, dm.role_in_desk, dm.joined_at
                FROM users u
                INNER JOIN desk_members dm ON u.id = dm.user_id
                WHERE dm.desk_id = ? AND dm.role_in_desk = ? AND dm.status = 'active'
                ORDER BY dm.joined_at ASC";
        
        return $this->db->query($sql, [$this->id, $role])->fetchAll();
    }

    /**
     * Agrega un miembro al desk
     */
    public function addMember(int $userId, string $role, int $addedBy): bool
    {
        // Verificar si ya es miembro activo
        $existing = $this->db->query(
            "SELECT id FROM desk_members WHERE desk_id = ? AND user_id = ? AND status = 'active'",
            [$this->id, $userId]
        )->fetch();

        if ($existing) {
            return true; // Ya es miembro
        }

        // Desactivar membresías anteriores
        $this->db->query(
            "UPDATE desk_members SET status = 'inactive', left_at = NOW() 
             WHERE desk_id = ? AND user_id = ? AND status = 'active'",
            [$this->id, $userId]
        );

        // Agregar nueva membresía
        $data = [
            'desk_id' => $this->id,
            'user_id' => $userId,
            'role_in_desk' => $role,
            'status' => 'active'
        ];

        return $this->db->insert('desk_members', $data) > 0;
    }

    /**
     * Remueve un miembro del desk
     */
    public function removeMember(int $userId): bool
    {
        $sql = "UPDATE desk_members 
                SET status = 'inactive', left_at = NOW() 
                WHERE desk_id = ? AND user_id = ? AND status = 'active'";
        
        $stmt = $this->db->query($sql, [$this->id, $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Cambia el rol de un miembro
     */
    public function changeMemberRole(int $userId, string $newRole): bool
    {
        $sql = "UPDATE desk_members 
                SET role_in_desk = ? 
                WHERE desk_id = ? AND user_id = ? AND status = 'active'";
        
        $stmt = $this->db->query($sql, [$newRole, $this->id, $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Obtiene los leads del desk
     */
    public function getLeads(int $limit = null): array
    {
        return Lead::getByDesk($this->id, $limit);
    }

    /**
     * Obtiene las métricas diarias del desk
     */
    public function getDailyMetrics(string $date): ?DailyDeskMetric
    {
        return DailyDeskMetric::where(['desk_id' => $this->id, 'date' => $date]);
    }

    /**
     * Obtiene las métricas del desk para un período
     */
    public function getMetricsForPeriod(string $startDate, string $endDate): array
    {
        $sql = "SELECT * FROM daily_desk_metrics 
                WHERE desk_id = ? AND date BETWEEN ? AND ?
                ORDER BY date ASC";
        
        return DailyDeskMetric::query($sql, [$this->id, $startDate, $endDate]);
    }

    /**
     * Obtiene estadísticas del desk
     */
    public function getStats(string $period = 'month'): array
    {
        $dateCondition = match($period) {
            'today' => "DATE(created_at) = CURDATE()",
            'week' => "created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)",
            'month' => "created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)",
            'quarter' => "created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)",
            'year' => "created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)",
            default => "created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)"
        };

        $sql = "SELECT 
                    COUNT(*) as total_leads,
                    SUM(CASE WHEN status = 'contacted' THEN 1 ELSE 0 END) as contacted_leads,
                    SUM(CASE WHEN status = 'demo_account' THEN 1 ELSE 0 END) as demo_conversions,
                    SUM(CASE WHEN status = 'ftd' OR status = 'client' THEN 1 ELSE 0 END) as ftd_conversions,
                    SUM(ftd_amount) as total_ftd_amount,
                    SUM(total_deposits) as total_deposits,
                    AVG(ftd_amount) as avg_ftd
                FROM leads 
                WHERE desk_id = ? AND {$dateCondition}";
        
        $result = $this->db->query($sql, [$this->id])->fetch();
        
        // Calcular ratios
        $result['contact_rate'] = $result['total_leads'] > 0 ? 
            round(($result['contacted_leads'] / $result['total_leads']) * 100, 2) : 0;
        
        $result['demo_conversion_rate'] = $result['contacted_leads'] > 0 ? 
            round(($result['demo_conversions'] / $result['contacted_leads']) * 100, 2) : 0;
        
        $result['ftd_conversion_rate'] = $result['demo_conversions'] > 0 ? 
            round(($result['ftd_conversions'] / $result['demo_conversions']) * 100, 2) : 0;

        // Progreso hacia objetivos
        $result['monthly_target'] = $this->target_monthly;
        $result['daily_target'] = $this->target_daily;
        $result['target_progress'] = $this->target_monthly > 0 ? 
            round(($result['total_ftd_amount'] / $this->target_monthly) * 100, 2) : 0;

        return $result;
    }

    /**
     * Obtiene el ranking de miembros del desk
     */
    public function getMemberRanking(string $period = 'month'): array
    {
        $dateCondition = match($period) {
            'today' => "DATE(l.created_at) = CURDATE()",
            'week' => "l.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)",
            'month' => "l.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)",
            'quarter' => "l.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)",
            'year' => "l.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)",
            default => "l.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)"
        };

        $sql = "SELECT 
                    u.id, u.first_name, u.last_name, dm.role_in_desk,
                    COUNT(l.id) as total_leads,
                    SUM(CASE WHEN l.status = 'contacted' THEN 1 ELSE 0 END) as contacted_leads,
                    SUM(CASE WHEN l.status = 'ftd' OR l.status = 'client' THEN 1 ELSE 0 END) as conversions,
                    SUM(l.ftd_amount) as total_ftd_amount,
                    AVG(l.ftd_amount) as avg_ftd
                FROM users u
                INNER JOIN desk_members dm ON u.id = dm.user_id
                LEFT JOIN leads l ON u.id = l.assigned_to AND {$dateCondition}
                WHERE dm.desk_id = ? AND dm.status = 'active'
                GROUP BY u.id, u.first_name, u.last_name, dm.role_in_desk
                ORDER BY total_ftd_amount DESC, conversions DESC";
        
        return $this->db->query($sql, [$this->id])->fetchAll();
    }

    /**
     * Obtiene la configuración del desk
     */
    public function getSettings(): array
    {
        $sql = "SELECT setting_key, setting_value FROM desk_settings WHERE desk_id = ?";
        $results = $this->db->query($sql, [$this->id])->fetchAll();
        
        $settings = [];
        foreach ($results as $result) {
            $settings[$result['setting_key']] = $result['setting_value'];
        }
        
        return $settings;
    }

    /**
     * Establece una configuración del desk
     */
    public function setSetting(string $key, string $value): bool
    {
        $existing = $this->db->query(
            "SELECT id FROM desk_settings WHERE desk_id = ? AND setting_key = ?",
            [$this->id, $key]
        )->fetch();

        if ($existing) {
            $sql = "UPDATE desk_settings SET setting_value = ?, updated_at = NOW() 
                    WHERE desk_id = ? AND setting_key = ?";
            $stmt = $this->db->query($sql, [$value, $this->id, $key]);
            return $stmt->rowCount() > 0;
        } else {
            $data = [
                'desk_id' => $this->id,
                'setting_key' => $key,
                'setting_value' => $value
            ];
            return $this->db->insert('desk_settings', $data) > 0;
        }
    }

    /**
     * Obtiene los objetivos del desk
     */
    public function getTargets(string $periodType = 'monthly'): array
    {
        $sql = "SELECT * FROM targets 
                WHERE target_type = 'desk' AND target_id = ? AND period_type = ?
                AND status = 'active'
                ORDER BY period_start DESC";
        
        return Target::query($sql, [$this->id, $periodType]);
    }

    /**
     * Establece un objetivo para el desk
     */
    public function setTarget(array $targetData, int $createdBy): bool
    {
        $targetData['target_type'] = 'desk';
        $targetData['target_id'] = $this->id;
        $targetData['created_by'] = $createdBy;
        
        $target = new Target($targetData);
        return $target->save();
    }

    /**
     * Obtiene desks activos
     */
    public static function getActive(): array
    {
        return static::all(['status' => self::STATUS_ACTIVE], 'name ASC');
    }

    /**
     * Busca desks por criterios
     */
    public static function search(array $filters): array
    {
        $conditions = [];
        $params = [];
        
        if (!empty($filters['status'])) {
            $conditions[] = "status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['manager_id'])) {
            $conditions[] = "manager_id = ?";
            $params[] = $filters['manager_id'];
        }
        
        if (!empty($filters['search'])) {
            $conditions[] = "(name LIKE ? OR description LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$searchTerm, $searchTerm]);
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        
        $sql = "SELECT * FROM desks {$whereClause} ORDER BY name";
        
        return static::query($sql, $params);
    }

    /**
     * Obtiene estadísticas globales de todos los desks
     */
    public static function getGlobalStats(string $period = 'month'): array
    {
        $dateCondition = match($period) {
            'today' => "DATE(l.created_at) = CURDATE()",
            'week' => "l.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)",
            'month' => "l.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)",
            'quarter' => "l.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)",
            'year' => "l.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)",
            default => "l.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)"
        };

        $sql = "SELECT 
                    d.id, d.name,
                    COUNT(l.id) as total_leads,
                    SUM(CASE WHEN l.status = 'contacted' THEN 1 ELSE 0 END) as contacted_leads,
                    SUM(CASE WHEN l.status = 'ftd' OR l.status = 'client' THEN 1 ELSE 0 END) as conversions,
                    SUM(l.ftd_amount) as total_ftd_amount,
                    d.target_monthly,
                    ROUND((SUM(l.ftd_amount) / d.target_monthly) * 100, 2) as target_progress
                FROM desks d
                LEFT JOIN leads l ON d.id = l.desk_id AND {$dateCondition}
                WHERE d.status = 'active'
                GROUP BY d.id, d.name, d.target_monthly
                ORDER BY total_ftd_amount DESC";
        
        $instance = new static();
        return $instance->db->query($sql)->fetchAll();
    }
}