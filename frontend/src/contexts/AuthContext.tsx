import { createContext, useContext, useState, useEffect, ReactNode } from 'react'
import { authApi } from '../services/api'
import authService from '../services/authService'
import toast from 'react-hot-toast'

interface User {
  id: number
  username: string
  email: string
  first_name: string
  last_name: string
  phone?: string
  avatar?: string
  department?: string
  position?: string
  desk?: {
    id: number | null
    name: string | null
  }
  supervisor?: {
    name: string
  }
  roles: string[]
  role_names: string[]
  permissions: string[]
  status: string
  settings: Record<string, any>
}

interface AuthContextType {
  user: User | null
  token: string | null
  isAuthenticated: boolean
  isLoading: boolean
  login: (username: string, password: string, remember?: boolean) => Promise<boolean>
  logout: (onNavigate?: () => void) => void
  refreshUser: () => Promise<void>
  hasPermission: (permission: string) => boolean
  hasRole: (role: string) => boolean
  updateUserSettings: (settings: Record<string, any>) => void
}

const AuthContext = createContext<AuthContextType | undefined>(undefined)

interface AuthProviderProps {
  children: ReactNode
}

export function AuthProvider({ children }: AuthProviderProps) {
  const [user, setUser] = useState<User | null>(null)
  const [token, setToken] = useState<string | null>(null)
  const [isLoading, setIsLoading] = useState(true)

  const isAuthenticated = !!user && !!token

  // Verificar token al cargar la aplicación
  useEffect(() => {
    const initAuth = async () => {
      const storedToken = localStorage.getItem('auth_token')
      
      if (storedToken) {
        try {
          // Intentar usar datos almacenados en localStorage primero
          const storedUser = localStorage.getItem('user')
          if (storedUser) {
            try {
              const parsedUser = JSON.parse(storedUser)
              setUser(parsedUser)
              setToken(storedToken)
              console.log('Usando datos de usuario desde localStorage')
            } catch (e) {
              console.error('Error parseando usuario de localStorage:', e)
            }
          }
          
          // Intentar verificar token en paralelo
          const response = await authApi.verifyToken(storedToken)
          if (response.success) {
            setToken(storedToken)
            setUser(response.user)
            // Guardar usuario en localStorage como respaldo para reconexiones
            localStorage.setItem('user', JSON.stringify(response.user))
            
            // IMPORTANTE: Cargar el perfil completo con permisos después de verificar el token
            if (authService) {
              try {
                console.log('Cargando perfil completo del usuario...')
                const profileLoaded = await authService.loadUserProfile()
                if (profileLoaded) {
                  console.log('Perfil cargado exitosamente con permisos completos')
                  // Actualizar el usuario con los datos completos del perfil
                  const completeUserData = {
                    ...response.user,
                    permissions: authService.permissions || [],
                    roles: authService.roles || []
                  }
                  setUser(completeUserData)
                  localStorage.setItem('user', JSON.stringify(completeUserData))
                } else {
                  console.warn('No se pudo cargar el perfil completo, usando datos básicos del token')
                }
              } catch (profileError) {
                console.error('Error cargando perfil después de verificar token:', profileError)
                // Continuar con los datos básicos del token, no fallar completamente
              }
            } else {
              console.warn('authService no está disponible, usando datos básicos del token')
            }
          } else {
            // Token inválido, limpiar storage SOLO si hay respuesta explícita de error
            console.log('Token inválido según servidor, limpiando...')
            localStorage.removeItem('auth_token')
            toast.error('Tu sesión ha expirado. Inicia sesión nuevamente.')
          }
        } catch (error) {
          console.error('Error verificando token:', error)
          // NUNCA eliminar token en caso de error de red o timeout
          console.log('Error de red/timeout, manteniendo token')
          // Mantener la sesión con datos de localStorage
          // Intentar cargar el perfil completo para recuperar permisos/roles
          try {
            if (authService) {
              console.log('Intentando cargar perfil pese al error de verificación...')
              const profileLoaded = await authService.loadUserProfile()
              if (profileLoaded) {
                console.log('Perfil cargado exitosamente tras fallback de red')
                const storedUser = localStorage.getItem('user')
                const baseUser = storedUser ? JSON.parse(storedUser) : null
                const completeUserData = {
                  ...(baseUser || {}),
                  permissions: authService.permissions || [],
                  roles: authService.roles || []
                }
                if (baseUser) {
                  setUser(completeUserData)
                  localStorage.setItem('user', JSON.stringify(completeUserData))
                }
              } else {
                console.warn('No se pudo cargar el perfil en fallback; usando datos básicos si existen')
              }
            }
          } catch (profileError) {
            console.error('Error cargando perfil en fallback:', profileError)
          }
        }
      }
      
      setIsLoading(false)
    }

    initAuth()
  }, [])

  const login = async (username: string, password: string, remember: boolean = false): Promise<boolean> => {
    try {
      setIsLoading(true)
      console.log('AuthContext: Iniciando login para', username)
      
      const response = await authApi.login(username, password, remember)
      console.log('AuthContext: Respuesta recibida:', response)
      
      if (response && response.success) {
        console.log('AuthContext: Login exitoso, guardando datos')
        setToken(response.token)
        setUser(response.user)
        localStorage.setItem('auth_token', response.token)
        try {
          localStorage.setItem('user', JSON.stringify(response.user))
        } catch (e) {
          console.error('No se pudo persistir el usuario en localStorage:', e)
        }

        // Establecer header y cargar perfil completo inmediatamente tras login
        try {
          if (authService) {
            authService.setAuthHeader(response.token)
            console.log('AuthContext: Cargando perfil completo post-login...')
            const profileLoaded = await authService.loadUserProfile()
            if (profileLoaded) {
              const completeUserData = {
                ...response.user,
                permissions: authService.permissions || [],
                roles: authService.roles || []
              }
              setUser(completeUserData)
              localStorage.setItem('user', JSON.stringify(completeUserData))
            } else {
              console.warn('AuthContext: No se pudo cargar el perfil completo post-login')
            }
          }
        } catch (profileError) {
          console.error('AuthContext: Error cargando perfil post-login:', profileError)
        }
        
        toast.success(`¡Bienvenido, ${response.user.first_name}!`)
        return true
      } else {
        console.log('AuthContext: Login fallido:', response)
        toast.error(response?.message || 'Error al iniciar sesión')
        return false
      }
    } catch (error: any) {
      console.error('AuthContext: Error en login:', error)
      const message = error.response?.data?.message || error.message || 'Error de conexión'
      toast.error(message)
      return false
    } finally {
      setIsLoading(false)
    }
  }

  const logout = async (onNavigate?: () => void) => {
    try {
      if (token) {
        await authApi.logout()
      }
    } catch (error) {
      console.error('Error al cerrar sesión:', error)
    } finally {
      setUser(null)
      setToken(null)
      localStorage.removeItem('auth_token')
      localStorage.removeItem('user')
      toast.success('Sesión cerrada exitosamente')
      
      // Redirección después de logout
      const targetLoginUrl = 'https://spin2pay.com/auth/login'
      if (onNavigate) {
        // Ignorar navegación interna en producción; usar redirección absoluta
        setTimeout(() => {
          window.location.href = targetLoginUrl
        }, 100)
      } else {
        // Fallback para casos donde no se puede usar useNavigate
        setTimeout(() => {
          window.location.replace(targetLoginUrl)
        }, 100)
      }
    }
  }

  const refreshUser = async () => {
    if (!token) return

    try {
      const response = await authApi.verifyToken(token)
      if (response.success) {
        setUser(response.user)
      } else {
        // Token inválido, cerrar sesión
        logout()
      }
    } catch (error) {
      console.error('Error refrescando usuario:', error)
      logout()
    }
  }

  const hasPermission = (permission: string): boolean => {
    if (!user) return false

    // 1) SuperAdmin/Admin siempre tienen acceso
    const userRolesRaw = user.roles || []
    const userRoleNames = user.role_names || []
    const hasAdminRole = [...userRolesRaw, ...userRoleNames].some((r: any) => {
      const name = typeof r === 'string' ? r : (r?.name || '')
      return name === 'admin' || name === 'super_admin'
    })
    if (hasAdminRole) return true

    // 2) Intentar con authService (usa mapeo interno)
    try {
      if (authService && authService.hasPermissionLocal(permission)) {
        return true
      }
    } catch (e) {
      console.warn('hasPermission: fallo en authService.hasPermissionLocal, usamos fallback del usuario', e)
    }

    // 3) Fallback: verificar permisos disponibles en user con mapeo
    const permissionMap: Record<string, string> = {
      'view_dashboard': 'dashboard.view',
      'view_leads': 'leads.view',
      'leads.view.assigned': 'leads.view',
      'create_leads': 'leads.create',
      'edit_leads': 'leads.edit',
      'delete_leads': 'leads.delete',
      'assign_leads': 'leads.assign',
      'import_leads': 'leads.import',
      'export_leads': 'leads.export',
      'view_users': 'users.view',
      'create_users': 'users.create',
      'edit_users': 'users.edit',
      'delete_users': 'users.delete',
      'view_roles': 'roles.view',
      'create_roles': 'roles.create',
      'edit_roles': 'roles.edit',
      'delete_roles': 'roles.delete',
      'manage_roles': 'roles.view',
      'view_desks': 'desks.view',
      'create_desks': 'desks.create',
      'edit_desks': 'desks.edit',
      'delete_desks': 'desks.delete',
      'manage_desks': 'desks.view',
      'view_trading': 'trading.view',
      'view_trading_accounts': 'trading_accounts.view',
      'create_trading': 'trading.create',
      'edit_trading': 'trading.edit',
      'delete_trading': 'trading.delete',
      'view_deposits_withdrawals': 'deposits_withdrawals.view',
      'approve_transactions': 'transactions.approve',
      'process_transactions': 'transactions.process',
      'view_reports': 'reports.view',
      'create_reports': 'reports.create',
      'manage_permissions': 'user_permissions.edit',
      'view_audit': 'system.audit',
      'manage_states': 'manage_states',
      'view_states': 'states.view'
    }

    const mapped = permissionMap[permission]
    const userPerms = user.permissions || []
    const normalizedUserPerms = userPerms.map((p: any) => (typeof p === 'string' ? p : (p?.name || p?.slug)))

    if (mapped) {
      return normalizedUserPerms.includes(permission) || normalizedUserPerms.includes(mapped)
    }
    return normalizedUserPerms.includes(permission)
  }

  const hasRole = (role: string): boolean => {
    if (!user) return false

    // Verificar roles locales (strings u objetos) y role_names
    const rolesRaw = user.roles || []
    const roleNames = user.role_names || []
    const hasLocalRole = [...rolesRaw, ...roleNames].some((r: any) => {
      const name = typeof r === 'string' ? r : (r?.name || '')
      return name === role
    })
    if (hasLocalRole) return true

    // Intentar con authService
    try {
      return !!authService && authService.hasRole(role)
    } catch {
      return false
    }
  }

  const updateUserSettings = (newSettings: Record<string, any>) => {
    if (!user) return
    
    const updatedUser = {
      ...user,
      settings: { ...user.settings, ...newSettings }
    }
    
    setUser(updatedUser)
    
    // Aquí podrías hacer una llamada a la API para persistir los cambios
    // await userApi.updateSettings(user.id, newSettings)
  }

  const value: AuthContextType = {
    user,
    token,
    isAuthenticated,
    isLoading,
    login,
    logout,
    refreshUser,
    hasPermission,
    hasRole,
    updateUserSettings
  }

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  const context = useContext(AuthContext)
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider')
  }
  return context
}

export { AuthContext }
export default AuthProvider
