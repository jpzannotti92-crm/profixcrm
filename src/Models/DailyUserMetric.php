<?php

namespace IaTradeCRM\Models;

/**
 * Modelo DailyUserMetric
 * Gestiona las métricas diarias por usuario
 */
class DailyUserMetric extends BaseModel
{
    protected $table = 'daily_user_metrics';
    
    protected $fillable = [
        'user_id', 'desk_id', 'date', 'leads_assigned', 'leads_contacted',
        'leads_converted_demo', 'leads_converted_ftd', 'leads_lost',
        'calls_made', 'calls_answered', 'emails_sent', 'meetings_scheduled',
        'ftd_amount', 'total_deposits', 'commission_earned', 'working_hours',
        'talk_time_minutes'
    ];

    /**
     * Obtiene el usuario asociado
     */
    public function getUser(): ?User
    {
        return User::find($this->user_id);
    }

    /**
     * Obtiene el desk asociado
     */
    public function getDesk(): ?Desk
    {
        return $this->desk_id ? Desk::find($this->desk_id) : null;
    }
}

/**
 * Modelo DailyDeskMetric
 * Gestiona las métricas diarias por desk
 */
class DailyDeskMetric extends BaseModel
{
    protected $table = 'daily_desk_metrics';
    
    protected $fillable = [
        'desk_id', 'date', 'total_leads', 'new_leads', 'contacted_leads',
        'demo_conversions', 'ftd_conversions', 'lost_leads', 'total_calls',
        'answered_calls', 'total_emails', 'meetings_scheduled', 'total_ftd_amount',
        'total_deposits', 'average_ftd', 'contact_rate', 'demo_conversion_rate',
        'ftd_conversion_rate', 'call_answer_rate'
    ];

    /**
     * Obtiene el desk asociado
     */
    public function getDesk(): ?Desk
    {
        return Desk::find($this->desk_id);
    }
}

/**
 * Modelo Target
 * Gestiona los objetivos del sistema
 */
class Target extends BaseModel
{
    protected $table = 'targets';
    
    protected $fillable = [
        'target_type', 'target_id', 'period_type', 'period_start', 'period_end',
        'leads_target', 'contacts_target', 'demos_target', 'ftd_target',
        'revenue_target', 'deposits_target', 'calls_target', 'emails_target',
        'status', 'created_by'
    ];

    /**
     * Obtiene el usuario o desk objetivo
     */
    public function getTarget()
    {
        if ($this->target_type === 'user') {
            return User::find($this->target_id);
        } elseif ($this->target_type === 'desk') {
            return Desk::find($this->target_id);
        }
        return null;
    }
}

/**
 * Modelo Alert
 * Gestiona las alertas del sistema
 */
class Alert extends BaseModel
{
    protected $table = 'alerts';
    
    protected $fillable = [
        'alert_type', 'title', 'message', 'severity', 'user_id', 'desk_id',
        'role_id', 'is_read', 'is_dismissed', 'data'
    ];

    /**
     * Obtiene el usuario asociado
     */
    public function getUser(): ?User
    {
        return $this->user_id ? User::find($this->user_id) : null;
    }

    /**
     * Obtiene el desk asociado
     */
    public function getDesk(): ?Desk
    {
        return $this->desk_id ? Desk::find($this->desk_id) : null;
    }

    /**
     * Obtiene el rol asociado
     */
    public function getRole(): ?Role
    {
        return $this->role_id ? Role::find($this->role_id) : null;
    }
}