import { useState } from 'react'
import { useQuery, useMutation } from '@tanstack/react-query'
import { 
  XMarkIcon,
  UserIcon,
  ShieldCheckIcon,
  BuildingOfficeIcon,
  CheckIcon,
  ArrowRightIcon,
  ArrowLeftIcon
} from '@heroicons/react/24/outline'
import { usersApi, rolesApi, desksApi } from '../../services/api'
import toast from 'react-hot-toast'

interface UserWizardProps {
  isOpen: boolean
  onClose: () => void
  onSuccess: () => void
}

interface UserFormData {
  // Información básica
  username: string
  email: string
  first_name: string
  last_name: string
  password: string
  confirm_password: string
  phone?: string
  
  // Asignaciones
  role_id?: number
  desk_id?: number
  
  // Estado
  status: 'active' | 'inactive'
}

const STEPS = [
  { id: 1, title: 'Información Personal', icon: UserIcon },
  { id: 2, title: 'Asignación de Rol', icon: ShieldCheckIcon },
  { id: 3, title: 'Mesa de Trabajo', icon: BuildingOfficeIcon },
  { id: 4, title: 'Confirmación', icon: CheckIcon }
]

export default function UserWizard({ isOpen, onClose, onSuccess }: UserWizardProps) {
  const [currentStep, setCurrentStep] = useState(1)
  const [formData, setFormData] = useState<UserFormData>({
    username: '',
    email: '',
    first_name: '',
    last_name: '',
    password: '',
    confirm_password: '',
    phone: '',
    role_id: undefined,
    desk_id: undefined,
    status: 'active'
  })

  // Obtener roles disponibles
  const { data: rolesData, isLoading: rolesLoading, error: rolesError } = useQuery({
    queryKey: ['roles-for-user'],
    queryFn: () => rolesApi.getRoles(),
    enabled: isOpen,
    staleTime: 5 * 60 * 1000
  })

  // Obtener mesas disponibles
  const { data: desksData, isLoading: desksLoading } = useQuery({
    queryKey: ['desks-for-user'],
    queryFn: () => desksApi.getDesks(),
    enabled: isOpen,
    staleTime: 5 * 60 * 1000
  })

  // Mutación para crear usuario
  const createUserMutation = useMutation({
    mutationFn: (userData: any) => usersApi.createUser(userData),
    onSuccess: () => {
      toast.success('Usuario creado exitosamente')
      onSuccess()
      handleClose()
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || 'Error al crear el usuario')
    }
  })

  const handleClose = () => {
    setCurrentStep(1)
    setFormData({
      username: '',
      email: '',
      first_name: '',
      last_name: '',
      password: '',
      confirm_password: '',
      phone: '',
      role_id: undefined,
      desk_id: undefined,
      status: 'active'
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
        if (!formData.username.trim()) {
          toast.error('El nombre de usuario es requerido')
          return false
        }
        if (!formData.email.trim()) {
          toast.error('El email es requerido')
          return false
        }
        if (!formData.first_name.trim()) {
          toast.error('El nombre es requerido')
          return false
        }
        if (!formData.last_name.trim()) {
          toast.error('El apellido es requerido')
          return false
        }
        if (!formData.password.trim()) {
          toast.error('La contraseña es requerida')
          return false
        }
        if (formData.password !== formData.confirm_password) {
          toast.error('Las contraseñas no coinciden')
          return false
        }
        if (formData.password.length < 6) {
          toast.error('La contraseña debe tener al menos 6 caracteres')
          return false
        }
        return true
      case 2:
        if (!formData.role_id) {
          toast.error('Debe seleccionar un rol')
          return false
        }
        return true
      case 3:
        // Mesa es opcional
        return true
      default:
        return true
    }
  }

  const handleSubmit = () => {
    if (validateCurrentStep()) {
      const userData = {
        username: formData.username,
        email: formData.email,
        first_name: formData.first_name,
        last_name: formData.last_name,
        password: formData.password,
        phone: formData.phone || null,
        role_id: formData.role_id,
        desk_id: formData.desk_id || null,
        status: formData.status
      }
      
      createUserMutation.mutate(userData)
    }
  }

  const getSelectedRole = () => {
    return rolesData?.data?.find((role: any) => role.id === formData.role_id)
  }

  const getSelectedDesk = () => {
    return desksData?.data?.find((desk: any) => desk.id === formData.desk_id)
  }

  if (!isOpen) return null

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div className="bg-white dark:bg-secondary-800 rounded-lg w-full max-w-4xl mx-4 max-h-[90vh] overflow-hidden">
        {/* Header */}
        <div className="flex items-center justify-between p-6 border-b border-secondary-200 dark:border-secondary-700">
          <div>
            <h2 className="text-xl font-semibold text-secondary-900 dark:text-white">
              Asistente para Crear Usuario
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
                  Ingresa los datos básicos del nuevo usuario
                </p>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                    Nombre de Usuario *
                  </label>
                  <input
                    type="text"
                    value={formData.username}
                    onChange={(e) => setFormData({ ...formData, username: e.target.value })}
                    className="input"
                    placeholder="ej: jperez"
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
                    placeholder="ej: juan.perez@empresa.com"
                  />
                </div>

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
                    Teléfono
                  </label>
                  <input
                    type="tel"
                    value={formData.phone}
                    onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                    className="input"
                    placeholder="ej: +1234567890"
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
                  <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                    Contraseña *
                  </label>
                  <input
                    type="password"
                    value={formData.password}
                    onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                    className="input"
                    placeholder="Mínimo 6 caracteres"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                    Confirmar Contraseña *
                  </label>
                  <input
                    type="password"
                    value={formData.confirm_password}
                    onChange={(e) => setFormData({ ...formData, confirm_password: e.target.value })}
                    className="input"
                    placeholder="Repite la contraseña"
                  />
                </div>
              </div>
            </div>
          )}

          {/* Step 2: Asignación de Rol */}
          {currentStep === 2 && (
            <div className="space-y-6">
              <div className="text-center mb-6">
                <ShieldCheckIcon className="w-12 h-12 text-primary-600 mx-auto mb-2" />
                <h3 className="text-lg font-medium text-secondary-900 dark:text-white">
                  Asignación de Rol
                </h3>
                <p className="text-sm text-secondary-600 dark:text-secondary-400">
                  Selecciona el rol que tendrá el usuario en el sistema
                </p>
              </div>

              {rolesLoading ? (
                <div className="text-center py-8">
                  <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600 mx-auto"></div>
                  <p className="text-sm text-secondary-500 mt-2">Cargando roles...</p>
                </div>
              ) : rolesError ? (
                <div className="text-center py-8">
                  <div className="text-red-500 mb-4">
                    <svg className="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 19.5c-.77.833.192 2.5 1.732 2.5z" />
                    </svg>
                  </div>
                  <p className="text-sm text-red-600 dark:text-red-400 mb-4">
                    Error al cargar los roles. Verifica tu conexión e inténtalo de nuevo.
                  </p>
                  <button
                    onClick={() => window.location.reload()}
                    className="btn-secondary text-sm"
                  >
                    Recargar página
                  </button>
                </div>
              ) : !rolesData?.data || rolesData.data.length === 0 ? (
                <div className="text-center py-8">
                  <p className="text-sm text-secondary-500">No hay roles disponibles</p>
                </div>
              ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  {rolesData?.data?.map((role: any) => (
                    <div
                      key={role.id}
                      onClick={() => setFormData({ ...formData, role_id: role.id })}
                      className={`
                        p-4 border-2 rounded-lg cursor-pointer transition-all hover:shadow-md
                        ${formData.role_id === role.id
                          ? 'border-primary-600 bg-primary-50 dark:bg-primary-900/20'
                          : 'border-secondary-200 dark:border-secondary-700 hover:border-primary-300'
                        }
                      `}
                    >
                      <div className="flex items-center justify-between">
                        <div>
                          <h4 className="font-medium text-secondary-900 dark:text-white">
                            {role.display_name}
                          </h4>
                          <p className="text-sm text-secondary-600 dark:text-secondary-400 mt-1">
                            {role.description || 'Sin descripción'}
                          </p>
                          <p className="text-xs text-secondary-500 mt-2">
                            {role.permissions?.length || 0} permisos asignados
                          </p>
                        </div>
                        {formData.role_id === role.id && (
                          <CheckIcon className="w-6 h-6 text-primary-600" />
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          )}

          {/* Step 3: Mesa de Trabajo */}
          {currentStep === 3 && (
            <div className="space-y-6">
              <div className="text-center mb-6">
                <BuildingOfficeIcon className="w-12 h-12 text-primary-600 mx-auto mb-2" />
                <h3 className="text-lg font-medium text-secondary-900 dark:text-white">
                  Mesa de Trabajo
                </h3>
                <p className="text-sm text-secondary-600 dark:text-secondary-400">
                  Asigna el usuario a una mesa de trabajo (opcional)
                </p>
              </div>

              <div className="mb-4">
                <label className="flex items-center space-x-2">
                  <input
                    type="checkbox"
                    checked={!formData.desk_id}
                    onChange={(e) => {
                      if (e.target.checked) {
                        setFormData({ ...formData, desk_id: undefined })
                      }
                    }}
                    className="rounded border-secondary-300 text-primary-600 focus:ring-primary-500"
                  />
                  <span className="text-sm text-secondary-700 dark:text-secondary-300">
                    No asignar a ninguna mesa por ahora
                  </span>
                </label>
              </div>

              {desksLoading ? (
                <div className="text-center py-8">
                  <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600 mx-auto"></div>
                  <p className="text-sm text-secondary-500 mt-2">Cargando mesas...</p>
                </div>
              ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  {desksData?.data?.map((desk: any) => (
                    <div
                      key={desk.id}
                      onClick={() => setFormData({ ...formData, desk_id: desk.id })}
                      className={`
                        p-4 border-2 rounded-lg cursor-pointer transition-all hover:shadow-md
                        ${formData.desk_id === desk.id
                          ? 'border-primary-600 bg-primary-50 dark:bg-primary-900/20'
                          : 'border-secondary-200 dark:border-secondary-700 hover:border-primary-300'
                        }
                      `}
                    >
                      <div className="flex items-center justify-between">
                        <div>
                          <h4 className="font-medium text-secondary-900 dark:text-white">
                            {desk.name}
                          </h4>
                          <p className="text-sm text-secondary-600 dark:text-secondary-400 mt-1">
                            {desk.description || 'Sin descripción'}
                          </p>
                          <p className="text-xs text-secondary-500 mt-2">
                            {desk.users_count || 0} usuarios asignados
                          </p>
                        </div>
                        {formData.desk_id === desk.id && (
                          <CheckIcon className="w-6 h-6 text-primary-600" />
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              )}
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
                  Revisa la información antes de crear el usuario
                </p>
              </div>

              <div className="bg-secondary-50 dark:bg-secondary-900 rounded-lg p-6 space-y-4">
                <div>
                  <h4 className="font-medium text-secondary-900 dark:text-white mb-3">
                    Información Personal
                  </h4>
                  <div className="grid grid-cols-2 gap-4 text-sm">
                    <div>
                      <span className="text-secondary-600 dark:text-secondary-400">Usuario:</span>
                      <span className="ml-2 font-medium">{formData.username}</span>
                    </div>
                    <div>
                      <span className="text-secondary-600 dark:text-secondary-400">Email:</span>
                      <span className="ml-2 font-medium">{formData.email}</span>
                    </div>
                    <div>
                      <span className="text-secondary-600 dark:text-secondary-400">Nombre:</span>
                      <span className="ml-2 font-medium">{formData.first_name} {formData.last_name}</span>
                    </div>
                    <div>
                      <span className="text-secondary-600 dark:text-secondary-400">Estado:</span>
                      <span className={`ml-2 px-2 py-1 rounded text-xs ${
                        formData.status === 'active' 
                          ? 'bg-success-100 text-success-800' 
                          : 'bg-danger-100 text-danger-800'
                      }`}>
                        {formData.status === 'active' ? 'Activo' : 'Inactivo'}
                      </span>
                    </div>
                  </div>
                </div>

                <div>
                  <h4 className="font-medium text-secondary-900 dark:text-white mb-3">
                    Asignaciones
                  </h4>
                  <div className="space-y-2 text-sm">
                    <div>
                      <span className="text-secondary-600 dark:text-secondary-400">Rol:</span>
                      <span className="ml-2 font-medium">
                        {getSelectedRole()?.display_name || 'No asignado'}
                      </span>
                    </div>
                    <div>
                      <span className="text-secondary-600 dark:text-secondary-400">Mesa:</span>
                      <span className="ml-2 font-medium">
                        {getSelectedDesk()?.name || 'No asignado'}
                      </span>
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
                disabled={createUserMutation.isPending}
                className="btn-primary disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {createUserMutation.isPending ? 'Creando...' : 'Crear Usuario'}
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}
