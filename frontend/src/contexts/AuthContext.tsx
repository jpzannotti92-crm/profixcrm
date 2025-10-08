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
      toast.success('Sesión cerrada exitosamente')
      
      // Si se proporciona un callback de navegación, usarlo
      if (onNavigate) {
        setTimeout(() => {
          onNavigate()
        }, 100)
      } else {
        // Fallback para casos donde no se puede usar useNavigate
        setTimeout(() => {
          window.location.replace('/auth/login')
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
    
    // Usar el método del authService que incluye el mapeo completo
    if (!authService) {
      console.warn('authService no está disponible, verificando permisos desde el usuario')
      return user.permissions?.includes(permission) || false
    }
    
    return authService.hasPermissionLocal(permission)
  }

  const hasRole = (role: string): boolean => {
    if (!user) return false
    return user.roles.includes(role)
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
