// Tipos de usuario y autenticación
export interface User {
  id: number
  username: string
  first_name: string
  last_name: string
  email: string
  role: string
  status: 'active' | 'inactive' | 'suspended'
  created_at: string
  updated_at: string
}

export interface AuthResponse {
  success: boolean
  message: string
  data?: {
    token: string
    user: User
  }
}

// Tipos de Lead
export interface Lead {
  id: number | null
  first_name: string
  last_name: string
  email: string
  phone?: string
  country?: string
  status: 'new' | 'contacted' | 'qualified' | 'converted' | 'lost'
  source?: string
  desk_id?: number
  desk_name?: string
  assigned_user_id?: number
  assigned_user_first_name?: string
  assigned_user_last_name?: string
  assigned_to?: number
  assigned_to_name?: string
  notes?: string
  created_at: string
  updated_at: string
  last_contact?: string
}

export interface LeadFormData {
  first_name: string
  last_name: string
  email: string
  phone?: string
  country?: string
  desk_id?: number
  last_comment?: string
  last_contact?: string
  campaign?: string
  assigned_user_id?: number
  status?: Lead['status']
  source?: string
}

// Tipos de Desk
export interface Desk {
  id: number
  name: string
  description?: string
  color: string
  status: 'active' | 'inactive'
  max_users: number
  created_at: string
  updated_at: string
  assigned_users: number
  total_leads: number
  converted_leads: number
  conversion_rate: number
  users: User[]
}

// Tipos de Role
export interface Role {
  id: number
  name: string
  display_name: string
  description?: string
  color: string
  is_system: boolean
  permissions_count: number
  users_count: number
  created_at: string
  updated_at: string
}

// Tipos de Trading Account
export interface TradingAccount {
  id: number
  lead_id: number
  account_number: string
  platform: 'mt4' | 'mt5' | 'ctrader' | 'webtrader'
  account_type: 'demo' | 'live'
  balance: number
  equity: number
  margin: number
  free_margin: number
  margin_level: number
  currency: string
  leverage: number
  status: 'active' | 'inactive' | 'suspended'
  created_at: string
  updated_at: string
  
  // Campos relacionados
  lead_name?: string
  lead_email?: string
}

// Tipos de Depósitos y Retiros
export interface Transaction {
  id: number
  lead_id: number
  trading_account_id?: number
  type: 'deposit' | 'withdrawal'
  amount: number
  currency: string
  method: 'credit_card' | 'bank_transfer' | 'crypto' | 'ewallet'
  status: 'pending' | 'approved' | 'rejected' | 'completed'
  reference_number: string
  notes?: string
  processed_by?: number
  processed_at?: string
  created_at: string
  updated_at: string
  
  // Campos relacionados
  lead_name?: string
  processed_by_name?: string
}

// Tipos de Dashboard
export interface DashboardStats {
  total_leads: number
  total_conversions: number
  total_revenue: number
  conversion_rate: number
  active_desks: number
  active_users: number
  leads_trend: number
  conversions_trend: number
  revenue_trend: number
}

export interface ChartData {
  labels: string[]
  datasets: {
    label: string
    data: number[]
    backgroundColor?: string | string[]
    borderColor?: string
    borderWidth?: number
    fill?: boolean
  }[]
}

// Tipos de API Response
export interface ApiResponse<T = any> {
  success: boolean
  message: string
  data?: T
}

export interface PaginatedResponse<T> {
  data: T[]
  pagination: {
    page: number
    limit: number
    total: number
    pages: number
    total_pages: number
  }
  stats?: any
}

// Tipos de formularios
export interface ImportMapping {
  [key: string]: string
}

export interface ImportData {
  headers: string[]
  rows: string[][]
  mapping: ImportMapping
}

// Tipos de filtros
export interface LeadFilters {
  search?: string
  status?: string
  desk_id?: string
  assigned_user_id?: string
  source?: string
  country?: string
  date_from?: string
  date_to?: string
}

export interface UserFilters {
  search?: string
  status?: string
  role?: string
  desk_id?: string
}

export interface DeskFilters {
  search?: string
  status?: string
}

// Tipos de notificaciones
export interface Notification {
  id: number
  title: string
  message: string
  type: 'info' | 'success' | 'warning' | 'error'
  read: boolean
  created_at: string
}

// Tipos de configuración
export interface AppConfig {
  app_name: string
  app_version: string
  api_url: string
  features: {
    trading_accounts: boolean
    deposits_withdrawals: boolean
    notifications: boolean
    reports: boolean
  }
}
