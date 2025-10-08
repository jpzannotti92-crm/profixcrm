import React, { useState } from 'react'
import { 
  ChevronDownIcon, 
  ChevronUpIcon, 
  FunnelIcon,
  AdjustmentsHorizontalIcon
} from '@heroicons/react/24/outline'

interface CollapsibleFilterBarProps {
  children: React.ReactNode
  title?: string
  defaultExpanded?: boolean
  showFilterCount?: boolean
  activeFiltersCount?: number
}

export default function CollapsibleFilterBar({ 
  children, 
  title = "Filtros", 
  defaultExpanded = true,
  showFilterCount = false,
  activeFiltersCount = 0
}: CollapsibleFilterBarProps) {
  const [isExpanded, setIsExpanded] = useState(defaultExpanded)

  return (
    <div className="card">
      {/* Header colapsable */}
      <div 
        className="card-body pb-0 cursor-pointer select-none"
        onClick={() => setIsExpanded(!isExpanded)}
      >
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="flex items-center gap-2">
              <AdjustmentsHorizontalIcon className="w-5 h-5 text-secondary-500" />
              <h3 className="font-medium text-secondary-900 dark:text-secondary-100">
                {title}
              </h3>
            </div>
            
            {showFilterCount && activeFiltersCount > 0 && (
              <div className="flex items-center gap-2">
                <span className="bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200 text-xs px-2 py-1 rounded-full">
                  {activeFiltersCount} activo{activeFiltersCount !== 1 ? 's' : ''}
                </span>
              </div>
            )}
          </div>

          <button className="p-1 rounded-lg hover:bg-secondary-100 dark:hover:bg-secondary-700 transition-colors">
            {isExpanded ? (
              <ChevronUpIcon className="w-5 h-5 text-secondary-500" />
            ) : (
              <ChevronDownIcon className="w-5 h-5 text-secondary-500" />
            )}
          </button>
        </div>
      </div>

      {/* Contenido colapsable */}
      <div className={`transition-all duration-300 ease-in-out overflow-hidden ${
        isExpanded ? 'max-h-96 opacity-100' : 'max-h-0 opacity-0'
      }`}>
        <div className="card-body pt-4">
          {children}
        </div>
      </div>

      {/* Indicador visual cuando está colapsado */}
      {!isExpanded && (
        <div className="px-6 pb-4">
          <div className="h-px bg-gradient-to-r from-transparent via-secondary-200 dark:via-secondary-700 to-transparent"></div>
          <div className="flex justify-center -mt-2">
            <div className="bg-white dark:bg-secondary-800 px-3">
              <FunnelIcon className="w-4 h-4 text-secondary-400" />
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
