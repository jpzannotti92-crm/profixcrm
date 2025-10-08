import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { 
  PlusIcon, 
  MagnifyingGlassIcon,
  FunnelIcon,
  EyeIcon,
  PencilIcon,
  TrashIcon,
  BuildingOfficeIcon,
  UsersIcon,
  ChartBarIcon,
  CurrencyDollarIcon
} from '@heroicons/react/24/outline'

import { desksApi, usersApi } from '../../services/api'
import LoadingSpinner from '../../components/ui/LoadingSpinner'
import { cn } from '../../utils/cn'
import toast from 'react-hot-toast'
import { User, PaginatedResponse } from '../../types'

interface Desk {
  id: number
  name: string
  description: string
  manager_id?: number
  manager_name?: string
  status: 'active' | 'inactive'
  users_count: number
  leads_count: number
  conversions_count?: number
  conversion_rate?: number
  total_revenue?: number
  target_monthly?: number
  user_count?: number
  lead_count?: number
  color?: string
  max_leads?: number
  auto_assign?: boolean
  working_hours_start?: string
  working_hours_end?: string
  timezone?: string
  created_at: string
  updated_at: string
  users?: Array<{
    id: number
    username: string
    first_name: string
    last_name: string
    email: string
    is_primary: boolean
    assigned_at: string
  }>
}

interface DeskModalProps {
  isOpen: boolean
  onClose: () => void
  desk?: Desk | null
  onSuccess: () => void
}

interface DeskDetailModalProps {
  isOpen: boolean
  desk: Desk | null
  onClose: () => void
}

interface DeskFilters {
  search: string
  status: string
  manager_id: string
}

export default function DesksPage() {
  const [filters, setFilters] = useState<DeskFilters>({
    search: '',
    status: '',
    manager_id: '',
  })
  const [showCreateModal, setShowCreateModal] = useState(false)
  const [showEditModal, setShowEditModal] = useState(false)
  const [showDetailModal, setShowDetailModal] = useState(false)
  const [selectedDesk, setSelectedDesk] = useState<Desk | null>(null)
  const limit = 20
  const page = 1

  const queryClient = useQueryClient()

  const { data, isLoading } = useQuery<PaginatedResponse<Desk>>({
    queryKey: ['desks', filters, page],
    queryFn: () => desksApi.getDesks({ ...filters, page, limit }),
    placeholderData: (previousData) => previousData,
  })

  const deleteDeskMutation = useMutation({
    mutationFn: (id: number) => desksApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['desks'] })
      toast.success('Mesa eliminada exitosamente')
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || 'Error al eliminar la mesa')
    }
  })

  const getStatusBadge = (status: Desk['status']) => {
    const statusConfig = {
      active: { label: 'Activa', color: 'bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-200' },
      inactive: { label: 'Inactiva', color: 'bg-danger-100 text-danger-800 dark:bg-danger-900 dark:text-danger-200' },
    }

    const config = statusConfig[status] || statusConfig.active
    return (
      <span className={cn('badge', config.color)}>
        {config.label}
      </span>
    )
  }

  const formatCurrency = (amount: number | undefined) => {
    if (amount === undefined || amount === null) return '$0.00'
    return new Intl.NumberFormat('es-ES', {
      style: 'currency',
      currency: 'USD',
    }).format(amount)
  }

  const handleDeleteDesk = (desk: Desk) => {
    if (desk.users_count > 0) {
      toast.error('No se puede eliminar una mesa que tiene usuarios asignados')
      return
    }

    if (window.confirm(`¿Estás seguro de que quieres eliminar la mesa "${desk.name}"?`)) {
      deleteDeskMutation.mutate(desk.id)
    }
  }

  const handleEditDesk = (desk: Desk) => {
    setSelectedDesk(desk)
    setShowEditModal(true)
  }

  const handleViewDesk = (desk: Desk) => {
    setSelectedDesk(desk)
    setShowDetailModal(true)
  }

  if (isLoading && !data) {
    return (
      <div className="flex items-center justify-center h-64">
        <LoadingSpinner size="lg" />
      </div>
    )
  }

  return (
    <div className="space-y-6 animate-fade-in">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold text-secondary-900 dark:text-white">
            Gestión de Mesas
          </h1>
          <p className="text-secondary-600 dark:text-secondary-400 mt-1">
            Administra mesas de trabajo y equipos de ventas
          </p>
        </div>
        
        <div className="flex space-x-3 mt-4 sm:mt-0">
          <button 
            onClick={() => setShowCreateModal(true)}
            className="btn-primary"
          >
            <PlusIcon className="w-4 h-4 mr-2" />
            Nueva Mesa
          </button>
        </div>
      </div>

      {/* Filtros */}
      <div className="card">
        <div className="card-body">
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div className="relative">
              <MagnifyingGlassIcon className="w-5 h-5 absolute left-3 top-1/2 transform -translate-y-1/2 text-secondary-400" />
              <input
                type="text"
                placeholder="Buscar mesas..."
                className="input pl-10"
                value={filters.search}
                onChange={(e) => setFilters({ ...filters, search: e.target.value })}
              />
            </div>
            
            <select
              className="input"
              value={filters.status}
              onChange={(e) => setFilters({ ...filters, status: e.target.value })}
            >
              <option value="">Todos los estados</option>
              <option value="active">Activa</option>
              <option value="inactive">Inactiva</option>
            </select>

            <select
              className="input"
              value={filters.manager_id}
              onChange={(e) => setFilters({ ...filters, manager_id: e.target.value })}
            >
              <option value="">Todos los managers</option>
              <option value="2">Carlos Rodríguez</option>
              <option value="7">Laura Sánchez</option>
              <option value="12">Fernando Castro</option>
            </select>

            <button
              onClick={() => setFilters({ search: '', status: '', manager_id: '' })}
              className="btn-ghost"
            >
              <FunnelIcon className="w-4 h-4 mr-2" />
              Limpiar
            </button>
          </div>
        </div>
      </div>

      {/* Estadísticas rápidas */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div className="card">
          <div className="card-body text-center">
            <div className="text-2xl font-bold text-primary-600 dark:text-primary-400">
              {data?.pagination?.total || 0}
            </div>
            <div className="text-sm text-secondary-600 dark:text-secondary-400">
              Total Mesas
            </div>
          </div>
        </div>
        <div className="card">
          <div className="card-body text-center">
            <div className="text-2xl font-bold text-success-600 dark:text-success-400">
              {data?.data?.filter((desk: Desk) => desk.status === 'active').length || 0}
            </div>
            <div className="text-sm text-secondary-600 dark:text-secondary-400">
              Activas
            </div>
          </div>
        </div>
        <div className="card">
          <div className="card-body text-center">
            <div className="text-2xl font-bold text-blue-600 dark:text-blue-400">
              {data?.data?.reduce((sum: number, desk: Desk) => sum + (desk.user_count || desk.users_count || 0), 0) || 0}
            </div>
            <div className="text-sm text-secondary-600 dark:text-secondary-400">
              Total Usuarios
            </div>
          </div>
        </div>
        <div className="card">
          <div className="card-body text-center">
            <div className="text-2xl font-bold text-green-600 dark:text-green-400">
              {formatCurrency(data?.data?.reduce((sum: number, desk: Desk) => sum + (desk.total_revenue || 0), 0) || 0)}
            </div>
            <div className="text-sm text-secondary-600 dark:text-secondary-400">
              Revenue Total
            </div>
          </div>
        </div>
      </div>

      {/* Grid de mesas */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {data?.data?.map((desk: Desk) => (
          <div key={desk.id} className="card hover:shadow-lg transition-shadow">
            <div className="card-body">
              {/* Header de la mesa */}
              <div className="flex items-start justify-between mb-4">
                <div className="flex items-center">
                  <div className="w-12 h-12 bg-primary-100 dark:bg-primary-900 rounded-lg flex items-center justify-center">
                    <BuildingOfficeIcon className="w-6 h-6 text-primary-600 dark:text-primary-400" />
                  </div>
                  <div className="ml-3">
                    <h3 className="text-lg font-semibold text-secondary-900 dark:text-white">
                      {desk.name}
                    </h3>
                    <p className="text-sm text-secondary-500 dark:text-secondary-400">
                      {desk.manager_name || 'Sin manager'}
                    </p>
                  </div>
                </div>
                {getStatusBadge(desk.status)}
              </div>

              {/* Descripción */}
              <p className="text-sm text-secondary-600 dark:text-secondary-300 mb-4 line-clamp-2">
                {desk.description}
              </p>

              {/* Métricas */}
              <div className="grid grid-cols-2 gap-4 mb-4">
                <div className="text-center">
                  <div className="flex items-center justify-center mb-1">
                    <UsersIcon className="w-4 h-4 text-secondary-400 mr-1" />
                    <span className="text-lg font-bold text-secondary-900 dark:text-white">
                      {desk.users_count}
                    </span>
                  </div>
                  <p className="text-xs text-secondary-500 dark:text-secondary-400">Usuarios</p>
                </div>
                
                <div className="text-center">
                  <div className="flex items-center justify-center mb-1">
                    <ChartBarIcon className="w-4 h-4 text-secondary-400 mr-1" />
                    <span className="text-lg font-bold text-secondary-900 dark:text-white">
                      {desk.leads_count}
                    </span>
                  </div>
                  <p className="text-xs text-secondary-500 dark:text-secondary-400">Leads</p>
                </div>
              </div>

              {/* Conversiones y Revenue */}
              <div className="space-y-2 mb-4">
                <div className="flex justify-between items-center">
                  <span className="text-sm text-secondary-600 dark:text-secondary-400">Conversiones:</span>
                  <span className="text-sm font-medium text-secondary-900 dark:text-white">
                    {desk.conversions_count} ({(desk.conversion_rate || 0).toFixed(1)}%)
                  </span>
                </div>
                <div className="flex justify-between items-center">
                  <span className="text-sm text-secondary-600 dark:text-secondary-400">Revenue:</span>
                  <span className="text-sm font-medium text-success-600 dark:text-success-400">
                    {formatCurrency(desk.total_revenue)}
                  </span>
                </div>
                <div className="flex justify-between items-center">
                  <span className="text-sm text-secondary-600 dark:text-secondary-400">Target:</span>
                  <span className="text-sm font-medium text-secondary-900 dark:text-white">
                    {formatCurrency(desk.target_monthly)}
                  </span>
                </div>
              </div>

              {/* Progress bar */}
              <div className="mb-4">
                <div className="flex justify-between text-xs text-secondary-600 dark:text-secondary-400 mb-1">
                  <span>Progreso del mes</span>
                  <span>{(((desk.total_revenue || 0) / (desk.target_monthly || 1)) * 100).toFixed(0)}%</span>
                </div>
                <div className="w-full bg-secondary-200 dark:bg-secondary-700 rounded-full h-2">
                  <div 
                    className="bg-primary-600 h-2 rounded-full transition-all duration-300"
                    style={{ width: `${Math.min(((desk.total_revenue || 0) / (desk.target_monthly || 1)) * 100, 100)}%` }}
                  ></div>
                </div>
              </div>

              {/* Acciones */}
              <div className="flex justify-end space-x-2">
                <button 
                  onClick={() => handleViewDesk(desk)}
                  className="text-primary-600 hover:text-primary-900 dark:text-primary-400 dark:hover:text-primary-300"
                  title="Ver detalles"
                >
                  <EyeIcon className="w-4 h-4" />
                </button>
                <button 
                  onClick={() => handleEditDesk(desk)}
                  className="text-secondary-600 hover:text-secondary-900 dark:text-secondary-400 dark:hover:text-secondary-300"
                  title="Editar mesa"
                >
                  <PencilIcon className="w-4 h-4" />
                </button>
                <button 
                  onClick={() => handleDeleteDesk(desk)}
                  disabled={desk.users_count > 0}
                  className="text-danger-600 hover:text-danger-900 dark:text-danger-400 dark:hover:text-danger-300 disabled:opacity-50 disabled:cursor-not-allowed"
                  title="Eliminar mesa"
                >
                  <TrashIcon className="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>
        )) || []}
      </div>

      {/* Estado vacío */}
      {data?.data && data.data.length === 0 && (
        <div className="text-center py-12">
          <div className="text-secondary-400 mb-4">
            <BuildingOfficeIcon className="w-12 h-12 mx-auto" />
          </div>
          <h3 className="text-lg font-medium text-secondary-900 dark:text-white mb-2">
            No se encontraron mesas
          </h3>
          <p className="text-secondary-500 dark:text-secondary-400 mb-6">
            Comienza creando tu primera mesa o ajusta los filtros de búsqueda.
          </p>
          <button 
            onClick={() => setShowCreateModal(true)}
            className="btn-primary"
          >
            <PlusIcon className="w-4 h-4 mr-2" />
            Crear Primera Mesa
          </button>
        </div>
      )}

      {/* Modales */}
      {showCreateModal && (
        <DeskModal
          isOpen={showCreateModal}
          onClose={() => setShowCreateModal(false)}
          desk={null}
          onSuccess={() => {
            setShowCreateModal(false)
            queryClient.invalidateQueries({ queryKey: ['desks'] })
          }}
        />
      )}

      {showEditModal && selectedDesk && (
        <DeskModal
          isOpen={showEditModal}
          onClose={() => {
            setShowEditModal(false)
            setSelectedDesk(null)
          }}
          desk={selectedDesk}
          onSuccess={() => {
            setShowEditModal(false)
            setSelectedDesk(null)
            queryClient.invalidateQueries({ queryKey: ['desks'] })
          }}
        />
      )}

      {showDetailModal && selectedDesk && (
        <DeskDetailModal
          isOpen={showDetailModal}
          desk={selectedDesk}
          onClose={() => {
            setShowDetailModal(false)
            setSelectedDesk(null)
          }}
        />
      )}
    </div>
  )
}

// Componente Modal para Crear/Editar Mesa
function DeskModal({ isOpen, onClose, desk, onSuccess }: DeskModalProps) {
  const [formData, setFormData] = useState({
    name: desk?.name || '',
    description: desk?.description || '',
    manager_id: desk?.manager_id || '',
    manager_name: desk?.manager_name || '',
    status: desk?.status || 'active',
    target_monthly: desk?.target_monthly || 0
  })

  // Cargar usuarios para el selector de manager
  const { data: usersData, isLoading: usersLoading } = useQuery<PaginatedResponse<User>>({
    queryKey: ['users-for-desk'],
    queryFn: () => usersApi.getUsers({ status: 'active' }),
    enabled: isOpen,
      staleTime: 5 * 60 * 1000 // 5 minutos
  })

  const createDeskMutation = useMutation<Desk, Error, DeskFormData>({
    mutationFn: (data: DeskFormData) => desksApi.create(data),
    onSuccess: () => {
      toast.success('Mesa creada exitosamente')
      onSuccess()
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || 'Error al crear la mesa')
    }
  })

  const updateDeskMutation = useMutation<Desk, Error, DeskFormData>({
    mutationFn: (data: DeskFormData) => desksApi.update(desk!.id, data),
    onSuccess: () => {
      toast.success('Mesa actualizada exitosamente')
      onSuccess()
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || 'Error al actualizar la mesa')
    }
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    
    if (desk) {
      updateDeskMutation.mutate(formData)
    } else {
      createDeskMutation.mutate(formData)
    }
  }

  const handleManagerChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const selectedId = e.target.value
    const selectedUser = usersData?.data?.find((user: User) => user.id.toString() === selectedId)
    
    setFormData({ 
      ...formData, 
      manager_id: selectedId,
      manager_name: selectedUser ? `${selectedUser.first_name} ${selectedUser.last_name}` : ''
    })
  }

  if (!isOpen) return null

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div className="bg-white dark:bg-secondary-800 rounded-lg p-6 w-full max-w-2xl mx-4">
        <h3 className="text-lg font-semibold text-secondary-900 dark:text-white mb-6">
          {desk ? 'Editar Mesa' : 'Crear Nueva Mesa'}
        </h3>
        
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                Nombre de la Mesa
              </label>
              <input
                type="text"
                value={formData.name}
                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                className="input"
                placeholder="ej: Mesa Principal"
                required
              />
            </div>
            
            <div>
              <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                Estado
              </label>
              <select
                value={formData.status}
                onChange={(e) => setFormData({ ...formData, status: e.target.value as 'active' | 'inactive' })}
                className="input"
              >
                <option value="active">Activa</option>
                <option value="inactive">Inactiva</option>
              </select>
            </div>
          </div>

          <div>
            <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
              Descripción
            </label>
            <textarea
              value={formData.description}
              onChange={(e) => setFormData({ ...formData, description: e.target.value })}
              className="input min-h-[80px]"
              placeholder="Describe el propósito y características de esta mesa..."
              required
            />
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                Manager
              </label>
              {usersLoading ? (
                <div className="input flex items-center justify-center">
                  <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-primary-600"></div>
                  <span className="ml-2 text-sm text-secondary-500">Cargando usuarios...</span>
                </div>
              ) : (
                <select
                  value={formData.manager_id}
                  onChange={handleManagerChange}
                  className="input"
                >
                  <option value="">Sin manager asignado</option>
                  {usersData?.data?.map((user: User) => (
                    <option key={user.id} value={user.id}>
                      {user.first_name} {user.last_name} ({user.username})
                    </option>
                  ))}
                </select>
              )}
            </div>

            <div>
              <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                Target Mensual ($)
              </label>
              <input
                type="number"
                value={formData.target_monthly}
                onChange={(e) => setFormData({ ...formData, target_monthly: parseFloat(e.target.value) || 0 })}
                className="input"
                placeholder="50000"
                min="0"
                step="100"
              />
            </div>
          </div>

          <div className="flex justify-end space-x-3 pt-6 border-t border-secondary-200 dark:border-secondary-700">
            <button type="button" onClick={onClose} className="btn-secondary">
              Cancelar
            </button>
            <button 
              type="submit" 
              disabled={createDeskMutation.isPending || updateDeskMutation.isPending}
              className="btn-primary disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {desk ? 'Actualizar' : 'Crear'} Mesa
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

// Componente Modal de Detalles de Mesa
function DeskDetailModal({ isOpen, desk, onClose }: DeskDetailModalProps) {
  if (!isOpen || !desk) return null

  // Mapeo de manager_id a nombres (esto debería venir de la API)
  const managerNames: { [key: string]: string } = {
    '2': 'Carlos Rodríguez',
    '7': 'Laura Sánchez', 
    '12': 'Fernando Castro',
    '16': 'Patricia Mendoza'
  }

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div className="bg-white dark:bg-secondary-800 rounded-lg p-6 w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto">
        <div className="flex justify-between items-start mb-6">
          <div>
            <h3 className="text-xl font-semibold text-secondary-900 dark:text-white">
              {desk.name}
            </h3>
            <p className="text-secondary-600 dark:text-secondary-400">
              Manager: {desk.manager_name || (desk.manager_id ? managerNames[desk.manager_id.toString()] : null) || 'Sin asignar'}
            </p>
          </div>
          <button onClick={onClose} className="text-secondary-400 hover:text-secondary-600">
            ✕
          </button>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
          <div className="card">
            <div className="card-body text-center">
              <UsersIcon className="w-8 h-8 text-primary-600 mx-auto mb-2" />
              <div className="text-2xl font-bold text-secondary-900 dark:text-white">
                {desk.users_count}
              </div>
              <div className="text-sm text-secondary-600 dark:text-secondary-400">
                Usuarios Asignados
              </div>
            </div>
          </div>

          <div className="card">
            <div className="card-body text-center">
              <ChartBarIcon className="w-8 h-8 text-warning-600 mx-auto mb-2" />
              <div className="text-2xl font-bold text-secondary-900 dark:text-white">
                {desk.conversions_count}
              </div>
              <div className="text-sm text-secondary-600 dark:text-secondary-400">
                Conversiones ({(desk.conversion_rate || 0).toFixed(1)}%)
              </div>
            </div>
          </div>

          <div className="card">
            <div className="card-body text-center">
              <CurrencyDollarIcon className="w-8 h-8 text-success-600 mx-auto mb-2" />
              <div className="text-2xl font-bold text-secondary-900 dark:text-white">
                {desk.total_revenue ? new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'USD' }).format(desk.total_revenue) : '$0.00'}
              </div>
              <div className="text-sm text-secondary-600 dark:text-secondary-400">
                Revenue Total
              </div>
            </div>
          </div>
        </div>

        {/* Lista de usuarios */}
        <div className="card">
          <div className="card-header">
            <h4 className="text-lg font-semibold text-secondary-900 dark:text-white">
              Usuarios de la Mesa
            </h4>
          </div>
          <div className="card-body p-0">
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-secondary-50 dark:bg-secondary-800">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase">
                      Usuario
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase">
                      Rol
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase">
                      Leads
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase">
                      Conversiones
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-secondary-200 dark:divide-secondary-700">
                  {desk.users?.map((user) => (
                    <tr key={user.id}>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="text-sm font-medium text-secondary-900 dark:text-white">
                          {user.first_name} {user.last_name}
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="text-sm text-secondary-600 dark:text-secondary-400">
                          {user.username}
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="text-sm text-secondary-900 dark:text-white">
                          -
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="text-sm text-success-600 dark:text-success-400">
                          -
                        </div>
                      </td>
                    </tr>
                  )) || []}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

interface DeskFormData {
  name: string
  description: string
  manager_id: number | string
  manager_name: string
  status: 'active' | 'inactive'
  target_monthly: number
}
