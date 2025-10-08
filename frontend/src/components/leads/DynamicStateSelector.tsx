import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { 
  ChevronDownIcon,
  CheckCircleIcon,
  ClockIcon,
  ExclamationTriangleIcon,
  ArrowPathIcon
} from '@heroicons/react/24/outline'
import { cn } from '../../utils/cn'
import toast from 'react-hot-toast'

interface DynamicState {
  id: number
  name: string
  color: string
  icon?: string
  description?: string
  is_active: boolean
  order_index: number
}

interface StateTransition {
  id: number
  from_state_id: number
  to_state_id: number
  name: string
  description?: string
  requires_comment: boolean
  is_active: boolean
}

interface StateHistory {
  id: number
  lead_id: number
  from_state_id?: number
  to_state_id: number
  from_state_name?: string
  to_state_name: string
  comment?: string
  changed_by: number
  changed_by_name: string
  created_at: string
}

interface DynamicStateSelectorProps {
  leadId: number
  currentStateId?: number
  currentStateName?: string
  onStateChange?: (newStateId: number, newStateName: string) => void
  showHistory?: boolean
  disabled?: boolean
}

export default function DynamicStateSelector({
  leadId,
  currentStateName,
  onStateChange,
  showHistory = false,
  disabled = false
}: DynamicStateSelectorProps) {
  const [isOpen, setIsOpen] = useState(false)
  const [showHistoryModal, setShowHistoryModal] = useState(false)
  const [selectedTransition, setSelectedTransition] = useState<StateTransition | null>(null)
  const [comment, setComment] = useState('')
  const queryClient = useQueryClient()

  // Obtener estados disponibles para el lead
  const { data: availableStates, isLoading: isLoadingStates } = useQuery({
    queryKey: ['lead-available-states', leadId],
    queryFn: async () => {
      const response = await fetch(`/api/leads.php?action=available-states&id=${leadId}`, {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
        }
      })
      if (!response.ok) throw new Error('Error al cargar estados disponibles')
      return response.json()
    },
    enabled: !!leadId
  })

  // Obtener historial de estados
  const { data: stateHistory, isLoading: isLoadingHistory } = useQuery({
    queryKey: ['lead-state-history', leadId],
    queryFn: async () => {
      const response = await fetch(`/api/leads.php?action=state-history&id=${leadId}`, {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
        }
      })
      if (!response.ok) throw new Error('Error al cargar historial de estados')
      return response.json()
    },
    enabled: !!leadId && showHistory
  })

  // Mutación para cambiar estado
  const changeStateMutation = useMutation({
    mutationFn: async ({ toStateId, comment }: { toStateId: number, comment?: string }) => {
      const response = await fetch('/api/lead-state-change.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
        },
        body: JSON.stringify({
          lead_id: leadId,
          to_state_id: toStateId,
          comment
        })
      })
      if (!response.ok) {
        const error = await response.json()
        throw new Error(error.message || 'Error al cambiar estado')
      }
      return response.json()
    },
    onSuccess: (data) => {
      queryClient.invalidateQueries({ queryKey: ['lead', leadId] })
      queryClient.invalidateQueries({ queryKey: ['lead-available-states', leadId] })
      queryClient.invalidateQueries({ queryKey: ['lead-state-history', leadId] })
      queryClient.invalidateQueries({ queryKey: ['leads'] })
      
      setSelectedTransition(null)
      setComment('')
      setIsOpen(false)
      
      if (onStateChange && data.success) {
        onStateChange(data.new_state_id, data.new_state_name)
      }
      
      toast.success('Estado cambiado correctamente')
    },
    onError: (error: Error) => {
      toast.error(error.message)
    }
  })

  const handleStateChange = (transition: StateTransition) => {
    if (transition.requires_comment) {
      setSelectedTransition(transition)
    } else {
      changeStateMutation.mutate({ toStateId: transition.to_state_id })
    }
  }

  const handleConfirmChange = () => {
    if (selectedTransition) {
      changeStateMutation.mutate({
        toStateId: selectedTransition.to_state_id,
        comment: comment.trim() || undefined
      })
    }
  }

  const getStateColor = (color: string) => {
    const colorMap: Record<string, string> = {
      'blue': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
      'green': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
      'yellow': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
      'red': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
      'purple': 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
      'indigo': 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
      'pink': 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-200',
      'gray': 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
    }
    return colorMap[color] || colorMap['gray']
  }

  const currentState = availableStates?.current_state

  return (
    <div className="relative">
      {/* Selector de Estado Actual */}
      <div className="flex items-center space-x-3">
        <div className="relative">
          <button
            onClick={() => !disabled && setIsOpen(!isOpen)}
            disabled={disabled || isLoadingStates}
            className={cn(
              'inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium transition-all duration-200',
              currentState ? getStateColor(currentState.color) : 'bg-gray-100 text-gray-800',
              !disabled && 'hover:shadow-md cursor-pointer',
              disabled && 'opacity-50 cursor-not-allowed'
            )}
          >
            {isLoadingStates ? (
              <ArrowPathIcon className="w-4 h-4 animate-spin mr-1.5" />
            ) : (
              <CheckCircleIcon className="w-4 h-4 mr-1.5" />
            )}
            <span className="capitalize">
              {currentState?.name || currentStateName || 'Sin estado'}
            </span>
            {!disabled && (
              <ChevronDownIcon className={cn(
                'w-4 h-4 ml-1.5 transition-transform duration-200',
                isOpen && 'rotate-180'
              )} />
            )}
          </button>

          {/* Dropdown de Estados Disponibles */}
          {isOpen && availableStates?.available_transitions && (
            <div className="absolute top-full left-0 mt-2 w-64 bg-white dark:bg-secondary-800 rounded-lg shadow-lg border border-secondary-200 dark:border-secondary-700 z-50">
              <div className="p-2">
                <div className="text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase tracking-wide px-2 py-1 mb-1">
                  Cambiar a:
                </div>
                {availableStates.available_transitions.map((transition: StateTransition) => (
                  <button
                    key={transition.id}
                    onClick={() => handleStateChange(transition)}
                    disabled={changeStateMutation.isPending}
                    className="w-full text-left px-3 py-2 rounded-md hover:bg-secondary-50 dark:hover:bg-secondary-700 transition-colors disabled:opacity-50"
                  >
                    <div className="flex items-center justify-between">
                      <div className="flex items-center space-x-2">
                        <div className={cn(
                          'w-3 h-3 rounded-full',
                          `bg-${availableStates.states.find((s: DynamicState) => s.id === transition.to_state_id)?.color || 'gray'}-500`
                        )} />
                        <span className="text-sm font-medium text-secondary-900 dark:text-white">
                          {transition.name}
                        </span>
                      </div>
                      {transition.requires_comment && (
                        <ExclamationTriangleIcon className="w-4 h-4 text-amber-500" />
                      )}
                    </div>
                    {transition.description && (
                      <p className="text-xs text-secondary-500 dark:text-secondary-400 mt-1 ml-5">
                        {transition.description}
                      </p>
                    )}
                  </button>
                ))}
              </div>
            </div>
          )}
        </div>

        {/* Botón de Historial */}
        {showHistory && (
          <button
            onClick={() => setShowHistoryModal(true)}
            className="p-1.5 text-secondary-400 hover:text-secondary-600 dark:hover:text-secondary-300 transition-colors rounded-lg hover:bg-secondary-100 dark:hover:bg-secondary-800"
            title="Ver historial de estados"
          >
            <ClockIcon className="w-4 h-4" />
          </button>
        )}
      </div>

      {/* Modal de Confirmación con Comentario */}
      {selectedTransition && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white dark:bg-secondary-800 rounded-lg shadow-xl max-w-md w-full mx-4">
            <div className="p-6">
              <h3 className="text-lg font-semibold text-secondary-900 dark:text-white mb-4">
                Cambiar Estado
              </h3>
              <p className="text-secondary-600 dark:text-secondary-400 mb-4">
                ¿Estás seguro de cambiar el estado a <strong>{selectedTransition.name}</strong>?
              </p>
              <div className="mb-4">
                <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                  Comentario {selectedTransition.requires_comment && <span className="text-red-500">*</span>}
                </label>
                <textarea
                  value={comment}
                  onChange={(e) => setComment(e.target.value)}
                  placeholder="Describe la razón del cambio de estado..."
                  className="w-full px-3 py-2 border border-secondary-300 dark:border-secondary-600 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 dark:bg-secondary-700 dark:text-white"
                  rows={3}
                  required={selectedTransition.requires_comment}
                />
              </div>
              <div className="flex justify-end space-x-3">
                <button
                  onClick={() => {
                    setSelectedTransition(null)
                    setComment('')
                  }}
                  className="px-4 py-2 text-secondary-600 dark:text-secondary-400 hover:text-secondary-800 dark:hover:text-secondary-200 transition-colors"
                >
                  Cancelar
                </button>
                <button
                  onClick={handleConfirmChange}
                  disabled={changeStateMutation.isPending || (selectedTransition.requires_comment && !comment.trim())}
                  className="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                  {changeStateMutation.isPending ? 'Cambiando...' : 'Confirmar'}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Modal de Historial */}
      {showHistoryModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white dark:bg-secondary-800 rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[80vh] overflow-hidden">
            <div className="p-6 border-b border-secondary-200 dark:border-secondary-700">
              <h3 className="text-lg font-semibold text-secondary-900 dark:text-white">
                Historial de Estados
              </h3>
            </div>
            <div className="p-6 overflow-y-auto max-h-96">
              {isLoadingHistory ? (
                <div className="flex justify-center py-8">
                  <ArrowPathIcon className="w-6 h-6 animate-spin text-secondary-400" />
                </div>
              ) : stateHistory?.data?.length > 0 ? (
                <div className="space-y-4">
                  {stateHistory.data.map((entry: StateHistory) => (
                    <div key={entry.id} className="flex items-start space-x-3 p-3 bg-secondary-50 dark:bg-secondary-700 rounded-lg">
                      <div className="flex-shrink-0 w-8 h-8 bg-primary-100 dark:bg-primary-900 rounded-full flex items-center justify-center">
                        <CheckCircleIcon className="w-4 h-4 text-primary-600 dark:text-primary-400" />
                      </div>
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center justify-between">
                          <p className="text-sm font-medium text-secondary-900 dark:text-white">
                            {entry.from_state_name ? 
                              `${entry.from_state_name} → ${entry.to_state_name}` : 
                              `Estado inicial: ${entry.to_state_name}`
                            }
                          </p>
                          <p className="text-xs text-secondary-500 dark:text-secondary-400">
                            {new Date(entry.created_at).toLocaleDateString('es-ES', {
                              day: '2-digit',
                              month: '2-digit',
                              year: 'numeric',
                              hour: '2-digit',
                              minute: '2-digit'
                            })}
                          </p>
                        </div>
                        <p className="text-xs text-secondary-600 dark:text-secondary-400 mt-1">
                          Por: {entry.changed_by_name}
                        </p>
                        {entry.comment && (
                          <p className="text-sm text-secondary-700 dark:text-secondary-300 mt-2 italic">
                            "{entry.comment}"
                          </p>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="text-center py-8">
                  <ClockIcon className="w-12 h-12 text-secondary-300 dark:text-secondary-600 mx-auto mb-4" />
                  <p className="text-secondary-500 dark:text-secondary-400">
                    No hay historial de cambios de estado
                  </p>
                </div>
              )}
            </div>
            <div className="p-6 border-t border-secondary-200 dark:border-secondary-700">
              <button
                onClick={() => setShowHistoryModal(false)}
                className="w-full px-4 py-2 bg-secondary-100 dark:bg-secondary-700 text-secondary-700 dark:text-secondary-300 rounded-md hover:bg-secondary-200 dark:hover:bg-secondary-600 transition-colors"
              >
                Cerrar
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Overlay para cerrar dropdown */}
      {isOpen && (
        <div 
          className="fixed inset-0 z-40" 
          onClick={() => setIsOpen(false)}
        />
      )}
    </div>
  )
}
