import { useState, useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { useNavigate, Link } from 'react-router-dom'
import toast from 'react-hot-toast'
import { 
  EnvelopeIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  SparklesIcon,
  RocketLaunchIcon,
  ArrowRightIcon,
  UserIcon
} from '@heroicons/react/24/outline'
import LoadingSpinner from '../../components/ui/LoadingSpinner'
import { leadsApi } from '../../services/api'

interface RegistrationFormData {
  email: string
}

interface Lead {
  id: number
  email: string
  first_name: string
  last_name: string
  phone?: string
  country?: string
  trading_accounts?: any[]
  status: string
}

export default function RegistrationPage() {
  const [detectedLead, setDetectedLead] = useState<Lead | null>(null)
  const [isCheckingEmail, setIsCheckingEmail] = useState(false)
  // Wizard de cuentas de trading removido
  const navigate = useNavigate()

  const {
    register,
    handleSubmit,
    watch,
    formState: { errors, isValid }
  } = useForm<RegistrationFormData>({
    mode: 'onChange'
  })

  const email = watch('email')

  // Debounce para verificación de email
  useEffect(() => {
    if (email && email.includes('@') && email.includes('.')) {
      const timer = setTimeout(() => {
        checkEmailForLead(email)
      }, 800)
      return () => clearTimeout(timer)
    } else {
      setDetectedLead(null)
    }
  }, [email])

  const checkEmailForLead = async (emailToCheck: string) => {
    setIsCheckingEmail(true)
    try {
      const response = await leadsApi.getByEmail(emailToCheck)
      if (response.data.exists) {
        setDetectedLead(response.data.lead)
        toast.success(`¡Bienvenido de vuelta, ${response.data.lead.first_name}!`, {
          icon: '👋',
          duration: 3000
        })
      } else {
        setDetectedLead(null)
      }
    } catch (error) {
      setDetectedLead(null)
    } finally {
      setIsCheckingEmail(false)
    }
  }

  const onSubmit = (_data: RegistrationFormData) => {
    toast.success('Registro procesado. Continúa iniciando sesión.')
    navigate('/auth/login')
  }

  // Lógica de wizard eliminada

  // Wizard eliminado; se muestra formulario simple

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-900 via-blue-900 to-slate-900 flex items-center justify-center p-4">
      {/* Background Effects */}
      <div className="absolute inset-0 overflow-hidden">
        <div className="absolute -top-40 -right-40 w-80 h-80 bg-blue-500/20 rounded-full blur-3xl"></div>
        <div className="absolute -bottom-40 -left-40 w-80 h-80 bg-purple-500/20 rounded-full blur-3xl"></div>
        <div className="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-emerald-500/10 rounded-full blur-3xl"></div>
      </div>

      <div className="relative w-full max-w-md">
        {/* Header */}
        <div className="text-center mb-8">
          <div className="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-r from-blue-500 to-purple-600 rounded-2xl mb-6 shadow-2xl">
            <SparklesIcon className="w-8 h-8 text-white" />
          </div>
          <h1 className="text-3xl font-bold text-white mb-2">
            Únete a iaTrade
          </h1>
          <p className="text-slate-300 text-lg">
            Comienza tu viaje en el trading profesional
          </p>
        </div>

        {/* Main Form Card */}
        <div className="bg-white/10 backdrop-blur-xl rounded-3xl border border-white/20 shadow-2xl p-8">
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
            {/* Email Field */}
            <div className="space-y-2">
              <label className="block text-sm font-medium text-white">
                Correo Electrónico
              </label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                  <EnvelopeIcon className="h-5 w-5 text-slate-400" />
                </div>
                <input
                  {...register('email', {
                    required: 'El email es requerido',
                    pattern: {
                      value: /^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}$/i,
                      message: 'Email inválido'
                    }
                  })}
                  type="email"
                  placeholder="tu@email.com"
                  className="w-full pl-12 pr-12 py-4 bg-white/5 border border-white/20 rounded-xl text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                />
                
                {/* Loading/Status Icons */}
                <div className="absolute inset-y-0 right-0 pr-4 flex items-center">
                  {isCheckingEmail ? (
                    <LoadingSpinner size="sm" />
                  ) : detectedLead ? (
                    <CheckCircleIcon className="h-5 w-5 text-emerald-400" />
                  ) : email && email.includes('@') && !errors.email ? (
                    <CheckCircleIcon className="h-5 w-5 text-slate-400" />
                  ) : null}
                </div>
              </div>
              
              {errors.email && (
                <p className="text-red-400 text-sm flex items-center gap-1">
                  <ExclamationTriangleIcon className="w-4 h-4" />
                  {errors.email.message}
                </p>
              )}
            </div>

            {/* Lead Detection Status */}
            {detectedLead && (
              <div className="bg-emerald-500/20 border border-emerald-500/30 rounded-xl p-4">
                <div className="flex items-center gap-3">
                  <div className="flex-shrink-0">
                    <UserIcon className="w-6 h-6 text-emerald-400" />
                  </div>
                  <div>
                    <p className="text-emerald-300 font-medium">
                      ¡Cuenta encontrada!
                    </p>
                    <p className="text-emerald-200 text-sm">
                      Hola {detectedLead.first_name} {detectedLead.last_name}, continúa con tu configuración
                    </p>
                  </div>
                </div>
              </div>
            )}

            {/* Submit Button */}
            <button
              type="submit"
              disabled={!isValid || isCheckingEmail}
              className="w-full bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 disabled:from-slate-600 disabled:to-slate-700 text-white font-semibold py-4 px-6 rounded-xl transition-all duration-200 flex items-center justify-center gap-3 shadow-lg hover:shadow-xl disabled:cursor-not-allowed group"
            >
              {isCheckingEmail ? (
                <>
                  <LoadingSpinner size="sm" />
                  <span>Verificando...</span>
                </>
              ) : (
                <>
                  <RocketLaunchIcon className="w-5 h-5 group-hover:scale-110 transition-transform" />
                  <span>{detectedLead ? 'Continuar' : 'Comenzar'}</span>
                  <ArrowRightIcon className="w-5 h-5 group-hover:translate-x-1 transition-transform" />
                </>
              )}
            </button>
          </form>

          {/* Footer */}
          <div className="text-center mt-6 pt-6 border-t border-white/10">
            <p className="text-slate-400">
              ¿Ya tienes cuenta?{' '}
              <Link 
                to="/auth/login" 
                className="text-blue-400 hover:text-blue-300 font-medium transition-colors"
              >
                Inicia sesión
              </Link>
            </p>
          </div>
        </div>

        {/* Benefits Cards */}
        <div className="mt-8 grid grid-cols-3 gap-4">
          <div className="text-center p-4 bg-white/5 backdrop-blur-xl rounded-xl border border-white/10 hover:bg-white/10 transition-all duration-200">
            <div className="w-10 h-10 bg-blue-500/20 rounded-lg flex items-center justify-center mx-auto mb-2">
              <SparklesIcon className="w-5 h-5 text-blue-400" />
            </div>
            <p className="text-xs text-slate-300 font-medium">Tecnología IA</p>
          </div>
          <div className="text-center p-4 bg-white/5 backdrop-blur-xl rounded-xl border border-white/10 hover:bg-white/10 transition-all duration-200">
            <div className="w-10 h-10 bg-emerald-500/20 rounded-lg flex items-center justify-center mx-auto mb-2">
              <RocketLaunchIcon className="w-5 h-5 text-emerald-400" />
            </div>
            <p className="text-xs text-slate-300 font-medium">Rápido & Seguro</p>
          </div>
          <div className="text-center p-4 bg-white/5 backdrop-blur-xl rounded-xl border border-white/10 hover:bg-white/10 transition-all duration-200">
            <div className="w-10 h-10 bg-purple-500/20 rounded-lg flex items-center justify-center mx-auto mb-2">
              <UserIcon className="w-5 h-5 text-purple-400" />
            </div>
            <p className="text-xs text-slate-300 font-medium">Soporte 24/7</p>
          </div>
        </div>

        {/* Trust Indicators */}
        <div className="mt-6 text-center">
          <p className="text-slate-500 text-xs">
            Más de 10,000 traders confían en nosotros
          </p>
        </div>
      </div>
    </div>
  )
}
