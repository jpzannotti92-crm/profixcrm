// Tipos específicos para Lead
export interface Lead {
  id: number | null
  first_name: string
  last_name: string
  email: string
  phone?: string
  country?: string
  city?: string
  company?: string
  position?: string
  status: 'new' | 'contacted' | 'qualified' | 'converted' | 'lost'
  source?: string
  campaign?: string
  desk_id?: number
  desk_name?: string
  assigned_user_id?: number
  assigned_user_first_name?: string
  assigned_user_last_name?: string
  assigned_to?: number
  assigned_to_name?: string
  notes?: string
  budget?: number
  interest_level?: string
  priority?: 'low' | 'medium' | 'high' | 'urgent'
  last_contact?: string
  created_at: string
  updated_at: string
}

export interface LeadFormData {
  first_name: string
  last_name: string
  email: string
  phone?: string
  country?: string
  city?: string
  company?: string
  position?: string
  desk_id?: number
  assigned_user_id?: number
  assigned_to?: number
  status?: Lead['status']
  source?: string
  campaign?: string
  budget?: number
  interest_level?: string
  priority?: Lead['priority']
  notes?: string
  last_contact?: string
}

export interface LeadFilters {
  search?: string
  status?: string
  desk_id?: string
  desk?: string
  assigned_user_id?: string
  assigned_to?: string
  source?: string
  country?: string
  priority?: string
  date_from?: string
  date_to?: string
}

export type LeadStatus = Lead['status']
export type LeadPriority = Lead['priority']
