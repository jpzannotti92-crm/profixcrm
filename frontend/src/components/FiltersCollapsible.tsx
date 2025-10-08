import { useState, useEffect } from 'react'
import { 
  ChevronDownIcon, 
  ChevronUpIcon, 
  FunnelIcon, 
  PlusIcon,
  BookmarkIcon,
  StarIcon,
  TrashIcon,
  XMarkIcon,
  SparklesIcon
} from '@heroicons/react/24/outline'
import { StarIcon as StarIconSolid } from '@heroicons/react/24/solid'
import FilterWizard from './FilterWizard'

interface SavedFilter {
  id: string
  name: string
  filters: Record<string, any>
  isDefault?: boolean
  createdAt: string
}

interface FiltersCollapsibleProps {
  currentFilters: Record<string, any>
  onFilterChange: (key: string, value: string) => void
  onLoadFilter: (filters: Record<string, any>) => void
  desksData?: any
  activeFiltersCount: number
}

export default function FiltersCollapsible({
  currentFilters,
  onFilterChange,
  onLoadFilter,
  desksData,
  activeFiltersCount
}: FiltersCollapsibleProps) {
  const [isExpanded, setIsExpanded] = useState(false)
  const [savedFilters, setSavedFilters] = useState<SavedFilter[]>([])
  const [showCreateModal, setShowCreateModal] = useState(false)
  const [newFilterName, setNewFilterName] = useState('')
  const [showFilterWizard, setShowFilterWizard] = useState(false)

  // Cargar filtros guardados del localStorage
  useEffect(() => {
    const saved = localStorage.getItem('leadFilters')
    if (saved) {
      try {
        setSavedFilters(JSON.parse(saved))
      } catch (error) {
        console.error('Error loading saved filters:', error)
      }
    }
  }, [])

  // Guardar filtros en localStorage
  const saveFiltersToStorage = (filters: SavedFilter[]) => {
    localStorage.setItem('leadFilters', JSON.stringify(filters))
    setSavedFilters(filters)
  }

  // Crear nuevo filtro
  const handleCreateFilter = () => {
    if (!newFilterName.trim()) return

    const newFilter: SavedFilter = {
      id: Date.now().toString(),
      name: newFilterName.trim(),
      filters: { ...currentFilters },
      createdAt: new Date().toISOString()
    }

    const updatedFilters = [...savedFilters, newFilter]
    saveFiltersToStorage(updatedFilters)
    setNewFilterName('')
    setShowCreateModal(false)
  }

  // Eliminar filtro
  const handleDeleteFilter = (filterId: string) => {
    const updatedFilters = savedFilters.filter(f => f.id !== filterId)
    saveFiltersToStorage(updatedFilters)
  }

  // Marcar como predeterminado
  const handleSetDefault = (filterId: string) => {
    const updatedFilters = savedFilters.map(f => ({
      ...f,
      isDefault: f.id === filterId
    }))
    saveFiltersToStorage(updatedFilters)
  }

  // Aplicar filtro guardado
  const handleApplyFilter = (filter: SavedFilter) => {
    onLoadFilter(filter.filters)
  }

  // Manejar filtro del wizard
  const handleWizardFilterSave = (filterConfig: any) => {
    const newFilter: SavedFilter = {
      id: Date.now().toString(),
      name: filterConfig.name,
      filters: {
        // Convertir las condiciones del wizard a formato de filtros
        ...filterConfig.conditions.reduce((acc: any, condition: any) => {
          acc[condition.field] = condition.value
          return acc
        }, {}),
        // Guardar configuración adicional del wizard
        _wizardConfig: filterConfig
      },
      createdAt: new Date().toISOString()
    }

    const updatedFilters = [...savedFilters, newFilter]
    saveFiltersToStorage(updatedFilters)
    
    // Aplicar el filtro inmediatamente
    onLoadFilter(newFilter.filters)
  }

  // Contar filtros activos en los filtros básicos
  const getBasicFiltersCount = () => {
    return Object.entries(currentFilters).filter(([key, value]) => 
      key !== 'search' && value !== '' && value !== null && value !== undefined
    ).length
  }

  return (
    <div className="card">
      <div className="card-body">
        {/* Header del colapsable */}
        <div 
          className="flex items-center justify-between cursor-pointer"
          onClick={() => setIsExpanded(!isExpanded)}
        >
          <div className="flex items-center space-x-3">
            <FunnelIcon className="w-5 h-5 text-primary-600 dark:text-primary-400" />
            <h3 className="text-lg font-semibold text-secondary-900 dark:text-secondary-100">
              Filtros y Vistas
            </h3>
            {activeFiltersCount > 0 && (
              <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200">
                {activeFiltersCount} activos
              </span>
            )}
          </div>
          <div className="flex items-center space-x-2">
            <button
              onClick={(e) => {
                e.stopPropagation()
                setShowFilterWizard(true)
              }}
              className="flex items-center space-x-2 text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 transition-colors font-medium"
            >
              <SparklesIcon className="w-4 h-4" />
              <span>Crear Filtro</span>
            </button>
            {isExpanded ? (
              <ChevronUpIcon className="w-5 h-5 text-secondary-400" />
            ) : (
              <ChevronDownIcon className="w-5 h-5 text-secondary-400" />
            )}
          </div>
        </div>

        {/* Contenido expandible */}
        {isExpanded && (
          <div className="mt-6 space-y-6">
            {/* Filtros básicos */}
            <div>
              <h4 className="text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-3">
                Filtros Básicos
              </h4>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <select
                  className="input"
                  value={currentFilters.status || ''}
                  onChange={(e) => onFilterChange('status', e.target.value)}
                >
                  <option value="">Todos los estados</option>
                  <option value="new">Nuevo</option>
                  <option value="contacted">Contactado</option>
                  <option value="qualified">Calificado</option>
                  <option value="converted">Convertido</option>
                  <option value="lost">Perdido</option>
                </select>

                <select
                  className="input"
                  value={currentFilters.desk_id || ''}
                  onChange={(e) => onFilterChange('desk_id', e.target.value)}
                >
                  <option value="">Todas las mesas</option>
                  {desksData?.data?.desks?.map((desk: any) => (
                    <option key={desk.id} value={desk.id}>
                      {desk.name}
                    </option>
                  ))}
                </select>

                <button
                  onClick={() => setShowCreateModal(true)}
                  className="btn-primary flex items-center justify-center"
                  disabled={getBasicFiltersCount() === 0}
                >
                  <PlusIcon className="w-4 h-4 mr-2" />
                  Guardar Vista
                </button>
              </div>
            </div>

            {/* Vistas guardadas */}
            <div>
              <h4 className="text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-3">
                Vistas Guardadas
              </h4>
              
              {savedFilters.length === 0 ? (
                <div className="text-center py-8 text-secondary-500 dark:text-secondary-400">
                  <BookmarkIcon className="w-12 h-12 mx-auto mb-3 opacity-50" />
                  <p>No hay vistas guardadas</p>
                  <p className="text-sm">Configura filtros y guarda tu primera vista</p>
                </div>
              ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                  {savedFilters.map((filter) => (
                    <div
                      key={filter.id}
                      className="relative group bg-white dark:bg-secondary-800 border border-secondary-200 dark:border-secondary-700 rounded-lg p-4 hover:shadow-md transition-all duration-200 hover:border-primary-300 dark:hover:border-primary-600"
                    >
                      {/* Badge de predeterminado */}
                      {filter.isDefault && (
                        <div className="absolute -top-2 -right-2">
                          <StarIconSolid className="w-5 h-5 text-yellow-500" />
                        </div>
                      )}

                      {/* Nombre del filtro */}
                      <div className="flex items-start justify-between mb-3">
                        <h5 className="font-medium text-secondary-900 dark:text-secondary-100 truncate pr-2">
                          {filter.name}
                        </h5>
                        <div className="flex items-center space-x-1 opacity-0 group-hover:opacity-100 transition-opacity">
                          <button
                            onClick={() => handleSetDefault(filter.id)}
                            className="p-1 text-secondary-400 hover:text-yellow-500 transition-colors"
                            title="Marcar como predeterminado"
                          >
                            <StarIcon className="w-4 h-4" />
                          </button>
                          <button
                            onClick={() => handleDeleteFilter(filter.id)}
                            className="p-1 text-secondary-400 hover:text-red-500 transition-colors"
                            title="Eliminar vista"
                          >
                            <TrashIcon className="w-4 h-4" />
                          </button>
                        </div>
                      </div>

                      {/* Resumen de filtros */}
                      <div className="space-y-1 mb-4">
                        {Object.entries(filter.filters).map(([key, value]) => {
                          if (!value || key === 'search' || key === '_wizardConfig') return null
                          
                          // Convertir el valor a string si es un objeto
                          const displayValue = typeof value === 'object' 
                            ? JSON.stringify(value) 
                            : String(value)
                          
                          return (
                            <div key={key} className="flex items-center text-xs text-secondary-600 dark:text-secondary-400">
                              <span className="capitalize font-medium mr-2">{key.replace('_', ' ')}:</span>
                              <span className="truncate">{displayValue}</span>
                            </div>
                          )
                        })}
                        {Object.keys(filter.filters).filter(key => filter.filters[key] && key !== 'search').length === 0 && (
                          <span className="text-xs text-secondary-500 dark:text-secondary-400">Sin filtros específicos</span>
                        )}
                      </div>

                      {/* Fecha de creación */}
                      <div className="text-xs text-secondary-500 dark:text-secondary-400 mb-3">
                        {new Date(filter.createdAt).toLocaleDateString()}
                      </div>

                      {/* Botón aplicar */}
                      <button
                        onClick={() => handleApplyFilter(filter)}
                        className="w-full btn-outline-primary text-sm py-2"
                      >
                        Aplicar Vista
                      </button>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        )}
      </div>

      {/* Modal para crear nueva vista */}
      {showCreateModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white dark:bg-secondary-800 rounded-lg p-6 w-full max-w-md mx-4">
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-lg font-semibold text-secondary-900 dark:text-secondary-100">
                Guardar Vista
              </h3>
              <button
                onClick={() => setShowCreateModal(false)}
                className="text-secondary-400 hover:text-secondary-600 dark:hover:text-secondary-200"
              >
                <XMarkIcon className="w-5 h-5" />
              </button>
            </div>
            
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                  Nombre de la vista
                </label>
                <input
                  type="text"
                  className="input w-full"
                  placeholder="Ej: Leads calificados mesa 1"
                  value={newFilterName}
                  onChange={(e) => setNewFilterName(e.target.value)}
                  onKeyPress={(e) => e.key === 'Enter' && handleCreateFilter()}
                />
              </div>
              
              <div className="flex justify-end space-x-3">
                <button
                  onClick={() => setShowCreateModal(false)}
                  className="btn-ghost"
                >
                  Cancelar
                </button>
                <button
                  onClick={handleCreateFilter}
                  className="btn-primary"
                  disabled={!newFilterName.trim()}
                >
                  Guardar
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Filter Wizard */}
      <FilterWizard
        isOpen={showFilterWizard}
        onClose={() => setShowFilterWizard(false)}
        onSave={handleWizardFilterSave}
        desksData={desksData}
      />
    </div>
  )
}
