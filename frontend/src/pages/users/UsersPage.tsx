import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import {
  PlusIcon,
  UserPlusIcon,
  MagnifyingGlassIcon,
  FunnelIcon,
} from '@heroicons/react/24/outline'

import { usersApi } from '../../services/api'
import type { User } from '../../types'
import LoadingSpinner from '../../components/ui/LoadingSpinner'
import UserWizard from '../../components/wizards/UserWizard'
import { useAuth } from '../../contexts/AuthContext'
import { rolesApi, desksApi } from '../../services/api'
import AssignDeskModal from '../../components/modals/AssignDeskModal'
import AssignRoleModal from '../../components/modals/AssignRoleModal'
import { cn } from '../../utils/cn'

export default function UsersPage() {
  const { hasPermission } = useAuth()
  const [showCreateModal, setShowCreateModal] = useState(false)
  const [showAssignDeskModal, setShowAssignDeskModal] = useState(false)
  const [showAssignRoleModal, setShowAssignRoleModal] = useState(false)
  const [selectedUser, setSelectedUser] = useState<User | null>(null)

  // Filtros y paginación (alineados a otros módulos)
  const [filters, setFilters] = useState({
    search: '',
    status: '',
    role_id: '',
    desk_id: '',
  })
  const [page, setPage] = useState(1)
  const limit = 20

  const queryClient = useQueryClient()

  const { data, isLoading } = useQuery({
    queryKey: ['users', filters, page],
    queryFn: () => usersApi.getUsers({ ...filters, page, limit }),
    placeholderData: (previous) => previous,
  })

  // Query para obtener roles disponibles
  const { data: rolesData } = useQuery({
    queryKey: ['roles-for-assignment'],
    queryFn: () => rolesApi.getRoles({ status: 'active' }),
    staleTime: 5 * 60 * 1000, // 5 minutos
  })

  // Query para obtener desks disponibles
  const { data: desksData } = useQuery({
    queryKey: ['desks-for-assignment'],
    queryFn: () => desksApi.getDesks({ status: 'active' }),
    staleTime: 5 * 60 * 1000, // 5 minutos
  })

  const getStatusBadge = (status: string) => {
    const statusConfig: Record<string, { label: string; color: string }> = {
      active: { label: 'Activo', color: 'bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-200' },
      inactive: { label: 'Inactivo', color: 'bg-danger-100 text-danger-800 dark:bg-danger-900 dark:text-danger-200' },
    }

    const config = statusConfig[status] || statusConfig.active
    return <span className={cn('badge', config.color)}>{config.label}</span>
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
            Gestión de Usuarios
          </h1>
          <p className="text-secondary-600 dark:text-secondary-400 mt-1">
            Administra usuarios, roles y permisos del sistema
          </p>
        </div>
        
        <div className="flex space-x-3 mt-4 sm:mt-0">
          {hasPermission('users.create') && (
            <button className="btn-secondary">
              <UserPlusIcon className="w-4 h-4 mr-2" />
              Importar Usuarios
            </button>
          )}
          {hasPermission('users.create') && (
            <button 
              onClick={() => setShowCreateModal(true)}
              className="btn-primary"
            >
              <PlusIcon className="w-4 h-4 mr-2" />
              Nuevo Usuario
            </button>
          )}
        </div>
      </div>

      {/* Filtros */}
      <div className="card">
        <div className="card-body p-0">
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div className="relative">
              <MagnifyingGlassIcon className="w-5 h-5 absolute left-3 top-1/2 transform -translate-y-1/2 text-secondary-400" />
              <input
                type="text"
                placeholder="Buscar usuarios..."
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

            <select
              className="input"
              value={filters.role_id}
              onChange={(e) => setFilters({ ...filters, role_id: e.target.value })}
            >
              <option value="">Todos los roles</option>
              {(rolesData?.data || []).map((r: any) => (
                <option key={r.id} value={r.id}>{r.display_name || r.name}</option>
              ))}
            </select>

            <div className="flex gap-2">
              <select
                className="input flex-1"
                value={filters.desk_id}
                onChange={(e) => setFilters({ ...filters, desk_id: e.target.value })}
              >
                <option value="">Todas las mesas</option>
                {(desksData?.data || []).map((d: any) => (
                  <option key={d.id} value={d.id}>{d.name}</option>
                ))}
              </select>
              <button
                onClick={() => setFilters({ search: '', status: '', role_id: '', desk_id: '' })}
                className="btn-ghost whitespace-nowrap"
              >
                <FunnelIcon className="w-4 h-4 mr-2" />
                Limpiar
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Estadísticas rápidas */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div className="card">
          <div className="card-body text-center">
            <div className="text-2xl font-bold text-primary-600 dark:text-primary-400">
              {data?.pagination?.total || (Array.isArray((data as any)?.data) ? (data as any).data.length : 0)}
            </div>
            <div className="text-sm text-secondary-600 dark:text-secondary-400">
              Total Usuarios
            </div>
          </div>
        </div>
        <div className="card">
          <div className="card-body text-center">
            <div className="text-2xl font-bold text-success-600 dark:text-success-400">
              {Array.isArray((data as any)?.data) ? (data as any).data.filter((u: any) => u.status === 'active').length : 0}
            </div>
            <div className="text-sm text-secondary-600 dark:text-secondary-400">
              Activos
            </div>
          </div>
        </div>
        <div className="card">
          <div className="card-body text-center">
            <div className="text-2xl font-bold text-blue-600 dark:text-blue-400">
              {Array.isArray((data as any)?.data) ? (data as any).data.filter((u: any) => u.desk_id).length : 0}
            </div>
            <div className="text-sm text-secondary-600 dark:text-secondary-400">
              Con Mesa
            </div>
          </div>
        </div>
        <div className="card">
          <div className="card-body text-center">
            <div className="text-2xl font-bold text-secondary-700 dark:text-secondary-300">
              {Array.isArray((data as any)?.data) ? (data as any).data.filter((u: any) => u.roles && String(u.roles).trim().length > 0).length : 0}
            </div>
            <div className="text-sm text-secondary-600 dark:text-secondary-400">
              Con Rol
            </div>
          </div>
        </div>
      </div>

      {/* Modales */}
      <UserWizard
        isOpen={showCreateModal}
        onClose={() => setShowCreateModal(false)}
        onSuccess={() => {
          queryClient.invalidateQueries({ queryKey: ['users'] })
        }}
      />

      {showAssignDeskModal && selectedUser && (
        <AssignDeskModal
          isOpen={showAssignDeskModal}
          onClose={() => {
            setShowAssignDeskModal(false)
            setSelectedUser(null)
          }}
          user={selectedUser}
          desks={desksData?.data || []}
          onSuccess={() => {
            queryClient.invalidateQueries({ queryKey: ['users'] })
            setShowAssignDeskModal(false)
            setSelectedUser(null)
          }}
        />
      )}

      {showAssignRoleModal && selectedUser && (
        <AssignRoleModal
          isOpen={showAssignRoleModal}
          onClose={() => {
            setShowAssignRoleModal(false)
            setSelectedUser(null)
          }}
          user={selectedUser}
          roles={rolesData?.data || []}
          onSuccess={() => {
            queryClient.invalidateQueries({ queryKey: ['users'] })
            setShowAssignRoleModal(false)
            setSelectedUser(null)
          }}
        />
      )}

      {/* Lista de usuarios: mostrar solo la tabla, sin encabezado de tarjeta */}
      <div className="card">
        <div className="card-body p-0">
          {data && (data as any).success === false && (
            <div className="alert-error">
              <p>{(data as any).message || 'No se pudieron cargar los usuarios'}</p>
            </div>
          )}

          {data && Array.isArray((data as any).data) && (data as any).data.length > 0 ? (
            <>
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-secondary-50 dark:bg-secondary-800">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase tracking-wider">ID</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase tracking-wider">Nombre</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase tracking-wider">Email</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase tracking-wider">Rol(es)</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase tracking-wider">Estado</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase tracking-wider">Mesa</th>
                    <th className="px-6 py-3 text-right text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase tracking-wider">Acciones</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-secondary-200 dark:divide-secondary-700">
                  {((data as any).data as any[]).map((u: any) => (
                    <tr key={u.id} className="hover:bg-secondary-50 dark:hover:bg-secondary-800 transition-colors">
                      <td className="px-6 py-4 whitespace-nowrap">{u.id}</td>
                      <td className="px-6 py-4 whitespace-nowrap">{[u.first_name, u.last_name].filter(Boolean).join(' ') || u.username}</td>
                      <td className="px-6 py-4 whitespace-nowrap">{u.email}</td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        {Array.isArray(u.roles) ? (
                          <div className="flex flex-wrap gap-1">
                            {u.roles.map((r: any, idx: number) => (
                              <span key={idx} className="badge bg-secondary-100 text-secondary-800 dark:bg-secondary-900 dark:text-secondary-200">
                                {r.display_name || r.name || String(r)}
                              </span>
                            ))}
                          </div>
                        ) : (
                          <span className="text-secondary-700 dark:text-secondary-300">
                            {u.roles || '-'}
                          </span>
                        )}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        {getStatusBadge(u.status || 'active')}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">{u.desk_name || '-'}</td>
                      <td className="px-6 py-4 whitespace-nowrap text-right space-x-2">
                        {hasPermission('users.assign_desk') && (
                          <button
                            className="btn-xs btn-secondary"
                            onClick={() => {
                              setSelectedUser(u)
                              setShowAssignDeskModal(true)
                            }}
                          >
                            Asignar Mesa
                          </button>
                        )}
                        {hasPermission('users.assign_role') && (
                          <button
                            className="btn-xs btn-secondary"
                            onClick={() => {
                              setSelectedUser(u)
                              setShowAssignRoleModal(true)
                            }}
                          >
                            Asignar Rol
                          </button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            {/* Paginación */}
            {data && (data as any)?.pagination && Number((data as any)?.pagination?.pages ?? (data as any)?.pagination?.total_pages ?? 0) > 1 && (
              <div className="px-6 py-4 border-t border-secondary-200 dark:border-secondary-700">
                <div className="flex items-center justify-between">
                  <div className="text-sm text-secondary-700 dark:text-secondary-300">
                    Mostrando {((page - 1) * limit) + 1} a {Math.min(page * limit, Number((data as any)?.pagination?.total ?? (((data as any)?.data || []).length)))} de {Number((data as any)?.pagination?.total ?? (((data as any)?.data || []).length))} resultados
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
                      disabled={page >= Number((data as any)?.pagination?.pages ?? (data as any)?.pagination?.total_pages ?? 1)}
                      className="btn-secondary disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      Siguiente
                    </button>
                  </div>
                </div>
              </div>
            )}
            </>
          ) : (
            <div className="p-6 text-center text-secondary-600 dark:text-secondary-400">
              No hay usuarios disponibles.
            </div>
          )}
        </div>
      </div>
    </div>
  )
}
