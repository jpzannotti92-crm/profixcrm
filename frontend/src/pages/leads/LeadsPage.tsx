import { useState, useEffect } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { 
  TrashIcon, 
  PencilIcon, 
  EyeIcon,
  PlusIcon,
  ArrowUpTrayIcon,
  UserGroupIcon,
  ArrowRightIcon
} from '@heroicons/react/24/outline'
import { leadsApi, usersApi, desksApi } from '../../services/api'
import { usePermissions } from '../../components/PermissionGuard'
import authService from '../../services/authService.js'
import SearchCollapsible from '../../components/SearchCollapsible'
import FiltersCollapsible from '../../components/FiltersCollapsible'
import { Lead, LeadFilters } from '../../types/lead'
import { User, PaginatedResponse, Desk } from '../../types'
import LoadingSpinner from '../../components/ui/LoadingSpinner'
import LeadWizard from '../../components/wizards/LeadWizard'
import ImportWizard from '../../components/wizards/ImportWizard'
import PermissionGuard, { PermissionButton } from '../../components/PermissionGuard'
import { cn } from '../../utils/cn'
import BulkAssignModal from '../../components/BulkAssignModal'
import AssignLeadModal from '../../components/AssignLeadModal'
import { useDebounce } from '../../hooks/useDebounce'

export default function LeadsPage() {
  const [filters, setFilters] = useState<LeadFilters>({
    status: '',
    desk: '',
    assigned_to: '',
    source: '',
    country: '',
    date_from: '',
    date_to: ''
  })
  
  // Estados para búsqueda avanzada
  const [searchValues, setSearchValues] = useState<{
    general: string;
    email: string;
    id: string;
    name: string;
    phone: string;
  }>({
    general: '',
    email: '',
    id: '',
    name: '',
    phone: ''
  })
  
  const [userFilters, setUserFilters] = useState({})
  const [page, setPage] = useState(1)
  const [limit, setLimit] = useState(20)
  const [selectedLeads, setSelectedLeads] = useState<number[]>([])
  const [showBulkAssignModal, setShowBulkAssignModal] = useState(false)
  const [showCreateModal, setShowCreateModal] = useState(false)
  const [showImportModal, setShowImportModal] = useState(false)
  const [singleAssignLeadId, setSingleAssignLeadId] = useState<number | null>(null)

  // Debounce para búsquedas
  const debouncedGeneralSearch = useDebounce(searchValues.general, 300)
  const debouncedEmailSearch = useDebounce(searchValues.email, 300)
  const debouncedIdSearch = useDebounce(searchValues.id, 300)
  const debouncedNameSearch = useDebounce(searchValues.name, 300)
  const debouncedPhoneSearch = useDebounce(searchValues.phone, 300)

  const queryClient = useQueryClient()
  const { 
    hasPermission, 
    loading 
  } = usePermissions()

  // Cargar filtros basados en permisos del usuario
  useEffect(() => {
    const loadUserFilters = async () => {
      try {
        const filtersData = await authService.getLeadsFilters()
        setUserFilters(filtersData.filters || {})
      } catch (error) {
        console.error('Error cargando filtros de usuario:', error)
      }
    }

    if (!loading) {
      loadUserFilters()
    }
  }, [loading])

  // Construir parámetros de búsqueda combinados
  const buildSearchParams = () => {
    const searchParams: any = { ...filters }
    
    // Combinar todas las búsquedas en un solo parámetro o usar campos específicos
    if (debouncedGeneralSearch) {
      searchParams.search = debouncedGeneralSearch
    }
    if (debouncedEmailSearch) {
      searchParams.email = debouncedEmailSearch
    }
    if (debouncedIdSearch) {
      searchParams.id = debouncedIdSearch
    }
    if (debouncedNameSearch) {
      searchParams.name = debouncedNameSearch
    }
    if (debouncedPhoneSearch) {
      searchParams.phone = debouncedPhoneSearch
    }
    
    return searchParams
  }

  const { data, isLoading } = useQuery<PaginatedResponse<Lead>>({
    queryKey: ['leads', buildSearchParams(), page, limit, userFilters],
    queryFn: () => leadsApi.getLeads({ 
      ...buildSearchParams(), 
      ...userFilters, // Aplicar filtros basados en permisos
      page, 
      limit 
    }),
    placeholderData: (previousData) => previousData,
    staleTime: 30000, // Cache por 30 segundos
    enabled: !loading
  })

  // Función para manejar cambios en filtros
  const handleFilterChange = (key: string, value: string) => {
    setFilters(prev => ({
      ...prev,
      [key]: value
    }))
    setPage(1)
  }

  // Función para manejar cambios en búsqueda
  const handleSearchChange = (searchType: string, value: string) => {
    setSearchValues((prev) => ({
      ...prev,
      [searchType]: value
    }))
    setPage(1) // Resetear página al cambiar búsqueda
  }

  // Función para limpiar búsquedas
  const handleClearSearch = () => {
    setSearchValues({
      general: '',
      email: '',
      id: '',
      name: '',
      phone: ''
    })
    setPage(1)
  }

  // Contar filtros activos (incluyendo búsquedas)
  const getActiveFiltersCount = () => {
    const filterCount = Object.values(filters).filter(value => 
      value !== '' && value !== null && value !== undefined
    ).length
    
    const searchCount = Object.values(searchValues).filter(value => 
      value !== '' && value !== null && value !== undefined
    ).length
    
    return filterCount + searchCount
  }

  // Función para cargar filtros guardados
  const handleLoadSavedFilter = (savedFilters: Record<string, any>) => {
    setFilters((prev: LeadFilters) => ({
      ...prev,
      ...savedFilters
    }))
    setPage(1)
  }

  // Obtener usuarios para asignación
  const { data: usersData } = useQuery<PaginatedResponse<User>>({
    queryKey: ['users-for-assignment'],
    queryFn: () => usersApi.getUsers({ status: 'active', role: 'Sales Agent' }),
    staleTime: 5 * 60 * 1000, // 5 minutos
  })

  // Obtener desks para filtros
  const { data: desksData } = useQuery<PaginatedResponse<Desk>>({
    queryKey: ['desks-for-filters'],
    queryFn: () => desksApi.getDesks({ status: 'active' }),
    staleTime: 5 * 60 * 1000, // 5 minutos
  })

  // Legacy function - now works with dynamic states
  const getStatusBadge = (status: string) => {
    // Default color mapping for common states
    const getStatusColor = (stateName: string) => {
      const lowerStatus = stateName.toLowerCase()
      if (lowerStatus.includes('nuevo') || lowerStatus.includes('new')) {
        return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
      }
      if (lowerStatus.includes('contactado') || lowerStatus.includes('contacted')) {
        return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
      }
      if (lowerStatus.includes('calificado') || lowerStatus.includes('qualified')) {
        return 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200'
      }
      if (lowerStatus.includes('convertido') || lowerStatus.includes('converted')) {
        return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
      }
      if (lowerStatus.includes('perdido') || lowerStatus.includes('lost')) {
        return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
      }
      // Default color for unknown states
      return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
    }

    return (
      <span className={cn('badge', getStatusColor(status))}>
        {status}
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

  const handleSelectLead = (leadId: number | null) => {
    if (leadId === null) return
    
    setSelectedLeads(prev => 
      prev.includes(leadId) 
        ? prev.filter(id => id !== leadId)
        : [...prev, leadId]
    )
  }

  const handleSelectAll = () => {
    if (selectedLeads.length === (data?.data?.length || 0)) {
      setSelectedLeads([])
    } else {
      setSelectedLeads(data?.data?.map((lead: Lead) => lead.id).filter((id): id is number => id !== null) || [])
    }
  }

  const handleBulkAssign = () => {
    if (selectedLeads.length === 0) return
    setShowBulkAssignModal(true)
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <LoadingSpinner size="lg" />
        <p className="ml-3 text-secondary-600 dark:text-secondary-400">
          Verificando permisos...
        </p>
      </div>
    )
  }

  if (!hasPermission('leads.view')) {
    return (
      <div className="text-center py-12">
        <h2 className="text-2xl font-bold text-secondary-900 dark:text-white mb-4">
          Acceso Denegado
        </h2>
        <p className="text-secondary-600 dark:text-secondary-400">
          No tienes permisos para ver los leads.
        </p>
      </div>
    )
  }

  if (isLoading && !data) {
    return (
      <div className="flex items-center justify-center h-64">
        <LoadingSpinner size="lg" />
      </div>
    )
  }

  return (
    <PermissionGuard permission="leads.view">
      <div className="space-y-6 animate-fade-in">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h1 className="text-2xl font-bold text-secondary-900 dark:text-white">
              Gestión de Leads
            </h1>
            <p className="text-secondary-600 dark:text-secondary-400 mt-1">
              Administra y da seguimiento a todos tus leads
            </p>
          </div>
          
          <div className="flex space-x-3 mt-4 sm:mt-0">
            <PermissionButton 
              permission="leads.create"
              onClick={() => setShowImportModal(true)}
              className="btn-secondary"
            >
              <ArrowUpTrayIcon className="w-4 h-4 mr-2" />
              Importar
            </PermissionButton>
            
            {selectedLeads.length > 0 && hasPermission('leads.assign') && (
              <PermissionButton 
                permission="leads.assign"
                onClick={handleBulkAssign}
                className="btn-primary"
              >
                <UserGroupIcon className="w-4 h-4 mr-2" />
                Asignar {selectedLeads.length} Leads
              </PermissionButton>
            )}
            
            <PermissionButton 
              permission="leads.create"
              onClick={() => setShowCreateModal(true)}
              className="btn-primary"
            >
              <PlusIcon className="w-4 h-4 mr-2" />
              Nuevo Lead
            </PermissionButton>
          </div>
        </div>

      {/* Búsqueda Avanzada */}
      <SearchCollapsible
        searchValues={searchValues}
        onSearchChange={handleSearchChange}
        onClearSearch={handleClearSearch}
      />

      {/* Filtros y Vistas Guardadas */}
      <FiltersCollapsible
        currentFilters={filters}
        onFilterChange={handleFilterChange}
        onLoadFilter={handleLoadSavedFilter}
        desksData={desksData}
        activeFiltersCount={getActiveFiltersCount()}
      />

      {/* Estadísticas rápidas */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div className="card">
          <div className="card-body text-center">
            <div className="text-2xl font-bold text-primary-600 dark:text-primary-400">
              {data?.pagination?.total || 0}
            </div>
            <div className="text-sm text-secondary-600 dark:text-secondary-400">
              Total Leads
            </div>
          </div>
        </div>
        <div className="card">
          <div className="card-body text-center">
            <div className="text-2xl font-bold text-success-600 dark:text-success-400">
              {data?.stats?.converted_leads || 0}
            </div>
            <div className="text-sm text-secondary-600 dark:text-secondary-400">
              Convertidos
            </div>
          </div>
        </div>
        <div className="card">
          <div className="card-body text-center">
            <div className="text-2xl font-bold text-warning-600 dark:text-warning-400">
              {data?.stats?.qualified_leads || 0}
            </div>
            <div className="text-sm text-secondary-600 dark:text-secondary-400">
              Calificados
            </div>
          </div>
        </div>
        <div className="card">
          <div className="card-body text-center">
            <div className="text-2xl font-bold text-secondary-600 dark:text-secondary-400">
              {data?.stats?.new_leads || 0}
            </div>
            <div className="text-sm text-secondary-600 dark:text-secondary-400">
              Nuevos
            </div>
          </div>
        </div>
      </div>

      {/* Tabla de leads */}
      <div className="card">
        <div className="card-body p-0">
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead className="bg-secondary-50 dark:bg-secondary-800">
                <tr>
                  <th className="px-6 py-3 text-left">
                    <input
                      type="checkbox"
                      checked={selectedLeads.length === (data?.data?.length || 0) && (data?.data?.length || 0) > 0}
                      onChange={handleSelectAll}
                      className="rounded border-secondary-300 text-primary-600 focus:ring-primary-500"
                    />
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase tracking-wider">
                    Lead
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase tracking-wider">
                    Contacto
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase tracking-wider">
                    Estado
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase tracking-wider">
                    Mesa
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase tracking-wider">
                    Asignado a
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
                {Array.isArray(data?.data) ? data.data.map((lead: Lead) => (
                  <tr key={lead.id} className="hover:bg-secondary-50 dark:hover:bg-secondary-800 transition-colors">
                    <td className="px-6 py-4 whitespace-nowrap">
                      <input
                        type="checkbox"
                        checked={lead.id !== null && selectedLeads.includes(lead.id)}
                        onChange={() => handleSelectLead(lead.id)}
                        className="rounded border-secondary-300 text-primary-600 focus:ring-primary-500"
                      />
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="flex items-center">
                        <div className="w-10 h-10 bg-primary-100 dark:bg-primary-900 rounded-full flex items-center justify-center">
                          <span className="text-sm font-medium text-primary-600 dark:text-primary-400">
                            {lead.first_name?.charAt(0)}{lead.last_name?.charAt(0)}
                          </span>
                        </div>
                        <div className="ml-4">
                          <Link 
                            to={`/leads/${lead.id}`}
                            className="text-sm font-medium text-secondary-900 dark:text-white hover:text-primary-600 dark:hover:text-primary-400 transition-colors cursor-pointer"
                          >
                            {lead.first_name} {lead.last_name}
                          </Link>
                          <Link 
                            to={`/leads/${lead.id}`}
                            className="block text-sm text-secondary-500 dark:text-secondary-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors cursor-pointer"
                          >
                            ID: {lead.id}
                          </Link>
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="text-sm text-secondary-900 dark:text-white">
                        {lead.email}
                      </div>
                      <div className="text-sm text-secondary-500 dark:text-secondary-400">
                        {lead.phone || 'Sin teléfono'}
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      {getStatusBadge(lead.status)}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="text-sm text-secondary-900 dark:text-white">
                        {lead.desk_name || 'Sin mesa'}
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="text-sm text-secondary-900 dark:text-white">
                        {lead.assigned_to_name || 'Sin asignar'}
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-secondary-500 dark:text-secondary-400">
                      {formatDate(lead.created_at)}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                      <div className="flex items-center justify-end space-x-2">
                        {(hasPermission('leads.assign') || hasPermission('leads.update')) && (
                          <button 
                            onClick={() => setSingleAssignLeadId(lead.id)}
                            className="text-warning-600 hover:text-warning-900 dark:text-warning-400 dark:hover:text-warning-300"
                            title="Asignar lead"
                          >
                            <ArrowRightIcon className="w-4 h-4" />
                          </button>
                        )}
                        <Link
                          to={`/leads/${lead.id}`}
                          className="text-primary-600 hover:text-primary-900 dark:text-primary-400 dark:hover:text-primary-300"
                          title="Ver detalles"
                        >
                          <EyeIcon className="w-4 h-4" />
                        </Link>
                        {hasPermission('leads.edit') && (
                          <button 
                            className="text-secondary-600 hover:text-secondary-900 dark:text-secondary-400 dark:hover:text-secondary-300"
                            title="Editar lead"
                          >
                            <PencilIcon className="w-4 h-4" />
                          </button>
                        )}
                        {hasPermission('leads.delete') && (
                          <button 
                            className="text-danger-600 hover:text-danger-900 dark:text-danger-400 dark:hover:text-danger-300"
                            title="Eliminar lead"
                          >
                            <TrashIcon className="w-4 h-4" />
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                )) : (
                  <tr>
                    <td colSpan={8} className="px-6 py-4 text-center text-secondary-500 dark:text-secondary-400">
                      No hay datos disponibles
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>

          {/* Paginación */}
          {data && data.pagination && data.pagination.total_pages > 1 && (
            <div className="px-6 py-4 border-t border-secondary-200 dark:border-secondary-700">
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-4">
                  <div className="text-sm text-secondary-700 dark:text-secondary-300">
                    Mostrando {((page - 1) * limit) + 1} a {Math.min(page * limit, data.pagination.total)} de {data.pagination.total} resultados
                  </div>
                  <div className="flex items-center space-x-2">
                    <label className="text-sm text-secondary-700 dark:text-secondary-300">
                      Mostrar:
                    </label>
                    <select
                      value={limit}
                      onChange={(e) => {
                        setLimit(Number(e.target.value))
                        setPage(1) // Reset to first page when changing limit
                      }}
                      className="px-2 py-1 text-sm border border-secondary-300 dark:border-secondary-600 rounded bg-white dark:bg-secondary-800 text-secondary-900 dark:text-white"
                    >
                      <option value={20}>20</option>
                      <option value={50}>50</option>
                      <option value={100}>100</option>
                    </select>
                    <span className="text-sm text-secondary-700 dark:text-secondary-300">
                      por página
                    </span>
                  </div>
                </div>
                <div className="flex space-x-2">
                  <button
                    onClick={() => setPage(page - 1)}
                    disabled={page === 1}
                    className="btn-secondary disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    Anterior
                  </button>
                  <span className="px-3 py-2 text-sm text-secondary-700 dark:text-secondary-300">
                    Página {page} de {data.pagination.total_pages}
                  </span>
                  <button
                    onClick={() => setPage(page + 1)}
                    disabled={page >= data.pagination.total_pages}
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
            <svg className="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
          </div>
          <h3 className="text-lg font-medium text-secondary-900 dark:text-white mb-2">
            No se encontraron leads
          </h3>
          <p className="text-secondary-500 dark:text-secondary-400 mb-6">
            Comienza agregando tu primer lead o ajusta los filtros de búsqueda.
          </p>
          <button className="btn-primary">
            <PlusIcon className="w-4 h-4 mr-2" />
            Crear Primer Lead
          </button>
        </div>
      )}

      {/* Modal de Asignación Masiva */}
      {showBulkAssignModal && (
        <BulkAssignModal
          selectedLeads={selectedLeads}
          users={usersData?.data || []}
          onClose={() => setShowBulkAssignModal(false)}
          onAssign={async (assignments: any) => {
            console.log('Asignaciones masivas:', assignments)
            try {
              // Normalizar payload para API: convertir grupos por usuario en items por lead
              const perLeadAssignments = assignments.flatMap((a: any) =>
                (a.leadIds || []).map((lid: number) => ({
                  lead_id: lid,
                  assigned_to: a.userId,
                  status: a.status
                }))
              )

              console.log('Payload normalizado para API:', { leadIds: selectedLeads, assignments: perLeadAssignments })

              // Llamar a la API de asignación masiva con el payload normalizado
              const result = await leadsApi.bulkAssign(selectedLeads, perLeadAssignments)
              
              // Actualizar la lista de leads
              queryClient.invalidateQueries({ queryKey: ['leads'] })
              
              // Limpiar selección
              setSelectedLeads([])
              setShowBulkAssignModal(false)
              
              // Mostrar mensaje de confirmación
              alert(`¡Asignación completada! ${result?.updated_count || 0} leads asignados exitosamente.`)
              
              console.log('Asignación masiva completada exitosamente:', result)
            } catch (error: any) {
              console.error('Error en asignación masiva:', error)
              alert('Error al asignar leads: ' + (error.response?.data?.message || error.message || 'Error desconocido'))
            }
            
            setShowBulkAssignModal(false)
            setSelectedLeads([])
          }}
        />
      )}

      {/* Modal de Asignación Única */}
      {singleAssignLeadId !== null && (
        <AssignLeadModal
          leadId={singleAssignLeadId}
          users={usersData?.data || []}
          onClose={() => setSingleAssignLeadId(null)}
          onAssign={async ({ userId, status }) => {
            try {
              const perLeadAssignments = [{
                lead_id: singleAssignLeadId as number,
                assigned_to: userId,
                status
              }]

              const result = await leadsApi.bulkAssign([singleAssignLeadId as number], perLeadAssignments)

              queryClient.invalidateQueries({ queryKey: ['leads'] })
              setSingleAssignLeadId(null)

              alert(`Lead asignado correctamente (${result?.updated_count || 0} actualizado).`)
            } catch (error: any) {
              console.error('Error asignando lead:', error)
              alert('Error al asignar lead: ' + (error.response?.data?.message || error.message || 'Error desconocido'))
            }
          }}
        />
      )}

      {/* Lead Wizard Modal */}
      <LeadWizard
        isOpen={showCreateModal}
        onClose={() => setShowCreateModal(false)}
        onSuccess={() => {
          queryClient.invalidateQueries({ queryKey: ['leads'] })
        }}
      />

      {/* Import Wizard Modal */}
      <ImportWizard
        isOpen={showImportModal}
        onClose={() => setShowImportModal(false)}
        onSuccess={() => {
          queryClient.invalidateQueries({ queryKey: ['leads'] })
        }}
      />
      </div>
    </PermissionGuard>
  )
}