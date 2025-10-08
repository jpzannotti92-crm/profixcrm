import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { 
  PlusIcon, 
  MagnifyingGlassIcon,
  FunnelIcon,
  EyeIcon,
  PencilIcon,
  TrashIcon,
  ShieldCheckIcon,
  UsersIcon
} from '@heroicons/react/24/outline'

import { rolesApi } from '../../services/api'
import LoadingSpinner from '../../components/ui/LoadingSpinner'
import { cn } from '../../utils/cn'
import toast from 'react-hot-toast'

interface Role {
  id: number
  name: string
  display_name: string
  description: string
  permissions: string[]
  users_count: number
  status: 'active' | 'inactive'
  created_at: string
  updated_at: string
  color?: string
  is_system?: boolean
}

interface Permission {
  id: number
  name: string
  display_name: string
  description: string
  module: string
  action: string
}

interface RoleModalProps {
  isOpen: boolean
  onClose: () => void
  role?: Role | null
  permissions: Permission[]
  permissionsLoading?: boolean
  permissionsError?: any
  onSuccess: () => void
}

interface RoleFilters {
  search: string
  status: string
}

interface RoleFormData {
  name: string
  display_name: string
  description: string
  permissions: string[]
  status: 'active' | 'inactive'
}

export default function RolesPage() {
  const [filters, setFilters] = useState<RoleFilters>({
    search: '',
    status: '',
  })
  const [page, setPage] = useState(1)
  const [showCreateModal, setShowCreateModal] = useState(false)
  const [showEditModal, setShowEditModal] = useState(false)
  const [selectedRole, setSelectedRole] = useState<Role | null>(null)
  const limit = 20

  const queryClient = useQueryClient()

  const { data, isLoading } = useQuery({
    queryKey: ['roles', filters, page],
    queryFn: () => rolesApi.getRoles({ ...filters, page, limit }),
    placeholderData: (previousData) => previousData,
  })

  // Query para obtener permisos
  const {
    data: permissionsData,
    isLoading: permissionsLoading,
    error: permissionsError,
  } = useQuery({
    queryKey: ['permissions'],
    queryFn: rolesApi.getPermissions,
    staleTime: 10 * 60 * 1000, // 10 minutos
  })

  // DEBUG: Logs detallados
  console.log('=== DEBUG PERMISOS ===')
  console.log('permissionsData:', permissionsData)
  console.log('permissionsData length:', permissionsData?.length)
  console.log('permissionsLoading:', permissionsLoading)
  console.log('permissionsError:', permissionsError)
  console.log('permissionsData type:', typeof permissionsData)
  console.log('permissionsData is array:', Array.isArray(permissionsData))
  console.log('Token en localStorage:', localStorage.getItem('auth_token'))
  console.log('User en localStorage:', localStorage.getItem('user'))

  const deleteRoleMutation = useMutation({
    mutationFn: (id: number) => rolesApi.deleteRole(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['roles'] })
      toast.success('Rol eliminado exitosamente')
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || 'Error al eliminar el rol')
    }
  })

  const getStatusBadge = (status: Role['status']) => {
    const statusConfig = {
      active: { label: 'Activo', color: 'bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-200' },
      inactive: { label: 'Inactivo', color: 'bg-danger-100 text-danger-800 dark:bg-danger-900 dark:text-danger-200' },
    }

    const config = statusConfig[status] || statusConfig.active
    return (
      <span className={cn('badge', config.color)}>
        {config.label}
      </span>
    )
  }

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('es-ES', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    })
  }

  const handleDeleteRole = (role: Role) => {
    if (role.users_count > 0) {
      toast.error('No se puede eliminar un rol que tiene usuarios asignados')
      return
    }

    if (window.confirm(`¿Estás seguro de que quieres eliminar el rol "${role.display_name}"?`)) {
      deleteRoleMutation.mutate(role.id)
    }
  }

  const handleEditRole = (role: Role) => {
    setSelectedRole(role)
    setShowEditModal(true)
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
            Gestión de Roles
          </h1>
          <p className="text-secondary-600 dark:text-secondary-400 mt-1">
            Administra roles y permisos del sistema
          </p>
        </div>
        
        <div className="flex space-x-3 mt-4 sm:mt-0">
          <button 
            onClick={() => setShowCreateModal(true)}
            className="btn-primary"
          >
            <PlusIcon className="w-4 h-4 mr-2" />
            Nuevo Rol
          </button>
        </div>
      </div>

      {/* Filtros */}
      <div className="card">
        <div className="card-body">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div className="relative">
              <MagnifyingGlassIcon className="w-5 h-5 absolute left-3 top-1/2 transform -translate-y-1/2 text-secondary-400" />
              <input
                type="text"
                placeholder="Buscar roles..."
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
              <option value="active">Activo</option>
              <option value="inactive">Inactivo</option>
            </select>

            <button
              onClick={() => setFilters({ search: '', status: '' })}
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
              Total Roles
            </div>
          </div>
        </div>
        <div className="card">
          <div className="card-body text-center">
            <div className="text-2xl font-bold text-success-600 dark:text-success-400">
              {data?.data?.filter((role: Role) => role.status === 'active').length || 0}
            </div>
            <div className="text-sm text-secondary-600 dark:text-secondary-400">
              Activos
            </div>
          </div>
        </div>
        <div className="card">
          <div className="card-body text-center">
            <div className="text-2xl font-bold text-blue-600 dark:text-blue-400">
              {data?.data?.reduce((sum: number, role: Role) => sum + (role.users_count || 0), 0) || 0}
            </div>
            <div className="text-sm text-secondary-600 dark:text-secondary-400">
              Usuarios Asignados
            </div>
          </div>
        </div>
        <div className="card">
          <div className="card-body text-center">
            <div className="text-2xl font-bold text-purple-600 dark:text-purple-400">
              {permissionsData?.length || 0}
            </div>
            <div className="text-sm text-secondary-600 dark:text-secondary-400">
              Permisos Disponibles
            </div>
          </div>
        </div>
      </div>

      {/* Tabla de roles */}
      <div className="card">
        <div className="card-body p-0">
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead className="bg-secondary-50 dark:bg-secondary-800">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase tracking-wider">
                    Rol
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase tracking-wider">
                    Descripción
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase tracking-wider">
                    Permisos
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase tracking-wider">
                    Usuarios
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase tracking-wider">
                    Estado
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase tracking-wider">
                    Fecha
                  </th>
                  <th className="px-6 py-3 text-right text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase tracking-wider">
                    Acciones
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-secondary-200 dark:divide-secondary-700">
                {data?.data?.map((role: Role) => (
                  <tr key={role.id} className="hover:bg-secondary-50 dark:hover:bg-secondary-800 transition-colors">
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="flex items-center">
                        <div className="w-10 h-10 bg-primary-100 dark:bg-primary-900 rounded-full flex items-center justify-center">
                          <ShieldCheckIcon className="w-5 h-5 text-primary-600 dark:text-primary-400" />
                        </div>
                        <div className="ml-4">
                          <div className="text-sm font-medium text-secondary-900 dark:text-white">
                            {role.display_name}
                          </div>
                          <div className="text-sm text-secondary-500 dark:text-secondary-400">
                            {role.name}
                          </div>
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <div className="text-sm text-secondary-900 dark:text-white max-w-xs truncate">
                        {role.description}
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="text-sm text-secondary-900 dark:text-white">
                        {role.permissions.length} permisos
                      </div>
                      <div className="text-xs text-secondary-500 dark:text-secondary-400">
                        {role.permissions.slice(0, 2).join(', ')}
                        {role.permissions.length > 2 && '...'}
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="flex items-center">
                        <UsersIcon className="w-4 h-4 text-secondary-400 mr-1" />
                        <span className="text-sm text-secondary-900 dark:text-white">
                          {role.users_count}
                        </span>
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      {getStatusBadge(role.status)}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-secondary-500 dark:text-secondary-400">
                      {formatDate(role.created_at)}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                      <div className="flex items-center justify-end space-x-2">
                        <button 
                          onClick={() => handleEditRole(role)}
                          className="text-primary-600 hover:text-primary-900 dark:text-primary-400 dark:hover:text-primary-300"
                        >
                          <EyeIcon className="w-4 h-4" />
                        </button>
                        <button 
                          onClick={() => handleEditRole(role)}
                          className="text-secondary-600 hover:text-secondary-900 dark:text-secondary-400 dark:hover:text-secondary-300"
                        >
                          <PencilIcon className="w-4 h-4" />
                        </button>
                        <button 
                          onClick={() => handleDeleteRole(role)}
                          disabled={role.users_count > 0}
                          className="text-danger-600 hover:text-danger-900 dark:text-danger-400 dark:hover:text-danger-300 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                          <TrashIcon className="w-4 h-4" />
                        </button>
                      </div>
                    </td>
                  </tr>
                )) || []}
              </tbody>
            </table>
          </div>

          {/* Paginación */}
          {data && data.pagination && data.pagination.pages > 1 && (
            <div className="px-6 py-4 border-t border-secondary-200 dark:border-secondary-700">
              <div className="flex items-center justify-between">
                <div className="text-sm text-secondary-700 dark:text-secondary-300">
                  Mostrando {((page - 1) * limit) + 1} a {Math.min(page * limit, data.pagination.total)} de {data.pagination.total} resultados
                </div>
                <div className="flex space-x-2">
                  <button
                    onClick={() => setPage(page - 1)}
                    disabled={page === 1}
                    className="btn-secondary disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    Anterior
                  </button>
                  <button
                    onClick={() => setPage(page + 1)}
                    disabled={page === data.pagination.pages}
                    className="btn-secondary disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    Siguiente
                  </button>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Estado vacío */}
      {data?.data && data.data.length === 0 && (
        <div className="text-center py-12">
          <div className="text-secondary-400 mb-4">
            <ShieldCheckIcon className="w-12 h-12 mx-auto" />
          </div>
          <h3 className="text-lg font-medium text-secondary-900 dark:text-white mb-2">
            No se encontraron roles
          </h3>
          <p className="text-secondary-500 dark:text-secondary-400 mb-6">
            Comienza creando tu primer rol o ajusta los filtros de búsqueda.
          </p>
          <button 
            onClick={() => setShowCreateModal(true)}
            className="btn-primary"
          >
            <PlusIcon className="w-4 h-4 mr-2" />
            Crear Primer Rol
          </button>
        </div>
      )}

      {/* Modales */}
      {showCreateModal && (
        <RoleModal
          isOpen={showCreateModal}
          onClose={() => setShowCreateModal(false)}
          role={null}
          permissions={permissionsData || []}
          permissionsLoading={permissionsLoading}
          permissionsError={permissionsError}
          onSuccess={() => {
            setShowCreateModal(false)
            queryClient.invalidateQueries({ queryKey: ['roles'] })
          }}
        />
      )}

      {showEditModal && selectedRole && (
        <RoleModal
          isOpen={showEditModal}
          onClose={() => {
            setShowEditModal(false)
            setSelectedRole(null)
          }}
          role={selectedRole}
          permissions={permissionsData || []}
          permissionsLoading={permissionsLoading}
          permissionsError={permissionsError}
          onSuccess={() => {
            setShowEditModal(false)
            setSelectedRole(null)
            queryClient.invalidateQueries({ queryKey: ['roles'] })
          }}
        />
      )}
    </div>
  )
}

// Componente Modal para Crear/Editar Rol
function RoleModal({ isOpen, onClose, role, permissions, permissionsLoading = false, permissionsError = null, onSuccess }: RoleModalProps) {
  const [formData, setFormData] = useState<RoleFormData>({
    name: role?.name || '',
    display_name: role?.display_name || '',
    description: role?.description || '',
    permissions: role?.permissions || [],
    status: role?.status || 'active'
  })

  // Debug logs para identificar el problema
  console.log('RoleModal - Props recibidas:', {
    permissions,
    permissionsLength: permissions?.length,
    permissionsLoading,
    permissionsError,
    isArray: Array.isArray(permissions)
  })

  const createRoleMutation = useMutation({
    mutationFn: (data: RoleFormData) => rolesApi.createRole(data),
    onSuccess: () => {
      toast.success('Rol creado exitosamente')
      onSuccess()
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || 'Error al crear el rol')
    }
  })

  const updateRoleMutation = useMutation({
    mutationFn: (data: RoleFormData) => {
      if (!role?.id) {
        throw new Error('Role ID is required for update')
      }
      return rolesApi.updateRole(role.id, data)
    },
    onSuccess: () => {
      toast.success('Rol actualizado exitosamente')
      onSuccess()
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || 'Error al actualizar el rol')
    }
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    
    if (role) {
      updateRoleMutation.mutate(formData)
    } else {
      createRoleMutation.mutate(formData)
    }
  }

  const handlePermissionToggle = (permission: string) => {
    setFormData(prev => ({
      ...prev,
      permissions: prev.permissions.includes(permission)
        ? prev.permissions.filter((p: string) => p !== permission)
        : [...prev.permissions, permission]
    }))
  }

  if (!isOpen) return null

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div className="bg-white dark:bg-secondary-800 rounded-lg p-6 w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto">
        <h3 className="text-lg font-semibold text-secondary-900 dark:text-white mb-6">
          {role ? 'Editar Rol' : 'Crear Nuevo Rol'}
        </h3>
        
        <form onSubmit={handleSubmit} className="space-y-6">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                Nombre del Rol
              </label>
              <input
                type="text"
                value={formData.name}
                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                className="input"
                placeholder="ej: sales_agent"
                required
              />
            </div>
            
            <div>
              <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                Nombre para Mostrar
              </label>
              <input
                type="text"
                value={formData.display_name}
                onChange={(e) => setFormData({ ...formData, display_name: e.target.value })}
                className="input"
                placeholder="ej: Agente de Ventas"
                required
              />
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
              placeholder="Describe las responsabilidades de este rol..."
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
              <option value="active">Activo</option>
              <option value="inactive">Inactivo</option>
            </select>
          </div>

          <div>
            <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-4">
              Permisos
            </label>
            {permissionsLoading ? (
              <div className="text-center py-4">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600 mx-auto"></div>
                <p className="text-sm text-secondary-500 mt-2">Cargando permisos...</p>
              </div>
            ) : permissionsError ? (
              <div className="text-center py-4">
                <p className="text-sm text-red-500">Error al cargar permisos</p>
                <p className="text-xs text-secondary-500 mt-1">
                  {permissionsError instanceof Error ? permissionsError.message : 'Error desconocido'}
                </p>
              </div>
            ) : (
              <div className="space-y-4">
                {permissions && Array.isArray(permissions) && permissions.length > 0 ? (
                  // Agrupar permisos por módulo
                  Object.entries(
                    permissions.reduce((acc: { [key: string]: Permission[] }, permission: Permission) => {
                      if (!acc[permission.module]) {
                        acc[permission.module] = [];
                      }
                      acc[permission.module].push(permission);
                      return acc;
                    }, {})
                  ).map(([module, modulePermissions]) => (
                    <div key={module} className="border border-secondary-200 dark:border-secondary-700 rounded-lg p-4">
                      <h4 className="font-medium text-secondary-900 dark:text-white mb-3 capitalize">
                        {module}
                      </h4>
                      <div className="grid grid-cols-2 md:grid-cols-3 gap-2">
                        {modulePermissions.map((permission: Permission) => (
                          <label key={permission.name} className="flex items-center space-x-2">
                            <input
                              type="checkbox"
                              checked={formData.permissions.includes(permission.name)}
                              onChange={() => handlePermissionToggle(permission.name)}
                              className="rounded border-secondary-300 text-primary-600 focus:ring-primary-500"
                            />
                            <span className="text-sm text-secondary-700 dark:text-secondary-300">
                              {permission.display_name}
                            </span>
                          </label>
                        ))}
                      </div>
                    </div>
                  ))
                ) : (
                  <div className="text-center py-8 border border-secondary-200 dark:border-secondary-700 rounded-lg">
                    <p className="text-secondary-500">No hay permisos disponibles</p>
                    <p className="text-xs text-secondary-400 mt-1">
                      Permisos recibidos: {permissions ? permissions.length : 0}
                    </p>
                  </div>
                )}
              </div>
            )}
          </div>

          <div className="flex justify-end space-x-3 pt-6 border-t border-secondary-200 dark:border-secondary-700">
            <button type="button" onClick={onClose} className="btn-secondary">
              Cancelar
            </button>
            <button 
              type="submit" 
              disabled={createRoleMutation.isPending || updateRoleMutation.isPending}
              className="btn-primary disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {role ? 'Actualizar' : 'Crear'} Rol
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
