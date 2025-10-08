import { Outlet } from 'react-router-dom'
import { useTheme } from '../contexts/ThemeContext'
import { SunIcon, MoonIcon } from '@heroicons/react/24/outline'

export default function AuthLayout() {
  const { theme, toggleTheme } = useTheme()

  return (
    <div className="min-h-screen w-full bg-gradient-to-br from-primary-50 via-white to-secondary-50 dark:from-secondary-900 dark:via-secondary-800 dark:to-primary-900 flex">
      {/* Panel izquierdo - Branding */}
      <div className="hidden lg:flex lg:w-1/2 relative overflow-hidden">
        <div className="absolute inset-0 bg-gradient-to-br from-primary-600 to-primary-800 opacity-90" />
        <div className="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxnIGZpbGw9IiNmZmZmZmYiIGZpbGwtb3BhY2l0eT0iMC4xIj48Y2lyY2xlIGN4PSIzMCIgY3k9IjMwIiByPSI0Ii8+PC9nPjwvZz48L3N2Zz4=')] opacity-20" />
        
        <div className="relative z-10 flex flex-col justify-center items-center text-white p-8 xl:p-12">
          <div className="text-center">
            <div className="w-16 xl:w-20 h-16 xl:h-20 bg-white/20 rounded-2xl flex items-center justify-center mb-6 xl:mb-8 backdrop-blur-sm">
              <svg className="w-8 xl:w-10 h-8 xl:h-10 text-white" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
              </svg>
            </div>
            
            <h1 className="text-3xl xl:text-4xl font-bold mb-3 xl:mb-4">iaTrade CRM</h1>
            <p className="text-lg xl:text-xl text-primary-100 mb-6 xl:mb-8">
              Plataforma Profesional de Trading
            </p>
            
            <div className="space-y-3 xl:space-y-4 text-left max-w-md">
              <div className="flex items-center space-x-3">
                <div className="w-2 h-2 bg-success-400 rounded-full" />
                <span className="text-sm xl:text-base text-primary-100">Gestión avanzada de leads</span>
              </div>
              <div className="flex items-center space-x-3">
                <div className="w-2 h-2 bg-success-400 rounded-full" />
                <span className="text-sm xl:text-base text-primary-100">Cuentas de trading integradas</span>
              </div>
              <div className="flex items-center space-x-3">
                <div className="w-2 h-2 bg-success-400 rounded-full" />
                <span className="text-sm xl:text-base text-primary-100">Análisis en tiempo real</span>
              </div>
              <div className="flex items-center space-x-3">
                <div className="w-2 h-2 bg-success-400 rounded-full" />
                <span className="text-sm xl:text-base text-primary-100">Reportes profesionales</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Panel derecho - Formulario */}
      <div className="flex-1 flex flex-col justify-center py-8 sm:py-12 px-4 sm:px-6 lg:px-16 xl:px-20 2xl:px-24 w-full">
        <div className="mx-auto w-full max-w-sm lg:max-w-md xl:max-w-lg">
          {/* Botón de tema */}
          <div className="flex justify-end mb-6 sm:mb-8">
            <button
              onClick={toggleTheme}
              className="p-2 rounded-lg bg-white dark:bg-secondary-800 shadow-soft border border-secondary-200 dark:border-secondary-700 hover:bg-secondary-50 dark:hover:bg-secondary-700 transition-colors"
              aria-label={`Cambiar a tema ${theme === 'light' ? 'oscuro' : 'claro'}`}
            >
              {theme === 'light' ? (
                <MoonIcon className="w-5 h-5 text-secondary-600" />
              ) : (
                <SunIcon className="w-5 h-5 text-secondary-400" />
              )}
            </button>
          </div>

          {/* Logo móvil */}
          <div className="lg:hidden text-center mb-6 sm:mb-8">
            <div className="w-14 sm:w-16 h-14 sm:h-16 bg-primary-600 rounded-2xl flex items-center justify-center mx-auto mb-3 sm:mb-4">
              <svg className="w-7 sm:w-8 h-7 sm:h-8 text-white" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
              </svg>
            </div>
            <h1 className="text-xl sm:text-2xl font-bold text-secondary-900 dark:text-white">iaTrade CRM</h1>
          </div>

          {/* Contenido del formulario */}
          <Outlet />
        </div>
      </div>
    </div>
  )
}
