import { useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { 
  ArrowLeftIcon,
  PencilIcon,
  PhoneIcon,
  EnvelopeIcon,
  MapPinIcon,
  CalendarIcon,
  UserIcon,
  ClockIcon,
  XCircleIcon,
  ChartBarIcon,
  ArrowTrendingUpIcon,
  CurrencyDollarIcon,
  ComputerDesktopIcon,
  BanknotesIcon,
  ChevronDownIcon,
  ChevronUpIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  InformationCircleIcon,
  DocumentTextIcon,
  TagIcon,
  GlobeAltIcon,
  MegaphoneIcon
} from '@heroicons/react/24/outline'
import { leadsApi, leadTradingLinkApi } from '../../services/api'
import LoadingSpinner from '../../components/ui/LoadingSpinner'
import Pagination from '../../components/ui/Pagination'
import LeadActivities from '../../components/leads/LeadActivities'
import DynamicStateSelector from '../../components/leads/DynamicStateSelector'
import { cn } from '../../utils/cn'
import toast from 'react-hot-toast'
import { useAuth } from '../../contexts/AuthContext'
import authService from '../../services/authService.js'

// Type definitions
interface TradingAccount {
  id: number
  account_number: string
  platform: string
  account_type: string
  leverage: string
  status: string
  balance: number
  equity: number
  margin: number
  profit_loss: number
  client_email: string
  created_at: string
}

interface FinancialTransaction {
  id: number
  type: 'deposit' | 'withdrawal'
  amount: number
  currency: string
  status: string
  agent_name: string
  processed_by_name: string
  created_at: string
  payment_method?: string
  notes?: string
}

interface FinancialData {
  success: boolean
  data: {
    summary: {
      total_deposits: number
      total_withdrawals: number
      net_amount: number
      transaction_count: number
    }
    transactions: FinancialTransaction[]
  }
}

interface Position {
  id: number
  symbol: string
  type: 'buy' | 'sell'
  volume: number
  open_price: number
  current_price?: number
  profit_loss: number
  swap: number
  commission: number
  open_time: string
  close_time?: string
  status: 'open' | 'closed' | 'pending'
  account_number: string
  platform: string
}

interface PositionsData {
  success: boolean
  data: {
    open_positions: Position[]
    closed_positions: Position[]
    pending_positions: Position[]
  }
}

export default function LeadDetailPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { hasPermission } = useAuth()
  const [isEditing, setIsEditing] = useState(false)
  const [showAssignModal, setShowAssignModal] = useState(false)
  // Ajuste de estado inicial de las secciones desplegables:
  // Por defecto, todas colapsadas excepto "Información del Lead" y "Actividades y Comunicaciones" (esta última no es colapsable actualmente).
  const [isTradingAccountsCollapsed, setIsTradingAccountsCollapsed] = useState(true)
  const [isFinancesCollapsed, setIsFinancesCollapsed] = useState(true)
  const [isInfoCollapsed, setIsInfoCollapsed] = useState(false)
  const [isPositionsCollapsed, setIsPositionsCollapsed] = useState(true)
  const [activePositionsTab, setActivePositionsTab] = useState<'open' | 'closed' | 'pending'>('open')
  
  // Estados de paginación para posiciones
  const [openPositionsPage, setOpenPositionsPage] = useState(1)
  const [closedPositionsPage, setClosedPositionsPage] = useState(1)
  const [pendingPositionsPage, setPendingPositionsPage] = useState(1)
  const positionsPerPage = 5

  // Consulta para obtener datos del lead
  const { data: lead, isLoading } = useQuery({
    queryKey: ['lead', id],
    queryFn: () => leadsApi.getLead(Number(id)),
    enabled: !!id,
  })

  // Consulta para obtener datos financieros del lead
  const { data: financesData, isLoading: isLoadingFinances } = useQuery({
    queryKey: ['lead-finances', id],
    queryFn: async () => {
      const apiUrl = await authService.getApiUrl();
      const token = localStorage.getItem('auth_token');
      return fetch(`${apiUrl}/lead-finances.php?lead_id=${id}`, {
        headers: {
          'Authorization': token ? `Bearer ${token}` : '',
          'Accept': 'application/json'
        }
      })
        .then(res => res.json()) as Promise<FinancialData>;
    },
    enabled: !!id,
    refetchOnWindowFocus: false
  })

  // Cargar solo los usuarios pertenecientes al desk del lead
  const { data: deskData } = useQuery({
    queryKey: ['desk', lead?.desk_id],
    queryFn: async () => {
      const apiUrl = await authService.getApiUrl()
      const token = localStorage.getItem('auth_token')
      const res = await fetch(`${apiUrl}/desks.php?id=${lead?.desk_id}`, {
        headers: {
          'Authorization': token ? `Bearer ${token}` : '',
          'Accept': 'application/json'
        }
      })
      return res.json()
    },
    enabled: !!lead?.desk_id,
    refetchOnWindowFocus: false
  })

  const { data: tradingAccountsData, isLoading: isLoadingTradingAccounts } = useQuery({
    queryKey: ['trading-accounts', id],
    queryFn: () => leadTradingLinkApi.getLeadAccounts(Number(id)),
    enabled: !!id,
    refetchOnWindowFocus: false
  })

  // Consulta para obtener posiciones de trading (simulada por ahora)
  const { data: positionsData, isLoading: isLoadingPositions } = useQuery({
    queryKey: ['positions', id],
    queryFn: () => {
      // Simulamos más datos de posiciones para demostrar la paginación
      return Promise.resolve({
        success: true,
        data: {
          open_positions: [
            {
              id: 1,
              symbol: 'EURUSD',
              type: 'buy' as const,
              volume: 0.1,
              open_price: 1.0850,
              current_price: 1.0875,
              profit_loss: 25.00,
              swap: -0.50,
              commission: -2.00,
              open_time: '2024-01-15T10:30:00Z',
              status: 'open' as const,
              account_number: '12345678',
              platform: 'MetaTrader 5'
            },
            {
              id: 2,
              symbol: 'GBPUSD',
              type: 'sell' as const,
              volume: 0.2,
              open_price: 1.2650,
              current_price: 1.2630,
              profit_loss: 40.00,
              swap: -1.20,
              commission: -4.00,
              open_time: '2024-01-15T14:15:00Z',
              status: 'open' as const,
              account_number: '12345678',
              platform: 'MetaTrader 5'
            },
            {
              id: 5,
              symbol: 'USDCAD',
              type: 'buy' as const,
              volume: 0.15,
              open_price: 1.3450,
              current_price: 1.3465,
              profit_loss: 15.75,
              swap: -0.80,
              commission: -3.00,
              open_time: '2024-01-15T16:45:00Z',
              status: 'open' as const,
              account_number: '12345678',
              platform: 'MetaTrader 5'
            },
            {
              id: 6,
              symbol: 'GOLD',
              type: 'sell' as const,
              volume: 0.05,
              open_price: 2025.50,
              current_price: 2020.30,
              profit_loss: 26.00,
              swap: -1.50,
              commission: -2.50,
              open_time: '2024-01-15T18:20:00Z',
              status: 'open' as const,
              account_number: '12345678',
              platform: 'MetaTrader 5'
            },
            {
              id: 7,
              symbol: 'NZDUSD',
              type: 'buy' as const,
              volume: 0.12,
              open_price: 0.6180,
              current_price: 0.6195,
              profit_loss: 18.00,
              swap: -0.60,
              commission: -2.40,
              open_time: '2024-01-15T20:10:00Z',
              status: 'open' as const,
              account_number: '12345678',
              platform: 'MetaTrader 5'
            },
            {
              id: 8,
              symbol: 'EURGBP',
              type: 'sell' as const,
              volume: 0.08,
              open_price: 0.8580,
              current_price: 0.8570,
              profit_loss: 8.00,
              swap: -0.40,
              commission: -1.60,
              open_time: '2024-01-15T21:30:00Z',
              status: 'open' as const,
              account_number: '12345678',
              platform: 'MetaTrader 5'
            }
          ],
          closed_positions: [
            {
              id: 3,
              symbol: 'USDJPY',
              type: 'buy' as const,
              volume: 0.15,
              open_price: 148.50,
              current_price: 149.20,
              profit_loss: 70.50,
              swap: -2.30,
              commission: -3.00,
              open_time: '2024-01-14T09:00:00Z',
              close_time: '2024-01-14T16:30:00Z',
              status: 'closed' as const,
              account_number: '12345678',
              platform: 'MetaTrader 5'
            },
            {
              id: 9,
              symbol: 'EURJPY',
              type: 'sell' as const,
              volume: 0.10,
              open_price: 161.20,
              current_price: 160.85,
              profit_loss: 35.00,
              swap: -1.80,
              commission: -2.00,
              open_time: '2024-01-13T11:15:00Z',
              close_time: '2024-01-13T17:45:00Z',
              status: 'closed' as const,
              account_number: '12345678',
              platform: 'MetaTrader 5'
            },
            {
              id: 10,
              symbol: 'GBPJPY',
              type: 'buy' as const,
              volume: 0.08,
              open_price: 187.90,
              current_price: 188.45,
              profit_loss: 44.00,
              swap: -2.10,
              commission: -1.60,
              open_time: '2024-01-12T14:20:00Z',
              close_time: '2024-01-12T19:30:00Z',
              status: 'closed' as const,
              account_number: '12345678',
              platform: 'MetaTrader 5'
            },
            {
              id: 11,
              symbol: 'AUDJPY',
              type: 'sell' as const,
              volume: 0.12,
              open_price: 97.80,
              current_price: 97.45,
              profit_loss: 42.00,
              swap: -1.40,
              commission: -2.40,
              open_time: '2024-01-11T08:30:00Z',
              close_time: '2024-01-11T15:15:00Z',
              status: 'closed' as const,
              account_number: '12345678',
              platform: 'MetaTrader 5'
            },
            {
              id: 12,
              symbol: 'USDCHF',
              type: 'buy' as const,
              volume: 0.18,
              open_price: 0.8720,
              current_price: 0.8745,
              profit_loss: 45.00,
              swap: -0.90,
              commission: -3.60,
              open_time: '2024-01-10T13:45:00Z',
              close_time: '2024-01-10T18:20:00Z',
              status: 'closed' as const,
              account_number: '12345678',
              platform: 'MetaTrader 5'
            },
            {
              id: 13,
              symbol: 'CADCHF',
              type: 'sell' as const,
              volume: 0.06,
              open_price: 0.6490,
              current_price: 0.6475,
              profit_loss: 9.00,
              swap: -0.30,
              commission: -1.20,
              open_time: '2024-01-09T10:10:00Z',
              close_time: '2024-01-09T16:40:00Z',
              status: 'closed' as const,
              account_number: '12345678',
              platform: 'MetaTrader 5'
            }
          ],
          pending_positions: [
            {
              id: 4,
              symbol: 'AUDUSD',
              type: 'buy' as const,
              volume: 0.1,
              open_price: 0.6750,
              profit_loss: 0,
              swap: 0,
              commission: 0,
              open_time: '2024-01-15T18:00:00Z',
              status: 'pending' as const,
              account_number: '12345678',
              platform: 'MetaTrader 5'
            },
            {
              id: 14,
              symbol: 'CHFJPY',
              type: 'sell' as const,
              volume: 0.07,
              open_price: 170.25,
              profit_loss: 0,
              swap: 0,
              commission: 0,
              open_time: '2024-01-15T19:30:00Z',
              status: 'pending' as const,
              account_number: '12345678',
              platform: 'MetaTrader 5'
            },
            {
              id: 15,
              symbol: 'SILVER',
              type: 'buy' as const,
              volume: 0.20,
              open_price: 23.45,
              profit_loss: 0,
              swap: 0,
              commission: 0,
              open_time: '2024-01-15T20:45:00Z',
              status: 'pending' as const,
              account_number: '12345678',
              platform: 'MetaTrader 5'
            }
          ]
        }
      } as PositionsData)
    },
    enabled: !!id,
    refetchOnWindowFocus: false,
    refetchInterval: 5000 // Actualizar cada 5 segundos para simular tiempo real
  })

  // Función para manejar cambio de estado del lead (ahora manejado por DynamicStateSelector)
  const handleLeadStateChange = (_newStateId: number, newStateName: string) => {
    // Invalidar queries para actualizar la información del lead
    queryClient.invalidateQueries({ queryKey: ['lead', id] })
    queryClient.invalidateQueries({ queryKey: ['leads'] })
    toast.success(`Estado cambiado a: ${newStateName}`)
  }

  const updateLeadMutation = useMutation({
    mutationFn: (updatedLead: any) => leadsApi.update(Number(id), updatedLead),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['lead', id] })
      setIsEditing(false)
      toast.success('Lead actualizado correctamente')
    },
    onError: () => {
      toast.error('Error al actualizar el lead')
    }
  })

  const assignLeadMutation = useMutation({
    mutationFn: (userId: number) => leadsApi.update(Number(id), { assigned_to: userId }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['lead', id] })
      queryClient.invalidateQueries({ queryKey: ['leads'] })
      setShowAssignModal(false)
      toast.success('Lead asignado correctamente')
    },
    onError: (error: any) => {
      console.error('Error al asignar lead:', error)
      const errorMessage = error.response?.data?.message || error.message || 'Error desconocido'
      toast.error(`Error al asignar el lead: ${errorMessage}`)
    }
  })

  const unlinkTradingAccountMutation = useMutation({
    mutationFn: (accountId: number) => leadTradingLinkApi.unlinkAccount(accountId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['trading-accounts', id] })
      toast.success('Cuenta desvinculada correctamente')
    },
    onError: () => {
      toast.error('Error al desvincular la cuenta')
    }
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    const formData = new FormData(e.target as HTMLFormElement)
    const updatedLead = {
      name: formData.get('name'),
      email: formData.get('email'),
      phone: formData.get('phone'),
      country: formData.get('country'),
      status: formData.get('status'),
      source: formData.get('source'),
      campaign: formData.get('campaign'),
      assigned_to: formData.get('assigned_to') ? Number(formData.get('assigned_to')) : null
    }
    updateLeadMutation.mutate(updatedLead)
  }

  const handleAssignLead = (userId: number) => {
    assignLeadMutation.mutate(userId)
  }

  // Funciones de paginación para posiciones
  const getPaginatedPositions = (positions: Position[], page: number) => {
    const startIndex = (page - 1) * positionsPerPage
    const endIndex = startIndex + positionsPerPage
    return positions.slice(startIndex, endIndex)
  }

  const getTotalPages = (positions: Position[]) => {
    return Math.ceil(positions.length / positionsPerPage)
  }

  // Obtener posiciones paginadas para cada pestaña
  const paginatedOpenPositions = positionsData?.data?.open_positions 
    ? getPaginatedPositions(positionsData.data.open_positions, openPositionsPage)
    : []
  
  const paginatedClosedPositions = positionsData?.data?.closed_positions 
    ? getPaginatedPositions(positionsData.data.closed_positions, closedPositionsPage)
    : []
  
  const paginatedPendingPositions = positionsData?.data?.pending_positions 
    ? getPaginatedPositions(positionsData.data.pending_positions, pendingPositionsPage)
    : []

  // Calcular total de páginas para cada pestaña
  const totalOpenPages = positionsData?.data?.open_positions 
    ? getTotalPages(positionsData.data.open_positions) 
    : 0
  
  const totalClosedPages = positionsData?.data?.closed_positions 
    ? getTotalPages(positionsData.data.closed_positions) 
    : 0
  
  const totalPendingPages = positionsData?.data?.pending_positions 
    ? getTotalPages(positionsData.data.pending_positions) 
    : 0

  if (isLoading) {
    return (
      <div className="flex justify-center items-center min-h-screen">
        <div className="text-center">
          <LoadingSpinner />
          <p className="mt-4 text-secondary-600 dark:text-secondary-400">Cargando información del lead...</p>
        </div>
      </div>
    )
  }

  if (!lead) {
    return (
      <div className="text-center py-12">
        <ExclamationTriangleIcon className="w-16 h-16 text-secondary-400 mx-auto mb-4" />
        <h2 className="text-2xl font-bold text-secondary-900 dark:text-white mb-4">
          Lead no encontrado
        </h2>
        <p className="text-secondary-600 dark:text-secondary-400 mb-6">
          El lead que buscas no existe o ha sido eliminado.
        </p>
        <button
          onClick={() => navigate('/leads')}
          className="btn btn-primary"
        >
          <ArrowLeftIcon className="w-4 h-4 mr-2" />
          Volver a Leads
        </button>
      </div>
    )
  }

  return (
    <div className="space-y-6 max-w-7xl mx-auto">
      {/* Header Mejorado */}
      <div className="bg-white dark:bg-secondary-900 rounded-xl shadow-sm border border-secondary-200 dark:border-secondary-700 p-6">
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-4">
            <button
              onClick={() => navigate('/leads')}
              className="p-2 text-secondary-400 hover:text-secondary-600 dark:hover:text-secondary-300 transition-colors rounded-lg hover:bg-secondary-100 dark:hover:bg-secondary-800"
            >
              <ArrowLeftIcon className="w-5 h-5" />
            </button>
            <div className="flex items-center space-x-4">
              <div className="w-12 h-12 bg-gradient-to-br from-primary-500 to-primary-600 rounded-xl flex items-center justify-center text-white font-bold text-lg">
                {lead.name?.charAt(0)?.toUpperCase() || 'L'}
              </div>
              <div>
                <h1 className="text-2xl font-bold text-secondary-900 dark:text-white">
                  {lead.name}
                </h1>
                <div className="flex items-center space-x-3 mt-1">
                  <p className="text-secondary-500 dark:text-secondary-400">
                    Lead #{lead.id}
                  </p>
                  <span className="text-secondary-300 dark:text-secondary-600">•</span>
                  <p className="text-secondary-500 dark:text-secondary-400 flex items-center">
                    <CalendarIcon className="w-4 h-4 mr-1" />
                    {new Date(lead.created_at).toLocaleDateString('es-ES')}
                  </p>
                </div>
              </div>
            </div>
          </div>
          <div className="flex items-center space-x-3">
            <DynamicStateSelector
              leadId={Number(id)}
              currentStateName={lead.status}
              onStateChange={handleLeadStateChange}
              showHistory={true}
              disabled={isEditing}
            />
            <button
              onClick={() => setIsEditing(!isEditing)}
              className={cn(
                "btn transition-all duration-200",
                isEditing 
                  ? "btn-secondary" 
                  : "btn-primary hover:shadow-lg"
              )}
            >
              <PencilIcon className="w-4 h-4 mr-2" />
              {isEditing ? 'Cancelar' : 'Editar'}
            </button>
          </div>


        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Columna Principal */}
        <div className="lg:col-span-2 space-y-6">
          
          {/* Información del Lead */}
          <div className="card">
            <div 
              className="card-header cursor-pointer hover:bg-secondary-50 dark:hover:bg-secondary-800 transition-colors"
              onClick={() => setIsInfoCollapsed(!isInfoCollapsed)}
            >
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-3">
                  <div className="w-8 h-8 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center">
                    <UserIcon className="w-4 h-4 text-blue-600 dark:text-blue-400" />
                  </div>
                  <h3 className="text-lg font-semibold text-secondary-900 dark:text-white">
                    Información del Lead
                  </h3>
                </div>
                {isInfoCollapsed ? (
                  <ChevronDownIcon className="w-5 h-5 text-secondary-400" />
                ) : (
                  <ChevronUpIcon className="w-5 h-5 text-secondary-400" />
                )}
              </div>
            </div>
            
            {!isInfoCollapsed && (
              <div className="card-body">
                {isEditing ? (
                  <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                      <div>
                        <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                          Nombre Completo
                        </label>
                        <input
                          type="text"
                          name="name"
                          defaultValue={lead.name}
                          className="input"
                          required
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                          Email
                        </label>
                        <input
                          type="email"
                          name="email"
                          defaultValue={lead.email}
                          className="input"
                          required
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                          Teléfono
                        </label>
                        <input
                          type="tel"
                          name="phone"
                          defaultValue={lead.phone}
                          className="input"
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                          País
                        </label>
                        <input
                          type="text"
                          name="country"
                          defaultValue={lead.country}
                          className="input"
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                          Estado
                        </label>
                        <select name="status" defaultValue={lead.status} className="input">
                          <option value="new">Nuevo</option>
                          <option value="contacted">Contactado</option>
                          <option value="qualified">Calificado</option>
                          <option value="converted">Convertido</option>
                          <option value="lost">Perdido</option>
                        </select>
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                          Fuente
                        </label>
                        <input
                          type="text"
                          name="source"
                          defaultValue={lead.source}
                          className="input"
                        />
                      </div>
                      <div className="md:col-span-2">
                        <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                          Campaña
                        </label>
                        <input
                          type="text"
                          name="campaign"
                          defaultValue={lead.campaign}
                          className="input"
                        />
                      </div>
                    </div>
                    <div className="flex justify-end space-x-3 pt-4 border-t border-secondary-200 dark:border-secondary-700">
                      <button
                        type="button"
                        onClick={() => setIsEditing(false)}
                        className="btn btn-secondary"
                      >
                        Cancelar
                      </button>
                      <button
                        type="submit"
                        disabled={updateLeadMutation.isPending}
                        className="btn btn-primary"
                      >
                        {updateLeadMutation.isPending ? (
                          <>
                            <LoadingSpinner />
                            <span className="ml-2">Guardando...</span>
                          </>
                        ) : (
                          'Guardar Cambios'
                        )}
                      </button>
                    </div>
                  </form>
                ) : (
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="space-y-4">
                      <div className="flex items-center space-x-3 p-3 bg-secondary-50 dark:bg-secondary-800 rounded-lg">
                        <EnvelopeIcon className="w-5 h-5 text-secondary-400" />
                        <div>
                          <p className="text-sm text-secondary-500 dark:text-secondary-400">Email</p>
                          <p className="font-medium text-secondary-900 dark:text-white">{lead.email}</p>
                        </div>
                      </div>
                      <div className="flex items-center space-x-3 p-3 bg-secondary-50 dark:bg-secondary-800 rounded-lg">
                        <PhoneIcon className="w-5 h-5 text-secondary-400" />
                        <div>
                          <p className="text-sm text-secondary-500 dark:text-secondary-400">Teléfono</p>
                          <p className="font-medium text-secondary-900 dark:text-white">{lead.phone || 'No especificado'}</p>
                        </div>
                      </div>
                      <div className="flex items-center space-x-3 p-3 bg-secondary-50 dark:bg-secondary-800 rounded-lg">
                        <MapPinIcon className="w-5 h-5 text-secondary-400" />
                        <div>
                          <p className="text-sm text-secondary-500 dark:text-secondary-400">País</p>
                          <p className="font-medium text-secondary-900 dark:text-white">{lead.country || 'No especificado'}</p>
                        </div>
                      </div>
                    </div>
                    <div className="space-y-4">
                      <div className="flex items-center space-x-3 p-3 bg-secondary-50 dark:bg-secondary-800 rounded-lg">
                        <GlobeAltIcon className="w-5 h-5 text-secondary-400" />
                        <div>
                          <p className="text-sm text-secondary-500 dark:text-secondary-400">Fuente</p>
                          <p className="font-medium text-secondary-900 dark:text-white">{lead.source || 'No especificado'}</p>
                        </div>
                      </div>
                      <div className="flex items-center space-x-3 p-3 bg-secondary-50 dark:bg-secondary-800 rounded-lg">
                        <MegaphoneIcon className="w-5 h-5 text-secondary-400" />
                        <div>
                          <p className="text-sm text-secondary-500 dark:text-secondary-400">Campaña</p>
                          <p className="font-medium text-secondary-900 dark:text-white">{lead.campaign || 'No especificado'}</p>
                        </div>
                      </div>
                      <div className="flex items-center space-x-3 p-3 bg-secondary-50 dark:bg-secondary-800 rounded-lg">
                        <UserIcon className="w-5 h-5 text-secondary-400" />
                        <div>
                          <p className="text-sm text-secondary-500 dark:text-secondary-400">Asignado a</p>
                          <p className="font-medium text-secondary-900 dark:text-white">
                            {lead.assigned_to_name || 'Sin asignar'}
                          </p>
                        </div>
                      </div>
                    </div>
                  </div>
                )}
              </div>
            )}
          </div>

          {/* Sección de Actividades y Comunicaciones */}
          <LeadActivities 
            leadId={lead.id} 
            onLeadStatusChange={(newStatus) => handleLeadStateChange(0, newStatus)}
          />

          {/* Sección de Finanzas */}
          <div className="card">
            <div 
              className="card-header cursor-pointer hover:bg-secondary-50 dark:hover:bg-secondary-800 transition-colors"
              onClick={() => setIsFinancesCollapsed(!isFinancesCollapsed)}
            >
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-3">
                  <div className="w-8 h-8 bg-green-100 dark:bg-green-900 rounded-lg flex items-center justify-center">
                    <BanknotesIcon className="w-4 h-4 text-green-600 dark:text-green-400" />
                  </div>
                  <h3 className="text-lg font-semibold text-secondary-900 dark:text-white">
                    Depósitos y Retiros
                  </h3>
                  {financesData?.data?.summary && (
                    <span className="bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200 px-2 py-1 rounded-full text-xs font-medium">
                      {financesData.data.summary.transaction_count} transacciones
                    </span>
                  )}
                </div>
                {isFinancesCollapsed ? (
                  <ChevronDownIcon className="w-5 h-5 text-secondary-400" />
                ) : (
                  <ChevronUpIcon className="w-5 h-5 text-secondary-400" />
                )}
              </div>
            </div>
            
            {!isFinancesCollapsed && (
              <div className="card-body">
                {isLoadingFinances ? (
                  <div className="flex justify-center py-8">
                    <div className="text-center">
                      <LoadingSpinner />
                      <p className="mt-2 text-sm text-secondary-500 dark:text-secondary-400">
                        Cargando información financiera...
                      </p>
                    </div>
                  </div>
                ) : financesData?.success && financesData?.data ? (
                  <div className="space-y-6">
                    {/* Resumen Financiero */}
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                      <div className="bg-gradient-to-br from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 rounded-xl p-4 border border-green-200 dark:border-green-800">
                        <div className="flex items-center justify-between">
                          <div>
                            <p className="text-sm font-medium text-green-600 dark:text-green-400">Total Depósitos</p>
                            <p className="text-2xl font-bold text-green-700 dark:text-green-300">
                              ${(financesData.data.summary.total_deposits || 0).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                            </p>
                          </div>
                          <ArrowTrendingUpIcon className="w-8 h-8 text-green-500" />
                        </div>
                      </div>

                      <div className="bg-gradient-to-br from-red-50 to-rose-50 dark:from-red-900/20 dark:to-rose-900/20 rounded-xl p-4 border border-red-200 dark:border-red-800">
                        <div className="flex items-center justify-between">
                          <div>
                            <p className="text-sm font-medium text-red-600 dark:text-red-400">Total Retiros</p>
                            <p className="text-2xl font-bold text-red-700 dark:text-red-300">
                              ${(financesData.data.summary.total_withdrawals || 0).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                            </p>
                          </div>
                          <ArrowTrendingUpIcon className="w-8 h-8 text-red-500 rotate-180" />
                        </div>
                      </div>

                      <div className={`bg-gradient-to-br rounded-xl p-4 border ${
                        financesData.data.summary.net_amount >= 0
                          ? 'from-blue-50 to-cyan-50 dark:from-blue-900/20 dark:to-cyan-900/20 border-blue-200 dark:border-blue-800'
                          : 'from-orange-50 to-amber-50 dark:from-orange-900/20 dark:to-amber-900/20 border-orange-200 dark:border-orange-800'
                      }`}>
                        <div className="flex items-center justify-between">
                          <div>
                            <p className={`text-sm font-medium ${
                              financesData.data.summary.net_amount >= 0
                                ? 'text-blue-600 dark:text-blue-400'
                                : 'text-orange-600 dark:text-orange-400'
                            }`}>Balance Neto</p>
                            <p className={`text-2xl font-bold ${
                              financesData.data.summary.net_amount >= 0
                                ? 'text-blue-700 dark:text-blue-300'
                                : 'text-orange-700 dark:text-orange-300'
                            }`}>
                              ${(financesData.data.summary.net_amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                            </p>
                          </div>
                          <CurrencyDollarIcon className={`w-8 h-8 ${
                            financesData.data.summary.net_amount >= 0
                              ? 'text-blue-500'
                              : 'text-orange-500'
                          }`} />
                        </div>
                      </div>
                    </div>

                    {/* Historial de Transacciones */}
                    {financesData.data.transactions && financesData.data.transactions.length > 0 ? (
                      <div className="space-y-4">
                        <h4 className="text-md font-semibold text-secondary-900 dark:text-white flex items-center">
                          <DocumentTextIcon className="w-5 h-5 mr-2 text-secondary-500" />
                          Historial de Transacciones
                        </h4>
                        <div className="overflow-hidden rounded-lg border border-secondary-200 dark:border-secondary-700">
                          <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-secondary-200 dark:divide-secondary-700">
                              <thead className="bg-secondary-50 dark:bg-secondary-800">
                                <tr>
                                  <th className="px-6 py-3 text-left text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase tracking-wider">
                                    Tipo
                                  </th>
                                  <th className="px-6 py-3 text-left text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase tracking-wider">
                                    Monto
                                  </th>
                                  <th className="px-6 py-3 text-left text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase tracking-wider">
                                    Agente
                                  </th>
                                  <th className="px-6 py-3 text-left text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase tracking-wider">
                                    Fecha
                                  </th>
                                  <th className="px-6 py-3 text-left text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase tracking-wider">
                                    Estado
                                  </th>
                                </tr>
                              </thead>
                              <tbody className="bg-white dark:bg-secondary-900 divide-y divide-secondary-200 dark:divide-secondary-700">
                                {financesData.data.transactions.map((transaction) => (
                                  <tr key={transaction.id} className="hover:bg-secondary-50 dark:hover:bg-secondary-800 transition-colors">
                                    <td className="px-6 py-4 whitespace-nowrap">
                                      <div className="flex items-center">
                                        <div className={`w-8 h-8 rounded-full flex items-center justify-center ${
                                          transaction.type === 'deposit' 
                                            ? 'bg-green-100 dark:bg-green-900' 
                                            : 'bg-red-100 dark:bg-red-900'
                                        }`}>
                                          <ArrowTrendingUpIcon className={`w-4 h-4 ${
                                            transaction.type === 'deposit' 
                                              ? 'text-green-600 dark:text-green-400' 
                                              : 'text-red-600 dark:text-red-400 rotate-180'
                                          }`} />
                                        </div>
                                        <div className="ml-3">
                                          <div className="text-sm font-medium text-secondary-900 dark:text-white capitalize">
                                            {transaction.type === 'deposit' ? 'Depósito' : 'Retiro'}
                                          </div>
                                          {transaction.payment_method && (
                                            <div className="text-sm text-secondary-500 dark:text-secondary-400">
                                              {transaction.payment_method}
                                            </div>
                                          )}
                                        </div>
                                      </div>
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap">
                                      <div className={`text-sm font-semibold ${
                                        transaction.type === 'deposit' 
                                          ? 'text-green-600 dark:text-green-400' 
                                          : 'text-red-600 dark:text-red-400'
                                      }`}>
                                        {transaction.type === 'deposit' ? '+' : '-'}${(transaction.amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                                      </div>
                                      <div className="text-xs text-secondary-500 dark:text-secondary-400">
                                        {transaction.currency || 'USD'}
                                      </div>
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap">
                                      <div className="text-sm text-secondary-900 dark:text-white">
                                        {transaction.agent_name || transaction.processed_by_name || 'No especificado'}
                                      </div>
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap">
                                      <div className="text-sm text-secondary-900 dark:text-white">
                                        {new Date(transaction.created_at).toLocaleDateString('es-ES')}
                                      </div>
                                      <div className="text-xs text-secondary-500 dark:text-secondary-400">
                                        {new Date(transaction.created_at).toLocaleTimeString('es-ES', { 
                                          hour: '2-digit', 
                                          minute: '2-digit' 
                                        })}
                                      </div>
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap">
                                      <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                        transaction.status === 'completed' || transaction.status === 'approved'
                                          ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                          : transaction.status === 'pending'
                                          ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                                          : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                      }`}>
                                        {transaction.status === 'completed' ? 'Completado' :
                                         transaction.status === 'approved' ? 'Aprobado' :
                                         transaction.status === 'pending' ? 'Pendiente' :
                                         transaction.status}
                                      </span>
                                    </td>
                                  </tr>
                                ))}
                              </tbody>
                            </table>
                          </div>
                        </div>
                      </div>
                    ) : (
                      <div className="text-center py-8">
                        <BanknotesIcon className="w-12 h-12 text-secondary-300 dark:text-secondary-600 mx-auto mb-4" />
                        <p className="text-secondary-500 dark:text-secondary-400">
                          No hay transacciones registradas
                        </p>
                      </div>
                    )}
                  </div>
                ) : (
                  <div className="text-center py-8">
                    <ExclamationTriangleIcon className="w-12 h-12 text-secondary-300 dark:text-secondary-600 mx-auto mb-4" />
                    <p className="text-secondary-500 dark:text-secondary-400">
                      No se pudo cargar la información financiera
                    </p>
                  </div>
                )}
              </div>
            )}
          </div>

          {/* Sección de Cuentas de Trading */}
          <div className="card">
            <div 
              className="card-header cursor-pointer hover:bg-secondary-50 dark:hover:bg-secondary-800 transition-colors"
              onClick={() => setIsTradingAccountsCollapsed(!isTradingAccountsCollapsed)}
            >
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-3">
                  <div className="w-8 h-8 bg-purple-100 dark:bg-purple-900 rounded-lg flex items-center justify-center">
                    <ComputerDesktopIcon className="w-4 h-4 text-purple-600 dark:text-purple-400" />
                  </div>
                  <h3 className="text-lg font-semibold text-secondary-900 dark:text-white">
                    Cuentas de Trading
                  </h3>
                  {tradingAccountsData?.success && tradingAccountsData?.data?.length > 0 && (
                    <span className="bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200 px-2 py-1 rounded-full text-xs font-medium">
                      {tradingAccountsData.data.length} cuentas
                    </span>
                  )}
                </div>
                {isTradingAccountsCollapsed ? (
                  <ChevronDownIcon className="w-5 h-5 text-secondary-400" />
                ) : (
                  <ChevronUpIcon className="w-5 h-5 text-secondary-400" />
                )}
              </div>
            </div>
            
            {!isTradingAccountsCollapsed && (
              <div className="card-body">
                {isLoadingTradingAccounts ? (
                  <div className="flex justify-center py-8">
                    <div className="text-center">
                      <LoadingSpinner />
                      <p className="mt-2 text-sm text-secondary-500 dark:text-secondary-400">
                        Cargando cuentas de trading...
                      </p>
                    </div>
                  </div>
                ) : tradingAccountsData?.success && tradingAccountsData?.data?.length > 0 ? (
                  <div className="space-y-6">
                    {tradingAccountsData.data.map((account: TradingAccount) => (
                      <div key={account.id} className="bg-secondary-50 dark:bg-secondary-800 rounded-xl p-6 border border-secondary-200 dark:border-secondary-700">
                        {/* Header de la cuenta */}
                        <div className="flex items-center justify-between mb-6">
                          <div className="flex items-center space-x-4">
                            <div className="w-14 h-14 bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl flex items-center justify-center text-white font-bold text-lg shadow-lg">
                              {account.platform.substring(0, 2).toUpperCase()}
                            </div>
                            <div>
                              <h4 className="text-lg font-bold text-secondary-900 dark:text-white">
                                {account.account_number}
                              </h4>
                              <p className="text-sm text-secondary-500 dark:text-secondary-400">
                                {account.platform} • {account.account_type} • {account.leverage}
                              </p>
                              <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium mt-1 ${
                                account.status === 'active' 
                                  ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' 
                                  : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                              }`}>
                                {account.status === 'active' ? 'Activa' : 'Inactiva'}
                              </span>
                            </div>
                          </div>
                          <button
                            onClick={() => unlinkTradingAccountMutation.mutate(account.id)}
                            disabled={unlinkTradingAccountMutation.isPending}
                            className="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 text-sm font-medium transition-colors hover:bg-red-50 dark:hover:bg-red-900/20 px-3 py-1 rounded-lg"
                          >
                            Desvincular
                          </button>
                        </div>

                        {/* KPIs Grid */}
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                          {/* Balance */}
                          <div className="bg-white dark:bg-secondary-900 rounded-lg p-4 border border-secondary-200 dark:border-secondary-700">
                            <div className="flex items-center justify-between">
                              <div>
                                <p className="text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase tracking-wide">Balance</p>
                                <p className="text-xl font-bold text-secondary-900 dark:text-white">
                                  ${(account.balance || 0).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                                </p>
                              </div>
                              <CurrencyDollarIcon className="w-8 h-8 text-blue-500" />
                            </div>
                          </div>

                          {/* Equity */}
                          <div className="bg-white dark:bg-secondary-900 rounded-lg p-4 border border-secondary-200 dark:border-secondary-700">
                            <div className="flex items-center justify-between">
                              <div>
                                <p className="text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase tracking-wide">Equity</p>
                                <p className="text-xl font-bold text-secondary-900 dark:text-white">
                                  ${(account.equity || 0).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                                </p>
                              </div>
                              <ChartBarIcon className="w-8 h-8 text-purple-500" />
                            </div>
                          </div>

                          {/* Margin */}
                          <div className="bg-white dark:bg-secondary-900 rounded-lg p-4 border border-secondary-200 dark:border-secondary-700">
                            <div className="flex items-center justify-between">
                              <div>
                                <p className="text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase tracking-wide">Margen</p>
                                <p className="text-xl font-bold text-secondary-900 dark:text-white">
                                  ${(account.margin || 0).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                                </p>
                              </div>
                              <BanknotesIcon className="w-8 h-8 text-orange-500" />
                            </div>
                          </div>

                          {/* P&L */}
                          <div className="bg-white dark:bg-secondary-900 rounded-lg p-4 border border-secondary-200 dark:border-secondary-700">
                            <div className="flex items-center justify-between">
                              <div>
                                <p className="text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase tracking-wide">P&L</p>
                                <p className={`text-xl font-bold ${
                                  (account.profit_loss || 0) >= 0
                                    ? 'text-green-600 dark:text-green-400'
                                    : 'text-red-600 dark:text-red-400'
                                }`}>
                                  ${(account.profit_loss || 0).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                                </p>
                              </div>
                              <ArrowTrendingUpIcon className={`w-8 h-8 ${
                                (account.profit_loss || 0) >= 0 ? 'text-green-500' : 'text-red-500 rotate-180'
                              }`} />
                            </div>
                          </div>
                        </div>

                        {/* KPIs adicionales */}
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                          {/* Margen Libre */}
                          <div className="bg-gradient-to-r from-cyan-50 to-blue-50 dark:from-cyan-900/20 dark:to-blue-900/20 rounded-lg p-4 border border-cyan-200 dark:border-cyan-800">
                            <div className="flex items-center justify-between">
                              <div>
                                <p className="text-sm font-medium text-cyan-600 dark:text-cyan-400">Margen Libre</p>
                                <p className="text-lg font-bold text-cyan-700 dark:text-cyan-300">
                                  ${((account.equity || 0) - (account.margin || 0)).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                                </p>
                              </div>
                            </div>
                          </div>

                          {/* Nivel de Margen */}
                          <div className="bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-indigo-900/20 dark:to-purple-900/20 rounded-lg p-4 border border-indigo-200 dark:border-indigo-800">
                            <div className="flex items-center justify-between">
                              <div>
                                <p className="text-sm font-medium text-indigo-600 dark:text-indigo-400">Nivel de Margen</p>
                                <p className="text-lg font-bold text-indigo-700 dark:text-indigo-300">
                                  {account.margin > 0 ? ((account.equity / account.margin) * 100).toFixed(2) : '0.00'}%
                                </p>
                              </div>
                            </div>
                          </div>

                          {/* ROI */}
                          <div className="bg-gradient-to-r from-emerald-50 to-green-50 dark:from-emerald-900/20 dark:to-green-900/20 rounded-lg p-4 border border-emerald-200 dark:border-emerald-800">
                            <div className="flex items-center justify-between">
                              <div>
                                <p className="text-sm font-medium text-emerald-600 dark:text-emerald-400">ROI</p>
                                <p className={`text-lg font-bold ${
                                  (account.profit_loss || 0) >= 0 
                                    ? 'text-emerald-700 dark:text-emerald-300' 
                                    : 'text-red-600 dark:text-red-400'
                                }`}>
                                  {account.balance > 0 ? (((account.profit_loss || 0) / account.balance) * 100).toFixed(2) : '0.00'}%
                                </p>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                ) : (
                  <div className="text-center py-8">
                    <ComputerDesktopIcon className="w-12 h-12 text-secondary-300 dark:text-secondary-600 mx-auto mb-4" />
                    <p className="text-secondary-500 dark:text-secondary-400 mb-2">
                      No hay cuentas de trading vinculadas
                    </p>
                    <p className="text-sm text-secondary-400 dark:text-secondary-500">
                      Las cuentas se vincularán automáticamente cuando coincida el email
                    </p>
                  </div>
                )}
              </div>
            )}
          </div>

          {/* Sección de Posiciones */}
          <div className="card">
            <div 
              className="card-header cursor-pointer hover:bg-secondary-50 dark:hover:bg-secondary-800 transition-colors"
              onClick={() => setIsPositionsCollapsed(!isPositionsCollapsed)}
            >
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-3">
                  <div className="w-8 h-8 bg-indigo-100 dark:bg-indigo-900 rounded-lg flex items-center justify-center">
                    <ChartBarIcon className="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                  </div>
                  <h3 className="text-lg font-semibold text-secondary-900 dark:text-white">
                    Posiciones de Trading
                  </h3>
                </div>
                {isPositionsCollapsed ? (
                  <ChevronDownIcon className="w-5 h-5 text-secondary-400" />
                ) : (
                  <ChevronUpIcon className="w-5 h-5 text-secondary-400" />
                )}
              </div>
            </div>

            {!isPositionsCollapsed && (
              <div className="card-body">
                {isLoadingPositions ? (
                  <div className="flex justify-center py-8">
                    <LoadingSpinner />
                  </div>
                ) : positionsData?.success && positionsData?.data ? (
                  <div className="space-y-6">
                    {/* Pestañas */}
                    <div className="border-b border-secondary-200 dark:border-secondary-700">
                      <nav className="-mb-px flex space-x-8">
                        <button
                          onClick={() => setActivePositionsTab('open')}
                          className={cn(
                            'py-2 px-1 border-b-2 font-medium text-sm transition-colors',
                            activePositionsTab === 'open'
                              ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                              : 'border-transparent text-secondary-500 hover:text-secondary-700 hover:border-secondary-300 dark:text-secondary-400 dark:hover:text-secondary-300'
                          )}
                        >
                          Posiciones Abiertas ({positionsData.data.open_positions.length})
                        </button>
                        <button
                          onClick={() => setActivePositionsTab('closed')}
                          className={cn(
                            'py-2 px-1 border-b-2 font-medium text-sm transition-colors',
                            activePositionsTab === 'closed'
                              ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                              : 'border-transparent text-secondary-500 hover:text-secondary-700 hover:border-secondary-300 dark:text-secondary-400 dark:hover:text-secondary-300'
                          )}
                        >
                          Posiciones Cerradas ({positionsData.data.closed_positions.length})
                        </button>
                        <button
                          onClick={() => setActivePositionsTab('pending')}
                          className={cn(
                            'py-2 px-1 border-b-2 font-medium text-sm transition-colors',
                            activePositionsTab === 'pending'
                              ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                              : 'border-transparent text-secondary-500 hover:text-secondary-700 hover:border-secondary-300 dark:text-secondary-400 dark:hover:text-secondary-300'
                          )}
                        >
                          Posiciones Pendientes ({positionsData.data.pending_positions.length})
                        </button>
                      </nav>
                    </div>

                    {/* Contenido de las pestañas */}
                    <div className="space-y-4">
                      {activePositionsTab === 'open' && (
                        <>
                          {paginatedOpenPositions.length > 0 ? (
                            <>
                              {paginatedOpenPositions.map((position) => (
                                <div key={position.id} className="bg-secondary-50 dark:bg-secondary-800 rounded-lg p-4 border border-secondary-200 dark:border-secondary-700">
                                  <div className="flex items-center justify-between mb-3">
                                    <div className="flex items-center space-x-3">
                                      <div className={cn(
                                        'w-10 h-10 rounded-lg flex items-center justify-center text-white font-bold text-sm',
                                        position.type === 'buy' 
                                          ? 'bg-green-500' 
                                          : 'bg-red-500'
                                      )}>
                                        {position.type === 'buy' ? 'BUY' : 'SELL'}
                                      </div>
                                      <div>
                                        <h4 className="font-semibold text-secondary-900 dark:text-white">
                                          {position.symbol}
                                        </h4>
                                        <p className="text-sm text-secondary-500 dark:text-secondary-400">
                                          Volumen: {position.volume} • {position.platform}
                                        </p>
                                      </div>
                                    </div>
                                    <div className="text-right">
                                      <p className={cn(
                                        'font-bold text-lg',
                                        position.profit_loss >= 0
                                          ? 'text-green-600 dark:text-green-400'
                                          : 'text-red-600 dark:text-red-400'
                                      )}>
                                        ${position.profit_loss.toFixed(2)}
                                      </p>
                                      <p className="text-sm text-secondary-500 dark:text-secondary-400">
                                        P&L
                                      </p>
                                    </div>
                                  </div>
                                  
                                  <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                    <div>
                                      <p className="text-secondary-500 dark:text-secondary-400">Precio Apertura</p>
                                      <p className="font-medium text-secondary-900 dark:text-white">
                                        {position.open_price.toFixed(5)}
                                      </p>
                                    </div>
                                    <div>
                                      <p className="text-secondary-500 dark:text-secondary-400">Precio Actual</p>
                                      <p className="font-medium text-secondary-900 dark:text-white">
                                        {position.current_price?.toFixed(5) || 'N/A'}
                                      </p>
                                    </div>
                                    <div>
                                      <p className="text-secondary-500 dark:text-secondary-400">Swap</p>
                                      <p className={cn(
                                        'font-medium',
                                        position.swap >= 0
                                          ? 'text-green-600 dark:text-green-400'
                                          : 'text-red-600 dark:text-red-400'
                                      )}>
                                        ${position.swap.toFixed(2)}
                                      </p>
                                    </div>
                                    <div>
                                      <p className="text-secondary-500 dark:text-secondary-400">Comisión</p>
                                      <p className="font-medium text-red-600 dark:text-red-400">
                                        ${position.commission.toFixed(2)}
                                      </p>
                                    </div>
                                  </div>
                                  
                                  <div className="mt-3 pt-3 border-t border-secondary-200 dark:border-secondary-700">
                                    <p className="text-xs text-secondary-500 dark:text-secondary-400">
                                      Abierta: {new Date(position.open_time).toLocaleString('es-ES')}
                                    </p>
                                  </div>
                                </div>
                              ))}
                              
                              {/* Paginación para posiciones abiertas */}
                              {totalOpenPages > 1 && (
                                <div className="flex justify-center mt-6">
                                  <Pagination
                                    currentPage={openPositionsPage}
                                    totalItems={positionsData?.data?.open_positions?.length || 0}
                                    itemsPerPage={10}
                                    onPageChange={setOpenPositionsPage}
                                  />
                                </div>
                              )}
                            </>
                          ) : (
                            <div className="text-center py-8">
                              <ChartBarIcon className="w-12 h-12 text-secondary-300 dark:text-secondary-600 mx-auto mb-4" />
                              <p className="text-secondary-500 dark:text-secondary-400">
                                No hay posiciones abiertas
                              </p>
                            </div>
                          )}
                        </>
                      )}

                      {activePositionsTab === 'closed' && (
                        <>
                          {paginatedClosedPositions.length > 0 ? (
                            <>
                              {paginatedClosedPositions.map((position) => (
                                <div key={position.id} className="bg-secondary-50 dark:bg-secondary-800 rounded-lg p-4 border border-secondary-200 dark:border-secondary-700">
                                  <div className="flex items-center justify-between mb-3">
                                    <div className="flex items-center space-x-3">
                                      <div className={cn(
                                        'w-10 h-10 rounded-lg flex items-center justify-center text-white font-bold text-sm',
                                        position.type === 'buy' 
                                          ? 'bg-green-500' 
                                          : 'bg-red-500'
                                      )}>
                                        {position.type === 'buy' ? 'BUY' : 'SELL'}
                                      </div>
                                      <div>
                                        <h4 className="font-semibold text-secondary-900 dark:text-white">
                                          {position.symbol}
                                        </h4>
                                        <p className="text-sm text-secondary-500 dark:text-secondary-400">
                                          Volumen: {position.volume} • {position.platform}
                                        </p>
                                      </div>
                                    </div>
                                    <div className="text-right">
                                      <p className={cn(
                                        'font-bold text-lg',
                                        position.profit_loss >= 0
                                          ? 'text-green-600 dark:text-green-400'
                                          : 'text-red-600 dark:text-red-400'
                                      )}>
                                        ${position.profit_loss.toFixed(2)}
                                      </p>
                                      <p className="text-sm text-secondary-500 dark:text-secondary-400">
                                        P&L Final
                                      </p>
                                    </div>
                                  </div>
                                  
                                  <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                    <div>
                                      <p className="text-secondary-500 dark:text-secondary-400">Precio Apertura</p>
                                      <p className="font-medium text-secondary-900 dark:text-white">
                                        {position.open_price.toFixed(5)}
                                      </p>
                                    </div>
                                    <div>
                                      <p className="text-secondary-500 dark:text-secondary-400">Precio Cierre</p>
                                      <p className="font-medium text-secondary-900 dark:text-white">
                                        {position.current_price?.toFixed(5) || 'N/A'}
                                      </p>
                                    </div>
                                    <div>
                                      <p className="text-secondary-500 dark:text-secondary-400">Swap</p>
                                      <p className={cn(
                                        'font-medium',
                                        position.swap >= 0
                                          ? 'text-green-600 dark:text-green-400'
                                          : 'text-red-600 dark:text-red-400'
                                      )}>
                                        ${position.swap.toFixed(2)}
                                      </p>
                                    </div>
                                    <div>
                                      <p className="text-secondary-500 dark:text-secondary-400">Comisión</p>
                                      <p className="font-medium text-red-600 dark:text-red-400">
                                        ${position.commission.toFixed(2)}
                                      </p>
                                    </div>
                                  </div>
                                  
                                  <div className="mt-3 pt-3 border-t border-secondary-200 dark:border-secondary-700 grid grid-cols-2 gap-4">
                                    <div>
                                      <p className="text-xs text-secondary-500 dark:text-secondary-400">
                                        Abierta: {new Date(position.open_time).toLocaleString('es-ES')}
                                      </p>
                                    </div>
                                    <div>
                                      <p className="text-xs text-secondary-500 dark:text-secondary-400">
                                        Cerrada: {position.close_time ? new Date(position.close_time).toLocaleString('es-ES') : 'N/A'}
                                      </p>
                                    </div>
                                  </div>
                                </div>
                              ))}
                              
                              {/* Paginación para posiciones cerradas */}
                              {totalClosedPages > 1 && (
                                <div className="flex justify-center mt-6">
                                  <Pagination
                                    currentPage={closedPositionsPage}
                                    totalItems={positionsData?.data?.closed_positions?.length || 0}
                                    itemsPerPage={10}
                                    onPageChange={setClosedPositionsPage}
                                  />
                                </div>
                              )}
                            </>
                          ) : (
                            <div className="text-center py-8">
                              <CheckCircleIcon className="w-12 h-12 text-secondary-300 dark:text-secondary-600 mx-auto mb-4" />
                              <p className="text-secondary-500 dark:text-secondary-400">
                                No hay posiciones cerradas
                              </p>
                            </div>
                          )}
                        </>
                      )}

                      {activePositionsTab === 'pending' && (
                        <>
                          {paginatedPendingPositions.length > 0 ? (
                            <>
                              {paginatedPendingPositions.map((position) => (
                                <div key={position.id} className="bg-secondary-50 dark:bg-secondary-800 rounded-lg p-4 border border-secondary-200 dark:border-secondary-700">
                                  <div className="flex items-center justify-between mb-3">
                                    <div className="flex items-center space-x-3">
                                      <div className="w-10 h-10 bg-yellow-500 rounded-lg flex items-center justify-center text-white font-bold text-sm">
                                        PEND
                                      </div>
                                      <div>
                                        <h4 className="font-semibold text-secondary-900 dark:text-white">
                                          {position.symbol}
                                        </h4>
                                        <p className="text-sm text-secondary-500 dark:text-secondary-400">
                                          Volumen: {position.volume} • {position.platform}
                                        </p>
                                      </div>
                                    </div>
                                    <div className="text-right">
                                      <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                        Pendiente
                                      </span>
                                    </div>
                                  </div>
                                  
                                  <div className="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                                    <div>
                                      <p className="text-secondary-500 dark:text-secondary-400">Tipo</p>
                                      <p className="font-medium text-secondary-900 dark:text-white uppercase">
                                        {position.type}
                                      </p>
                                    </div>
                                    <div>
                                      <p className="text-secondary-500 dark:text-secondary-400">Precio Objetivo</p>
                                      <p className="font-medium text-secondary-900 dark:text-white">
                                        {position.open_price.toFixed(5)}
                                      </p>
                                    </div>
                                    <div>
                                      <p className="text-secondary-500 dark:text-secondary-400">Estado</p>
                                      <p className="font-medium text-yellow-600 dark:text-yellow-400">
                                        Esperando ejecución
                                      </p>
                                    </div>
                                  </div>
                                  
                                  <div className="mt-3 pt-3 border-t border-secondary-200 dark:border-secondary-700">
                                    <p className="text-xs text-secondary-500 dark:text-secondary-400">
                                      Creada: {new Date(position.open_time).toLocaleString('es-ES')}
                                    </p>
                                  </div>
                                </div>
                              ))}
                              
                              {/* Paginación para posiciones pendientes */}
                              {totalPendingPages > 1 && (
                                <div className="flex justify-center mt-6">
                                  <Pagination
                                    currentPage={pendingPositionsPage}
                                    totalItems={positionsData?.data?.pending_positions?.length || 0}
                                    itemsPerPage={10}
                                    onPageChange={setPendingPositionsPage}
                                  />
                                </div>
                              )}
                            </>
                          ) : (
                            <div className="text-center py-8">
                              <ClockIcon className="w-12 h-12 text-secondary-300 dark:text-secondary-600 mx-auto mb-4" />
                              <p className="text-secondary-500 dark:text-secondary-400">
                                No hay posiciones pendientes
                              </p>
                            </div>
                          )}
                        </>
                      )}
                    </div>
                  </div>
                ) : (
                  <div className="text-center py-8">
                    <ChartBarIcon className="w-12 h-12 text-secondary-300 dark:text-secondary-600 mx-auto mb-4" />
                    <p className="text-secondary-500 dark:text-secondary-400 mb-2">
                      No hay datos de posiciones disponibles
                    </p>
                    <p className="text-sm text-secondary-400 dark:text-secondary-500">
                      Las posiciones aparecerán cuando haya actividad de trading
                    </p>
                  </div>
                )}
              </div>
            )}
          </div>


        </div>

        {/* Columna Lateral */}
        <div className="space-y-6">
          
          {/* Estado del Lead */}
          <div className="card">
            <div className="card-header">
              <div className="flex items-center space-x-3">
                <div className="w-8 h-8 bg-orange-100 dark:bg-orange-900 rounded-lg flex items-center justify-center">
                  <TagIcon className="w-4 h-4 text-orange-600 dark:text-orange-400" />
                </div>
                <h3 className="text-lg font-semibold text-secondary-900 dark:text-white">
                  Estado del Lead
                </h3>
              </div>
            </div>
            <div className="card-body">
              <div className="space-y-4">
                <div className="flex items-center justify-between p-3 bg-secondary-50 dark:bg-secondary-800 rounded-lg">
                  <span className="text-sm font-medium text-secondary-700 dark:text-secondary-300">Estado Actual</span>
                  <DynamicStateSelector
                    leadId={Number(id)}
                    currentStateName={lead.status}
                    onStateChange={handleLeadStateChange}
                    showHistory={false}
                    disabled={false}
                  />
                </div>
                <div className="flex items-center justify-between p-3 bg-secondary-50 dark:bg-secondary-800 rounded-lg">
                  <span className="text-sm font-medium text-secondary-700 dark:text-secondary-300">Creado</span>
                  <span className="text-sm text-secondary-900 dark:text-white">
                    {new Date(lead.created_at).toLocaleDateString('es-ES')}
                  </span>
                </div>
                <div className="flex items-center justify-between p-3 bg-secondary-50 dark:bg-secondary-800 rounded-lg">
                  <span className="text-sm font-medium text-secondary-700 dark:text-secondary-300">Última Actualización</span>
                  <span className="text-sm text-secondary-900 dark:text-white">
                    {lead.updated_at ? new Date(lead.updated_at).toLocaleDateString('es-ES') : 'N/A'}
                  </span>
                </div>
              </div>
            </div>
          </div>

          {/* Asignación */}
          <div className="card">
            <div className="card-header">
              <div className="flex items-center space-x-3">
                <div className="w-8 h-8 bg-indigo-100 dark:bg-indigo-900 rounded-lg flex items-center justify-center">
                  <UserIcon className="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                </div>
                <h3 className="text-lg font-semibold text-secondary-900 dark:text-white">
                  Asignación
                </h3>
              </div>
            </div>
            <div className="card-body">
              <div className="space-y-4">
                <div className="flex items-center justify-between">
                  <span className="text-sm font-medium text-secondary-700 dark:text-secondary-300">
                    Asignado a:
                  </span>
                  <span className="text-sm text-secondary-900 dark:text-white">
                    {lead.assigned_to_name || 'Sin asignar'}
                  </span>
                </div>
                {hasPermission('leads.assign') && (
                  <button
                    onClick={() => setShowAssignModal(true)}
                    className="w-full btn btn-secondary text-sm"
                  >
                    <UserIcon className="w-4 h-4 mr-2" />
                    {lead.assigned_to ? 'Reasignar' : 'Asignar'} Lead
                  </button>
                )}
              </div>
            </div>
          </div>

          {/* Información Adicional */}
          <div className="card">
            <div className="card-header">
              <div className="flex items-center space-x-3">
                <div className="w-8 h-8 bg-cyan-100 dark:bg-cyan-900 rounded-lg flex items-center justify-center">
                  <InformationCircleIcon className="w-4 h-4 text-cyan-600 dark:text-cyan-400" />
                </div>
                <h3 className="text-lg font-semibold text-secondary-900 dark:text-white">
                  Información Adicional
                </h3>
              </div>
            </div>
            <div className="card-body">
              <div className="space-y-3">
                <div className="flex items-center justify-between text-sm">
                  <span className="text-secondary-600 dark:text-secondary-400">ID del Lead:</span>
                  <span className="font-mono text-secondary-900 dark:text-white">#{lead.id}</span>
                </div>
                <div className="flex items-center justify-between text-sm">
                  <span className="text-secondary-600 dark:text-secondary-400">Fuente:</span>
                  <span className="text-secondary-900 dark:text-white">{lead.source || 'N/A'}</span>
                </div>
                <div className="flex items-center justify-between text-sm">
                  <span className="text-secondary-600 dark:text-secondary-400">Campaña:</span>
                  <span className="text-secondary-900 dark:text-white">{lead.campaign || 'N/A'}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Modal de Asignación */}
      {showAssignModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
          <div className="bg-white dark:bg-secondary-900 rounded-xl shadow-xl max-w-md w-full">
            <div className="p-6">
              <div className="flex items-center justify-between mb-4">
                <h3 className="text-lg font-semibold text-secondary-900 dark:text-white">
                  Asignar Lead
                </h3>
                <button
                  onClick={() => setShowAssignModal(false)}
                  className="text-secondary-400 hover:text-secondary-600 dark:hover:text-secondary-300"
                >
                  <XCircleIcon className="w-6 h-6" />
                </button>
              </div>
              <div className="space-y-3">
                {(deskData?.data?.users || []).map((user: any) => (
                  <button
                    key={user.id}
                    onClick={() => handleAssignLead(user.id)}
                    disabled={assignLeadMutation.isPending}
                    className="w-full text-left p-3 rounded-lg border border-secondary-200 dark:border-secondary-700 hover:bg-secondary-50 dark:hover:bg-secondary-800 transition-colors"
                  >
                    <div className="flex items-center space-x-3">
                      <div className="w-8 h-8 bg-primary-100 dark:bg-primary-900 rounded-full flex items-center justify-center">
                        <span className="text-sm font-medium text-primary-600 dark:text-primary-400">
                          {`${user.first_name?.charAt(0) ?? ''}${user.last_name?.charAt(0) ?? ''}`.toUpperCase()}
                        </span>
                      </div>
                      <div>
                        <p className="font-medium text-secondary-900 dark:text-white">{`${user.first_name} ${user.last_name}`}</p>
                        {/* Ocultamos email según solicitud */}
                      </div>
                    </div>
                  </button>
                ))}
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
