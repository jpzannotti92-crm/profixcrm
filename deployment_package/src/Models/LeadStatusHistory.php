<?php

namespace IaTradeCRM\Models;

/**
 * Modelo LeadStatusHistory
 * Gestiona el historial de cambios de estado de leads
 */
class LeadStatusHistory extends BaseModel
{
    protected $table = 'lead_status_history';
    
    protected $fillable = [
        'lead_id', 'old_status', 'new_status', 'changed_by', 'reason'
    ];

    /**
     * Obtiene el lead asociado
     */
    public function getLead(): ?Lead
    {
        return Lead::find($this->lead_id);
    }

    /**
     * Obtiene el usuario que hizo el cambio
     */
    public function getChangedBy(): ?User
    {
        return User::find($this->changed_by);
    }
}

/**
 * Modelo LeadActivity
 * Gestiona las actividades de los leads
 */
class LeadActivity extends BaseModel
{
    protected $table = 'lead_activities';
    
    protected $fillable = [
        'lead_id', 'user_id', 'activity_type', 'subject', 'description',
        'outcome', 'duration_minutes', 'scheduled_at', 'completed_at', 'status'
    ];

    /**
     * Obtiene el lead asociado
     */
    public function getLead(): ?Lead
    {
        return Lead::find($this->lead_id);
    }

    /**
     * Obtiene el usuario asociado
     */
    public function getUser(): ?User
    {
        return User::find($this->user_id);
    }
}

/**
 * Modelo LeadDocument
 * Gestiona los documentos de los leads
 */
class LeadDocument extends BaseModel
{
    protected $table = 'lead_documents';
    
    protected $fillable = [
        'lead_id', 'document_type', 'file_name', 'file_path', 'file_size',
        'mime_type', 'uploaded_by', 'verified', 'verified_by'
    ];

    /**
     * Obtiene el lead asociado
     */
    public function getLead(): ?Lead
    {
        return Lead::find($this->lead_id);
    }

    /**
     * Obtiene el usuario que subió el documento
     */
    public function getUploadedBy(): ?User
    {
        return User::find($this->uploaded_by);
    }
}

/**
 * Modelo LeadAssignment
 * Gestiona las asignaciones de leads
 */
class LeadAssignment extends BaseModel
{
    protected $table = 'lead_assignments';
    
    protected $fillable = [
        'lead_id', 'assigned_from', 'assigned_to', 'desk_id', 'reason', 'assigned_by'
    ];

    /**
     * Obtiene el lead asociado
     */
    public function getLead(): ?Lead
    {
        return Lead::find($this->lead_id);
    }

    /**
     * Obtiene el usuario asignado
     */
    public function getAssignedTo(): ?User
    {
        return User::find($this->assigned_to);
    }
}