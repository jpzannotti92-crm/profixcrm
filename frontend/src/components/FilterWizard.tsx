import { useState, useEffect } from 'react'
import { 
  XMarkIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  CheckCircleIcon,
  FunnelIcon,
  ChartBarIcon,
  CurrencyDollarIcon,
  CalendarIcon,
  UserIcon,
  BuildingOfficeIcon,
  TagIcon,
  GlobeAltIcon,
  SparklesIcon
} from '@heroicons/react/24/outline'

interface FilterWizardProps {
  isOpen: boolean
  onClose: () => void
  onSave: (filterConfig: FilterConfig) => void
  desksData?: any
  usersData?: any
}

interface FilterConfig {
  name: string
  description: string
  conditions: FilterCondition[]
  kpis: string[]
  visualization: 'table' | 'cards' | 'chart'
}

interface FilterCondition {
  field: string
  operator: string
  value: string | number
  logic?: 'AND' | 'OR'
}

interface FieldOption {
  value: string | number
  label: string
}

const FILTER_FIELDS = [
  { id: 'status', name: 'Estado del Lead', icon: TagIcon, type: 'select', options: ['new', 'contacted', 'qualified', 'converted', 'lost'] },
  { id: 'desk_id', name: 'Mesa de Trabajo', icon: BuildingOfficeIcon, type: 'select', options: [] },
  { id: 'assigned_user_id', name: 'Usuario Asignado', icon: UserIcon, type: 'select', options: [] },
  { id: 'country', name: 'País', icon: GlobeAltIcon, type: 'text' },
  { id: 'source', name: 'Fuente', icon: TagIcon, type: 'text' },
  { id: 'budget', name: 'Presupuesto', icon: CurrencyDollarIcon, type: 'number' },
  { id: 'created_at', name: 'Fecha de Creación', icon: CalendarIcon, type: 'date' },
  { id: 'updated_at', name: 'Última Actualización', icon: CalendarIcon, type: 'date' }
]

const OPERATORS = {
  text: [
    { value: 'contains', label: 'Contiene' },
    { value: 'equals', label: 'Es igual a' },
    { value: 'starts_with', label: 'Comienza con' },
    { value: 'ends_with', label: 'Termina con' }
  ],
  number: [
    { value: 'equals', label: 'Es igual a' },
    { value: 'greater_than', label: 'Mayor que' },
    { value: 'less_than', label: 'Menor que' },
    { value: 'between', label: 'Entre' }
  ],
  select: [
    { value: 'equals', label: 'Es igual a' },
    { value: 'not_equals', label: 'No es igual a' },
    { value: 'in', label: 'Está en' }
  ],
  date: [
    { value: 'equals', label: 'Es igual a' },
    { value: 'after', label: 'Después de' },
    { value: 'before', label: 'Antes de' },
    { value: 'between', label: 'Entre' }
  ]
}

const KPIS = [
  { id: 'total_leads', name: 'Total de Leads', icon: ChartBarIcon },
  { id: 'conversion_rate', name: 'Tasa de Conversión', icon: ChartBarIcon },
  { id: 'avg_budget', name: 'Presupuesto Promedio', icon: CurrencyDollarIcon },
  { id: 'leads_by_status', name: 'Leads por Estado', icon: TagIcon },
  { id: 'leads_by_source', name: 'Leads por Fuente', icon: GlobeAltIcon },
  { id: 'leads_by_desk', name: 'Leads por Mesa', icon: BuildingOfficeIcon },
  { id: 'monthly_trend', name: 'Tendencia Mensual', icon: CalendarIcon }
]

export default function FilterWizard({ isOpen, onClose, onSave, desksData, usersData }: FilterWizardProps) {
  const [currentStep, setCurrentStep] = useState(1)
  const [filterConfig, setFilterConfig] = useState<FilterConfig>({
    name: '',
    description: '',
    conditions: [],
    kpis: [],
    visualization: 'table'
  })

  const totalSteps = 4

  useEffect(() => {
    if (!isOpen) {
      setCurrentStep(1)
      setFilterConfig({
        name: '',
        description: '',
        conditions: [],
        kpis: [],
        visualization: 'table'
      })
    }
  }, [isOpen])

  const addCondition = () => {
    setFilterConfig(prev => ({
      ...prev,
      conditions: [...prev.conditions, {
        field: '',
        operator: '',
        value: '',
        logic: prev.conditions.length > 0 ? 'AND' : undefined
      }]
    }))
  }

  const updateCondition = (index: number, updates: Partial<FilterCondition>) => {
    setFilterConfig(prev => ({
      ...prev,
      conditions: prev.conditions.map((condition, i) => 
        i === index ? { ...condition, ...updates } : condition
      )
    }))
  }

  const removeCondition = (index: number) => {
    setFilterConfig(prev => ({
      ...prev,
      conditions: prev.conditions.filter((_, i) => i !== index)
    }))
  }

  const toggleKPI = (kpiId: string) => {
    setFilterConfig(prev => ({
      ...prev,
      kpis: prev.kpis.includes(kpiId) 
        ? prev.kpis.filter(id => id !== kpiId)
        : [...prev.kpis, kpiId]
    }))
  }

  const canProceed = () => {
    switch (currentStep) {
      case 1:
        return filterConfig.name.trim() !== ''
      case 2:
        return filterConfig.conditions.length > 0 && 
               filterConfig.conditions.every(c => c.field && c.operator && c.value)
      case 3:
        return filterConfig.kpis.length > 0
      case 4:
        return true
      default:
        return false
    }
  }

  const handleSave = () => {
    onSave(filterConfig)
    onClose()
  }

  const getFieldOptions = (fieldId: string): FieldOption[] => {
    const field = FILTER_FIELDS.find(f => f.id === fieldId)
    if (!field) return []

    switch (fieldId) {
      case 'desk_id':
        return desksData?.data?.desks?.map((desk: any) => ({ value: desk.id, label: desk.name })) || []
      case 'assigned_user_id':
        return usersData?.data?.users?.map((user: any) => ({ value: user.id, label: user.name })) || []
      case 'status':
        return [
          { value: 'new', label: 'Nuevo' },
          { value: 'contacted', label: 'Contactado' },
          { value: 'qualified', label: 'Calificado' },
          { value: 'converted', label: 'Convertido' },
          { value: 'lost', label: 'Perdido' }
        ]
      default:
        return field.options?.map(opt => ({ value: opt, label: opt })) || []
    }
  }

  if (!isOpen) return null

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div className="bg-white dark:bg-secondary-800 rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden">
        {/* Header */}
        <div className="flex items-center justify-between p-6 border-b border-secondary-200 dark:border-secondary-700">
          <div className="flex items-center space-x-3">
            <div className="w-10 h-10 bg-primary-100 dark:bg-primary-900 rounded-lg flex items-center justify-center">
              <SparklesIcon className="w-6 h-6 text-primary-600 dark:text-primary-400" />
            </div>
            <div>
              <h2 className="text-xl font-bold text-secondary-900 dark:text-white">
                Asistente de Filtros Inteligentes
              </h2>
              <p className="text-sm text-secondary-600 dark:text-secondary-400">
                Paso {currentStep} de {totalSteps}
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-2 text-secondary-400 hover:text-secondary-600 dark:hover:text-secondary-200 transition-colors"
          >
            <XMarkIcon className="w-6 h-6" />
          </button>
        </div>

        {/* Progress Bar */}
        <div className="px-6 py-4 bg-secondary-50 dark:bg-secondary-900">
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm font-medium text-secondary-700 dark:text-secondary-300">
              Progreso
            </span>
            <span className="text-sm text-secondary-500 dark:text-secondary-400">
              {Math.round((currentStep / totalSteps) * 100)}%
            </span>
          </div>
          <div className="w-full bg-secondary-200 dark:bg-secondary-700 rounded-full h-2">
            <div 
              className="bg-primary-600 h-2 rounded-full transition-all duration-300"
              style={{ width: `${(currentStep / totalSteps) * 100}%` }}
            />
          </div>
        </div>

        {/* Content */}
        <div className="p-6 overflow-y-auto max-h-[60vh]">
          {/* Step 1: Información Básica */}
          {currentStep === 1 && (
            <div className="space-y-6">
              <div className="text-center mb-8">
                <FunnelIcon className="w-16 h-16 text-primary-600 dark:text-primary-400 mx-auto mb-4" />
                <h3 className="text-2xl font-bold text-secondary-900 dark:text-white mb-2">
                  Información del Filtro
                </h3>
                <p className="text-secondary-600 dark:text-secondary-400">
                  Comencemos definiendo el nombre y descripción de tu filtro personalizado
                </p>
              </div>

              <div className="max-w-2xl mx-auto space-y-6">
                <div>
                  <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                    Nombre del Filtro *
                  </label>
                  <input
                    type="text"
                    className="input w-full"
                    placeholder="Ej: Leads Calificados Mesa Premium"
                    value={filterConfig.name}
                    onChange={(e) => setFilterConfig(prev => ({ ...prev, name: e.target.value }))}
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                    Descripción (Opcional)
                  </label>
                  <textarea
                    className="input w-full h-24 resize-none"
                    placeholder="Describe qué tipo de leads mostrará este filtro..."
                    value={filterConfig.description}
                    onChange={(e) => setFilterConfig(prev => ({ ...prev, description: e.target.value }))}
                  />
                </div>
              </div>
            </div>
          )}

          {/* Step 2: Condiciones de Filtro */}
          {currentStep === 2 && (
            <div className="space-y-6">
              <div className="text-center mb-8">
                <FunnelIcon className="w-16 h-16 text-primary-600 dark:text-primary-400 mx-auto mb-4" />
                <h3 className="text-2xl font-bold text-secondary-900 dark:text-white mb-2">
                  Condiciones de Filtrado
                </h3>
                <p className="text-secondary-600 dark:text-secondary-400">
                  Define las reglas que determinarán qué leads se mostrarán
                </p>
              </div>

              <div className="space-y-4">
                {filterConfig.conditions.map((condition, index) => (
                  <div key={index} className="bg-secondary-50 dark:bg-secondary-900 rounded-lg p-4 border border-secondary-200 dark:border-secondary-700">
                    <div className="flex items-center justify-between mb-4">
                      <div className="flex items-center space-x-2">
                        {index > 0 && (
                          <select
                            className="input-sm"
                            value={condition.logic || 'AND'}
                            onChange={(e) => updateCondition(index, { logic: e.target.value as 'AND' | 'OR' })}
                          >
                            <option value="AND">Y</option>
                            <option value="OR">O</option>
                          </select>
                        )}
                        <span className="text-sm font-medium text-secondary-700 dark:text-secondary-300">
                          Condición {index + 1}
                        </span>
                      </div>
                      <button
                        onClick={() => removeCondition(index)}
                        className="text-red-500 hover:text-red-700 transition-colors"
                      >
                        <XMarkIcon className="w-5 h-5" />
                      </button>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                      <div>
                        <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-1">
                          Campo
                        </label>
                        <select
                          className="input w-full"
                          value={condition.field}
                          onChange={(e) => updateCondition(index, { field: e.target.value, operator: '', value: '' })}
                        >
                          <option value="">Seleccionar campo</option>
                          {FILTER_FIELDS.map(field => (
                            <option key={field.id} value={field.id}>
                              {field.name}
                            </option>
                          ))}
                        </select>
                      </div>

                      <div>
                        <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-1">
                          Operador
                        </label>
                        <select
                          className="input w-full"
                          value={condition.operator}
                          onChange={(e) => updateCondition(index, { operator: e.target.value })}
                          disabled={!condition.field}
                        >
                          <option value="">Seleccionar operador</option>
                          {condition.field && OPERATORS[FILTER_FIELDS.find(f => f.id === condition.field)?.type as keyof typeof OPERATORS]?.map(op => (
                            <option key={op.value} value={op.value}>
                              {op.label}
                            </option>
                          ))}
                        </select>
                      </div>

                      <div>
                        <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-1">
                          Valor
                        </label>
                        {condition.field && FILTER_FIELDS.find(f => f.id === condition.field)?.type === 'select' ? (
                          <select
                            className="input w-full"
                            value={condition.value}
                            onChange={(e) => updateCondition(index, { value: e.target.value })}
                          >
                            <option value="">Seleccionar valor</option>
                            {getFieldOptions(condition.field).map((option: FieldOption) => (
                              <option key={option.value} value={option.value}>
                                {option.label}
                              </option>
                            ))}
                          </select>
                        ) : (
                          <input
                            type={FILTER_FIELDS.find(f => f.id === condition.field)?.type === 'number' ? 'number' : 
                                  FILTER_FIELDS.find(f => f.id === condition.field)?.type === 'date' ? 'date' : 'text'}
                            className="input w-full"
                            placeholder="Ingresa el valor"
                            value={condition.value}
                            onChange={(e) => updateCondition(index, { value: e.target.value })}
                          />
                        )}
                      </div>
                    </div>
                  </div>
                ))}

                <button
                  onClick={addCondition}
                  className="w-full btn-outline-primary py-3 border-2 border-dashed"
                >
                  + Agregar Condición
                </button>
              </div>
            </div>
          )}

          {/* Step 3: KPIs */}
          {currentStep === 3 && (
            <div className="space-y-6">
              <div className="text-center mb-8">
                <ChartBarIcon className="w-16 h-16 text-primary-600 dark:text-primary-400 mx-auto mb-4" />
                <h3 className="text-2xl font-bold text-secondary-900 dark:text-white mb-2">
                  Métricas y KPIs
                </h3>
                <p className="text-secondary-600 dark:text-secondary-400">
                  Selecciona las métricas que quieres visualizar con este filtro
                </p>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                {KPIS.map(kpi => (
                  <div
                    key={kpi.id}
                    className={`relative p-4 rounded-lg border-2 cursor-pointer transition-all duration-200 ${
                      filterConfig.kpis.includes(kpi.id)
                        ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
                        : 'border-secondary-200 dark:border-secondary-700 hover:border-primary-300 dark:hover:border-primary-600'
                    }`}
                    onClick={() => toggleKPI(kpi.id)}
                  >
                    {filterConfig.kpis.includes(kpi.id) && (
                      <div className="absolute -top-2 -right-2">
                        <CheckCircleIcon className="w-6 h-6 text-primary-600 bg-white dark:bg-secondary-800 rounded-full" />
                      </div>
                    )}
                    <div className="flex items-center space-x-3">
                      <kpi.icon className="w-8 h-8 text-primary-600 dark:text-primary-400" />
                      <div>
                        <h4 className="font-medium text-secondary-900 dark:text-white">
                          {kpi.name}
                        </h4>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Step 4: Visualización */}
          {currentStep === 4 && (
            <div className="space-y-6">
              <div className="text-center mb-8">
                <CheckCircleIcon className="w-16 h-16 text-green-600 mx-auto mb-4" />
                <h3 className="text-2xl font-bold text-secondary-900 dark:text-white mb-2">
                  ¡Filtro Listo!
                </h3>
                <p className="text-secondary-600 dark:text-secondary-400">
                  Revisa la configuración de tu filtro antes de guardarlo
                </p>
              </div>

              <div className="max-w-2xl mx-auto space-y-6">
                <div className="bg-secondary-50 dark:bg-secondary-900 rounded-lg p-6">
                  <h4 className="font-semibold text-secondary-900 dark:text-white mb-4">
                    Resumen del Filtro
                  </h4>
                  
                  <div className="space-y-3">
                    <div>
                      <span className="text-sm font-medium text-secondary-700 dark:text-secondary-300">Nombre:</span>
                      <p className="text-secondary-900 dark:text-white">{filterConfig.name}</p>
                    </div>
                    
                    {filterConfig.description && (
                      <div>
                        <span className="text-sm font-medium text-secondary-700 dark:text-secondary-300">Descripción:</span>
                        <p className="text-secondary-900 dark:text-white">{filterConfig.description}</p>
                      </div>
                    )}
                    
                    <div>
                      <span className="text-sm font-medium text-secondary-700 dark:text-secondary-300">Condiciones:</span>
                      <p className="text-secondary-900 dark:text-white">{filterConfig.conditions.length} condiciones configuradas</p>
                    </div>
                    
                    <div>
                      <span className="text-sm font-medium text-secondary-700 dark:text-secondary-300">KPIs:</span>
                      <p className="text-secondary-900 dark:text-white">{filterConfig.kpis.length} métricas seleccionadas</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="flex items-center justify-between p-6 border-t border-secondary-200 dark:border-secondary-700">
          <button
            onClick={() => setCurrentStep(Math.max(1, currentStep - 1))}
            disabled={currentStep === 1}
            className="btn-ghost flex items-center space-x-2 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <ChevronLeftIcon className="w-4 h-4" />
            <span>Anterior</span>
          </button>

          <div className="flex items-center space-x-2">
            {Array.from({ length: totalSteps }, (_, i) => (
              <div
                key={i}
                className={`w-2 h-2 rounded-full ${
                  i + 1 <= currentStep ? 'bg-primary-600' : 'bg-secondary-300 dark:bg-secondary-600'
                }`}
              />
            ))}
          </div>

          {currentStep < totalSteps ? (
            <button
              onClick={() => setCurrentStep(currentStep + 1)}
              disabled={!canProceed()}
              className="btn-primary flex items-center space-x-2 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <span>Siguiente</span>
              <ChevronRightIcon className="w-4 h-4" />
            </button>
          ) : (
            <button
              onClick={handleSave}
              className="btn-primary flex items-center space-x-2"
            >
              <CheckCircleIcon className="w-4 h-4" />
              <span>Guardar Filtro</span>
            </button>
          )}
        </div>
      </div>
    </div>
  )
}
