import React, { useState, useEffect } from 'react';
import authService from '../services/authService';

/**
 * Componente para proteger contenido basado en permisos
 */
const PermissionGuard = ({ 
    permission = null, 
    permissions = [], 
    role = null, 
    roles = [], 
    requireAll = false, 
    fallback = null, 
    children 
}) => {
    const [hasAccess, setHasAccess] = useState(false);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        checkAccess();
    }, [permission, permissions, role, roles, requireAll]);

    const checkAccess = async () => {
        setLoading(true);
        
        try {
            // Verificar que authService esté disponible
            if (!authService) {
                console.error('authService no está disponible');
                setHasAccess(false);
                setLoading(false);
                return;
            }

            let access = false;

            // Verificar permisos individuales
            if (permission) {
                access = authService.hasPermissionLocal(permission);
            }

            // Verificar múltiples permisos
            if (permissions.length > 0) {
                if (requireAll) {
                    access = permissions.every(p => authService.hasPermissionLocal(p));
                } else {
                    access = permissions.some(p => authService.hasPermissionLocal(p));
                }
            }

            // Verificar rol individual
            if (role) {
                access = access || authService.hasRole(role);
            }

            // Verificar múltiples roles
            if (roles.length > 0) {
                const hasAnyRole = roles.some(r => authService.hasRole(r));
                access = access || hasAnyRole;
            }

            // Si no se especificaron permisos ni roles, verificar solo autenticación
            if (!permission && permissions.length === 0 && !role && roles.length === 0) {
                access = authService.isAuthenticated();
            }

            setHasAccess(access);
        } catch (error) {
            console.error('Error verificando permisos:', error);
            setHasAccess(false);
        } finally {
            setLoading(false);
        }
    };

    if (loading) {
        return <div className="loading">Verificando permisos...</div>;
    }

    if (!hasAccess) {
        return fallback || <div className="access-denied">Acceso denegado</div>;
    }

    return <>{children}</>;
};

/**
 * Hook personalizado para verificar permisos
 */
export const usePermissions = () => {
    const [userPermissions, setUserPermissions] = useState([]);
    const [userRoles, setUserRoles] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        loadPermissions();
    }, []);

    const loadPermissions = async () => {
        try {
            if (!authService) {
                console.warn('authService no está disponible en loadPermissions');
                setUserPermissions([]);
                setUserRoles([]);
                return;
            }
            
            await authService.loadUserProfile();
            setUserPermissions(authService.getUserPermissions());
            setUserRoles(authService.getUserRoles());
        } catch (error) {
            console.error('Error cargando permisos:', error);
        } finally {
            setLoading(false);
        }
    };

    const hasPermission = (permission) => {
        if (!authService) {
            console.warn('authService no está disponible en hasPermission');
            return false;
        }
        return authService.hasPermissionLocal(permission);
    };

    const hasRole = (role) => {
        if (!authService) {
            console.warn('authService no está disponible en hasRole');
            return false;
        }
        return authService.hasRole(role);
    };

    const hasAnyPermission = (permissions) => {
        if (!authService) {
            console.warn('authService no está disponible en hasAnyPermission');
            return false;
        }
        return permissions.some(p => authService.hasPermissionLocal(p));
    };

    const hasAllPermissions = (permissions) => {
        if (!authService) {
            console.warn('authService no está disponible en hasAllPermissions');
            return false;
        }
        return permissions.every(p => authService.hasPermissionLocal(p));
    };

    const canPerformAction = (action, resource) => {
        if (!authService) {
            console.warn('authService no está disponible en canPerformAction');
            return false;
        }
        return authService.canPerformAction(action, resource);
    };

    const canAccessRoute = (route) => {
        if (!authService) {
            console.warn('authService no está disponible en canAccessRoute');
            return false;
        }
        return authService.canAccessRoute(route);
    };

    return {
        permissions: userPermissions,
        roles: userRoles,
        loading,
        hasPermission,
        hasRole,
        hasAnyPermission,
        hasAllPermissions,
        canPerformAction,
        canAccessRoute,
        isAuthenticated: authService ? authService.isAuthenticated() : false,
        isSuperAdmin: authService ? authService.isSuperAdmin() : false,
        isAdmin: authService ? authService.isAdmin() : false,
        isManager: authService ? authService.isManager() : false,
        hasSalesRole: authService ? authService.hasSalesRole() : false
    };
};

/**
 * Componente para mostrar/ocultar botones basado en permisos
 */
export const PermissionButton = ({ 
    permission, 
    permissions = [], 
    role, 
    roles = [], 
    requireAll = false,
    onClick,
    disabled = false,
    className = '',
    children,
    ...props 
}) => {
    const { hasPermission, hasRole, hasAnyPermission, hasAllPermissions } = usePermissions();

    const checkAccess = () => {
        let access = false;

        if (permission) {
            access = hasPermission(permission);
        }

        if (permissions.length > 0) {
            if (requireAll) {
                access = access || hasAllPermissions(permissions);
            } else {
                access = access || hasAnyPermission(permissions);
            }
        }

        if (role) {
            access = access || hasRole(role);
        }

        if (roles.length > 0) {
            const hasAnyRole = roles.some(r => hasRole(r));
            access = access || hasAnyRole;
        }

        return access;
    };

    if (!checkAccess()) {
        return null;
    }

    return (
        <button
            onClick={onClick}
            disabled={disabled}
            className={`permission-button ${className}`}
            {...props}
        >
            {children}
        </button>
    );
};

/**
 * Componente para mostrar información de acceso denegado
 */
export const AccessDenied = ({ message = "No tienes permisos para acceder a esta sección" }) => {
    return (
        <div className="access-denied-container">
            <div className="access-denied-content">
                <h2>Acceso Denegado</h2>
                <p>{message}</p>
                <button onClick={() => window.history.back()}>
                    Volver
                </button>
            </div>
        </div>
    );
};

/**
 * HOC para proteger componentes completos
 */
export const withPermissions = (WrappedComponent, requiredPermissions = []) => {
    return function PermissionProtectedComponent(props) {
        const { hasAnyPermission, loading } = usePermissions();

        if (loading) {
            return <div className="loading">Cargando...</div>;
        }

        if (requiredPermissions.length > 0 && !hasAnyPermission(requiredPermissions)) {
            return <AccessDenied />;
        }

        return <WrappedComponent {...props} />;
    };
};

export default PermissionGuard;