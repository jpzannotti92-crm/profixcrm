import axios from 'axios';
import { configManager } from '../utils/config.ts';

class AuthService {
    constructor() {
        // Asegurar envío de cookies (auth_token) en peticiones CORS/proxy
        axios.defaults.withCredentials = true;

        this.token = localStorage.getItem('auth_token');
        this.user = null;
        this.permissions = [];
        this.roles = [];
        this.desks = [];
        this.apiBaseUrl = null;
        
        // Configurar axios con el token
        if (this.token) {
            this.setAuthHeader(this.token);
        }
        
        // Inicializar configuración
        this.initializeConfig();
    }

    async initializeConfig() {
        try {
            this.apiBaseUrl = await configManager.getApiUrl();
        } catch (error) {
            console.error('Error inicializando configuración:', error);
            // Fallback
            this.apiBaseUrl = `${window.location.origin}/api`;
        }
    }

    async getApiUrl() {
        if (!this.apiBaseUrl) {
            await this.initializeConfig();
        }
        return this.apiBaseUrl;
    }

    setAuthHeader(token) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        // Encabezado alternativo para máxima compatibilidad con proxies/servidores
        axios.defaults.headers.common['X-Auth-Token'] = token;
    }

    removeAuthHeader() {
        delete axios.defaults.headers.common['Authorization'];
        delete axios.defaults.headers.common['X-Auth-Token'];
    }

    // Asegura que el header Authorization esté presente antes de llamar endpoints protegidos
    ensureAuthHeader() {
        if (!this.token) {
            const stored = localStorage.getItem('auth_token');
            if (stored) {
                this.token = stored;
            }
        }
        if (this.token && !axios.defaults.headers.common['Authorization']) {
            this.setAuthHeader(this.token);
        }
        return !!this.token;
    }

    async login(username, password) {
        try {
            const apiUrl = await this.getApiUrl();
            // Evitar rutas con .php para que pasen WAF/proxies
            console.log('🔐 Intentando login con URL:', `${apiUrl}/auth/login`);
            
            const response = await axios.post(`${apiUrl}/auth/login`, {
                username,
                password,
                remember: true
            });

            console.log('✅ Respuesta de login:', response.data);

            if (response.data.success) {
                // Alinear parseo con backend: token y user en raíz
                this.token = response.data.token;
                this.user = response.data.user;
                
                localStorage.setItem('auth_token', this.token);
                try { localStorage.setItem('user', JSON.stringify(this.user)); } catch (e) {}
                this.setAuthHeader(this.token);
                
                console.log('🔄 Cargando perfil de usuario...');
                // Cargar permisos del usuario después del login
                const profileLoaded = await this.loadUserProfile();
                
                if (!profileLoaded) {
                    console.warn('⚠️ No se pudo cargar el perfil del usuario, pero el login fue exitoso');
                }
                
                return {
                    success: true,
                    user: this.user,
                    token: this.token
                };
            } else {
                console.error('❌ Login fallido:', response.data.message);
                return {
                    success: false,
                    message: response.data.message
                };
            }
        } catch (error) {
            console.error('💥 Error crítico en login:', error);
            console.error('📍 URL que falló:', error.config?.url);
            console.error('📊 Status:', error.response?.status);
            console.error('📝 Respuesta:', error.response?.data);
            
            return {
                success: false,
                message: error.response?.data?.message || 'Error de conexión al servidor'
            };
        }
    }

    async logout() {
        try {
            const apiUrl = await this.getApiUrl();
            await axios.post(`${apiUrl}/auth/logout.php`);
        } catch (error) {
            console.error('Error during logout:', error);
        } finally {
            this.token = null;
            this.user = null;
            this.permissions = [];
            this.roles = [];
            this.desks = [];
            
            localStorage.removeItem('auth_token');
            localStorage.removeItem('user');
            this.removeAuthHeader();
        }
    }

    async loadUserProfile() {
        try {
            // Evitar llamadas sin token: causa 401 y mala UX
            const hasToken = this.ensureAuthHeader();
            if (!hasToken) {
                console.warn('No hay token de autenticación presente. Omitiendo solicitud de perfil.');
                return false;
            }
            const apiUrl = await this.getApiUrl();
            console.log('👤 Cargando perfil desde:', `${apiUrl}/user-permissions.php`);
            
            const response = await axios.get(`${apiUrl}/user-permissions.php?action=user-profile`);
            
            console.log('📋 Respuesta de perfil:', response.data);
            
            if (response.data.success) {
                this.user = response.data.data.user;
                this.permissions = response.data.data.permissions || [];
                this.roles = response.data.data.roles || [];
                this.desks = response.data.data.desks || [];
                
                console.log('✅ Perfil cargado exitosamente:', {
                    user: this.user?.username,
                    permissions: this.permissions.length,
                    roles: this.roles.length,
                    desks: this.desks.length
                });
                
                return true;
            } else {
                console.error('❌ Error cargando perfil:', response.data.message);
                return false;
            }
        } catch (error) {
            console.error('💥 Error crítico cargando perfil:', error);
            console.error('📍 URL que falló:', error.config?.url);
            console.error('📊 Status:', error.response?.status);
            console.error('📝 Respuesta:', error.response?.data);
            return false;
        }
    }

    async hasPermission(permission) {
        try {
            // Primero intentar verificación local con mapeo
            const localCheck = this.hasPermissionLocal(permission);
            if (localCheck) {
                return true;
            }
            
            // Si no tiene permiso local, verificar con el backend
            const apiUrl = await this.getApiUrl();
            const response = await axios.get(`${apiUrl}/user-permissions.php?action=check-permission&permission=${permission}`);
            return response.data.success && response.data.data.has_permission;
        } catch (error) {
            console.error('Error checking permission:', error);
            return false;
        }
    }

    async hasRole(role) {
        try {
            const apiUrl = await this.getApiUrl();
            const response = await axios.get(`${apiUrl}/user-permissions.php?action=check-role&role=${role}`);
            return response.data.success && response.data.data.has_role;
        } catch (error) {
            console.error('Error checking role:', error);
            return false;
        }
    }

    async assignUserToDesk(userId, deskId) {
        try {
            const apiUrl = await this.getApiUrl();
            const response = await axios.post(`${apiUrl}/user-permissions.php`, {
                action: 'assign-user-desk',
                user_id: userId,
                desk_id: deskId
            });
            
            if (response.data.success) {
                // Recargar el perfil del usuario para actualizar los desks
                await this.loadUserProfile();
                return true;
            }
            return false;
        } catch (error) {
            console.error('Error assigning user to desk:', error);
            return false;
        }
    }

    async canAccessLead(leadId) {
        try {
            const apiUrl = await this.getApiUrl();
            const response = await axios.get(`${apiUrl}/user-permissions.php?action=can-access-lead&lead_id=${leadId}`);
            return response.data.success && response.data.data.can_access;
        } catch (error) {
            console.error('Error checking lead access:', error);
            return false;
        }
    }

    async getLeadsFilters() {
        try {
            const hasToken = this.ensureAuthHeader();
            if (!hasToken) {
                console.warn('No hay token, no se puede obtener filtros de leads');
                return { 
                    success: false,
                    message: 'Token de autenticación requerido',
                    filters: {}
                };
            }
            const apiUrl = await this.getApiUrl();
            const response = await axios.get(`${apiUrl}/user-permissions.php?action=leads-filters`);
            
            return response.data.success ? response.data.data : { filters: {} };
        } catch (error) {
            console.error('Error obteniendo filtros de leads:', error);
            return { 
                success: false, 
                message: 'Error obteniendo filtros de leads',
                filters: {} 
            };
        }
    }

    // Métodos de verificación local (más rápidos)
    hasPermissionLocal(permission) {
        // Super admin y admin siempre tienen acceso completo en frontend
        // (el backend ya trata a admin como acceso total en varios endpoints)
        if (this.isSuperAdmin() || this.isAdmin()) {
            return true;
        }
        
        // Verificar si tenemos permisos cargados
        if (!this.permissions || !Array.isArray(this.permissions)) {
            console.warn('Permisos no cargados o formato incorrecto:', this.permissions);
            return false;
        }
        
        // Mapear permisos del frontend al formato del backend
        const permissionMap = {
            // Dashboard
            'view_dashboard': 'dashboard.view',
            // Leads
            'view_leads': 'leads.view',
            'leads.view.assigned': 'leads.view', // Mapear leads asignados a vista general
            'create_leads': 'leads.create',
            'edit_leads': 'leads.edit',
            'delete_leads': 'leads.delete',
            'assign_leads': 'leads.assign',
            'import_leads': 'leads.import',
            'export_leads': 'leads.export',
            // Users
            'view_users': 'users.view',
            'create_users': 'users.create',
            'edit_users': 'users.edit',
            'delete_users': 'users.delete',
            // Roles
            'view_roles': 'roles.view',
            'create_roles': 'roles.create',
            'edit_roles': 'roles.edit',
            'delete_roles': 'roles.delete',
            'manage_roles': 'roles.view',
            // Desks
            'view_desks': 'desks.view',
            'create_desks': 'desks.create',
            'edit_desks': 'desks.edit',
            'delete_desks': 'desks.delete',
            'manage_desks': 'desks.view',
            // Trading
            'view_trading': 'trading.view',
            'view_trading_accounts': 'trading_accounts.view',
            'create_trading': 'trading.create',
            'edit_trading': 'trading.edit',
            'delete_trading': 'trading.delete',
            // Deposits/Withdrawals
            'view_deposits_withdrawals': 'deposits_withdrawals.view',
            'approve_transactions': 'transactions.approve',
            'process_transactions': 'transactions.process',
            // Reports
            'view_reports': 'reports.view',
            'create_reports': 'reports.create',
            // System
            'manage_permissions': 'user_permissions.edit',
            'view_audit': 'system.audit',
            // States
            'manage_states': 'manage_states',
            'view_states': 'states.view'
        };
        
        // Obtener el permiso mapeado
        const mappedPermission = permissionMap[permission];
        
        // Si hay un mapeo, buscar tanto el permiso original como el mapeado
        if (mappedPermission) {
            return this.permissions.some(p => {
                const permissionName = typeof p === 'string' ? p : p.name;
                return permissionName === permission || permissionName === mappedPermission;
            });
        }
        
        // Si no hay mapeo, buscar solo el permiso original
        return this.permissions.some(p => {
            const permissionName = typeof p === 'string' ? p : p.name;
            return permissionName === permission;
        });
    }

    hasRole(role) {
        return this.roles.some(r => {
            const roleName = typeof r === 'string' ? r : r.name;
            return roleName === role;
        });
    }

    hasAnyPermission(permissions) {
        return permissions.some(permission => this.hasPermission(permission));
    }

    hasAllPermissions(permissions) {
        return permissions.every(permission => this.hasPermission(permission));
    }

    isSuperAdmin() {
        return this.hasRole('super_admin');
    }

    isAdmin() {
        return this.hasRole('admin');
    }

    isManager() {
        return this.hasRole('manager');
    }

    hasSalesRole() {
        return this.hasRole('sales');
    }

    isAuthenticated() {
        return !!this.token && !!this.user;
    }

    getCurrentUser() {
        return this.user;
    }

    getUserPermissions() {
        return this.permissions;
    }

    getUserRoles() {
        return this.roles;
    }

    getUserDesks() {
        return this.desks;
    }

    // Método para verificar si el usuario puede realizar una acción específica
    canPerformAction(action, resource = null) {
        const actionPermissionMap = {
            'view_leads': 'leads.view',
            'create_leads': 'leads.create',
            'edit_leads': 'leads.edit',
            'delete_leads': 'leads.delete',
            'view_users': 'users.view',
            'create_users': 'users.create',
            'edit_users': 'users.edit',
            'delete_users': 'users.delete',
            'manage_roles': 'roles.view',
            'manage_permissions': 'system.settings',
            'view_reports': 'reports.view',
            'manage_desks': 'desks.view'
        };

        const requiredPermission = actionPermissionMap[action];
        if (!requiredPermission) {
            console.warn(`Acción no reconocida: ${action}`);
            return false;
        }

        return this.hasPermission(requiredPermission);
    }

    // Método para obtener las acciones permitidas para un recurso
    getAllowedActions(resource = 'leads') {
        const resourceActions = {
            'leads': ['view_leads', 'create_leads', 'edit_leads', 'delete_leads'],
            'users': ['view_users', 'create_users', 'edit_users', 'delete_users'],
            'roles': ['manage_roles'],
            'permissions': ['manage_permissions'],
            'reports': ['view_reports'],
            'desks': ['manage_desks']
        };

        const actions = resourceActions[resource] || [];
        return actions.filter(action => this.hasPermission(action));
    }

    // Método para verificar acceso a rutas
    canAccessRoute(route) {
        const routePermissionMap = {
            '/leads': 'view_leads',
            '/leads/create': 'create_leads',
            '/leads/edit': 'edit_leads',
            '/users': 'view_users',
            '/users/create': 'create_users',
            '/users/edit': 'edit_users',
            '/roles': 'view_roles',
            '/permissions': 'manage_permissions',
            '/reports': 'view_reports',
            '/desks': 'view_desks',
            '/dashboard': null // Dashboard siempre accesible para usuarios autenticados
        };

        const requiredPermission = routePermissionMap[route];
        
        // Si no se requiere permiso específico, solo verificar autenticación
        if (requiredPermission === null) {
            return this.isAuthenticated();
        }

        // Si no está mapeada la ruta, denegar acceso
        if (requiredPermission === undefined) {
            return false;
        }

        return this.hasPermission(requiredPermission);
    }
}

// Crear instancia singleton
const authService = new AuthService();

export default authService;