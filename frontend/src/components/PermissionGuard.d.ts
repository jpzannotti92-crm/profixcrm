import { ReactNode } from 'react';

export interface PermissionGuardProps {
  permission?: string;
  role?: string;
  permissions?: string[];
  roles?: string[];
  requireAll?: boolean;
  children: ReactNode;
  fallback?: ReactNode;
}

export interface PermissionButtonProps {
  permission?: string;
  role?: string;
  permissions?: string[];
  roles?: string[];
  requireAll?: boolean;
  children: ReactNode;
  onClick?: () => void;
  className?: string;
  disabled?: boolean;
}

export interface UsePermissionsReturn {
  hasPermission: (permission: string) => boolean;
  hasRole: (role: string) => boolean;
  hasAnyPermission: (permissions: string[]) => boolean;
  hasAllPermissions: (permissions: string[]) => boolean;
  hasAnyRole: (roles: string[]) => boolean;
  hasAllRoles: (roles: string[]) => boolean;
  permissionsLoading: boolean;
  loading: boolean;
  userProfile: any;
}

declare const PermissionGuard: React.FC<PermissionGuardProps>;
export const usePermissions: () => UsePermissionsReturn;
export const PermissionButton: React.FC<PermissionButtonProps>;
export const AccessDenied: React.FC<{ message?: string }>;
export const withPermissions: <P extends object>(
  Component: React.ComponentType<P>,
  requiredPermissions: string[] | string,
  requiredRoles?: string[] | string
) => React.FC<P>;

export default PermissionGuard;
