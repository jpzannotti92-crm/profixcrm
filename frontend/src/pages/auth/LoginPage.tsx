import { useState, useEffect } from 'react'
import { useNavigate, useLocation } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { EyeIcon, EyeSlashIcon } from '@heroicons/react/24/outline'

import { useAuth } from '../../contexts/AuthContext'
import LoadingSpinner from '../../components/ui/LoadingSpinner'

interface LoginFormData {
  username: string
  password: string
  remember: boolean
}

export default function LoginPage() {
  const [showPassword, setShowPassword] = useState(false)
  const [isLoading, setIsLoading] = useState(false)
  
  const { login } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  
  const from = location.state?.from?.pathname || '/dashboard'

  // Redirigir a login de producción cuando el preview corre en localhost
  useEffect(() => {
    const host = window.location.hostname
    const isLocal = host === 'localhost' || host === '127.0.0.1'
    if (isLocal) {
      window.location.href = 'https://spin2pay.com/auth/login'
    }
  }, [])

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<LoginFormData>({
    defaultValues: {
      username: '',
      password: '',
      remember: false,
    },
  })

  const onSubmit = async (data: LoginFormData) => {
    setIsLoading(true)
    
    try {
      const success = await login(data.username, data.password, data.remember)
      if (success) {
        navigate(from, { replace: true })
      }
    } catch (error) {
      console.error('Login error:', error)
      // El error ya se maneja en el AuthContext con toast
    } finally {
      setIsLoading(false)
    }
  }

  return (
    <div className="animate-fade-in">
      <div className="mb-8">
        <h2 className="text-3xl font-bold text-secondary-900 dark:text-white">
          Iniciar Sesión
        </h2>
        <p className="mt-2 text-sm text-secondary-600 dark:text-secondary-400">
          Accede a tu cuenta para gestionar tu CRM
        </p>
      </div>

      <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
        {/* Campo Usuario */}
        <div>
          <label htmlFor="username" className="label">
            Usuario
          </label>
          <input
            {...register('username', {
              required: 'El usuario es requerido',
              minLength: {
                value: 3,
                message: 'El usuario debe tener al menos 3 caracteres'
              }
            })}
            type="text"
            id="username"
            className="input"
            placeholder="Ingresa tu usuario"
            disabled={isLoading}
          />
          {errors.username && (
            <p className="mt-1 text-sm text-danger-600 dark:text-danger-400">
              {errors.username.message}
            </p>
          )}
        </div>

        {/* Campo Contraseña */}
        <div>
          <label htmlFor="password" className="label">
            Contraseña
          </label>
          <div className="relative">
            <input
              {...register('password', {
                required: 'La contraseña es requerida',
                minLength: {
                  value: 6,
                  message: 'La contraseña debe tener al menos 6 caracteres'
                }
              })}
              type={showPassword ? 'text' : 'password'}
              id="password"
              className="input pr-10"
              placeholder="Ingresa tu contraseña"
              disabled={isLoading}
            />
            <button
              type="button"
              className="absolute inset-y-0 right-0 pr-3 flex items-center"
              onClick={() => setShowPassword(!showPassword)}
              disabled={isLoading}
            >
              {showPassword ? (
                <EyeSlashIcon className="h-5 w-5 text-secondary-400" />
              ) : (
                <EyeIcon className="h-5 w-5 text-secondary-400" />
              )}
            </button>
          </div>
          {errors.password && (
            <p className="mt-1 text-sm text-danger-600 dark:text-danger-400">
              {errors.password.message}
            </p>
          )}
        </div>

        {/* Recordar sesión */}
        <div className="flex items-center justify-between">
          <div className="flex items-center">
            <input
              {...register('remember')}
              id="remember"
              type="checkbox"
              className="h-4 w-4 text-primary-600 focus:ring-primary-500 border-secondary-300 rounded"
              disabled={isLoading}
            />
            <label htmlFor="remember" className="ml-2 block text-sm text-secondary-700 dark:text-secondary-300">
              Recordar sesión
            </label>
          </div>

          <div className="text-sm">
            <a
              href="#"
              className="font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300"
            >
              ¿Olvidaste tu contraseña?
            </a>
          </div>
        </div>

        {/* Botón de envío */}
        <button
          type="submit"
          disabled={isLoading}
          className="w-full btn-primary py-3 text-base font-medium disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {isLoading ? (
            <div className="flex items-center justify-center">
              <LoadingSpinner size="sm" color="white" className="mr-2" />
              Iniciando sesión...
            </div>
          ) : (
            'Iniciar Sesión'
          )}
        </button>
      </form>

      {/* Información de demo */}
      <div className="mt-8 p-4 bg-primary-50 dark:bg-primary-900/20 rounded-lg border border-primary-200 dark:border-primary-800">
        <h3 className="text-sm font-medium text-primary-800 dark:text-primary-200 mb-2">
          Credenciales de Demo
        </h3>
        <div className="text-sm text-primary-700 dark:text-primary-300 space-y-1">
          <p><strong>Usuario:</strong> admin</p>
          <p><strong>Contraseña:</strong> password</p>
        </div>
      </div>
    </div>
  )
}
