import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  ChatBubbleLeftRightIcon,
  PhoneIcon,
  EnvelopeIcon,
  DocumentTextIcon,
  PlusIcon,
  ClockIcon,
  UserIcon,
  XMarkIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  CalendarIcon
} from '@heroicons/react/24/outline'
import { cn } from '../../utils/cn'
import toast from 'react-hot-toast'

interface Activity {
  id: number
  lead_id: number
  user_id: number
  type: 'call' | 'email' | 'meeting' | 'note' | 'task' | 'follow_up'
  subject: string
  description?: string
  status: 'pending' | 'completed' | 'cancelled'
  scheduled_at?: string
  completed_at?: string
  duration_minutes?: number
  outcome?: string
  next_action?: string
  priority: 'low' | 'medium' | 'high'
  visibility: 'private' | 'team' | 'public'
  is_system_generated: boolean
  metadata?: any
  attachments?: string
  created_at: string
  updated_at: string
  user_name?: string
}

interface LeadActivitiesProps {
  leadId: number
  onLeadStatusChange?: (newStatus: string) => void
}

const activityTypeConfig = {
  call: {
    icon: PhoneIcon,
    label: 'Llamada',
    color: 'bg-blue-100 text-blue-600 dark:bg-blue-900 dark:text-blue-400'
  },
  email: {
    icon: EnvelopeIcon,
    label: 'Email',
    color: 'bg-green-100 text-green-600 dark:bg-green-900 dark:text-green-400'
  },
  meeting: {
    icon: DocumentTextIcon,
    label: 'Reunión',
    color: 'bg-purple-100 text-purple-600 dark:bg-purple-900 dark:text-purple-400'
  },
  note: {
    icon: DocumentTextIcon,
    label: 'Nota',
    color: 'bg-gray-100 text-gray-600 dark:bg-gray-900 dark:text-gray-400'
  },
  task: {
    icon: DocumentTextIcon,
    label: 'Tarea',
    color: 'bg-orange-100 text-orange-600 dark:bg-orange-900 dark:text-orange-400'
  },
  follow_up: {
    icon: ClockIcon,
    label: 'Seguimiento',
    color: 'bg-yellow-100 text-yellow-600 dark:bg-yellow-900 dark:text-yellow-400'
  }
}

export default function LeadActivities({ leadId, onLeadStatusChange }: LeadActivitiesProps) {
  const [showAddModal, setShowAddModal] = useState(false)
  const [currentPage, setCurrentPage] = useState(1)
  const [dateFilter, setDateFilter] = useState('')
  const [showDateFilterModal, setShowDateFilterModal] = useState(false)
  const queryClient = useQueryClient()

  // Fetch activities with pagination and date filter
  const { data: activitiesData, isLoading } = useQuery({
    queryKey: ['lead-activities', leadId, currentPage, dateFilter],
    queryFn: async () => {
      const token = localStorage.getItem('auth_token')
      const params = new URLSearchParams({
        lead_id: leadId.toString(),
        page: currentPage.toString(),
        limit: '3'
      })
      
      if (dateFilter) {
        params.append('date', dateFilter)
      }
      
      const response = await fetch(`/api/lead-activities.php?${params}`, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      })
      if (!response.ok) throw new Error('Error al cargar actividades')
      return response.json()
    }
  })

  const activities: Activity[] = activitiesData?.activities || []
  const pagination = activitiesData?.pagination || {}

  // Add activity mutation
  const addActivityMutation = useMutation({
    mutationFn: async (activityData: Partial<Activity>) => {
      const token = localStorage.getItem('auth_token')
      const response = await fetch('/api/lead-activities.php', {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({ ...activityData, lead_id: leadId })
      })
      if (!response.ok) throw new Error('Error al crear actividad')
      return response.json()
    },
    onSuccess: (_, variables: any) => {
      queryClient.invalidateQueries({ queryKey: ['lead-activities', leadId] })
      
      // Si se especificó un nuevo estado del lead, actualizarlo
      if (variables.leadStatus && onLeadStatusChange) {
        onLeadStatusChange(variables.leadStatus)
      }
      
      setShowAddModal(false)
      setCurrentPage(1) // Volver a la primera página después de agregar
      toast.success('Actividad creada exitosamente')
    },
    onError: () => {
      toast.error('Error al crear la actividad')
    }
  })

  if (isLoading) {
    return (
      <div className="card">
        <div className="card-body">
          <div className="flex items-center justify-center py-8">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
          </div>
        </div>
      </div>
    )
  }

  return (
    <div className="card">
      <div className="card-header">
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-3">
            <div className="w-8 h-8 bg-indigo-100 dark:bg-indigo-900 rounded-lg flex items-center justify-center">
              <ChatBubbleLeftRightIcon className="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
            </div>
            <h3 className="text-lg font-semibold text-secondary-900 dark:text-white">
              Actividades y Comunicaciones
            </h3>
          </div>
          <div className="flex items-center space-x-3">
            {/* Icono de filtro de fecha */}
            <button
              onClick={() => setShowDateFilterModal(true)}
              className={`p-2 rounded-lg transition-colors ${
                dateFilter 
                  ? 'bg-primary-100 text-primary-600 dark:bg-primary-900 dark:text-primary-400' 
                  : 'text-secondary-500 hover:bg-secondary-100 dark:hover:bg-secondary-800 hover:text-secondary-700 dark:hover:text-secondary-300'
              }`}
              title={dateFilter ? `Filtrado por: ${dateFilter}` : 'Filtrar por fecha'}
            >
              <CalendarIcon className="w-5 h-5" />
            </button>
            <button
              onClick={() => setShowAddModal(true)}
              className="btn btn-primary text-sm"
            >
              <PlusIcon className="w-4 h-4 mr-2" />
              Nueva Actividad
            </button>
          </div>
        </div>
      </div>

      <div className="card-body">
        {/* Lista de actividades */}
        <div className="space-y-4">
          {activities.length > 0 ? (
            activities.map((activity) => {
              const typeConfig = activityTypeConfig[activity.type] || {
                icon: DocumentTextIcon,
                label: 'Actividad',
                color: 'bg-gray-100 text-gray-600 dark:bg-gray-900 dark:text-gray-400'
              }
              const TypeIcon = typeConfig.icon

              return (
                <div
                  key={activity.id}
                  className="border border-secondary-200 dark:border-secondary-700 rounded-lg p-4 hover:bg-secondary-50 dark:hover:bg-secondary-800 transition-colors"
                >
                  <div className="flex items-start space-x-4">
                    <div className={cn('w-10 h-10 rounded-lg flex items-center justify-center', typeConfig.color)}>
                      <TypeIcon className="w-5 h-5" />
                    </div>
                    
                    <div className="flex-1 min-w-0">
                      {activity.description && (
                        <p className="text-sm text-secondary-900 dark:text-white mb-3">
                          {activity.description}
                        </p>
                      )}

                      <div className="flex items-center justify-between text-xs text-secondary-500 dark:text-secondary-400">
                        <div className="flex items-center space-x-4">
                          <span className="flex items-center">
                            <UserIcon className="w-3 h-3 mr-1" />
                            {activity.user_name || 'Usuario'}
                          </span>
                          <span className="flex items-center">
                            <ClockIcon className="w-3 h-3 mr-1" />
                            {new Date(activity.created_at).toLocaleDateString('es-ES', {
                              year: 'numeric',
                              month: 'short',
                              day: 'numeric',
                              hour: '2-digit',
                              minute: '2-digit'
                            })}
                          </span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              )
            })
          ) : (
            <div className="text-center py-8">
              <ChatBubbleLeftRightIcon className="w-12 h-12 text-secondary-300 dark:text-secondary-600 mx-auto mb-4" />
              <p className="text-secondary-500 dark:text-secondary-400">
                No hay actividades registradas
              </p>
            </div>
          )}
        </div>

        {/* Controles de paginación */}
        {pagination.total_pages > 1 && (
          <div className="mt-6 flex items-center justify-between border-t border-secondary-200 dark:border-secondary-700 pt-4">
            <div className="text-sm text-secondary-500 dark:text-secondary-400">
              Página {pagination.current_page} de {pagination.total_pages} 
              ({pagination.total_records} actividades en total)
            </div>
            
            <div className="flex items-center space-x-2">
              <button
                onClick={() => setCurrentPage(currentPage - 1)}
                disabled={!pagination.has_prev_page}
                className="flex items-center px-3 py-2 text-sm font-medium text-secondary-500 bg-white border border-secondary-300 rounded-md hover:bg-secondary-50 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-secondary-800 dark:border-secondary-600 dark:text-secondary-400 dark:hover:bg-secondary-700"
              >
                <ChevronLeftIcon className="w-4 h-4 mr-1" />
                Anterior
              </button>
              
              <span className="px-3 py-2 text-sm font-medium text-secondary-700 dark:text-secondary-300">
                {pagination.current_page}
              </span>
              
              <button
                onClick={() => setCurrentPage(currentPage + 1)}
                disabled={!pagination.has_next_page}
                className="flex items-center px-3 py-2 text-sm font-medium text-secondary-500 bg-white border border-secondary-300 rounded-md hover:bg-secondary-50 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-secondary-800 dark:border-secondary-600 dark:text-secondary-400 dark:hover:bg-secondary-700"
              >
                Siguiente
                <ChevronRightIcon className="w-4 h-4 ml-1" />
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Modal para agregar actividad */}
      {showAddModal && (
        <AddActivityModal
          onClose={() => setShowAddModal(false)}
          onSubmit={(data) => addActivityMutation.mutate(data)}
          isLoading={addActivityMutation.isPending}
        />
      )}

      {/* Modal de filtro de fecha */}
      {showDateFilterModal && (
        <DateFilterModal
          currentFilter={dateFilter}
          onApply={(date) => {
            setDateFilter(date)
            setCurrentPage(1)
            setShowDateFilterModal(false)
          }}
          onClear={() => {
            setDateFilter('')
            setCurrentPage(1)
            setShowDateFilterModal(false)
          }}
          onClose={() => setShowDateFilterModal(false)}
        />
      )}
    </div>
  )
}

// Componente del modal de filtro de fecha
interface DateFilterModalProps {
  currentFilter: string
  onApply: (date: string) => void
  onClear: () => void
  onClose: () => void
}

function DateFilterModal({ currentFilter, onApply, onClear, onClose }: DateFilterModalProps) {
  const [selectedDate, setSelectedDate] = useState(currentFilter)

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    onApply(selectedDate)
  }

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div className="bg-white dark:bg-secondary-800 rounded-lg shadow-xl w-full max-w-md mx-4">
        <div className="p-6">
          <div className="flex items-center justify-between mb-4">
            <h3 className="text-lg font-semibold text-secondary-900 dark:text-white">
              Filtrar por fecha
            </h3>
            <button
              onClick={onClose}
              className="text-secondary-400 hover:text-secondary-600 dark:hover:text-secondary-300"
            >
              <XMarkIcon className="w-5 h-5" />
            </button>
          </div>

          <form onSubmit={handleSubmit}>
            <div className="mb-4">
              <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                Seleccionar fecha
              </label>
              <input
                type="date"
                value={selectedDate}
                onChange={(e) => setSelectedDate(e.target.value)}
                className="w-full px-3 py-2 border border-secondary-300 dark:border-secondary-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent dark:bg-secondary-800 dark:text-white"
              />
            </div>

            <div className="flex justify-between space-x-3">
              <button
                type="button"
                onClick={onClear}
                className="px-4 py-2 text-sm font-medium text-secondary-700 dark:text-secondary-300 hover:text-secondary-900 dark:hover:text-white border border-secondary-300 dark:border-secondary-600 rounded-lg hover:bg-secondary-50 dark:hover:bg-secondary-700"
              >
                Limpiar filtro
              </button>
              <div className="flex space-x-2">
                <button
                  type="button"
                  onClick={onClose}
                  className="px-4 py-2 text-sm font-medium text-secondary-700 dark:text-secondary-300 hover:text-secondary-900 dark:hover:text-white"
                >
                  Cancelar
                </button>
                <button
                  type="submit"
                  className="btn btn-primary text-sm"
                >
                  Aplicar filtro
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  )
}

// Modal component for adding activities
interface AddActivityModalProps {
  onClose: () => void
  onSubmit: (data: Partial<Activity>) => void
  isLoading: boolean
}

function AddActivityModal({ onClose, onSubmit, isLoading }: AddActivityModalProps) {
  const [formData, setFormData] = useState({
    type: 'note' as Activity['type'],
    description: '',
    leadStatus: '',
    visibility: 'team' as Activity['visibility']
  })

  const leadStatusOptions = [
    { value: 'new', label: 'Nuevo' },
    { value: 'contacted', label: 'Contactado' },
    { value: 'qualified', label: 'Calificado' },
    { value: 'converted', label: 'Convertido' },
    { value: 'lost', label: 'Perdido' }
  ]

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!formData.description.trim()) {
      toast.error('El comentario es requerido')
      return
    }
    onSubmit(formData)
  }

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
      <div className="bg-white dark:bg-secondary-900 rounded-xl shadow-xl max-w-md w-full max-h-[90vh] overflow-y-auto">
        <div className="p-6">
          <div className="flex items-center justify-between mb-4">
            <h3 className="text-lg font-semibold text-secondary-900 dark:text-white">
              Nueva Actividad
            </h3>
            <button
              onClick={onClose}
              className="text-secondary-400 hover:text-secondary-600 dark:hover:text-secondary-300"
            >
              <XMarkIcon className="w-6 h-6" />
            </button>
          </div>

          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                Tipo de Actividad
              </label>
              <select
                value={formData.type}
                onChange={(e) => setFormData({ ...formData, type: e.target.value as Activity['type'] })}
                className="w-full px-3 py-2 border border-secondary-300 dark:border-secondary-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent dark:bg-secondary-800 dark:text-white"
              >
                {Object.entries(activityTypeConfig).map(([key, config]) => (
                  <option key={key} value={key}>{config.label}</option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                Comentario *
              </label>
              <textarea
                value={formData.description}
                onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                rows={3}
                className="w-full px-3 py-2 border border-secondary-300 dark:border-secondary-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent dark:bg-secondary-800 dark:text-white"
                placeholder="Ingrese su comentario"
                required
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                Estado del Lead (opcional)
              </label>
              <select
                value={formData.leadStatus}
                onChange={(e) => setFormData({ ...formData, leadStatus: e.target.value })}
                className="w-full px-3 py-2 border border-secondary-300 dark:border-secondary-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent dark:bg-secondary-800 dark:text-white"
              >
                <option value="">Sin cambios</option>
                {leadStatusOptions.map(option => (
                  <option key={option.value} value={option.value}>{option.label}</option>
                ))}
              </select>
            </div>

            <div className="flex justify-end space-x-3 pt-4">
              <button
                type="button"
                onClick={onClose}
                className="px-4 py-2 text-sm font-medium text-secondary-700 dark:text-secondary-300 hover:text-secondary-900 dark:hover:text-white"
              >
                Cancelar
              </button>
              <button
                type="submit"
                disabled={isLoading}
                className="btn btn-primary text-sm"
              >
                {isLoading ? 'Creando...' : 'Crear Actividad'}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  )
}