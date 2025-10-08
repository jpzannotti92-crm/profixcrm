import { useState, useEffect } from 'react'
import { 
  BookmarkIcon, 
  PlusIcon, 
  TrashIcon, 
  PencilIcon,
  XMarkIcon,
  CheckIcon
} from '@heroicons/react/24/outline'
import { BookmarkIcon as BookmarkSolidIcon } from '@heroicons/react/24/solid'

interface SavedFilter {
  id: string
  name: string
  filters: Record<string, any>
  createdAt: string
  isDefault?: boolean
}

interface SavedFiltersProps {
  currentFilters: Record<string, any>
  onLoadFilter: (filters: Record<string, any>) => void
  onFiltersChange?: () => void
}

export default function SavedFilters({ 
  currentFilters, 
  onLoadFilter, 
  onFiltersChange 
}: SavedFiltersProps) {
  const [savedFilters, setSavedFilters] = useState<SavedFilter[]>([])
  const [showSaveModal, setShowSaveModal] = useState(false)
  const [showFiltersDropdown, setShowFiltersDropdown] = useState(false)
  const [filterName, setFilterName] = useState('')
  const [editingFilter, setEditingFilter] = useState<string | null>(null)
  const [editName, setEditName] = useState('')

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

  // Verificar si hay filtros activos
  const hasActiveFilters = () => {
    return Object.values(currentFilters).some(value => 
      value !== '' && value !== null && value !== undefined
    )
  }

  // Guardar filtro actual
  const handleSaveFilter = () => {
    if (!filterName.trim() || !hasActiveFilters()) return

    const newFilter: SavedFilter = {
      id: Date.now().toString(),
      name: filterName.trim(),
      filters: { ...currentFilters },
      createdAt: new Date().toISOString()
    }

    const updatedFilters = [...savedFilters, newFilter]
    saveFiltersToStorage(updatedFilters)
    setFilterName('')
    setShowSaveModal(false)
    onFiltersChange?.()
  }

  // Cargar filtro guardado
  const handleLoadFilter = (filter: SavedFilter) => {
    onLoadFilter(filter.filters)
    setShowFiltersDropdown(false)
  }

  // Eliminar filtro guardado
  const handleDeleteFilter = (filterId: string) => {
    const updatedFilters = savedFilters.filter(f => f.id !== filterId)
    saveFiltersToStorage(updatedFilters)
    onFiltersChange?.()
  }

  // Editar nombre del filtro
  const handleEditFilter = (filterId: string, newName: string) => {
    if (!newName.trim()) return

    const updatedFilters = savedFilters.map(f => 
      f.id === filterId ? { ...f, name: newName.trim() } : f
    )
    saveFiltersToStorage(updatedFilters)
    setEditingFilter(null)
    setEditName('')
    onFiltersChange?.()
  }

  // Marcar como filtro por defecto
  const handleSetDefault = (filterId: string) => {
    const updatedFilters = savedFilters.map(f => ({
      ...f,
      isDefault: f.id === filterId
    }))
    saveFiltersToStorage(updatedFilters)
    onFiltersChange?.()
  }

  return (
    <div className="relative">
      {/* Botón principal */}
      <div className="flex items-center gap-2">
        <button
          onClick={() => setShowFiltersDropdown(!showFiltersDropdown)}
          className={`btn-ghost flex items-center gap-2 ${
            savedFilters.length > 0 ? 'text-primary-600' : ''
          }`}
          title="Filtros guardados"
        >
          <BookmarkIcon className="w-4 h-4" />
          <span className="hidden sm:inline">Filtros</span>
          {savedFilters.length > 0 && (
            <span className="bg-primary-100 text-primary-800 text-xs px-2 py-1 rounded-full">
              {savedFilters.length}
            </span>
          )}
        </button>

        {hasActiveFilters() && (
          <button
            onClick={() => setShowSaveModal(true)}
            className="btn-primary flex items-center gap-2"
            title="Guardar filtros actuales"
          >
            <PlusIcon className="w-4 h-4" />
            <span className="hidden sm:inline">Guardar</span>
          </button>
        )}
      </div>

      {/* Dropdown de filtros guardados */}
      {showFiltersDropdown && (
        <div className="absolute top-full left-0 mt-2 w-80 bg-white dark:bg-secondary-800 rounded-lg shadow-lg border border-secondary-200 dark:border-secondary-700 z-50">
          <div className="p-4">
            <h3 className="font-medium text-secondary-900 dark:text-secondary-100 mb-3">
              Filtros Guardados
            </h3>
            
            {savedFilters.length === 0 ? (
              <p className="text-secondary-500 text-sm text-center py-4">
                No hay filtros guardados
              </p>
            ) : (
              <div className="space-y-2 max-h-60 overflow-y-auto">
                {savedFilters.map((filter) => (
                  <div
                    key={filter.id}
                    className="flex items-center justify-between p-2 rounded-lg hover:bg-secondary-50 dark:hover:bg-secondary-700 group"
                  >
                    <div className="flex-1 min-w-0">
                      {editingFilter === filter.id ? (
                        <div className="flex items-center gap-2">
                          <input
                            type="text"
                            value={editName}
                            onChange={(e) => setEditName(e.target.value)}
                            className="input text-sm flex-1"
                            onKeyDown={(e) => {
                              if (e.key === 'Enter') {
                                handleEditFilter(filter.id, editName)
                              } else if (e.key === 'Escape') {
                                setEditingFilter(null)
                                setEditName('')
                              }
                            }}
                            autoFocus
                          />
                          <button
                            onClick={() => handleEditFilter(filter.id, editName)}
                            className="text-green-600 hover:text-green-700"
                          >
                            <CheckIcon className="w-4 h-4" />
                          </button>
                          <button
                            onClick={() => {
                              setEditingFilter(null)
                              setEditName('')
                            }}
                            className="text-secondary-400 hover:text-secondary-600"
                          >
                            <XMarkIcon className="w-4 h-4" />
                          </button>
                        </div>
                      ) : (
                        <div>
                          <div className="flex items-center gap-2">
                            <button
                              onClick={() => handleLoadFilter(filter)}
                              className="text-left flex-1 min-w-0"
                            >
                              <div className="flex items-center gap-2">
                                {filter.isDefault && (
                                  <BookmarkSolidIcon className="w-4 h-4 text-yellow-500" />
                                )}
                                <span className="font-medium text-secondary-900 dark:text-secondary-100 truncate">
                                  {filter.name}
                                </span>
                              </div>
                              <div className="text-xs text-secondary-500 mt-1">
                                {new Date(filter.createdAt).toLocaleDateString()}
                              </div>
                            </button>
                          </div>
                        </div>
                      )}
                    </div>

                    {editingFilter !== filter.id && (
                      <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button
                          onClick={() => handleSetDefault(filter.id)}
                          className={`p-1 rounded ${
                            filter.isDefault 
                              ? 'text-yellow-500' 
                              : 'text-secondary-400 hover:text-yellow-500'
                          }`}
                          title="Marcar como predeterminado"
                        >
                          <BookmarkSolidIcon className="w-4 h-4" />
                        </button>
                        <button
                          onClick={() => {
                            setEditingFilter(filter.id)
                            setEditName(filter.name)
                          }}
                          className="p-1 rounded text-secondary-400 hover:text-secondary-600"
                          title="Editar nombre"
                        >
                          <PencilIcon className="w-4 h-4" />
                        </button>
                        <button
                          onClick={() => handleDeleteFilter(filter.id)}
                          className="p-1 rounded text-secondary-400 hover:text-red-600"
                          title="Eliminar filtro"
                        >
                          <TrashIcon className="w-4 h-4" />
                        </button>
                      </div>
                    )}
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      )}

      {/* Modal para guardar filtro */}
      {showSaveModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white dark:bg-secondary-800 rounded-lg p-6 w-full max-w-md mx-4">
            <h3 className="text-lg font-medium text-secondary-900 dark:text-secondary-100 mb-4">
              Guardar Filtros
            </h3>
            
            <div className="mb-4">
              <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                Nombre del filtro
              </label>
              <input
                type="text"
                value={filterName}
                onChange={(e) => setFilterName(e.target.value)}
                className="input w-full"
                placeholder="Ej: Leads nuevos de Mesa A"
                onKeyDown={(e) => {
                  if (e.key === 'Enter') {
                    handleSaveFilter()
                  }
                }}
                autoFocus
              />
            </div>

            <div className="mb-4 p-3 bg-secondary-50 dark:bg-secondary-700 rounded-lg">
              <h4 className="text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                Filtros actuales:
              </h4>
              <div className="text-sm text-secondary-600 dark:text-secondary-400 space-y-1">
                {Object.entries(currentFilters).map(([key, value]) => {
                  if (value && value !== '') {
                    return (
                      <div key={key} className="flex justify-between">
                        <span className="capitalize">{key.replace('_', ' ')}:</span>
                        <span className="font-medium">{value}</span>
                      </div>
                    )
                  }
                  return null
                })}
              </div>
            </div>

            <div className="flex justify-end gap-3">
              <button
                onClick={() => {
                  setShowSaveModal(false)
                  setFilterName('')
                }}
                className="btn-ghost"
              >
                Cancelar
              </button>
              <button
                onClick={handleSaveFilter}
                disabled={!filterName.trim()}
                className="btn-primary disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Guardar
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Overlay para cerrar dropdown */}
      {showFiltersDropdown && (
        <div
          className="fixed inset-0 z-40"
          onClick={() => setShowFiltersDropdown(false)}
        />
      )}
    </div>
  )
}
