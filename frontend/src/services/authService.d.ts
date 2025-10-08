export interface UserProfile {
  id: number;
  first_name: string;
  last_name: string;
  email: string;
  role: string;
  permissions: string[];
  desk_id?: number;
}

export interface AuthService {
   // Properties
   permissions: string[];
   roles: string[];
   desks: string[];
   token: string | null;
   user: UserProfile | null;
   
   // Methods
   login: (email: string, password: string) => Promise<any>;
   logout: () => void;
   loadUserProfile: () => Promise<UserProfile | null>;
   hasPermission: (permission: string) => boolean;
   hasPermissionLocal: (permission: string) => boolean;
   hasRole: (role: string) => boolean;
   hasAnyPermission: (permissions: string[]) => boolean;
   hasAllPermissions: (permissions: string[]) => boolean;
   hasAnyRole: (roles: string[]) => boolean;
   hasAllRoles: (roles: string[]) => boolean;
   checkPermissionRemote: (permission: string) => Promise<boolean>;
   checkRoleRemote: (role: string) => Promise<boolean>;
   canAccessRoute: (route: string) => boolean;
   canPerformAction: (action: string, resource?: string | null) => boolean;
   getAllowedActions: (resource?: string) => string[];
   getUserProfile: () => UserProfile | null;
   isAuthenticated: () => boolean;
   isSuperAdmin: () => boolean;
   isAdmin: () => boolean;
   isManager: () => boolean;
   hasSalesRole: () => boolean;
   getToken: () => string | null;
   getCurrentUser: () => UserProfile | null;
   getUserPermissions: () => string[];
   getUserRoles: () => string[];
   getUserDesks: () => string[];
   assignUserToDesk: (userId: number, deskId: number) => Promise<boolean>;
   canAccessLead: (leadId: number) => Promise<boolean>;
   refreshPermissions: () => Promise<void>;
   getLeadsFilters: () => Promise<any>;
   getApiUrl: () => Promise<string>;
   setAuthHeader: (token: string) => void;
   removeAuthHeader: () => void;
   ensureAuthHeader: () => boolean;
}

declare const authService: AuthService;
export default authService;
