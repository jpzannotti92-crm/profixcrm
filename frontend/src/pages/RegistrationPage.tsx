import { useState, useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { useNavigate } from 'react-router-dom'
import toast from 'react-hot-toast'
import { 
  EnvelopeIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  SparklesIcon,
  RocketLaunchIcon,
  UserIcon,
  PhoneIcon,
  GlobeAltIcon
} from '@heroicons/react/24/outline'
import LoadingSpinner from '../components/ui/LoadingSpinner'

interface EmailFormData {
  email: string
}

interface Lead {
  id: number
  first_name: string
  last_name: string
  email: string
  phone?: string
  country?: string
  status: string
  trading_accounts?: any[]
}

export default function RegistrationPage() {
  const navigate = useNavigate()
  const [isCheckingLead, setIsCheckingLead] = useState(false)
  const [leadData, setLeadData] = useState<Lead | null>(null)
  // Wizard de cuentas de trading removido
  const [emailDebounceTimer, setEmailDebounceTimer] = useState<NodeJS.Timeout | null>(null)

  const {
    register,
    handleSubmit,
    watch,
    formState: { errors }
  } = useForm<EmailFormData>()

  const watchedEmail = watch('email')

  // Verificar lead por email con debounce
  useEffect(() => {
    if (emailDebounceTimer) {
      clearTimeout(emailDebounceTimer)
    }

    if (watchedEmail && watchedEmail.includes('@') && watchedEmail.length > 5) {
      const timer = setTimeout(async () => {
        setIsCheckingLead(true)
        try {
          // Simular búsqueda de lead
          await new Promise(resolve => setTimeout(resolve, 1200))
          
          // Simular lead encontrado (para demostración)
          if (watchedEmail.includes('test') || watchedEmail.includes('demo') || watchedEmail.includes('juan')) {
            const mockLead: Lead = {
              id: 1,
              first_name: 'Juan',
              last_name: 'Pérez',
              email: watchedEmail,
              phone: '+34 600 123 456',
              country: 'España',
              status: 'active',
              trading_accounts: [
                { id: 1, type: 'demo', balance: 10000, currency: 'USD' },
                { id: 2, type: 'real', balance: 1500, currency: 'EUR' }
              ]
            }
            
            setLeadData(mockLead)
            
            toast.success('¡Lead encontrado! Perfil existente detectado', {
              icon: '🎉',
              duration: 4000
            })
          } else {
            setLeadData(null)
            toast.success('Email disponible para registro', {
              icon: '✅',
              duration: 3000
            })
          }
        } catch (error) {
          console.error('Error checking lead:', error)
          toast.error('Error al verificar el email')
        } finally {
          setIsCheckingLead(false)
        }
      }, 1000)
      
      setEmailDebounceTimer(timer)
    } else {
      setLeadData(null)
    }

    return () => {
      if (emailDebounceTimer) {
        clearTimeout(emailDebounceTimer)
      }
    }
  }, [watchedEmail])

  const onSubmit = async (_data: EmailFormData) => {
    if (!leadData) {
      // Si no hay lead existente, crear uno nuevo
      toast.success('Creando nuevo perfil de trading...', {
        icon: '🚀',
        duration: 3000
      })
      
      toast.success('Registro procesado. Continúa iniciando sesión.')
      navigate('/auth/login')
    } else {
      // Si hay lead existente, proceder directamente
      toast.success('¡Bienvenido de vuelta! Configurando tu acceso...', {
        icon: '🎯',
        duration: 3000
      })
      
      setTimeout(() => {
        navigate('/auth/login')
      }, 1500)
    }
  }

  // Lógica de wizard eliminada

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100 dark:from-slate-900 dark:via-slate-800 dark:to-slate-900 flex items-center justify-center p-4">
      {/* Background Pattern */}
      <div className="absolute inset-0 bg-grid-pattern opacity-5"></div>
      
      {/* Main Container */}
      <div className="relative z-10 w-full max-w-md">
        {/* Logo and Header */}
        <div className="text-center mb-8">
          <div className="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-2xl mb-6 shadow-lg">
            <RocketLaunchIcon className="w-8 h-8 text-white" />
          </div>
          
          <h1 className="text-3xl font-bold text-slate-900 dark:text-white mb-2">
            Registro de iaTrade
          </h1>
          <p className="text-slate-600 dark:text-slate-400 text-lg">
            Accede a tu plataforma de trading
          </p>
        </div>

        {/* Main Card */}
        <div className="bg-white/80 dark:bg-slate-800/80 backdrop-blur-xl rounded-2xl shadow-2xl border border-white/20 dark:border-slate-700/50 p-8">
          
          {/* Lead Status Display */}
          {leadData && (
            <div className="mb-6 p-4 bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 border border-green-200 dark:border-green-800 rounded-xl">
              <div className="flex items-start space-x-3">
                <div className="flex-shrink-0">
                  <CheckCircleIcon className="w-6 h-6 text-green-600 dark:text-green-400" />
                </div>
                <div className="flex-1">
                  <h3 className="font-semibold text-green-800 dark:text-green-200 mb-1">
                    ¡Lead Encontrado!
                  </h3>
                  <div className="space-y-1 text-sm text-green-700 dark:text-green-300">
                    <div className="flex items-center space-x-2">
                      <UserIcon className="w-4 h-4" />
                      <span>{leadData.first_name} {leadData.last_name}</span>
                    </div>
                    {leadData.phone && (
                      <div className="flex items-center space-x-2">
                        <PhoneIcon className="w-4 h-4" />
                        <span>{leadData.phone}</span>
                      </div>
                    )}
                    {leadData.country && (
                      <div className="flex items-center space-x-2">
                        <GlobeAltIcon className="w-4 h-4" />
                        <span>{leadData.country}</span>
                      </div>
                    )}
                    {leadData.trading_accounts && leadData.trading_accounts.length > 0 && (
                      <div className="mt-2 text-xs bg-green-100 dark:bg-green-800/30 px-2 py-1 rounded-md inline-block">
                        {leadData.trading_accounts.length} cuenta(s) de trading existente(s)
                      </div>
                    )}
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* Email Form */}
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
            <div>
              <label className="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">
                Introduce tu email para comenzar
              </label>
              <div className="relative">
                <EnvelopeIcon className="absolute left-4 top-1/2 transform -translate-y-1/2 w-5 h-5 text-slate-400" />
                <input
                  {...register('email', {
                    required: 'El email es requerido',
                    pattern: {
                      value: /^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}$/i,
                      message: 'Email inválido'
                    }
                  })}
                  type="email"
                  className="w-full pl-12 pr-12 py-4 text-lg border-2 border-slate-200 dark:border-slate-600 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white transition-all duration-200 placeholder-slate-400"
                  placeholder="tu@email.com"
                />
                {isCheckingLead && (
                  <div className="absolute right-4 top-1/2 transform -translate-y-1/2">
                    <LoadingSpinner size="sm" />
                  </div>
                )}
              </div>
              {errors.email && (
                <p className="mt-2 text-sm text-red-600 dark:text-red-400 flex items-center space-x-1">
                  <ExclamationTriangleIcon className="w-4 h-4" />
                  <span>{errors.email.message}</span>
                </p>
              )}
            </div>

            {/* Submit Button */}
            <button
              type="submit"
              disabled={isCheckingLead || !watchedEmail}
              className="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-semibold py-4 px-6 rounded-xl transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center space-x-2 text-lg shadow-lg hover:shadow-xl"
            >
              {isCheckingLead ? (
                <>
                  <LoadingSpinner size="sm" color="white" />
                  <span>Verificando email...</span>
                </>
              ) : leadData ? (
                <>
                  <CheckCircleIcon className="w-5 h-5" />
                  <span>Acceder al WebTrader</span>
                </>
              ) : (
                <>
                  <SparklesIcon className="w-5 h-5" />
                  <span>Crear Cuenta de Trading</span>
                </>
              )}
            </button>
          </form>

          {/* Info Text */}
          <div className="mt-6 text-center">
            <p className="text-sm text-slate-500 dark:text-slate-400">
              {leadData ? (
                'Tu perfil ha sido encontrado. Haz clic para acceder.'
              ) : (
                'Introduce tu email para verificar si ya tienes una cuenta o crear una nueva.'
              )}
            </p>
          </div>

          {/* Features */}
          <div className="mt-8 pt-6 border-t border-slate-200 dark:border-slate-700">
            <div className="grid grid-cols-3 gap-4 text-center">
              <div className="space-y-2">
                <div className="w-8 h-8 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center mx-auto">
                  <SparklesIcon className="w-4 h-4 text-blue-600 dark:text-blue-400" />
                </div>
                <div className="text-xs text-slate-600 dark:text-slate-400">
                  Plataforma Avanzada
                </div>
              </div>
              <div className="space-y-2">
                <div className="w-8 h-8 bg-green-100 dark:bg-green-900/30 rounded-lg flex items-center justify-center mx-auto">
                  <CheckCircleIcon className="w-4 h-4 text-green-600 dark:text-green-400" />
                </div>
                <div className="text-xs text-slate-600 dark:text-slate-400">
                  Seguro y Regulado
                </div>
              </div>
              <div className="space-y-2">
                <div className="w-8 h-8 bg-purple-100 dark:bg-purple-900/30 rounded-lg flex items-center justify-center mx-auto">
                  <RocketLaunchIcon className="w-4 h-4 text-purple-600 dark:text-purple-400" />
                </div>
                <div className="text-xs text-slate-600 dark:text-slate-400">
                  Ejecución Rápida
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Footer */}
        <div className="mt-6 text-center">
          <p className="text-sm text-slate-500 dark:text-slate-400">
            ¿Necesitas ayuda?{' '}
            <button
              onClick={() => navigate('/support')}
              className="text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 font-medium transition-colors"
            >
              Contacta con soporte
            </button>
          </p>
        </div>
      </div>

      {/* Módulo WebTrader y Wizard deshabilitados */}
    </div>
  )
}
