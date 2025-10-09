import axios, { AxiosInstance, AxiosResponse } from 'axios'

// Configuración de la instancia de Axios
const resolvedBaseURL = (() => {
  const envBase = import.meta.env.VITE_API_URL
  if (envBase && typeof envBase === 'string') return envBase
  // Si estamos en localhost y no hay VITE_API_URL, apuntar a producción
  if (window.location.hostname === 'localhost') return 'https://spin2pay.com/api'
  return '/api'
})()

const api: AxiosInstance = axios.create({
  baseURL: resolvedBaseURL,
  timeout: 30000, // Aumentar timeout a 30 segundos
  withCredentials: true, // Enviar cookies (auth_token) en peticiones al backend
  headers: {
    'Content-Type': 'application/json',
  },
})

// Interceptor para agregar token de autenticación
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token') // Usar auth_token en lugar de token
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Interceptor para manejar errores globalmente
api.interceptors.response.use(
  (response: AxiosResponse) => response,
  (error) => {
    console.log('Error en API interceptor:', error)

    // Construir mensaje detallado desde el servidor si está disponible
    const serverMessage = error?.response?.data?.message
      || error?.response?.data?.error
      || (typeof error?.response?.data === 'string' ? error.response.data : null)

    // Verificar si es un error de red o timeout
    if (!error.response) {
      console.log('Error de red o timeout - no hacer logout')
      return Promise.reject({
        ...error,
        isNetworkError: true,
        status: 0,
        message: 'Error de conexión con el servidor',
        originalError: error
      })
    }

    // Obtener la URL del endpoint que falló
    const failedUrl = error.config?.url || ''

    // Crear una lista de endpoints críticos que nunca deben causar logout
    const criticalEndpoints = [
      '/leads.php',
      '/users.php',
      '/desks.php',
      '/user-permissions.php',
      'leads-filters',
      'user-profile'
    ]

    // Verificar si el endpoint que falló es crítico
    const isCriticalEndpoint = criticalEndpoints.some(endpoint => failedUrl.includes(endpoint))

    // Para errores 401 (Unauthorized)
    if (error.response?.status === 401) {
      console.log('Error 401 detectado en:', failedUrl)

      const errorPayload = {
        ...error,
        isDataError: true,
        endpoint: failedUrl,
        status: 401,
        responseData: error.response?.data,
        message: serverMessage || 'No autorizado: revisa encabezado Authorization y el token'
      }

      // Si es un endpoint crítico, NUNCA hacer logout
      if (isCriticalEndpoint) {
        console.log('Error en endpoint crítico - no hacer logout automático')
        return Promise.reject(errorPayload)
      }

      // Para cualquier otro error 401, también mantener la sesión por ahora
      console.log('Error 401 en endpoint no crítico - manteniendo sesión')
      return Promise.reject(errorPayload)
    }

    // Para cualquier otro tipo de error
    return Promise.reject({
      ...error,
      status: error.response?.status,
      responseData: error.response?.data,
      message: serverMessage || (error.response?.data?.message || 'Error en la solicitud'),
      originalError: error
    })
  }
)

// API de autenticación
export const authApi = {
  login: async (username: string, password: string, remember: boolean = false) => {
    console.log('API: Iniciando login para:', username)
    console.log('API: URL completa:', `${window.location.origin}/api/auth/login.php`)
    
    try {
      const response = await api.post('auth/login.php', { 
        username, 
        password, 
        remember 
      })
      console.log('API: Login exitoso - Status:', response.status)
      console.log('API: Data recibida:', response.data)
      
      return response.data
    } catch (error: any) {
      console.error('API: Error en login:', error)
      
      // Si hay una respuesta del servidor con error, usar esa respuesta
      if (error.response && error.response.data) {
        console.log('API: Respuesta de error del servidor:', error.response.data)
        return error.response.data
      }
      
      // Error de red o conexión
      throw new Error('No se puede conectar con el servidor')
    }
  },

  verifyToken: async (token: string) => {
    console.log('Verificando token')
    try {
      // Usar un timeout más largo para verificación de token
      const response = await api.get(`auth/verify.php?token=${encodeURIComponent(token)}` , {
        headers: {
          'Authorization': `Bearer ${token}`
        },
        timeout: 10000 // 10 segundos para evitar timeouts prematuros
      })
      console.log('Token verificado:', response.data)
      return response.data
    } catch (error: any) {
      console.error('Error verificando token:', error)
      
      // Si el error es de timeout o conexión, no invalidar sesión
      if (error.code === 'ECONNABORTED' || !error.response) {
        console.log('Error de conexión en verificación de token - no invalidar sesión')
        // Devolver respuesta simulada para evitar logout
        return {
          success: true,
          user: JSON.parse(localStorage.getItem('user') || '{}')
        }
      }

      // Manejar 401 explícitamente y devolver respuesta estructurada
      if (error.response?.status === 401) {
        const serverMessage = error?.response?.data?.message || 'Token inválido o expirado'
        return {
          success: false,
          message: serverMessage
        }
      }
      
      throw error
    }
  },

  logout: async () => {
    console.log('Cerrando sesión')
    try {
      const token = localStorage.getItem('auth_token')
      if (token) {
        const response = await api.post('auth/logout.php', {}, {
          headers: {
            'Authorization': `Bearer ${token}`
          }
        })
        console.log('Logout exitoso:', response.data)
        return response.data
      }
    } catch (error) {
      console.error('Error en logout:', error)
      throw error
    }
  },

  register: async (data: any) => {
    console.log('Registrando usuario:', data)
    try {
      const response = await api.post('auth/register.php', data)
      console.log('Registro exitoso:', response.data)
      return response.data
    } catch (error) {
      console.error('Error en registro:', error)
      throw error
    }
  }
}

// API de estadísticas de empleados
export const employeeStatsApi = {
  getStats: async (period: string = '30d') => {
    console.log('Obteniendo estadísticas de empleados para período:', period)
    try {
      const response = await api.get(`employee-stats.php?period=${period}`)
      console.log('Estadísticas obtenidas:', response.data)
      return response.data
    } catch (error) {
      console.error('Error obteniendo estadísticas:', error)
      throw error
    }
  },

  getActivities: async (limit: number = 10) => {
    console.log('Obteniendo actividades de empleados, límite:', limit)
    try {
      const response = await api.get(`employee-activities.php?limit=${limit}`)
      console.log('Actividades obtenidas:', response.data)
      return response.data
    } catch (error) {
      console.error('Error obteniendo actividades:', error)
      throw error
    }
  }
}

// API de roles
export const rolesApi = {
  getAll: async (params: any = {}) => {
    console.log('Obteniendo roles con parámetros:', params)
    try {
      const queryParams = new URLSearchParams()
      Object.keys(params).forEach(key => {
        if (params[key] !== undefined && params[key] !== null && params[key] !== '') {
          queryParams.append(key, params[key])
        }
      })
      
      const url = `roles.php${queryParams.toString() ? '?' + queryParams.toString() : ''}`
      console.log('URL de roles:', url)
      
      const response = await api.get(url)
      const res = response.data || {}

      // Normalizar colección de roles
      const rolesArray: any[] = Array.isArray(res?.data)
        ? res.data
        : Array.isArray(res?.data?.roles)
          ? res.data.roles
          : Array.isArray(res?.roles)
            ? res.roles
            : []

      // Normalizar paginación
      const paginationSrc = res?.pagination || res?.data?.pagination || {}
      const page = Number(paginationSrc.page ?? params.page ?? 1)
      const limit = Number(paginationSrc.limit ?? params.limit ?? 20)
      const total = Number(paginationSrc.total ?? (Array.isArray(rolesArray) ? rolesArray.length : 0))
      const pages = Number(
        paginationSrc.pages ?? (total && limit ? Math.ceil(total / limit) : 0)
      )

      return {
        data: rolesArray,
        pagination: {
          page,
          limit,
          total,
          pages,
          total_pages: Number(paginationSrc.total_pages ?? pages)
        }
      }
    } catch (error) {
      console.error('Error obteniendo roles:', error)
      // Fallback seguro para no romper la UI
      return {
        data: [],
        pagination: {
          page: Number(params?.page ?? 1),
          limit: Number(params?.limit ?? 20),
          total: 0,
          pages: 0,
          total_pages: 0
        }
      }
    }
  },

  getById: async (id: number) => {
    console.log('Obteniendo rol por ID:', id)
    try {
      const response = await api.get(`roles.php?id=${id}`)
      console.log('Rol obtenido:', response.data)
      return response.data
    } catch (error) {
      console.error('Error obteniendo rol:', error)
      throw error
    }
  },

  getPermissions: async () => {
    console.log('Obteniendo permisos disponibles')
    try {
      const response = await api.get('roles.php?scope=permissions')
      // El backend responde con { success: true, data: Permission[] }
      const permissions = response.data?.data || []
      console.log('Permisos obtenidos (normalizados):', permissions)
      return permissions
    } catch (error) {
      console.error('Error obteniendo permisos:', error)
      // Fallback seguro
      return []
    }
  },

  create: async (data: any) => {
    console.log('Creando rol:', data)
    try {
      const response = await api.post('roles.php', data)
      console.log('Rol creado:', response.data)
      return response.data
    } catch (error) {
      console.error('Error creando rol:', error)
      throw error
    }
  },

  update: async (id: number, data: any) => {
    console.log('Actualizando rol:', id, data)
    try {
      const response = await api.put(`roles.php?id=${id}`, data)
      console.log('Rol actualizado:', response.data)
      return response.data
    } catch (error) {
      console.error('Error actualizando rol:', error)
      throw error
    }
  },

  delete: async (id: number) => {
    console.log('Eliminando rol:', id)
    try {
      const response = await api.delete(`roles.php?id=${id}`)
      console.log('Rol eliminado:', response.data)
      return response.data
    } catch (error) {
      console.error('Error eliminando rol:', error)
      throw error
    }
  },

  // Aliases para mantener compatibilidad con componentes existentes
  getRoles: async (params: any = {}) => {
    console.log('Obteniendo roles (alias) con parámetros:', params)
    try {
      const queryParams = new URLSearchParams()
      Object.keys(params).forEach(key => {
        if (params[key] !== undefined && params[key] !== null && params[key] !== '') {
          queryParams.append(key, params[key])
        }
      })
      const url = `roles.php${queryParams.toString() ? '?' + queryParams.toString() : ''}`
      console.log('URL de roles (alias):', url)
      const response = await api.get(url)
      const res = response.data || {}

      const rolesArray: any[] = Array.isArray(res?.data)
        ? res.data
        : Array.isArray(res?.data?.roles)
          ? res.data.roles
          : Array.isArray(res?.roles)
            ? res.roles
            : []

      const paginationSrc = res?.pagination || res?.data?.pagination || {}
      const page = Number(paginationSrc.page ?? params.page ?? 1)
      const limit = Number(paginationSrc.limit ?? params.limit ?? 20)
      const total = Number(paginationSrc.total ?? (Array.isArray(rolesArray) ? rolesArray.length : 0))
      const pages = Number(
        paginationSrc.pages ?? (total && limit ? Math.ceil(total / limit) : 0)
      )

      return {
        data: rolesArray,
        pagination: {
          page,
          limit,
          total,
          pages,
          total_pages: Number(paginationSrc.total_pages ?? pages)
        }
      }
    } catch (error) {
      console.error('Error obteniendo roles (alias):', error)
      // Fallback seguro
      return {
        data: [],
        pagination: {
          page: Number(params?.page ?? 1),
          limit: Number(params?.limit ?? 20),
          total: 0,
          pages: 0,
          total_pages: 0
        }
      }
    }
  },

  createRole: async (data: any) => {
    console.log('Creando rol (alias):', data)
    try {
      const response = await api.post('roles.php', data)
      console.log('Rol creado (alias):', response.data)
      return response.data
    } catch (error) {
      console.error('Error creando rol (alias):', error)
      throw error
    }
  },

  updateRole: async (id: number, data: any) => {
    console.log('Actualizando rol (alias):', id, data)
    try {
      const response = await api.put(`roles.php?id=${id}`, data)
      console.log('Rol actualizado (alias):', response.data)
      return response.data
    } catch (error) {
      console.error('Error actualizando rol (alias):', error)
      throw error
    }
  },

  deleteRole: async (id: number) => {
    console.log('Eliminando rol (alias):', id)
    try {
      const response = await api.delete(`roles.php?id=${id}`)
      console.log('Rol eliminado (alias):', response.data)
      return response.data
    } catch (error) {
      console.error('Error eliminando rol (alias):', error)
      throw error
    }
  }
}

// API de escritorios (desks)
export const desksApi = {
  // Obtiene escritorios con normalización de la respuesta para soportar distintas estructuras
  getDesks: async (params: any = {}) => {
    try {
      const queryParams = new URLSearchParams()
      Object.keys(params || {}).forEach(key => {
        if (params[key] !== undefined && params[key] !== null && params[key] !== '') {
          queryParams.append(key, params[key])
        }
      })

      const url = `desks.php${queryParams.toString() ? '?' + queryParams.toString() : ''}`
      const response = await api.get(url)
      const res = response.data || {}

      // Normalizar la colección de escritorios
      const desksArray: any[] = Array.isArray(res?.data)
        ? res.data
        : Array.isArray(res?.data?.desks)
          ? res.data.desks
          : Array.isArray(res?.desks)
            ? res.desks
            : []

      // Normalizar paginación
      const paginationSrc = res?.pagination || res?.data?.pagination || {}
      const page = Number(paginationSrc.page ?? params.page ?? 1)
      const limit = Number(paginationSrc.limit ?? params.limit ?? 20)
      const total = Number(paginationSrc.total ?? (Array.isArray(desksArray) ? desksArray.length : 0))
      const pages = Number(
        paginationSrc.pages ?? (total && limit ? Math.ceil(total / limit) : 0)
      )

      return {
        data: desksArray,
        pagination: {
          page,
          limit,
          total,
          pages,
          total_pages: Number(paginationSrc.total_pages ?? pages)
        },
        stats: res?.stats
      }
    } catch (error: any) {
      // Respuesta segura ante errores para evitar fallos en componentes que esperan arreglo
      return {
        data: [],
        pagination: {
          page: Number(params?.page ?? 1),
          limit: Number(params?.limit ?? 20),
          total: 0,
          pages: 0,
          total_pages: 0
        },
        stats: undefined
      }
    }
  },
  getAll: async (params: any = {}) => {
    console.log('Obteniendo escritorios con parámetros:', params)
    try {
      const queryParams = new URLSearchParams()
      Object.keys(params).forEach(key => {
        if (params[key] !== undefined && params[key] !== null && params[key] !== '') {
          queryParams.append(key, params[key])
        }
      })
      
      const url = `desks.php${queryParams.toString() ? '?' + queryParams.toString() : ''}`
      console.log('URL de escritorios:', url)
      
      const response = await api.get(url)
      console.log('Escritorios obtenidos:', response.data)
      return response.data
    } catch (error: any) {
      console.error('Error obteniendo escritorios:', error)
      // Devolver un objeto de respuesta con error para evitar fallos en cascada
      return { 
        success: false, 
        message: error.isNetworkError ? 'Error de conexión al servidor' : 'Error obteniendo mesas',
        data: [],
        total: 0
      }
    }
  },

  getById: async (id: number) => {
    console.log('Obteniendo escritorio por ID:', id)
    try {
      const response = await api.get(`desks.php?id=${id}`)
      console.log('Escritorio obtenido:', response.data)
      return response.data
    } catch (error) {
      console.error('Error obteniendo escritorio:', error)
      throw error
    }
  },

  create: async (data: any) => {
    console.log('Creando escritorio:', data)
    try {
      const token = localStorage.getItem('auth_token')
      const payload = token ? { ...data, token } : data
      const response = await api.post('desks.php', payload)
      console.log('Escritorio creado:', response.data)
      return response.data
    } catch (error) {
      console.error('Error creando escritorio:', error)
      // Propagar el mensaje real del servidor si existe
      if ((error as any)?.response?.data) {
        return (error as any).response.data
      }
      throw error
    }
  },

  update: async (id: number, data: any) => {
    console.log('Actualizando escritorio:', id, data)
    try {
      const response = await api.put(`desks.php?id=${id}`, data)
      console.log('Escritorio actualizado:', response.data)
      return response.data
    } catch (error) {
      console.error('Error actualizando escritorio:', error)
      if ((error as any)?.response?.data) {
        return (error as any).response.data
      }
      throw error
    }
  },

  delete: async (id: number) => {
    console.log('Eliminando escritorio:', id)
    try {
      const response = await api.delete(`desks.php?id=${id}`)
      console.log('Escritorio eliminado:', response.data)
      return response.data
    } catch (error) {
      console.error('Error eliminando escritorio:', error)
      if ((error as any)?.response?.data) {
        return (error as any).response.data
      }
      throw error
    }
  }
}

// Aliases para compatibilidad con componentes que usan nombres antiguos
// getDesks, createDesk, updateDesk, deleteDesk
// Mantienen el mismo comportamiento que los métodos principales
export const desksApiLegacy = {
  getDesks: async (params: any = {}) => {
    const queryParams = new URLSearchParams()
    Object.keys(params || {}).forEach(key => {
      if (params[key] !== undefined && params[key] !== null && params[key] !== '') {
        queryParams.append(key, params[key])
      }
    })
    const url = `desks.php${queryParams.toString() ? '?' + queryParams.toString() : ''}`
    const response = await api.get(url)
    return response.data
  },
  createDesk: async (data: any) => {
    const token = localStorage.getItem('auth_token')
    const payload = token ? { ...data, token } : data
    const response = await api.post('desks.php', payload)
    return response.data
  },
  updateDesk: async (id: number, data: any) => {
    const response = await api.put(`desks.php?id=${id}`, data)
    return response.data
  },
  deleteDesk: async (id: number) => {
    const response = await api.delete(`desks.php?id=${id}`)
    return response.data
  }
}

// Compatibilidad: fusionar métodos legacy en desksApi si no existen
;(desksApi as any).getDesks = (desksApi as any).getDesks || (desksApiLegacy as any).getDesks
;(desksApi as any).createDesk = (desksApi as any).createDesk || (desksApiLegacy as any).createDesk
;(desksApi as any).updateDesk = (desksApi as any).updateDesk || (desksApiLegacy as any).updateDesk
;(desksApi as any).deleteDesk = (desksApi as any).deleteDesk || (desksApiLegacy as any).deleteDesk

// API para leads
export const leadsApi = {
  getLeads: async (params?: any) => {
    console.log('Obteniendo leads con parámetros:', params)
    try {
      const queryParams = new URLSearchParams()
      if (params) {
        Object.keys(params).forEach(key => {
          if (params[key]) queryParams.append(key, params[key])
        })
      }
      
      const url = `leads_simple.php${queryParams.toString() ? '?' + queryParams.toString() : ''}`
      console.log('URL de leads:', url)
      
      const response = await api.get(url, {
        timeout: 15000 // Aumentar timeout para leads
      })
      console.log('Respuesta de leads recibida:', response.data)
      return response.data
    } catch (error: any) {
      console.error('Error obteniendo leads:', error)
      // Devolver un objeto de respuesta con error para evitar fallos en cascada
      return { 
        success: false, 
        message: error.isNetworkError ? 'Error de conexión al servidor' : 'Error obteniendo leads',
        data: [],
        total: 0
      }
    }
  },

  getLead: async (id: number) => {
    console.log('Obteniendo lead individual:', id)
    try {
      const response = await api.get(`leads_simple.php?id=${id}`)
      console.log('Respuesta de lead individual:', response.data)
      return response.data.data
    } catch (error) {
      console.error('Error obteniendo lead:', error)
      throw error
    }
  },

  createLead: async (data: any) => {
    console.log('Creando lead:', data)
    try {
      const response = await api.post('leads_simple.php', data)
      console.log('Lead creado:', response.data)
      return response.data
    } catch (error) {
      console.error('Error creando lead:', error)
      throw error
    }
  },

  update: async (id: number, data: any) => {
    console.log('Actualizando lead:', id, data)
    try {
      const response = await api.put(`leads.php?id=${id}`, data)
      console.log('Lead actualizado:', response.data)
      return response.data
    } catch (error) {
      console.error('Error actualizando lead:', error)
      throw error
    }
  },

  delete: async (id: number) => {
    console.log('Eliminando lead:', id)
    try {
      const response = await api.delete(`leads.php?id=${id}`)
      console.log('Lead eliminado:', response.data)
      return response.data
    } catch (error) {
      console.error('Error eliminando lead:', error)
      throw error
    }
  },

  import: async (data: any) => {
    console.log('Importando leads:', data)
    try {
      const response = await api.post('leads.php?action=import', data)
      console.log('Leads importados:', response.data)
      return response.data
    } catch (error) {
      console.error('Error importando leads:', error)
      throw error
    }
  },

  getByEmail: async (email: string) => {
    console.log('Buscando lead por email:', email)
    try {
      const response = await api.get(`lead-trading-link.php?action=find_lead&email=${encodeURIComponent(email)}`)
      console.log('Lead encontrado por email:', response.data)
      return response.data
    } catch (error) {
      console.error('Error buscando lead por email:', error)
      throw error
    }
  },

  bulkAssign: async (leadIds: number[], assignments: any[]) => {
    console.log('Asignación masiva de leads:', { leadIds, assignments })
    try {
      const response = await api.post('bulk-assign-leads.php', {
        leadIds,
        assignments
      })
      console.log('Asignación masiva completada:', response.data)
      return response.data
    } catch (error: any) {
      console.error('Error en asignación masiva:', error)
      
      // Verificar si la respuesta es HTML (error de sintaxis)
      if (error.response?.data && typeof error.response.data === 'string' && error.response.data.includes('<')) {
        throw new Error('Error del servidor: Respuesta HTML inesperada')
      }
      
      throw error
    }
  }
}

// Compatibilidad: alias legacy para métodos de leads que pueda usar la UI
;(leadsApi as any).updateLead = (leadsApi as any).updateLead || (leadsApi as any).update
// Nota: el alias de getLeadAccounts se declara más abajo, después de definir leadTradingLinkApi para evitar TDZ

// API para usuarios
export const usersApi = {
  // Obtiene usuarios devolviendo siempre una estructura paginada homogénea
  getUsers: async (params?: Record<string, any>) => {
    console.log('Obteniendo usuarios con filtros:', params)
    try {
      const queryParams = new URLSearchParams()
      if (params) {
        Object.keys(params).forEach(key => {
          if (params[key] !== undefined && params[key] !== null && params[key] !== '') {
            queryParams.append(key, params[key])
          }
        })
      }

      const url = `users.php${queryParams.toString() ? '?' + queryParams.toString() : ''}`
      console.log('URL de usuarios:', url)

      const response = await api.get(url)
      const res = response.data || {}
      console.log('Usuarios obtenidos:', res)

      // Normalizar arreglo de usuarios
      const usersArray: any[] = Array.isArray(res?.data)
        ? res.data
        : Array.isArray(res?.data?.users)
          ? res.data.users
          : Array.isArray(res?.users)
            ? res.users
            : []

      // Normalizar paginación
      const paginationSrc = res?.pagination || res?.data?.pagination || {}
      const page = Number(paginationSrc.page ?? params?.page ?? 1)
      const limit = Number(paginationSrc.limit ?? params?.limit ?? 20)
      const total = Number(paginationSrc.total ?? (Array.isArray(usersArray) ? usersArray.length : 0))
      const pages = Number(paginationSrc.pages ?? (total && limit ? Math.ceil(total / limit) : 0))

      return {
        data: usersArray,
        pagination: {
          page,
          limit,
          total,
          pages,
          total_pages: Number(paginationSrc.total_pages ?? pages)
        }
      }
    } catch (error: any) {
      console.error('Error obteniendo usuarios:', error)
      // Estructura segura ante errores para evitar fallos al hacer .map
      return {
        data: [],
        pagination: {
          page: Number(params?.page ?? 1),
          limit: Number(params?.limit ?? 20),
          total: 0,
          pages: 0,
          total_pages: 0
        }
      }
    }
  },

  createUser: async (data: any) => {
    console.log('Creando usuario:', data)
    try {
      const response = await api.post('users.php', data)
      console.log('Usuario creado:', response.data)
      return response.data
    } catch (error) {
      console.error('Error creando usuario:', error)
      throw error
    }
  },

  updateUser: async (id: number, data: any) => {
    console.log('Actualizando usuario:', id, data)
    try {
      const response = await api.put(`users.php?id=${id}`, data)
      console.log('Usuario actualizado:', response.data)
      return response.data
    } catch (error) {
      console.error('Error actualizando usuario:', error)
      throw error
    }
  },

  deleteUser: async (id: number) => {
    console.log('Eliminando usuario:', id)
    try {
      const response = await api.delete(`users.php?id=${id}`)
      console.log('Usuario eliminado:', response.data)
      return response.data
    } catch (error) {
      console.error('Error eliminando usuario:', error)
      throw error
    }
  },

  assignDesk: async (userId: number, deskId: number, isPrimary: boolean = true) => {
    console.log('Asignando desk al usuario:', { userId, deskId, isPrimary })
    try {
      const response = await api.put(`users.php?id=${userId}`, {
        action: 'assign_desk',
        desk_id: deskId,
        is_primary: isPrimary
      })
      console.log('Desk asignado:', response.data)
      return response.data
    } catch (error) {
      console.error('Error asignando desk:', error)
      throw error
    }
  },

  assignRole: async (userId: number, roleId: number) => {
    console.log('Asignando rol al usuario:', { userId, roleId })
    try {
      const response = await api.put(`users.php?id=${userId}`, {
        action: 'assign_role',
        role_id: roleId
      })
      console.log('Rol asignado:', response.data)
      return response.data
    } catch (error) {
      console.error('Error asignando rol:', error)
      throw error
    }
  },

  toggleStatus: async (userId: number) => {
    console.log('Cambiando estado del usuario:', userId)
    try {
      const response = await api.put(`users.php?id=${userId}`, {
        action: 'toggle_status'
      })
      console.log('Estado cambiado:', response.data)
      return response.data
    } catch (error) {
      console.error('Error cambiando estado:', error)
      throw error
    }
  }
}

// API para cuentas de trading
export const tradingAccountsApi = {
  getTradingAccounts: async (params?: any) => {
    console.log('Obteniendo cuentas de trading con parámetros:', params)
    try {
      const queryParams = new URLSearchParams()
      if (params) {
        Object.keys(params).forEach(key => {
          if (params[key]) queryParams.append(key, params[key])
        })
      }
      
      const url = `trading-accounts.php${queryParams.toString() ? '?' + queryParams.toString() : ''}`
      console.log('URL de cuentas de trading:', url)
      
      const response = await api.get(url)
      console.log('Respuesta de cuentas de trading recibida:', response.data)
      return response.data
    } catch (error) {
      console.error('Error obteniendo cuentas de trading:', error)
      throw error
    }
  },

  getTradingAccount: async (id: number) => {
    console.log('Obteniendo cuenta de trading individual:', id)
    try {
      const response = await api.get(`trading-accounts.php?id=${id}`)
      console.log('Respuesta de cuenta de trading individual:', response.data)
      return response.data.data
    } catch (error) {
      console.error('Error obteniendo cuenta de trading:', error)
      throw error
    }
  },

  getAccountTypes: async () => {
    console.log('Obteniendo tipos de cuenta disponibles')
    try {
      const response = await api.get('trading-accounts.php?types=1')
      console.log('Tipos de cuenta obtenidos:', response.data)
      return response.data
    } catch (error) {
      console.error('Error obteniendo tipos de cuenta:', error)
      throw error
    }
  },

  createTradingAccount: async (data: any) => {
    console.log('Creando cuenta de trading:', data)
    try {
      const response = await api.post('trading-accounts.php', data)
      console.log('Cuenta de trading creada:', response.data)
      return response.data
    } catch (error: any) {
      console.error('Error creando cuenta de trading:', error)
      
      throw error
    }
  },

  updateTradingAccount: async (id: number, data: any) => {
    console.log('Actualizando cuenta de trading:', id, data)
    try {
      const response = await api.put(`trading-accounts.php?id=${id}`, data)
      console.log('Cuenta de trading actualizada:', response.data)
      return response.data
    } catch (error) {
      console.error('Error actualizando cuenta de trading:', error)
      throw error
    }
  },

  deleteTradingAccount: async (id: number) => {
    console.log('Eliminando cuenta de trading:', id)
    try {
      const response = await api.delete(`trading-accounts.php?id=${id}`)
      console.log('Cuenta de trading eliminada:', response.data)
      return response.data
    } catch (error) {
      console.error('Error eliminando cuenta de trading:', error)
      throw error
    }
  }
}

// API para importación de leads
export const leadImportApi = {
  getSystemFields: async () => {
    console.log('Obteniendo campos del sistema para mapeo')
    try {
      const response = await api.get('lead-import.php?fields=1')
      console.log('Campos del sistema obtenidos:', response.data)
      return response.data
    } catch (error) {
      console.error('Error obteniendo campos del sistema:', error)
      throw error
    }
  },

  getImportHistory: async () => {
    console.log('Obteniendo historial de importaciones')
    try {
      const response = await api.get('lead-import.php')
      console.log('Historial de importaciones obtenido:', response.data)
      return response.data
    } catch (error) {
      console.error('Error obteniendo historial de importaciones:', error)
      throw error
    }
  },

  analyzeFile: async (file: File) => {
    console.log('Analizando archivo:', file.name)
    try {
      const formData = new FormData()
      formData.append('file', file)
      
      const response = await api.post('lead-import.php?action=analyze', formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      })
      console.log('Archivo analizado:', response.data)
      return response.data
    } catch (error) {
      console.error('Error analizando archivo:', error)
      throw error
    }
  },

  importFile: async (data: any) => {
    console.log('Importando archivo de leads')
    try {
      const response = await api.post('lead-import.php?action=import', data)
      console.log('Archivo importado:', response.data)
      return response.data
    } catch (error) {
      console.error('Error importando archivo:', error)
      throw error
    }
  }
}

// API de depósitos y retiros
export const depositsWithdrawalsApi = {
  getMethods: async () => {
    console.log('Obteniendo métodos de pago disponibles')
    try {
      const response = await api.get('deposits-withdrawals.php?methods=1')
      console.log('Métodos obtenidos:', response.data)
      return response.data
    } catch (error) {
      console.error('Error obteniendo métodos de pago:', error)
      throw error
    }
  },

  create: async (data: any) => {
    console.log('Creando depósito/retiro:', data)
    try {
      const response = await api.post('deposits-withdrawals.php', data)
      console.log('Operación creada:', response.data)
      return response.data
    } catch (error) {
      console.error('Error creando depósito/retiro:', error)
      throw error
    }
  }
}

// API de dashboard y enlaces trading-lead
export const dashboardApi = {
  // Retorna sólo el payload de estadísticas para uso directo en React Query
  getStats: async () => {
    console.log('Obteniendo estadísticas del dashboard')
    try {
      const response = await api.get('dashboard.php')
      const stats = response.data?.data ?? response.data
      console.log('Estadísticas del dashboard obtenidas:', stats)
      return stats
    } catch (error) {
      console.error('Error obteniendo estadísticas del dashboard:', error)
      throw error
    }
  },

  // Método alternativo que devuelve la respuesta completa
  getDashboard: async () => {
    console.log('Obteniendo datos de dashboard (respuesta completa)')
    try {
      const response = await api.get('dashboard.php')
      console.log('Dashboard obtenido:', response.data)
      return response.data
    } catch (error) {
      console.error('Error obteniendo dashboard:', error)
      throw error
    }
  }
}

export const leadTradingLinkApi = {
  autoLink: async (leadId: number) => {
    console.log('Auto-vinculando lead con cuentas de trading:', leadId)
    try {
      const response = await api.post('lead-trading-link.php?action=auto_link', {
        lead_id: leadId
      })
      console.log('Auto-link completado:', response.data)
      return response.data
    } catch (error) {
      console.error('Error en auto-link:', error)
      throw error
    }
  },

  manualLink: async (leadId: number, accountId: number) => {
    console.log('Vinculación manual:', { leadId, accountId })
    try {
      const response = await api.post('lead-trading-link.php?action=manual_link', {
        lead_id: leadId,
        account_id: accountId
      })
      console.log('Manual-link completado:', response.data)
      return response.data
    } catch (error) {
      console.error('Error en manual-link:', error)
      throw error
    }
  },

  getLeadAccounts: async (leadId: number) => {
    console.log('Obteniendo cuentas vinculadas al lead:', leadId)
    try {
      const response = await api.get(`lead-trading-link.php?action=lead_accounts&lead_id=${leadId}`)
      console.log('Cuentas de lead:', response.data)
      return response.data
    } catch (error) {
      console.error('Error obteniendo cuentas del lead:', error)
      throw error
    }
  },

  unlinkAccount: async (accountId: number) => {
    console.log('Desvinculando cuenta de trading del lead:', accountId)
    try {
      const response = await api.post('lead-trading-link.php?action=unlink_account', {
        account_id: accountId
      })
      console.log('Cuenta desvinculada:', response.data)
      return response.data
    } catch (error) {
      console.error('Error desvinculando cuenta:', error)
      throw error
    }
  }
}

// Alias tardío para evitar ReferenceError por uso antes de inicialización (TDZ)
;(leadsApi as any).getLeadAccounts = (leadsApi as any).getLeadAccounts || (leadTradingLinkApi as any).getLeadAccounts

// API para resumen del empleado (estadísticas y actividades)
export const employeeApi = {
  getStats: async (period: string = 'month') => {
    console.log('Obteniendo estadísticas del empleado:', { period })
    try {
      const response = await api.get(`employee-stats.php?period=${encodeURIComponent(period)}`)
      console.log('Estadísticas del empleado obtenidas:', response.data)
      return response.data
    } catch (error) {
      console.error('Error obteniendo estadísticas del empleado:', error)
      throw error
    }
  },

  getActivities: async (limit: number = 10) => {
    console.log('Obteniendo actividades del empleado:', { limit })
    try {
      const response = await api.get(`employee-activities.php?limit=${encodeURIComponent(String(limit))}`)
      console.log('Actividades del empleado obtenidas:', response.data)
      return response.data
    } catch (error) {
      console.error('Error obteniendo actividades del empleado:', error)
      throw error
    }
  }
}

// API para WebTrader (instruments, candles, orders)
export const webTraderApi = {
  getInstruments: async () => {
    console.log('Obteniendo instrumentos de WebTrader')
    try {
      const response = await api.get('webtrader-data.php?endpoint=instruments')
      console.log('Instrumentos obtenidos:', response.data)
      return response.data
    } catch (error) {
      console.error('Error obteniendo instrumentos:', error)
      // Retorno seguro para no romper la UI si falla
      return { success: false, data: {} }
    }
  },

  // Precios: el backend indica que los precios vienen por WebSocket.
  // Devolvemos estructura vacía para que la UI continúe usando el feed en tiempo real.
  getPrices: async () => {
    console.log('Solicitando precios de WebTrader (placeholder)')
    try {
      const response = await api.get('webtrader-data.php?endpoint=prices')
      return response.data?.data ? response.data : { success: true, data: {} }
    } catch (error) {
      console.warn('No se pudieron obtener precios del endpoint, usando estructura vacía:', error)
      return { success: true, data: {} }
    }
  },

  getCandles: async (symbol: string, timeframe: string = 'M5', count: number = 100) => {
    console.log('Obteniendo velas OHLC:', { symbol, timeframe, count })
    try {
      const timeframeMap: Record<string, number> = { M1: 1, M5: 5, M15: 15, M30: 30, H1: 60, H4: 240, D1: 1440 }
      const minutes = timeframeMap[timeframe] ?? 5
      const days = Math.max(1, Math.ceil((count * minutes) / (60 * 24)))
      const url = `candles-data.php?endpoint=ohlc&symbol=${encodeURIComponent(symbol)}&timeframe=${encodeURIComponent(timeframe)}&days=${days}`
      const response = await api.get(url)
      console.log('Velas OHLC obtenidas:', response.data)
      return response.data
    } catch (error) {
      console.error('Error obteniendo velas OHLC:', error)
      throw error
    }
  },

  getOrders: async (accountId: number) => {
    console.log('Obteniendo órdenes abiertas:', { accountId })
    try {
      const response = await api.get(`webtrader-data.php?endpoint=orders&account_id=${accountId}`)
      console.log('Órdenes obtenidas:', response.data)
      return response.data
    } catch (error) {
      console.error('Error obteniendo órdenes:', error)
      throw error
    }
  },

  getAccountInfo: async (accountId: number) => {
    console.log('Obteniendo información de la cuenta:', { accountId })
    try {
      const response = await api.get(`webtrader-data.php?endpoint=account_info&account_id=${accountId}`)
      console.log('Información de la cuenta obtenida:', response.data)
      return response.data
    } catch (error) {
      console.error('Error obteniendo información de la cuenta:', error)
      throw error
    }
  },

  createOrder: async (orderData: any) => {
    console.log('Creando orden de trading:', orderData)
    try {
      const response = await api.post('webtrader-data.php?endpoint=create_order', orderData)
      console.log('Orden creada:', response.data)
      return response.data
    } catch (error) {
      console.error('Error creando orden:', error)
      throw error
    }
  },

  closeOrder: async (orderId: number) => {
    console.log('Cerrando orden:', { orderId })
    try {
      const response = await api.post('webtrader-data.php?endpoint=close_order', { order_id: orderId })
      console.log('Orden cerrada:', response.data)
      return response.data
    } catch (error) {
      console.error('Error cerrando orden:', error)
      throw error
    }
  }
}
