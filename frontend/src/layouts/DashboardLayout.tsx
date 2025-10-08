import { useState } from 'react'
import { Outlet, Link, useLocation, useNavigate } from 'react-router-dom'
import { 
  HomeIcon,
  UsersIcon,
  UserGroupIcon,
  BuildingOfficeIcon,
  ShieldCheckIcon,
  CreditCardIcon,
  Cog6ToothIcon,
  Bars3Icon,
  XMarkIcon,
  SunIcon,
  MoonIcon,
  BellIcon,
  ChevronDownIcon,
  ArrowRightOnRectangleIcon,
  ChartBarIcon
} from '@heroicons/react/24/outline'

import { useAuth } from '../contexts/AuthContext'
import { useTheme } from '../contexts/ThemeContext'
import type { ComponentType, SVGProps } from 'react'

// Strong typing for navigation items
interface NavChild {
  name: string
  href: string
  permission: string | null
}

type IconType = ComponentType<SVGProps<SVGSVGElement>>

interface NavItem {
  name: string
  href?: string
  icon: IconType
  permission: string | null
  roles?: string[]
  children?: NavChild[]
}

const navigation: NavItem[] = [
  { name: 'Dashboard', href: '/dashboard', icon: HomeIcon, permission: null }, // Dashboard siempre visible
  { name: 'Mi Resumen', href: '/employee-summary', icon: ChartBarIcon, permission: null, roles: ['sales', 'employee'] }, // Solo para empleados
  { name: 'Leads', href: '/leads', icon: UsersIcon, permission: 'view_leads' },
  { name: 'Usuarios', href: '/users', icon: UserGroupIcon, permission: 'view_users' },
  { name: 'Roles', href: '/roles', icon: ShieldCheckIcon, permission: 'view_roles' },
  { name: 'Mesas', href: '/desks', icon: BuildingOfficeIcon, permission: 'view_desks' },
  { name: 'Gestión de Estados', href: '/states', icon: Cog6ToothIcon, permission: 'manage_states' },
  { 
    name: 'Trading', 
    icon: CreditCardIcon,
    permission: null, // Mostrar si tiene algún permiso de trading
    children: [
      { name: 'Cuentas', href: '/trading', permission: 'view_trading_accounts' },
    ]
  },
]

export default function DashboardLayout() {
  const [sidebarOpen, setSidebarOpen] = useState(false)
  const [userMenuOpen, setUserMenuOpen] = useState(false)
  
  const { user, logout, hasPermission } = useAuth()
  const { theme, toggleTheme } = useTheme()
  const location = useLocation()
  const navigate = useNavigate()

  const handleLogout = () => {
    logout(() => navigate('/auth/login', { replace: true }))
  }

  // Filtrar navegación basada en permisos y roles
  const filteredNavigation = navigation
    .map((item) => {
      // Verificar permisos
      if (item.permission && !hasPermission(item.permission)) {
        return null
      }

      // Verificar roles específicos (al menos uno debe coincidir)
      if (item.roles && !item.roles.some((role) => user?.roles?.includes(role))) {
        return null
      }

      if (item.children) {
        const filteredChildren = item.children.filter((child) =>
          !child.permission || hasPermission(child.permission)
        )
        return filteredChildren.length > 0 ? { ...item, children: filteredChildren } : null
      }
      return item
    })
    .filter((item): item is NavItem => item !== null)

  return (
    <div className="min-h-screen w-full bg-secondary-50 dark:bg-secondary-900">
      {/* Sidebar móvil */}
      <div className={`fixed inset-0 z-50 lg:hidden ${sidebarOpen ? 'block' : 'hidden'}`}>
        <div className="fixed inset-0 bg-secondary-600 bg-opacity-75" onClick={() => setSidebarOpen(false)} />
        <div className="fixed inset-y-0 left-0 flex w-full max-w-xs flex-col bg-white dark:bg-secondary-800 shadow-xl">
          <div className="flex h-16 items-center justify-between px-4 border-b border-secondary-200 dark:border-secondary-700">
            <div className="flex items-center">
              <div className="w-8 h-8 bg-primary-600 rounded-lg flex items-center justify-center">
                <svg className="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                </svg>
              </div>
              <span className="ml-2 text-lg font-semibold text-secondary-900 dark:text-white">
                iaTrade CRM
              </span>
            </div>
            <button
              onClick={() => setSidebarOpen(false)}
              className="p-2 rounded-md text-secondary-400 hover:text-secondary-500 hover:bg-secondary-100 dark:hover:bg-secondary-700"
            >
              <XMarkIcon className="w-6 h-6" />
            </button>
          </div>
          
          <nav className="flex-1 px-4 py-4 space-y-1">
            {filteredNavigation.map((item) => (
              <div key={item.name}>
                {item.children ? (
                  <div>
                    <div className="flex items-center px-3 py-2 text-sm font-medium text-secondary-700 dark:text-secondary-300">
                      <item.icon className="w-5 h-5 mr-3" />
                      {item.name}
                    </div>
                    <div className="ml-8 space-y-1">
                      {item.children.map((child) => (
                        <Link
                          key={child.name}
                          to={child.href}
                          className={`block px-3 py-2 text-sm rounded-md transition-colors ${
                            location.pathname === child.href
                              ? 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-200'
                              : 'text-secondary-600 hover:bg-secondary-100 dark:text-secondary-400 dark:hover:bg-secondary-700'
                          }`}
                        >
                          {child.name}
                        </Link>
                      ))}
                    </div>
                  </div>
                ) : (
                  <Link
                    to={item.href!}
                    className={`flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors ${
                      location.pathname === item.href
                        ? 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-200'
                        : 'text-secondary-700 hover:bg-secondary-100 dark:text-secondary-300 dark:hover:bg-secondary-700'
                    }`}
                  >
                    <item.icon className="w-5 h-5 mr-3" />
                    {item.name}
                  </Link>
                )}
              </div>
            ))}
          </nav>
        </div>
      </div>

      {/* Sidebar fijo para desktop */}
      <div className="hidden lg:fixed lg:inset-y-0 lg:z-50 lg:flex lg:w-64 lg:flex-col">
        <div className="flex grow flex-col gap-y-5 overflow-y-auto bg-white dark:bg-secondary-800 border-r border-secondary-200 dark:border-secondary-700">
          <div className="flex h-16 shrink-0 items-center px-6 border-b border-secondary-200 dark:border-secondary-700">
            <div className="flex items-center">
              <div className="w-8 h-8 bg-primary-600 rounded-lg flex items-center justify-center">
                <span className="text-white font-bold text-sm">IT</span>
              </div>
              <span className="ml-3 text-lg font-semibold text-secondary-900 dark:text-white">
                iaTrade CRM
              </span>
            </div>
          </div>
          
          <nav className="flex flex-1 flex-col px-6">
            <ul role="list" className="flex flex-1 flex-col gap-y-7">
              <li>
                <ul role="list" className="-mx-2 space-y-1">
                  {filteredNavigation.map((item) => (
                    <li key={item.name}>
                      {item.children ? (
                        <div>
                          <div className="flex items-center px-3 py-2 text-sm font-medium text-secondary-700 dark:text-secondary-300">
                            <item.icon className="w-5 h-5 mr-3" />
                            {item.name}
                          </div>
                          <div className="ml-8 space-y-1">
                            {item.children.map((child) => (
                              <Link
                                key={child.name}
                                to={child.href}
                                className={`block px-3 py-2 text-sm rounded-md transition-colors ${
                                  location.pathname === child.href
                                    ? 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-200'
                                    : 'text-secondary-600 hover:bg-secondary-100 dark:text-secondary-400 dark:hover:bg-secondary-700'
                                }`}
                              >
                                {child.name}
                              </Link>
                            ))}
                          </div>
                        </div>
                      ) : (
                        <Link
                          to={item.href!}
                          className={`flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors ${
                            location.pathname === item.href
                              ? 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-200'
                              : 'text-secondary-700 hover:bg-secondary-100 dark:text-secondary-300 dark:hover:bg-secondary-700'
                          }`}
                        >
                          <item.icon className="w-5 h-5 mr-3" />
                          {item.name}
                        </Link>
                      )}
                    </li>
                  ))}
                </ul>
              </li>
            </ul>
          </nav>
        </div>
      </div>

      {/* Contenido principal */}
      <div className="lg:pl-64 w-full">
        {/* Header */}
        <header className="bg-white dark:bg-secondary-800 shadow-sm border-b border-secondary-200 dark:border-secondary-700 w-full">
          <div className="flex items-center justify-between h-16 px-4 sm:px-6 lg:px-8 w-full">
            <div className="flex items-center">
              <button
                onClick={() => setSidebarOpen(true)}
                className="lg:hidden p-2 rounded-md text-secondary-400 hover:text-secondary-500 hover:bg-secondary-100 dark:hover:bg-secondary-700"
              >
                <Bars3Icon className="w-6 h-6" />
              </button>
              
              <h1 className="ml-4 lg:ml-0 text-lg sm:text-xl font-semibold text-secondary-900 dark:text-white">
                {navigation.find(item => item.href === location.pathname)?.name || 
                 navigation.find(item => item.children?.some(child => child.href === location.pathname))?.children?.find(child => child.href === location.pathname)?.name ||
                 'Dashboard'}
              </h1>
            </div>

            <div className="flex items-center space-x-2 sm:space-x-4">
              {/* Botón de tema */}
              <button
                onClick={toggleTheme}
                className="p-2 rounded-lg bg-secondary-100 dark:bg-secondary-700 hover:bg-secondary-200 dark:hover:bg-secondary-600 transition-colors"
                aria-label={`Cambiar a tema ${theme === 'light' ? 'oscuro' : 'claro'}`}
              >
                {theme === 'light' ? (
                  <MoonIcon className="w-5 h-5 text-secondary-600 dark:text-secondary-400" />
                ) : (
                  <SunIcon className="w-5 h-5 text-secondary-600 dark:text-secondary-400" />
                )}
              </button>

              {/* Notificaciones */}
              <button className="p-2 rounded-lg bg-secondary-100 dark:bg-secondary-700 hover:bg-secondary-200 dark:hover:bg-secondary-600 transition-colors relative">
                <BellIcon className="w-5 h-5 text-secondary-600 dark:text-secondary-400" />
                <span className="absolute -top-1 -right-1 w-3 h-3 bg-danger-500 rounded-full text-xs text-white flex items-center justify-center">
                  3
                </span>
              </button>

              {/* Menú de usuario */}
              <div className="relative">
                <button
                  onClick={() => setUserMenuOpen(!userMenuOpen)}
                  className="flex items-center space-x-2 sm:space-x-3 p-2 rounded-lg hover:bg-secondary-100 dark:hover:bg-secondary-700 transition-colors"
                >
                  <div className="w-8 h-8 bg-primary-600 rounded-full flex items-center justify-center">
                    <span className="text-sm font-medium text-white">
                      {user?.first_name?.charAt(0)}{user?.last_name?.charAt(0)}
                    </span>
                  </div>
                  <div className="hidden sm:block text-left">
                    <p className="text-sm font-medium text-secondary-900 dark:text-white">
                      {user?.first_name} {user?.last_name}
                    </p>
                    <p className="text-xs text-secondary-500 dark:text-secondary-400">
                      {(() => {
                        // Mostrar nombre de rol de forma segura (soporta strings u objetos)
                        const firstRole = user?.roles?.[0] as unknown
                        const roleNameFromArray = typeof firstRole === 'string'
                          ? firstRole
                          : (firstRole && (firstRole as any).display_name) || (firstRole && (firstRole as any).name)
                        return roleNameFromArray || user?.role_names?.[0] || 'Sin rol'
                      })()}
                    </p>
                  </div>
                  <ChevronDownIcon className="w-4 h-4 text-secondary-400" />
                </button>

                {userMenuOpen && (
                  <div className="absolute right-0 mt-2 w-48 bg-white dark:bg-secondary-800 rounded-lg shadow-lg border border-secondary-200 dark:border-secondary-700 py-1 z-50">
                    <button
                      onClick={handleLogout}
                      className="flex items-center w-full px-4 py-2 text-sm text-secondary-700 dark:text-secondary-300 hover:bg-secondary-100 dark:hover:bg-secondary-700"
                    >
                      <ArrowRightOnRectangleIcon className="w-4 h-4 mr-3" />
                      Cerrar Sesión
                    </button>
                  </div>
                )}
              </div>
            </div>
          </div>
        </header>

        {/* Contenido de la página */}
        <main className="p-4 sm:p-6 lg:p-8 w-full">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
