import { useState } from 'react'
import { 
  ChevronDownIcon, 
  ChevronUpIcon, 
  MagnifyingGlassIcon,
  XMarkIcon,
  UserIcon,
  EnvelopeIcon,
  PhoneIcon,
  HashtagIcon
} from '@heroicons/react/24/outline'

interface SearchCollapsibleProps {
  searchValues: {
    general: string
    email: string
    id: string
    name: string
    phone: string
  }
  onSearchChange: (searchType: string, value: string) => void
  onClearSearch: () => void
}

export default function SearchCollapsible({
  searchValues,
  onSearchChange,
  onClearSearch
}: SearchCollapsibleProps) {
  const [isExpanded, setIsExpanded] = useState(false)

  // Contar búsquedas activas
  const getActiveSearchCount = () => {
    return Object.values(searchValues).filter(value => 
      value !== '' && value !== null && value !== undefined
    ).length
  }

  const activeSearchCount = getActiveSearchCount()

  return (
    <div className="card">
      <div className="card-body">
        {/* Header del colapsable */}
        <div 
          className="flex items-center justify-between cursor-pointer"
          onClick={() => setIsExpanded(!isExpanded)}
        >
          <div className="flex items-center space-x-3">
            <MagnifyingGlassIcon className="w-5 h-5 text-primary-600 dark:text-primary-400" />
            <h3 className="text-lg font-semibold text-secondary-900 dark:text-secondary-100">
              Búsqueda Avanzada
            </h3>
            {activeSearchCount > 0 && (
              <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200">
                {activeSearchCount} activas
              </span>
            )}
          </div>
          <div className="flex items-center space-x-2">
            {activeSearchCount > 0 && (
              <button
                onClick={(e) => {
                  e.stopPropagation()
                  onClearSearch()
                }}
                className="text-sm text-secondary-500 hover:text-secondary-700 dark:text-secondary-400 dark:hover:text-secondary-200 transition-colors"
              >
                Limpiar búsquedas
              </button>
            )}
            {isExpanded ? (
              <ChevronUpIcon className="w-5 h-5 text-secondary-400" />
            ) : (
              <ChevronDownIcon className="w-5 h-5 text-secondary-400" />
            )}
          </div>
        </div>

        {/* Contenido expandible con animación suave */}
        <div className={`transition-all duration-300 ease-in-out overflow-hidden ${
          isExpanded ? 'max-h-96 opacity-100 mt-6' : 'max-h-0 opacity-0'
        }`}>
          <div className="space-y-4">
            {/* Búsqueda general */}
            <div className="relative">
              <MagnifyingGlassIcon className="w-5 h-5 absolute left-3 top-1/2 transform -translate-y-1/2 text-secondary-400" />
              <input
                type="text"
                placeholder="Búsqueda general en todos los campos..."
                className="input pl-10 pr-10"
                value={searchValues.general}
                onChange={(e) => onSearchChange('general', e.target.value)}
              />
              {searchValues.general && (
                <button
                  onClick={() => onSearchChange('general', '')}
                  className="absolute right-3 top-1/2 transform -translate-y-1/2 text-secondary-400 hover:text-secondary-600 dark:hover:text-secondary-200 transition-colors"
                >
                  <XMarkIcon className="w-4 h-4" />
                </button>
              )}
            </div>

            {/* Búsquedas específicas */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              {/* Búsqueda por email */}
              <div className="relative">
                <EnvelopeIcon className="w-4 h-4 absolute left-3 top-1/2 transform -translate-y-1/2 text-secondary-400" />
                <input
                  type="email"
                  placeholder="Buscar por email..."
                  className="input pl-9 pr-8 text-sm"
                  value={searchValues.email}
                  onChange={(e) => onSearchChange('email', e.target.value)}
                />
                {searchValues.email && (
                  <button
                    onClick={() => onSearchChange('email', '')}
                    className="absolute right-2 top-1/2 transform -translate-y-1/2 text-secondary-400 hover:text-secondary-600 dark:hover:text-secondary-200 transition-colors"
                  >
                    <XMarkIcon className="w-3 h-3" />
                  </button>
                )}
              </div>

              {/* Búsqueda por ID */}
              <div className="relative">
                <HashtagIcon className="w-4 h-4 absolute left-3 top-1/2 transform -translate-y-1/2 text-secondary-400" />
                <input
                  type="text"
                  placeholder="Buscar por ID..."
                  className="input pl-9 pr-8 text-sm"
                  value={searchValues.id}
                  onChange={(e) => onSearchChange('id', e.target.value)}
                />
                {searchValues.id && (
                  <button
                    onClick={() => onSearchChange('id', '')}
                    className="absolute right-2 top-1/2 transform -translate-y-1/2 text-secondary-400 hover:text-secondary-600 dark:hover:text-secondary-200 transition-colors"
                  >
                    <XMarkIcon className="w-3 h-3" />
                  </button>
                )}
              </div>

              {/* Búsqueda por nombre */}
              <div className="relative">
                <UserIcon className="w-4 h-4 absolute left-3 top-1/2 transform -translate-y-1/2 text-secondary-400" />
                <input
                  type="text"
                  placeholder="Buscar por nombre..."
                  className="input pl-9 pr-8 text-sm"
                  value={searchValues.name}
                  onChange={(e) => onSearchChange('name', e.target.value)}
                />
                {searchValues.name && (
                  <button
                    onClick={() => onSearchChange('name', '')}
                    className="absolute right-2 top-1/2 transform -translate-y-1/2 text-secondary-400 hover:text-secondary-600 dark:hover:text-secondary-200 transition-colors"
                  >
                    <XMarkIcon className="w-3 h-3" />
                  </button>
                )}
              </div>

              {/* Búsqueda por teléfono */}
              <div className="relative">
                <PhoneIcon className="w-4 h-4 absolute left-3 top-1/2 transform -translate-y-1/2 text-secondary-400" />
                <input
                  type="tel"
                  placeholder="Buscar por teléfono..."
                  className="input pl-9 pr-8 text-sm"
                  value={searchValues.phone}
                  onChange={(e) => onSearchChange('phone', e.target.value)}
                />
                {searchValues.phone && (
                  <button
                    onClick={() => onSearchChange('phone', '')}
                    className="absolute right-2 top-1/2 transform -translate-y-1/2 text-secondary-400 hover:text-secondary-600 dark:hover:text-secondary-200 transition-colors"
                  >
                    <XMarkIcon className="w-3 h-3" />
                  </button>
                )}
              </div>
            </div>

            {/* Información de ayuda */}
            {activeSearchCount === 0 && (
              <div className="bg-secondary-50 dark:bg-secondary-800 rounded-lg p-4 border border-secondary-200 dark:border-secondary-700">
                <div className="flex items-start space-x-3">
                  <MagnifyingGlassIcon className="w-5 h-5 text-primary-600 dark:text-primary-400 mt-0.5 flex-shrink-0" />
                  <div>
                    <h4 className="text-sm font-medium text-secondary-900 dark:text-secondary-100 mb-1">
                      Búsqueda Inteligente
                    </h4>
                    <p className="text-xs text-secondary-600 dark:text-secondary-400 leading-relaxed">
                      Utiliza la búsqueda general para encontrar leads en todos los campos, o usa las búsquedas específicas para resultados más precisos. Puedes combinar múltiples criterios de búsqueda.
                    </p>
                  </div>
                </div>
              </div>
            )}

            {/* Resumen de búsquedas activas */}
            {activeSearchCount > 0 && (
              <div className="bg-primary-50 dark:bg-primary-900/20 rounded-lg p-3 border border-primary-200 dark:border-primary-800">
                <div className="flex items-center justify-between">
                  <div className="flex items-center space-x-2">
                    <MagnifyingGlassIcon className="w-4 h-4 text-primary-600 dark:text-primary-400" />
                    <span className="text-sm font-medium text-primary-900 dark:text-primary-100">
                      {activeSearchCount} búsqueda{activeSearchCount !== 1 ? 's' : ''} activa{activeSearchCount !== 1 ? 's' : ''}
                    </span>
                  </div>
                  <button
                    onClick={onClearSearch}
                    className="text-xs text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-200 font-medium transition-colors"
                  >
                    Limpiar todas
                  </button>
                </div>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}
