import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { 
  UserIcon,
  EnvelopeIcon,
  PhoneIcon,
  BuildingOfficeIcon,
  CurrencyDollarIcon,
  DocumentTextIcon,
  CheckCircleIcon,
  ArrowRightIcon,
  ArrowLeftIcon
} from '@heroicons/react/24/outline'
import { leadsApi, usersApi, desksApi } from '../../services/api'
import { User, Desk } from '../../types'
import LoadingSpinner from '../../components/ui/LoadingSpinner'
import { cn } from '../../utils/cn'
import toast from 'react-hot-toast'

// Types
interface LeadFormData {
  first_name: string
  last_name: string
  email: string
  phone?: string
  country?: string
  city?: string
  company?: string
  position?: string
  website?: string
  source?: string
  budget?: number
  interest_level?: string
  assigned_user_id?: number
  desk_id?: number
  notes?: string
}

type WizardStep = 'personal' | 'professional' | 'commercial' | 'assignment' | 'notes' | 'review'

interface ValidationErrors {
  [key: string]: string
}

// Constants
const COUNTRIES = [
  'España', 'México', 'Argentina', 'Colombia', 'Chile', 'Perú', 'Venezuela', 'Ecuador',
  'Estados Unidos', 'Reino Unido', 'Francia', 'Alemania', 'Italia', 'Brasil', 'Canadá'
]

const SOURCES = [
  'Website', 'Facebook', 'Google Ads', 'LinkedIn', 'Instagram', 'Email Campaign', 
  'Cold Call', 'Referral', 'Trade Show', 'Webinar'
]

const INTEREST_LEVELS = [
  { value: 'low', label: 'Bajo' },
  { value: 'medium', label: 'Medio' },
  { value: 'high', label: 'Alto' },
  { value: 'very_high', label: 'Muy Alto' }
]

const STEPS = [
  { key: 'personal' as const, title: 'Información Personal', icon: UserIcon },
  { key: 'professional' as const, title: 'Información Profesional', icon: BuildingOfficeIcon },
  { key: 'commercial' as const, title: 'Información Comercial', icon: CurrencyDollarIcon },
  { key: 'assignment' as const, title: 'Asignación', icon: UserIcon },
  { key: 'notes' as const, title: 'Notas', icon: DocumentTextIcon },
  { key: 'review' as const, title: 'Revisión', icon: CheckCircleIcon }
]

export default function LeadWizardPage() {
  const [currentStep, setCurrentStep] = useState<WizardStep>('personal')
  const [formData, setFormData] = useState<LeadFormData>({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    country: '',
    city: '',
    company: '',
    position: '',
    website: '',
    source: '',
    budget: undefined,
    interest_level: '',
    assigned_user_id: undefined,
    desk_id: undefined,
    notes: ''
  })
  const [errors, setErrors] = useState<ValidationErrors>({})

  const navigate = useNavigate()
  const queryClient = useQueryClient()

  // Queries
  const { data: usersData } = useQuery({
    queryKey: ['users-for-assignment'],
    queryFn: () => usersApi.getUsers({ limit: 100 }),
    staleTime: 5 * 60 * 1000
  })

  const { data: desksData } = useQuery({
    queryKey: ['desks-for-assignment'],
    queryFn: () => desksApi.getDesks({ limit: 100 }),
    staleTime: 5 * 60 * 1000
  })

  const users = usersData?.data || []
  const desks = desksData?.data || []

  // Mutation
  const createLeadMutation = useMutation({
    mutationFn: (data: LeadFormData) => leadsApi.createLead(data),
    onSuccess: (response) => {
      queryClient.invalidateQueries({ queryKey: ['leads'] })
      toast.success('Lead creado exitosamente')
      navigate(`/leads/${response.data.id}`)
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || 'Error al crear el lead')
    }
  })

  // Handlers
  const updateFormData = (field: keyof LeadFormData, value: string | number | undefined) => {
    setFormData(prev => ({ ...prev, [field]: value }))
    if (errors[field]) {
      setErrors(prev => ({ ...prev, [field]: '' }))
    }
  }

  const validateStep = (step: WizardStep): boolean => {
    const newErrors: ValidationErrors = {}

    if (step === 'personal') {
      if (!formData.first_name.trim()) newErrors.first_name = 'Nombre es requerido'
      if (!formData.last_name.trim()) newErrors.last_name = 'Apellido es requerido'
      if (!formData.email.trim()) newErrors.email = 'Email es requerido'
      else if (!/\S+@\S+\.\S+/.test(formData.email)) newErrors.email = 'Email inválido'
    }

    setErrors(newErrors)
    return Object.keys(newErrors).length === 0
  }

  const handleNext = () => {
    if (!validateStep(currentStep)) return

    const stepIndex = STEPS.findIndex(s => s.key === currentStep)
    if (stepIndex < STEPS.length - 1) {
      setCurrentStep(STEPS[stepIndex + 1].key)
    }
  }

  const handlePrevious = () => {
    const stepIndex = STEPS.findIndex(s => s.key === currentStep)
    if (stepIndex > 0) {
      setCurrentStep(STEPS[stepIndex - 1].key)
    }
  }

  const handleSubmit = () => {
    if (validateStep('personal')) {
      createLeadMutation.mutate(formData)
    }
  }

  const currentStepIndex = STEPS.findIndex(s => s.key === currentStep)

  return (
    <div className="min-h-screen bg-gray-50 py-8">
      <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        {/* Header */}
        <div className="text-center mb-8">
          <h1 className="text-3xl font-bold text-gray-900">Crear Nuevo Lead</h1>
          <p className="mt-2 text-gray-600">Complete la información paso a paso</p>
        </div>

        {/* Progress Steps */}
        <div className="mb-8">
          <div className="flex items-center justify-between">
            {STEPS.map((step, index) => {
              const Icon = step.icon
              const isActive = step.key === currentStep
              const isCompleted = index < currentStepIndex
              
              return (
                <div key={step.key} className="flex items-center">
                  <div className={cn(
                    "flex items-center justify-center w-10 h-10 rounded-full border-2",
                    isActive ? "border-primary-500 bg-primary-500 text-white" :
                    isCompleted ? "border-green-500 bg-green-500 text-white" :
                    "border-gray-300 bg-white text-gray-400"
                  )}>
                    <Icon className="w-5 h-5" />
                  </div>
                  <div className="ml-3 hidden sm:block">
                    <p className={cn(
                      "text-sm font-medium",
                      isActive ? "text-primary-600" : "text-gray-500"
                    )}>
                      {step.title}
                    </p>
                  </div>
                  {index < STEPS.length - 1 && (
                    <div className={cn(
                      "flex-1 h-0.5 mx-4",
                      isCompleted ? "bg-green-500" : "bg-gray-300"
                    )} />
                  )}
                </div>
              )
            })}
          </div>
        </div>

        {/* Form Content */}
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
          {currentStep === 'personal' && (
            <PersonalStep 
              formData={formData} 
              updateFormData={updateFormData} 
              errors={errors} 
            />
          )}
          {currentStep === 'professional' && (
            <ProfessionalStep 
              formData={formData} 
              updateFormData={updateFormData} 
              errors={errors} 
            />
          )}
          {currentStep === 'commercial' && (
            <CommercialStep 
              formData={formData} 
              updateFormData={updateFormData} 
              errors={errors} 
            />
          )}
          {currentStep === 'assignment' && (
            <AssignmentStep 
              formData={formData} 
              updateFormData={updateFormData} 
              errors={errors}
              users={users}
              desks={desks}
            />
          )}
          {currentStep === 'notes' && (
            <NotesStep 
              formData={formData} 
              updateFormData={updateFormData} 
              errors={errors} 
            />
          )}
          {currentStep === 'review' && (
            <ReviewStep 
              formData={formData} 
              users={users}
              desks={desks}
            />
          )}

          {/* Navigation */}
          <div className="flex justify-between mt-8 pt-6 border-t border-gray-200">
            <button
              type="button"
              onClick={handlePrevious}
              disabled={currentStepIndex === 0}
              className={cn(
                "flex items-center px-4 py-2 text-sm font-medium rounded-md",
                currentStepIndex === 0
                  ? "text-gray-400 cursor-not-allowed"
                  : "text-gray-700 bg-white border border-gray-300 hover:bg-gray-50"
              )}
            >
              <ArrowLeftIcon className="w-4 h-4 mr-2" />
              Anterior
            </button>

            {currentStep === 'review' ? (
              <button
                type="button"
                onClick={handleSubmit}
                disabled={createLeadMutation.isPending}
                className="flex items-center px-6 py-2 bg-primary-600 text-white text-sm font-medium rounded-md hover:bg-primary-700 disabled:opacity-50"
              >
                {createLeadMutation.isPending ? (
                  <LoadingSpinner size="sm" className="mr-2" />
                ) : (
                  <CheckCircleIcon className="w-4 h-4 mr-2" />
                )}
                Crear Lead
              </button>
            ) : (
              <button
                type="button"
                onClick={handleNext}
                className="flex items-center px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-md hover:bg-primary-700"
              >
                Siguiente
                <ArrowRightIcon className="w-4 h-4 ml-2" />
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}

// Step Components
interface StepProps {
  formData: LeadFormData
  updateFormData: (field: keyof LeadFormData, value: string | number | undefined) => void
  errors: ValidationErrors
}

function PersonalStep({ formData, updateFormData, errors }: StepProps) {
  return (
    <div className="space-y-6">
      <h2 className="text-xl font-semibold text-gray-900 mb-4">Información Personal</h2>
      
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Nombre *
          </label>
          <div className="relative">
            <UserIcon className="absolute left-3 top-3 h-5 w-5 text-gray-400" />
            <input
              type="text"
              value={formData.first_name}
              onChange={(e) => updateFormData('first_name', e.target.value)}
              className={cn(
                "pl-10 w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500",
                errors.first_name ? "border-red-300" : "border-gray-300"
              )}
              placeholder="Ingrese el nombre"
            />
          </div>
          {errors.first_name && (
            <p className="mt-1 text-sm text-red-600">{errors.first_name}</p>
          )}
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Apellido *
          </label>
          <input
            type="text"
            value={formData.last_name}
            onChange={(e) => updateFormData('last_name', e.target.value)}
            className={cn(
              "w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500",
              errors.last_name ? "border-red-300" : "border-gray-300"
            )}
            placeholder="Ingrese el apellido"
          />
          {errors.last_name && (
            <p className="mt-1 text-sm text-red-600">{errors.last_name}</p>
          )}
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Email *
          </label>
          <div className="relative">
            <EnvelopeIcon className="absolute left-3 top-3 h-5 w-5 text-gray-400" />
            <input
              type="email"
              value={formData.email}
              onChange={(e) => updateFormData('email', e.target.value)}
              className={cn(
                "pl-10 w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500",
                errors.email ? "border-red-300" : "border-gray-300"
              )}
              placeholder="ejemplo@email.com"
            />
          </div>
          {errors.email && (
            <p className="mt-1 text-sm text-red-600">{errors.email}</p>
          )}
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Teléfono
          </label>
          <div className="relative">
            <PhoneIcon className="absolute left-3 top-3 h-5 w-5 text-gray-400" />
            <input
              type="tel"
              value={formData.phone || ''}
              onChange={(e) => updateFormData('phone', e.target.value)}
              className="pl-10 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500"
              placeholder="+34 123 456 789"
            />
          </div>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            País
          </label>
          <select
            value={formData.country || ''}
            onChange={(e) => updateFormData('country', e.target.value)}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500"
          >
            <option value="">Seleccionar país</option>
            {COUNTRIES.map(country => (
              <option key={country} value={country}>{country}</option>
            ))}
          </select>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Ciudad
          </label>
          <input
            type="text"
            value={formData.city || ''}
            onChange={(e) => updateFormData('city', e.target.value)}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500"
            placeholder="Ingrese la ciudad"
          />
        </div>
      </div>
    </div>
  )
}

function ProfessionalStep({ formData, updateFormData }: StepProps) {
  return (
    <div className="space-y-6">
      <h2 className="text-xl font-semibold text-gray-900 mb-4">Información Profesional</h2>
      
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Empresa
          </label>
          <div className="relative">
            <BuildingOfficeIcon className="absolute left-3 top-3 h-5 w-5 text-gray-400" />
            <input
              type="text"
              value={formData.company || ''}
              onChange={(e) => updateFormData('company', e.target.value)}
              className="pl-10 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500"
              placeholder="Nombre de la empresa"
            />
          </div>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Cargo
          </label>
          <input
            type="text"
            value={formData.position || ''}
            onChange={(e) => updateFormData('position', e.target.value)}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500"
            placeholder="Cargo o posición"
          />
        </div>

        <div className="md:col-span-2">
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Sitio Web
          </label>
          <input
            type="url"
            value={formData.website || ''}
            onChange={(e) => updateFormData('website', e.target.value)}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500"
            placeholder="https://ejemplo.com"
          />
        </div>
      </div>
    </div>
  )
}

function CommercialStep({ formData, updateFormData }: StepProps) {
  return (
    <div className="space-y-6">
      <h2 className="text-xl font-semibold text-gray-900 mb-4">Información Comercial</h2>
      
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Fuente
          </label>
          <select
            value={formData.source || ''}
            onChange={(e) => updateFormData('source', e.target.value)}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500"
          >
            <option value="">Seleccionar fuente</option>
            {SOURCES.map(source => (
              <option key={source} value={source}>{source}</option>
            ))}
          </select>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Nivel de Interés
          </label>
          <select
            value={formData.interest_level || ''}
            onChange={(e) => updateFormData('interest_level', e.target.value)}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500"
          >
            <option value="">Seleccionar nivel</option>
            {INTEREST_LEVELS.map(level => (
              <option key={level.value} value={level.value}>{level.label}</option>
            ))}
          </select>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Presupuesto
          </label>
          <div className="relative">
            <CurrencyDollarIcon className="absolute left-3 top-3 h-5 w-5 text-gray-400" />
            <input
              type="number"
              value={formData.budget || ''}
              onChange={(e) => updateFormData('budget', e.target.value ? Number(e.target.value) : undefined)}
              className="pl-10 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500"
              placeholder="0"
              min="0"
            />
          </div>
        </div>
      </div>
    </div>
  )
}

interface AssignmentStepProps extends StepProps {
  users: User[]
  desks: Desk[]
}

function AssignmentStep({ formData, updateFormData, users, desks }: AssignmentStepProps) {
  return (
    <div className="space-y-6">
      <h2 className="text-xl font-semibold text-gray-900 mb-4">Asignación</h2>
      
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Usuario Asignado
          </label>
          <select
            value={formData.assigned_user_id || ''}
            onChange={(e) => updateFormData('assigned_user_id', e.target.value ? Number(e.target.value) : undefined)}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500"
          >
            <option value="">Sin asignar</option>
            {users.map(user => (
              <option key={user.id} value={user.id}>
                {user.first_name} {user.last_name}
              </option>
            ))}
          </select>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Mesa
          </label>
          <select
            value={formData.desk_id || ''}
            onChange={(e) => updateFormData('desk_id', e.target.value ? Number(e.target.value) : undefined)}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500"
          >
            <option value="">Sin asignar</option>
            {desks.map(desk => (
              <option key={desk.id} value={desk.id}>
                {desk.name}
              </option>
            ))}
          </select>
        </div>
      </div>
    </div>
  )
}

function NotesStep({ formData, updateFormData }: StepProps) {
  return (
    <div className="space-y-6">
      <h2 className="text-xl font-semibold text-gray-900 mb-4">Notas Adicionales</h2>
      
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Notas
        </label>
        <textarea
          value={formData.notes || ''}
          onChange={(e) => updateFormData('notes', e.target.value)}
          rows={6}
          className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500"
          placeholder="Información adicional sobre el lead..."
        />
      </div>
    </div>
  )
}

interface ReviewStepProps {
  formData: LeadFormData
  users: User[]
  desks: Desk[]
}

function ReviewStep({ formData, users, desks }: ReviewStepProps) {
  const assignedUser = users.find(u => u.id === formData.assigned_user_id)
  const assignedDesk = desks.find(d => d.id === formData.desk_id)
  const interestLevel = INTEREST_LEVELS.find(l => l.value === formData.interest_level)

  return (
    <div className="space-y-6">
      <h2 className="text-xl font-semibold text-gray-900 mb-4">Revisión Final</h2>
      
      <div className="bg-gray-50 rounded-lg p-6 space-y-4">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <h3 className="font-medium text-gray-900">Información Personal</h3>
            <p className="text-sm text-gray-600">
              {formData.first_name} {formData.last_name}
            </p>
            <p className="text-sm text-gray-600">{formData.email}</p>
            {formData.phone && <p className="text-sm text-gray-600">{formData.phone}</p>}
            {formData.country && <p className="text-sm text-gray-600">{formData.country}</p>}
          </div>

          <div>
            <h3 className="font-medium text-gray-900">Información Profesional</h3>
            {formData.company && <p className="text-sm text-gray-600">{formData.company}</p>}
            {formData.position && <p className="text-sm text-gray-600">{formData.position}</p>}
            {formData.website && <p className="text-sm text-gray-600">{formData.website}</p>}
          </div>

          <div>
            <h3 className="font-medium text-gray-900">Información Comercial</h3>
            {formData.source && <p className="text-sm text-gray-600">Fuente: {formData.source}</p>}
            {interestLevel && <p className="text-sm text-gray-600">Interés: {interestLevel.label}</p>}
            {formData.budget && <p className="text-sm text-gray-600">Presupuesto: ${formData.budget}</p>}
          </div>

          <div>
            <h3 className="font-medium text-gray-900">Asignación</h3>
            {assignedUser && (
              <p className="text-sm text-gray-600">
                Usuario: {assignedUser.first_name} {assignedUser.last_name}
              </p>
            )}
            {assignedDesk && <p className="text-sm text-gray-600">Mesa: {assignedDesk.name}</p>}
          </div>
        </div>

        {formData.notes && (
          <div>
            <h3 className="font-medium text-gray-900">Notas</h3>
            <p className="text-sm text-gray-600">{formData.notes}</p>
          </div>
        )}
      </div>
    </div>
  )
}
