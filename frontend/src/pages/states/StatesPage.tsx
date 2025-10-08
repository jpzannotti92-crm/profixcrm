import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { 
  PlusIcon,
  PencilIcon,
  TrashIcon,
  ArrowsUpDownIcon,
  SwatchIcon,
  EyeIcon,
  EyeSlashIcon,
  ArrowPathIcon,
  Cog6ToothIcon
} from '@heroicons/react/24/outline'
import { cn } from '../../utils/cn'
import toast from 'react-hot-toast'

interface DynamicState {
  id: number
  desk_id: number
  name: string
  color: string
  icon?: string
  description?: string
  is_active: boolean
  order_index: number
  created_at: string
  updated_at: string
}

interface StateTransition {
  id: number
  desk_id: number
  from_state_id: number
  to_state_id: number
  name: string
  description?: string
  requires_comment: boolean
  is_active: boolean
  created_at: string
  updated_at: string
}

interface StateFormData {
  name: string
  color: string
  icon?: string
  description?: string
  is_active: boolean
}

interface TransitionFormData {
  from_state_id: number
  to_state_id: number
  name: string
  description?: string
  requires_comment: boolean
  is_active: boolean
}

const colorOptions = [
  { value: 'blue', label: 'Azul', class: 'bg-blue-500' },
  { value: 'green', label: 'Verde', class: 'bg-green-500' },
  { value: 'yellow', label: 'Amarillo', class: 'bg-yellow-500' },
  { value: 'red', label: 'Rojo', class: 'bg-red-500' },
  { value: 'purple', label: 'Morado', class: 'bg-purple-500' },
  { value: 'indigo', label: 'Índigo', class: 'bg-indigo-500' },
  { value: 'pink', label: 'Rosa', class: 'bg-pink-500' },
  { value: 'gray', label: 'Gris', class: 'bg-gray-500' }
]

export default function StatesPage() {
  const [activeTab, setActiveTab] = useState<'states' | 'transitions'>('states')
  const [showStateModal, setShowStateModal] = useState(false)
  const [showTransitionModal, setShowTransitionModal] = useState(false)
  const [editingState, setEditingState] = useState<DynamicState | null>(null)
  const [editingTransition, setEditingTransition] = useState<StateTransition | null>(null)
  const [stateFormData, setStateFormData] = useState<StateFormData>({
    name: '',
    color: 'blue',
    icon: '',
    description: '',
    is_active: true
  })
  const [transitionFormData, setTransitionFormData] = useState<TransitionFormData>({
    from_state_id: 0,
    to_state_id: 0,
    name: '',
    description: '',
    requires_comment: false,
    is_active: true
  })

  const queryClient = useQueryClient()

  // Obtener estados
  const { data: states, isLoading: isLoadingStates } = useQuery({
    queryKey: ['desk-states'],
    queryFn: async () => {
      const response = await fetch('/api/states.php', {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
        }
      })
      if (!response.ok) throw new Error('Error al cargar estados')
      return response.json()
    }
  })

  // Obtener transiciones
  const { data: transitions, isLoading: isLoadingTransitions } = useQuery({
    queryKey: ['state-transitions'],
    queryFn: async () => {
      const response = await fetch('/api/state-transitions.php', {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
        }
      })
      if (!response.ok) throw new Error('Error al cargar transiciones')
      return response.json()
    }
  })

  // Mutación para guardar estado
  const saveStateMutation = useMutation({
    mutationFn: async (data: any) => {
      const url = editingState ? `/api/states.php?id=${editingState.id}` : '/api/states.php'
      const method = editingState ? 'PUT' : 'POST'
      
      const response = await fetch(url, {
        method,
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
        },
        body: JSON.stringify(data)
      })
      
      if (!response.ok) throw new Error('Error al guardar estado')
      return response.json()
    },
    onSuccess: () => {
      toast.success(editingState ? 'Estado actualizado' : 'Estado creado')
      setShowStateModal(false)
      resetStateForm()
      queryClient.invalidateQueries({ queryKey: ['desk-states'] })
    },
    onError: (error: any) => {
      toast.error(error.message || 'Error al guardar estado')
    }
  })

  // Mutación para eliminar estado
  const deleteStateMutation = useMutation({
    mutationFn: async (id: number) => {
      const response = await fetch(`/api/states.php?id=${id}`, {
        method: 'DELETE',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
        }
      })
      
      if (!response.ok) throw new Error('Error al eliminar estado')
      return response.json()
    },
    onSuccess: () => {
      toast.success('Estado eliminado')
      queryClient.invalidateQueries({ queryKey: ['desk-states'] })
    },
    onError: (error: any) => {
      toast.error(error.message || 'Error al eliminar estado')
    }
  })

  // Mutación para guardar transición
  const saveTransitionMutation = useMutation({
    mutationFn: async (data: any) => {
      const url = editingTransition ? `/api/state-transitions.php?id=${editingTransition.id}` : '/api/state-transitions.php'
      const method = editingTransition ? 'PUT' : 'POST'
      
      const response = await fetch(url, {
        method,
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
        },
        body: JSON.stringify(data)
      })
      
      if (!response.ok) throw new Error('Error al guardar transición')
      return response.json()
    },
    onSuccess: () => {
      toast.success(editingTransition ? 'Transición actualizada' : 'Transición creada')
      setShowTransitionModal(false)
      resetTransitionForm()
      queryClient.invalidateQueries({ queryKey: ['state-transitions'] })
    },
    onError: (error: any) => {
      toast.error(error.message || 'Error al guardar transición')
    }
  })

  // Mutación para eliminar transición
  const deleteTransitionMutation = useMutation({
    mutationFn: async (id: number) => {
      const response = await fetch(`/api/state-transitions.php?id=${id}`, {
        method: 'DELETE',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
        }
      })
      
      if (!response.ok) throw new Error('Error al eliminar transición')
      return response.json()
    },
    onSuccess: () => {
      toast.success('Transición eliminada')
      queryClient.invalidateQueries({ queryKey: ['state-transitions'] })
    },
    onError: (error: any) => {
      toast.error(error.message || 'Error al eliminar transición')
    }
  })

  const resetStateForm = () => {
    setStateFormData({
      name: '',
      color: 'blue',
      icon: '',
      description: '',
      is_active: true
    })
    setEditingState(null)
  }

  const resetTransitionForm = () => {
    setTransitionFormData({
      from_state_id: 0,
      to_state_id: 0,
      name: '',
      description: '',
      requires_comment: false,
      is_active: true
    })
    setEditingTransition(null)
  }

  const handleEditState = (state: DynamicState) => {
    setEditingState(state)
    setStateFormData({
      name: state.name,
      color: state.color,
      icon: state.icon || '',
      description: state.description || '',
      is_active: state.is_active
    })
    setShowStateModal(true)
  }

  const handleEditTransition = (transition: StateTransition) => {
    setEditingTransition(transition)
    setTransitionFormData({
      from_state_id: transition.from_state_id,
      to_state_id: transition.to_state_id,
      name: transition.name,
      description: transition.description || '',
      requires_comment: transition.requires_comment,
      is_active: transition.is_active
    })
    setShowTransitionModal(true)
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-secondary-900 dark:text-white flex items-center">
            <Cog6ToothIcon className="w-8 h-8 mr-3 text-primary-600" />
            Gestión de Estados Dinámicos
          </h1>
          <p className="text-secondary-600 dark:text-secondary-400 mt-1">
            Configura los estados y transiciones para tu mesa de trabajo
          </p>
        </div>
      </div>

      {/* Tabs */}
      <div className="border-b border-secondary-200 dark:border-secondary-700">
        <nav className="-mb-px flex space-x-8">
          <button
            onClick={() => setActiveTab('states')}
            className={cn(
              'py-2 px-1 border-b-2 font-medium text-sm transition-colors',
              activeTab === 'states'
                ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                : 'border-transparent text-secondary-500 hover:text-secondary-700 hover:border-secondary-300 dark:text-secondary-400 dark:hover:text-secondary-300'
            )}
          >
            Estados
          </button>
          <button
            onClick={() => setActiveTab('transitions')}
            className={cn(
              'py-2 px-1 border-b-2 font-medium text-sm transition-colors',
              activeTab === 'transitions'
                ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                : 'border-transparent text-secondary-500 hover:text-secondary-700 hover:border-secondary-300 dark:text-secondary-400 dark:hover:text-secondary-300'
            )}
          >
            Transiciones
          </button>
        </nav>
      </div>

      {/* Estados Tab */}
      {activeTab === 'states' && (
        <div className="space-y-6">
          <div className="flex justify-between items-center">
            <h2 className="text-lg font-semibold text-secondary-900 dark:text-white">
              Estados Configurados
            </h2>
            <button
              onClick={() => {
                resetStateForm()
                setEditingState(null)
                setShowStateModal(true)
              }}
              className="btn btn-primary"
            >
              <PlusIcon className="w-4 h-4 mr-2" />
              Nuevo Estado
            </button>
          </div>

          {isLoadingStates ? (
            <div className="flex justify-center py-8">
              <ArrowPathIcon className="w-6 h-6 animate-spin text-secondary-400" />
            </div>
          ) : states?.data?.length > 0 ? (
            <div className="grid gap-4">
              {states.data.map((state: DynamicState) => (
                <div
                  key={state.id}
                  className="bg-white dark:bg-secondary-800 rounded-lg border border-secondary-200 dark:border-secondary-700 p-4"
                >
                  <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-3">
                      <div className={cn('w-4 h-4 rounded-full', `bg-${state.color}-500`)} />
                      <div>
                        <h3 className="font-medium text-secondary-900 dark:text-white">
                          {state.name}
                        </h3>
                        {state.description && (
                          <p className="text-sm text-secondary-500 dark:text-secondary-400">
                            {state.description}
                          </p>
                        )}
                      </div>
                    </div>
                    <div className="flex items-center space-x-2">
                      <span className={cn(
                        'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium',
                        state.is_active
                          ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                          : 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
                      )}>
                        {state.is_active ? (
                          <EyeIcon className="w-3 h-3 mr-1" />
                        ) : (
                          <EyeSlashIcon className="w-3 h-3 mr-1" />
                        )}
                        {state.is_active ? 'Activo' : 'Inactivo'}
                      </span>
                      <button
                        onClick={() => handleEditState(state)}
                        className="p-1 text-secondary-400 hover:text-secondary-600"
                      >
                        <PencilIcon className="w-4 h-4" />
                      </button>
                      <button
                        onClick={() => deleteStateMutation.mutate(state.id)}
                        className="p-1 text-red-400 hover:text-red-600"
                      >
                        <TrashIcon className="w-4 h-4" />
                      </button>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="text-center py-8">
              <SwatchIcon className="w-12 h-12 text-secondary-300 dark:text-secondary-600 mx-auto mb-4" />
              <p className="text-secondary-500 dark:text-secondary-400">
                No hay estados configurados
              </p>
            </div>
          )}
        </div>
      )}

      {/* Transiciones Tab */}
      {activeTab === 'transitions' && (
        <div className="space-y-6">
          <div className="flex justify-between items-center">
            <h2 className="text-lg font-semibold text-secondary-900 dark:text-white">
              Transiciones Configuradas
            </h2>
            <button
              onClick={() => {
                resetTransitionForm()
                setEditingTransition(null)
                setShowTransitionModal(true)
              }}
              className="btn btn-primary"
            >
              <PlusIcon className="w-4 h-4 mr-2" />
              Nueva Transición
            </button>
          </div>

          {isLoadingTransitions ? (
            <div className="flex justify-center py-8">
              <ArrowPathIcon className="w-6 h-6 animate-spin text-secondary-400" />
            </div>
          ) : transitions?.data?.length > 0 ? (
            <div className="grid gap-4">
              {transitions.data.map((transition: StateTransition) => (
                <div
                  key={transition.id}
                  className="bg-white dark:bg-secondary-800 rounded-lg border border-secondary-200 dark:border-secondary-700 p-4"
                >
                  <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-3">
                      <ArrowsUpDownIcon className="w-5 h-5 text-secondary-400" />
                      <div>
                        <h3 className="font-medium text-secondary-900 dark:text-white">
                          {transition.name}
                        </h3>
                        <p className="text-sm text-secondary-500 dark:text-secondary-400">
                          Estado {transition.from_state_id} → Estado {transition.to_state_id}
                        </p>
                      </div>
                    </div>
                    <div className="flex items-center space-x-2">
                      {transition.requires_comment && (
                        <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                          Requiere comentario
                        </span>
                      )}
                      <span className={cn(
                        'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium',
                        transition.is_active
                          ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                          : 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
                      )}>
                        {transition.is_active ? 'Activa' : 'Inactiva'}
                      </span>
                      <button
                        onClick={() => handleEditTransition(transition)}
                        className="p-1 text-secondary-400 hover:text-secondary-600"
                      >
                        <PencilIcon className="w-4 h-4" />
                      </button>
                      <button
                        onClick={() => deleteTransitionMutation.mutate(transition.id)}
                        className="p-1 text-red-400 hover:text-red-600"
                      >
                        <TrashIcon className="w-4 h-4" />
                      </button>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="text-center py-8">
              <ArrowPathIcon className="w-12 h-12 text-secondary-300 dark:text-secondary-600 mx-auto mb-4" />
              <p className="text-secondary-500 dark:text-secondary-400">
                No hay transiciones configuradas
              </p>
            </div>
          )}
        </div>
      )}

      {/* Modal de Estado */}
      {showStateModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white dark:bg-secondary-800 rounded-lg shadow-xl max-w-md w-full mx-4">
            <div className="p-6">
              <h3 className="text-lg font-semibold text-secondary-900 dark:text-white mb-4">
                {editingState ? 'Editar Estado' : 'Nuevo Estado'}
              </h3>
              <form
                onSubmit={(e) => {
                  e.preventDefault()
                  saveStateMutation.mutate({
                    ...stateFormData,
                    ...(editingState && { id: editingState.id })
                  })
                }}
                className="space-y-4"
              >
                <div>
                  <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                    Nombre *
                  </label>
                  <input
                    type="text"
                    value={stateFormData.name}
                    onChange={(e) => setStateFormData({ ...stateFormData, name: e.target.value })}
                    className="w-full px-3 py-2 border border-secondary-300 dark:border-secondary-600 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 dark:bg-secondary-700 dark:text-white"
                    required
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                    Color
                  </label>
                  <div className="grid grid-cols-4 gap-2">
                    {colorOptions.map((color) => (
                      <button
                        key={color.value}
                        type="button"
                        onClick={() => setStateFormData({ ...stateFormData, color: color.value })}
                        className={cn(
                          'p-3 rounded-md border-2 transition-all',
                          stateFormData.color === color.value
                            ? 'border-primary-500 ring-2 ring-primary-200'
                            : 'border-secondary-200 dark:border-secondary-600'
                        )}
                      >
                        <div className={cn('w-6 h-6 rounded-full mx-auto', color.class)} />
                        <span className="text-xs mt-1 block">{color.label}</span>
                      </button>
                    ))}
                  </div>
                </div>
                <div>
                  <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                    Descripción
                  </label>
                  <textarea
                    value={stateFormData.description}
                    onChange={(e) => setStateFormData({ ...stateFormData, description: e.target.value })}
                    className="w-full px-3 py-2 border border-secondary-300 dark:border-secondary-600 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 dark:bg-secondary-700 dark:text-white"
                    rows={3}
                  />
                </div>
                <div className="flex items-center">
                  <input
                    type="checkbox"
                    id="is_active"
                    checked={stateFormData.is_active}
                    onChange={(e) => setStateFormData({ ...stateFormData, is_active: e.target.checked })}
                    className="rounded border-secondary-300 text-primary-600 focus:ring-primary-500"
                  />
                  <label htmlFor="is_active" className="ml-2 text-sm text-secondary-700 dark:text-secondary-300">
                    Estado activo
                  </label>
                </div>
                <div className="flex justify-end space-x-3 pt-4">
                  <button
                    type="button"
                    onClick={() => setShowStateModal(false)}
                    className="btn btn-ghost"
                  >
                    Cancelar
                  </button>
                  <button
                    type="submit"
                    disabled={saveStateMutation.isPending}
                    className="btn btn-primary"
                  >
                    {saveStateMutation.isPending ? 'Guardando...' : 'Guardar'}
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}

      {/* Modal de Transición */}
      {showTransitionModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white dark:bg-secondary-800 rounded-lg shadow-xl max-w-md w-full mx-4">
            <div className="p-6">
              <h3 className="text-lg font-semibold text-secondary-900 dark:text-white mb-4">
                {editingTransition ? 'Editar Transición' : 'Nueva Transición'}
              </h3>
              <form
                onSubmit={(e) => {
                  e.preventDefault()
                  saveTransitionMutation.mutate({
                    ...transitionFormData,
                    ...(editingTransition && { id: editingTransition.id })
                  })
                }}
                className="space-y-4"
              >
                <div>
                  <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                    Estado Origen *
                  </label>
                  <select
                    value={transitionFormData.from_state_id}
                    onChange={(e) => setTransitionFormData({ ...transitionFormData, from_state_id: Number(e.target.value) })}
                    className="w-full px-3 py-2 border border-secondary-300 dark:border-secondary-600 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 dark:bg-secondary-700 dark:text-white"
                    required
                  >
                    <option value={0}>Seleccionar estado origen</option>
                    {states?.data?.map((state: DynamicState) => (
                      <option key={state.id} value={state.id}>
                        {state.name}
                      </option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                    Estado Destino *
                  </label>
                  <select
                    value={transitionFormData.to_state_id}
                    onChange={(e) => setTransitionFormData({ ...transitionFormData, to_state_id: Number(e.target.value) })}
                    className="w-full px-3 py-2 border border-secondary-300 dark:border-secondary-600 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 dark:bg-secondary-700 dark:text-white"
                    required
                  >
                    <option value={0}>Seleccionar estado destino</option>
                    {states?.data?.map((state: DynamicState) => (
                      <option key={state.id} value={state.id}>
                        {state.name}
                      </option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                    Nombre de la Transición *
                  </label>
                  <input
                    type="text"
                    value={transitionFormData.name}
                    onChange={(e) => setTransitionFormData({ ...transitionFormData, name: e.target.value })}
                    className="w-full px-3 py-2 border border-secondary-300 dark:border-secondary-600 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 dark:bg-secondary-700 dark:text-white"
                    required
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                    Descripción
                  </label>
                  <textarea
                    value={transitionFormData.description}
                    onChange={(e) => setTransitionFormData({ ...transitionFormData, description: e.target.value })}
                    className="w-full px-3 py-2 border border-secondary-300 dark:border-secondary-600 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 dark:bg-secondary-700 dark:text-white"
                    rows={3}
                  />
                </div>
                <div className="space-y-3">
                  <div className="flex items-center">
                    <input
                      type="checkbox"
                      id="requires_comment"
                      checked={transitionFormData.requires_comment}
                      onChange={(e) => setTransitionFormData({ ...transitionFormData, requires_comment: e.target.checked })}
                      className="rounded border-secondary-300 text-primary-600 focus:ring-primary-500"
                    />
                    <label htmlFor="requires_comment" className="ml-2 text-sm text-secondary-700 dark:text-secondary-300">
                      Requiere comentario obligatorio
                    </label>
                  </div>
                  <div className="flex items-center">
                    <input
                      type="checkbox"
                      id="transition_active"
                      checked={transitionFormData.is_active}
                      onChange={(e) => setTransitionFormData({ ...transitionFormData, is_active: e.target.checked })}
                      className="rounded border-secondary-300 text-primary-600 focus:ring-primary-500"
                    />
                    <label htmlFor="transition_active" className="ml-2 text-sm text-secondary-700 dark:text-secondary-300">
                      Transición activa
                    </label>
                  </div>
                </div>
                <div className="flex justify-end space-x-3 pt-4">
                  <button
                    type="button"
                    onClick={() => setShowTransitionModal(false)}
                    className="btn btn-ghost"
                  >
                    Cancelar
                  </button>
                  <button
                    type="submit"
                    disabled={saveTransitionMutation.isPending}
                    className="btn btn-primary"
                  >
                    {saveTransitionMutation.isPending ? 'Guardando...' : 'Guardar'}
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
