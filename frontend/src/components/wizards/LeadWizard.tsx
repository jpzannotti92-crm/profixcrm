import { useState } from 'react'
import { useQuery, useMutation } from '@tanstack/react-query'
import { 
  XMarkIcon,
  UserIcon,
  BuildingOfficeIcon,
  CheckIcon,
  ArrowRightIcon,
  ArrowLeftIcon,
  CurrencyDollarIcon
} from '@heroicons/react/24/outline'
import { leadsApi, desksApi, usersApi } from '../../services/api'
import toast from 'react-hot-toast'

interface LeadWizardProps {
  isOpen: boolean
  onClose: () => void
  onSuccess: () => void
}

interface LeadFormData {
  // Información personal
  first_name: string
  last_name: string
  email: string
  phone: string
  
  // Información adicional
  company?: string
  position?: string
  country?: string
  city?: string
  
  // Asignaciones
  desk_id?: number
  assigned_to?: number
  
  // Lead info
  source: string
  status: string
  interest_level: string
  budget?: number
  notes?: string
}

const STEPS = [
  { id: 1, title: 'Información Personal', icon: UserIcon },
  { id: 2, title: 'Información Adicional', icon: BuildingOfficeIcon },
  { id: 3, title: 'Asignación y Seguimiento', icon: CurrencyDollarIcon },
  { id: 4, title: 'Confirmación', icon: CheckIcon }
]

const SOURCES = [
  'Website',
  'Facebook',
  'Google Ads',
  'Referral',
  'Cold Call',
  'Email Campaign',
  'Trade Show',
  'Other'
]

export default function LeadWizard({ isOpen, onClose, onSuccess }: LeadWizardProps) {
  const [currentStep, setCurrentStep] = useState(1)
  const [formData, setFormData] = useState<LeadFormData>({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    company: '',
    position: '',
    country: '',
    city: '',
    desk_id: undefined,
    assigned_to: undefined,
    source: 'Website',
    status: 'new',
    interest_level: 'medium',
    budget: undefined,
    notes: ''
  })

  // Obtener mesas disponibles
  const { data: desksData, isLoading: desksLoading } = useQuery({
    queryKey: ['desks-for-lead'],
    queryFn: () => desksApi.getDesks(),
    enabled: isOpen,
      staleTime: 5 * 60 * 1000
  })

  // Obtener usuarios disponibles
  const { data: usersData, isLoading: usersLoading } = useQuery({
    queryKey: ['users-for-lead'],
    queryFn: () => usersApi.getUsers({ status: 'active' }),
    enabled: isOpen,
      staleTime: 5 * 60 * 1000
  })

  // Mutación para crear lead
  const createLeadMutation = useMutation({
    mutationFn: (leadData: any) => leadsApi.createLead(leadData),
    onSuccess: () => {
      toast.success('Lead creado exitosamente')
      onSuccess()
      handleClose()
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || 'Error al crear el lead')
    }
  })

  const handleClose = () => {
    setCurrentStep(1)
    setFormData({
      first_name: '',
      last_name: '',
      email: '',
      phone: '',
      company: '',
      position: '',
      country: '',
      city: '',
      desk_id: undefined,
      assigned_to: undefined,
      source: 'Website',
      status: 'new',
      interest_level: 'medium',
      budget: undefined,
      notes: ''
    })
    onClose()
  }

  const handleNext = () => {
    if (validateCurrentStep()) {
      setCurrentStep(prev => Math.min(prev + 1, STEPS.length))
    }
  }

  const handlePrevious = () => {
    setCurrentStep(prev => Math.max(prev - 1, 1))
  }

  const validateCurrentStep = (): boolean => {
    switch (currentStep) {
      case 1:
        if (!formData.first_name.trim()) {
          toast.error('El nombre es requerido')
          return false
        }
        if (!formData.last_name.trim()) {
          toast.error('El apellido es requerido')
          return false
        }
        if (!formData.email.trim()) {
          toast.error('El email es requerido')
          return false
        }
        if (!formData.phone.trim()) {
          toast.error('El teléfono es requerido')
          return false
        }
        return true
      case 2:
        // Información adicional es opcional
        return true
      case 3:
        // Asignación es opcional
        return true
      default:
        return true
    }
  }

  const handleSubmit = () => {
    if (validateCurrentStep()) {
      const leadData = {
        first_name: formData.first_name,
        last_name: formData.last_name,
        email: formData.email,
        phone: formData.phone,
        company: formData.company || null,
        position: formData.position || null,
        country: formData.country || null,
        city: formData.city || null,
        desk_id: formData.desk_id || null,
        assigned_to: formData.assigned_to || null,
        source: formData.source,
        status: formData.status,
        interest_level: formData.interest_level,
        budget: formData.budget || null,
        notes: formData.notes || null
      }
      
      createLeadMutation.mutate(leadData)
    }
  }

  const getSelectedDesk = () => {
    return desksData?.data?.find((desk: any) => desk.id === formData.desk_id)
  }

  const getSelectedUser = () => {
    return usersData?.data?.find((user: any) => user.id === formData.assigned_to)
  }

  if (!isOpen) return null

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div className="bg-white dark:bg-secondary-800 rounded-lg w-full max-w-4xl mx-4 max-h-[90vh] overflow-hidden">
        {/* Header */}
        <div className="flex items-center justify-between p-6 border-b border-secondary-200 dark:border-secondary-700">
          <div>
            <h2 className="text-xl font-semibold text-secondary-900 dark:text-white">
              Asistente para Crear Lead
            </h2>
            <p className="text-sm text-secondary-600 dark:text-secondary-400 mt-1">
              Paso {currentStep} de {STEPS.length}: {STEPS[currentStep - 1].title}
            </p>
          </div>
          <button
            onClick={handleClose}
            className="text-secondary-400 hover:text-secondary-600 dark:hover:text-secondary-300"
          >
            <XMarkIcon className="w-6 h-6" />
          </button>
        </div>

        {/* Progress Steps */}
        <div className="px-6 py-4 border-b border-secondary-200 dark:border-secondary-700">
          <div className="flex items-center justify-between">
            {STEPS.map((step, index) => {
              const Icon = step.icon
              const isActive = currentStep === step.id
              const isCompleted = currentStep > step.id
              
              return (
                <div key={step.id} className="flex items-center">
                  <div className={`
                    flex items-center justify-center w-10 h-10 rounded-full border-2 transition-colors
                    ${isActive 
                      ? 'border-primary-600 bg-primary-600 text-white' 
                      : isCompleted 
                        ? 'border-success-600 bg-success-600 text-white'
                        : 'border-secondary-300 text-secondary-400'
                    }
                  `}>
                    {isCompleted ? (
                      <CheckIcon className="w-5 h-5" />
                    ) : (
                      <Icon className="w-5 h-5" />
                    )}
                  </div>
                  <div className="ml-3 hidden sm:block">
                    <p className={`text-sm font-medium ${
                      isActive ? 'text-primary-600' : isCompleted ? 'text-success-600' : 'text-secondary-500'
                    }`}>
                      {step.title}
                    </p>
                  </div>
                  {index < STEPS.length - 1 && (
                    <div className={`
                      w-12 h-0.5 mx-4 transition-colors
                      ${isCompleted ? 'bg-success-600' : 'bg-secondary-300'}
                    `} />
                  )}
                </div>
              )
            })}
          </div>
        </div>

        {/* Content */}
        <div className="p-6 overflow-y-auto max-h-96">
          {/* Step 1: Información Personal */}
          {currentStep === 1 && (
            <div className="space-y-6">
              <div className="text-center mb-6">
                <UserIcon className="w-12 h-12 text-primary-600 mx-auto mb-2" />
                <h3 className="text-lg font-medium text-secondary-900 dark:text-white">
                  Información Personal
                </h3>
                <p className="text-sm text-secondary-600 dark:text-secondary-400">
                  Ingresa los datos básicos del lead
                </p>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                    Nombre *
                  </label>
                  <input
                    type="text"
                    value={formData.first_name}
                    onChange={(e) => setFormData({ ...formData, first_name: e.target.value })}
                    className="input"
                    placeholder="ej: Juan"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                    Apellido *
                  </label>
                  <input
                    type="text"
                    value={formData.last_name}
                    onChange={(e) => setFormData({ ...formData, last_name: e.target.value })}
                    className="input"
                    placeholder="ej: Pérez"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                    Email *
                  </label>
                  <input
                    type="email"
                    value={formData.email}
                    onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                    className="input"
                    placeholder="ej: juan.perez@email.com"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                    Teléfono *
                  </label>
                  <input
                    type="tel"
                    value={formData.phone}
                    onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                    className="input"
                    placeholder="ej: +1234567890"
                  />
                </div>
              </div>
            </div>
          )}

          {/* Step 2: Información Adicional */}
          {currentStep === 2 && (
            <div className="space-y-6">
              <div className="text-center mb-6">
                <BuildingOfficeIcon className="w-12 h-12 text-primary-600 mx-auto mb-2" />
                <h3 className="text-lg font-medium text-secondary-900 dark:text-white">
                  Información Adicional
                </h3>
                <p className="text-sm text-secondary-600 dark:text-secondary-400">
                  Datos adicionales del lead (opcional)
                </p>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                    Empresa
                  </label>
                  <input
                    type="text"
                    value={formData.company}
                    onChange={(e) => setFormData({ ...formData, company: e.target.value })}
                    className="input"
                    placeholder="ej: Empresa ABC"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                    Posición
                  </label>
                  <input
                    type="text"
                    value={formData.position}
                    onChange={(e) => setFormData({ ...formData, position: e.target.value })}
                    className="input"
                    placeholder="ej: CEO, Manager"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                    País
                  </label>
                  <input
                    type="text"
                    value={formData.country}
                    onChange={(e) => setFormData({ ...formData, country: e.target.value })}
                    className="input"
                    placeholder="ej: España"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                    Ciudad
                  </label>
                  <input
                    type="text"
                    value={formData.city}
                    onChange={(e) => setFormData({ ...formData, city: e.target.value })}
                    className="input"
                    placeholder="ej: Madrid"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                    Presupuesto Estimado
                  </label>
                  <input
                    type="number"
                    value={formData.budget || ''}
                    onChange={(e) => setFormData({ ...formData, budget: parseFloat(e.target.value) || undefined })}
                    className="input"
                    placeholder="ej: 10000"
                    min="0"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                    Fuente
                  </label>
                  <select
                    value={formData.source}
                    onChange={(e) => setFormData({ ...formData, source: e.target.value })}
                    className="input"
                  >
                    {SOURCES.map(source => (
                      <option key={source} value={source}>{source}</option>
                    ))}
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                  Notas
                </label>
                <textarea
                  value={formData.notes}
                  onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
                  className="input min-h-[80px]"
                  placeholder="Información adicional sobre el lead..."
                />
              </div>
            </div>
          )}

          {/* Step 3: Asignación y Seguimiento */}
          {currentStep === 3 && (
            <div className="space-y-6">
              <div className="text-center mb-6">
                <CurrencyDollarIcon className="w-12 h-12 text-primary-600 mx-auto mb-2" />
                <h3 className="text-lg font-medium text-secondary-900 dark:text-white">
                  Asignación y Seguimiento
                </h3>
                <p className="text-sm text-secondary-600 dark:text-secondary-400">
                  Asigna el lead a una mesa y usuario
                </p>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                    Mesa
                  </label>
                  {desksLoading ? (
                    <div className="input flex items-center justify-center">
                      <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-primary-600"></div>
                      <span className="ml-2 text-sm text-secondary-500">Cargando mesas...</span>
                    </div>
                  ) : (
                    <select
                      value={formData.desk_id || ''}
                      onChange={(e) => setFormData({ ...formData, desk_id: e.target.value ? parseInt(e.target.value) : undefined })}
                      className="input"
                    >
                      <option value="">Sin mesa asignada</option>
                      {desksData?.data?.map((desk: any) => (
                        <option key={desk.id} value={desk.id}>
                          {desk.name}
                        </option>
                      ))}
                    </select>
                  )}
                </div>

                <div>
                  <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                    Asignar a Usuario
                  </label>
                  {usersLoading ? (
                    <div className="input flex items-center justify-center">
                      <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-primary-600"></div>
                      <span className="ml-2 text-sm text-secondary-500">Cargando usuarios...</span>
                    </div>
                  ) : (
                    <select
                      value={formData.assigned_to || ''}
                      onChange={(e) => setFormData({ ...formData, assigned_to: e.target.value ? parseInt(e.target.value) : undefined })}
                      className="input"
                    >
                      <option value="">Sin asignar</option>
                      {usersData?.data?.map((user: any) => (
                        <option key={user.id} value={user.id}>
                          {user.first_name} {user.last_name} ({user.username})
                        </option>
                      ))}
                    </select>
                  )}
                </div>

                <div>
                  <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                    Estado
                  </label>
                  <select
                    value={formData.status}
                    onChange={(e) => setFormData({ ...formData, status: e.target.value })}
                    className="input"
                  >
                    <option value="new">Nuevo</option>
                    <option value="contacted">Contactado</option>
                    <option value="qualified">Calificado</option>
                    <option value="proposal">Propuesta</option>
                    <option value="negotiation">Negociación</option>
                  </select>
                </div>

                <div>
                  <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                    Nivel de Interés
                  </label>
                  <select
                    value={formData.interest_level}
                    onChange={(e) => setFormData({ ...formData, interest_level: e.target.value })}
                    className="input"
                  >
                    <option value="low">Bajo</option>
                    <option value="medium">Medio</option>
                    <option value="high">Alto</option>
                    <option value="very_high">Muy Alto</option>
                  </select>
                </div>
              </div>
            </div>
          )}

          {/* Step 4: Confirmación */}
          {currentStep === 4 && (
            <div className="space-y-6">
              <div className="text-center mb-6">
                <CheckIcon className="w-12 h-12 text-success-600 mx-auto mb-2" />
                <h3 className="text-lg font-medium text-secondary-900 dark:text-white">
                  Confirmación
                </h3>
                <p className="text-sm text-secondary-600 dark:text-secondary-400">
                  Revisa la información antes de crear el lead
                </p>
              </div>

              <div className="bg-secondary-50 dark:bg-secondary-900 rounded-lg p-6 space-y-4">
                <div>
                  <h4 className="font-medium text-secondary-900 dark:text-white mb-3">
                    Información Personal
                  </h4>
                  <div className="grid grid-cols-2 gap-4 text-sm">
                    <div>
                      <span className="text-secondary-600 dark:text-secondary-400">Nombre:</span>
                      <span className="ml-2 font-medium">{formData.first_name} {formData.last_name}</span>
                    </div>
                    <div>
                      <span className="text-secondary-600 dark:text-secondary-400">Email:</span>
                      <span className="ml-2 font-medium">{formData.email}</span>
                    </div>
                    <div>
                      <span className="text-secondary-600 dark:text-secondary-400">Teléfono:</span>
                      <span className="ml-2 font-medium">{formData.phone}</span>
                    </div>
                    <div>
                      <span className="text-secondary-600 dark:text-secondary-400">Empresa:</span>
                      <span className="ml-2 font-medium">{formData.company || 'No especificada'}</span>
                    </div>
                  </div>
                </div>

                <div>
                  <h4 className="font-medium text-secondary-900 dark:text-white mb-3">
                    Asignaciones
                  </h4>
                  <div className="space-y-2 text-sm">
                    <div>
                      <span className="text-secondary-600 dark:text-secondary-400">Mesa:</span>
                      <span className="ml-2 font-medium">
                        {getSelectedDesk()?.name || 'No asignada'}
                      </span>
                    </div>
                    <div>
                      <span className="text-secondary-600 dark:text-secondary-400">Usuario:</span>
                      <span className="ml-2 font-medium">
                        {getSelectedUser() ? `${getSelectedUser()?.first_name} ${getSelectedUser()?.last_name}` : 'No asignado'}
                      </span>
                    </div>
                    <div>
                      <span className="text-secondary-600 dark:text-secondary-400">Estado:</span>
                      <span className="ml-2 font-medium capitalize">{formData.status}</span>
                    </div>
                    <div>
                      <span className="text-secondary-600 dark:text-secondary-400">Fuente:</span>
                      <span className="ml-2 font-medium">{formData.source}</span>
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
            onClick={handlePrevious}
            disabled={currentStep === 1}
            className="btn-secondary disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <ArrowLeftIcon className="w-4 h-4 mr-2" />
            Anterior
          </button>

          <div className="flex space-x-3">
            <button
              onClick={handleClose}
              className="btn-secondary"
            >
              Cancelar
            </button>
            
            {currentStep < STEPS.length ? (
              <button
                onClick={handleNext}
                className="btn-primary"
              >
                Siguiente
                <ArrowRightIcon className="w-4 h-4 ml-2" />
              </button>
            ) : (
              <button
                onClick={handleSubmit}
                disabled={createLeadMutation.isPending}
                className="btn-primary disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {createLeadMutation.isPending ? 'Creando...' : 'Crear Lead'}
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}
