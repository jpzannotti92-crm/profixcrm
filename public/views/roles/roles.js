/**
 * Módulo de Gestión de Roles
 * Sistema modular para iaTrade CRM
 */
const RolesModule = {
    // Datos del módulo
    data: {
        roles: [],
        permissions: [],
        filters: {},
        editingRoleId: null
    },

    // Inicialización del módulo
    init() {
        console.log('Inicializando módulo de Roles...');
        this.loadData();
        this.bindEvents();
        this.updateStats();
    },

    // Cargar datos
    loadData() {
        // Datos de demostración - Roles
        this.data.roles = [
            {
                id: 1,
                name: 'super_admin',
                displayName: 'Super Administrador',
                description: 'Acceso completo al sistema',
                level: 5,
                status: 'active',
                usersCount: 1,
                permissions: ['*'], // Todos los permisos
                color: 'danger',
                icon: 'crown',
                createdAt: '2024-01-01'
            },
            {
                id: 2,
                name: 'admin',
                displayName: 'Administrador',
                description: 'Administrador del sistema con permisos avanzados',
                level: 4,
                status: 'active',
                usersCount: 2,
                permissions: [
                    'leads.create', 'leads.read', 'leads.update', 'leads.delete', 'leads.assign', 'leads.export', 'leads.import',
                    'users.create', 'users.read', 'users.update', 'users.delete', 'users.roles',
                    'desks.create', 'desks.read', 'desks.update', 'desks.delete', 'desks.manage',
                    'reports.view', 'reports.export', 'reports.advanced', 'reports.create',
                    'kpis.view', 'kpis.manage', 'kpis.targets',
                    'settings.view', 'settings.update', 'settings.system',
                    'roles.read', 'roles.update'
                ],
                color: 'primary',
                icon: 'user-shield',
                createdAt: '2024-01-01'
            },
            {
                id: 3,
                name: 'manager',
                displayName: 'Manager',
                description: 'Gerente de desk con permisos de gestión',
                level: 3,
                status: 'active',
                usersCount: 4,
                permissions: [
                    'leads.create', 'leads.read', 'leads.update', 'leads.assign', 'leads.export',
                    'users.read', 'users.update',
                    'desks.read', 'desks.update', 'desks.manage',
                    'reports.view', 'reports.export', 'reports.advanced',
                    'kpis.view', 'kpis.manage'
                ],
                color: 'success',
                icon: 'user-tie',
                createdAt: '2024-01-01'
            },
            {
                id: 4,
                name: 'senior_sales',
                displayName: 'Senior Sales',
                description: 'Vendedor senior con permisos avanzados',
                level: 2,
                status: 'active',
                usersCount: 6,
                permissions: [
                    'leads.create', 'leads.read', 'leads.update', 'leads.assign',
                    'users.read',
                    'desks.read',
                    'reports.view', 'reports.export',
                    'kpis.view'
                ],
                color: 'info',
                icon: 'user-graduate',
                createdAt: '2024-01-01'
            },
            {
                id: 5,
                name: 'sales',
                displayName: 'Sales',
                description: 'Vendedor con permisos básicos',
                level: 1,
                status: 'active',
                usersCount: 8,
                permissions: [
                    'leads.read', 'leads.update',
                    'users.read',
                    'desks.read',
                    'reports.view',
                    'kpis.view'
                ],
                color: 'warning',
                icon: 'headset',
                createdAt: '2024-01-01'
            },
            {
                id: 6,
                name: 'retention',
                displayName: 'Retention',
                description: 'Especialista en retención de clientes',
                level: 2,
                status: 'active',
                usersCount: 3,
                permissions: [
                    'leads.read', 'leads.update',
                    'users.read',
                    'desks.read',
                    'reports.view',
                    'kpis.view'
                ],
                color: 'secondary',
                icon: 'user-check',
                createdAt: '2024-01-15'
            },
            {
                id: 7,
                name: 'vip',
                displayName: 'VIP Manager',
                description: 'Gestor de clientes VIP',
                level: 3,
                status: 'active',
                usersCount: 2,
                permissions: [
                    'leads.create', 'leads.read', 'leads.update', 'leads.assign',
                    'users.read',
                    'desks.read',
                    'reports.view', 'reports.export', 'reports.advanced',
                    'kpis.view', 'kpis.manage'
                ],
                color: 'dark',
                icon: 'gem',
                createdAt: '2024-02-01'
            },
            {
                id: 8,
                name: 'auditor',
                displayName: 'Auditor',
                description: 'Auditor del sistema',
                level: 3,
                status: 'inactive',
                usersCount: 0,
                permissions: [
                    'leads.read',
                    'users.read',
                    'desks.read',
                    'reports.view', 'reports.export', 'reports.advanced',
                    'kpis.view',
                    'audit.view', 'audit.export'
                ],
                color: 'muted',
                icon: 'search',
                createdAt: '2024-02-15'
            }
        ];

        // Definir todos los permisos disponibles
        this.data.permissions = [
            // Leads
            { id: 'leads.create', name: 'Crear Leads', module: 'leads' },
            { id: 'leads.read', name: 'Ver Leads', module: 'leads' },
            { id: 'leads.update', name: 'Actualizar Leads', module: 'leads' },
            { id: 'leads.delete', name: 'Eliminar Leads', module: 'leads' },
            { id: 'leads.assign', name: 'Asignar Leads', module: 'leads' },
            { id: 'leads.export', name: 'Exportar Leads', module: 'leads' },
            { id: 'leads.import', name: 'Importar Leads', module: 'leads' },
            
            // Users
            { id: 'users.create', name: 'Crear Usuarios', module: 'users' },
            { id: 'users.read', name: 'Ver Usuarios', module: 'users' },
            { id: 'users.update', name: 'Actualizar Usuarios', module: 'users' },
            { id: 'users.delete', name: 'Eliminar Usuarios', module: 'users' },
            { id: 'users.roles', name: 'Gestionar Roles de Usuario', module: 'users' },
            
            // Desks
            { id: 'desks.create', name: 'Crear Desks', module: 'desks' },
            { id: 'desks.read', name: 'Ver Desks', module: 'desks' },
            { id: 'desks.update', name: 'Actualizar Desks', module: 'desks' },
            { id: 'desks.delete', name: 'Eliminar Desks', module: 'desks' },
            { id: 'desks.manage', name: 'Gestionar Miembros', module: 'desks' },
            
            // Reports
            { id: 'reports.view', name: 'Ver Reportes', module: 'reports' },
            { id: 'reports.export', name: 'Exportar Reportes', module: 'reports' },
            { id: 'reports.advanced', name: 'Reportes Avanzados', module: 'reports' },
            { id: 'reports.create', name: 'Crear Reportes', module: 'reports' },
            
            // KPIs
            { id: 'kpis.view', name: 'Ver KPIs', module: 'kpis' },
            { id: 'kpis.manage', name: 'Gestionar KPIs', module: 'kpis' },
            { id: 'kpis.targets', name: 'Definir Metas', module: 'kpis' },
            
            // Settings
            { id: 'settings.view', name: 'Ver Configuración', module: 'settings' },
            { id: 'settings.update', name: 'Actualizar Configuración', module: 'settings' },
            { id: 'settings.system', name: 'Configuración del Sistema', module: 'settings' },
            
            // Roles
            { id: 'roles.create', name: 'Crear Roles', module: 'roles' },
            { id: 'roles.read', name: 'Ver Roles', module: 'roles' },
            { id: 'roles.update', name: 'Actualizar Roles', module: 'roles' },
            { id: 'roles.delete', name: 'Eliminar Roles', module: 'roles' },
            
            // Audit
            { id: 'audit.view', name: 'Ver Logs de Auditoría', module: 'audit' },
            { id: 'audit.export', name: 'Exportar Logs', module: 'audit' },
            { id: 'audit.delete', name: 'Eliminar Logs', module: 'audit' }
        ];

        this.renderRoles();
    },

    // Vincular eventos
    bindEvents() {
        // Filtros
        const filterInputs = ['filterRoleSearch', 'filterRoleLevel', 'filterRoleStatus'];
        filterInputs.forEach(filterId => {
            const element = document.getElementById(filterId);
            if (element) {
                element.addEventListener('change', () => this.applyFilters());
                element.addEventListener('input', () => this.applyFilters());
            }
        });
    },

    // Renderizar roles
    renderRoles() {
        const container = document.getElementById('rolesContainer');
        if (!container) return;

        const filteredRoles = this.getFilteredRoles();

        container.innerHTML = filteredRoles.map(role => `
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-${role.icon} text-${role.color} me-2"></i>
                            ${role.displayName}
                        </h5>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" onclick="RolesModule.editRole(${role.id})">
                                    <i class="fas fa-edit me-2"></i>Editar Permisos
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="RolesModule.viewRoleUsers(${role.id})">
                                    <i class="fas fa-users me-2"></i>Ver Usuarios
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="RolesModule.duplicateRole(${role.id})">
                                    <i class="fas fa-copy me-2"></i>Duplicar
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item ${role.status === 'active' ? 'text-warning' : 'text-success'}" 
                                       href="#" onclick="RolesModule.toggleRoleStatus(${role.id})">
                                    <i class="fas fa-${role.status === 'active' ? 'pause' : 'play'} me-2"></i>
                                    ${role.status === 'active' ? 'Desactivar' : 'Activar'}
                                </a></li>
                                ${role.level < 5 ? `
                                    <li><a class="dropdown-item text-danger" href="#" onclick="RolesModule.deleteRole(${role.id})">
                                        <i class="fas fa-trash me-2"></i>Eliminar
                                    </a></li>
                                ` : ''}
                            </ul>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">${role.description}</p>
                        
                        <div class="mb-3">
                            <small class="text-muted">Permisos:</small>
                            <div class="mt-1">
                                ${this.renderRolePermissions(role)}
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted">Usuarios asignados:</small>
                            <span class="badge bg-${role.color}">${role.usersCount}</span>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted">Nivel:</small>
                            <div>
                                ${this.renderLevelStars(role.level)}
                                <small class="ms-1">${role.level}/5</small>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">Estado:</small>
                            <span class="badge bg-${role.status === 'active' ? 'success' : 'secondary'}">
                                ${role.status === 'active' ? 'Activo' : 'Inactivo'}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    },

    // Renderizar permisos del rol
    renderRolePermissions(role) {
        if (role.permissions.includes('*')) {
            return '<span class="badge bg-success me-1">Todos los permisos</span>';
        }

        const modules = [...new Set(role.permissions.map(p => p.split('.')[0]))];
        const maxVisible = 4;
        
        let html = modules.slice(0, maxVisible).map(module => 
            `<span class="badge bg-${this.getModuleColor(module)} me-1 mb-1">${this.getModuleName(module)}</span>`
        ).join('');
        
        if (modules.length > maxVisible) {
            html += `<span class="badge bg-secondary me-1 mb-1">+${modules.length - maxVisible} más</span>`;
        }
        
        return html;
    },

    // Renderizar estrellas de nivel
    renderLevelStars(level) {
        let stars = '';
        for (let i = 1; i <= 5; i++) {
            if (i <= level) {
                stars += '<i class="fas fa-star text-warning"></i>';
            } else {
                stars += '<i class="far fa-star text-muted"></i>';
            }
        }
        return stars;
    },

    // Obtener roles filtrados
    getFilteredRoles() {
        let filtered = [...this.data.roles];

        if (this.data.filters.search) {
            const search = this.data.filters.search.toLowerCase();
            filtered = filtered.filter(role => 
                role.name.toLowerCase().includes(search) ||
                role.displayName.toLowerCase().includes(search) ||
                role.description.toLowerCase().includes(search)
            );
        }

        if (this.data.filters.level) {
            filtered = filtered.filter(role => role.level == this.data.filters.level);
        }

        if (this.data.filters.status) {
            filtered = filtered.filter(role => role.status === this.data.filters.status);
        }

        return filtered;
    },

    // Aplicar filtros
    applyFilters() {
        this.data.filters = {
            search: document.getElementById('filterRoleSearch')?.value || '',
            level: document.getElementById('filterRoleLevel')?.value || '',
            status: document.getElementById('filterRoleStatus')?.value || ''
        };

        this.renderRoles();
        this.updateStats();
        App.showNotification('Filtros aplicados', 'info');
    },

    // Actualizar estadísticas
    updateStats() {
        const filtered = this.getFilteredRoles();
        const stats = {
            total: filtered.length,
            active: filtered.filter(r => r.status === 'active').length,
            totalUsers: filtered.reduce((sum, r) => sum + r.usersCount, 0),
            totalPermissions: this.data.permissions.length
        };

        document.getElementById('totalRoles').textContent = stats.total;
        document.getElementById('activeRoles').textContent = stats.active;
        document.getElementById('totalUsers').textContent = stats.totalUsers;
        document.getElementById('totalPermissions').textContent = stats.totalPermissions;
    },

    // Mostrar modal de creación
    showCreateModal() {
        this.data.editingRoleId = null;
        document.getElementById('roleModalTitle').innerHTML = '<i class="fas fa-shield-alt me-2"></i>Crear Rol';
        document.getElementById('roleForm').reset();
        this.clearAllPermissions();
        
        const modal = new bootstrap.Modal(document.getElementById('roleModal'));
        modal.show();
    },

    // Editar rol
    editRole(roleId) {
        const role = this.data.roles.find(r => r.id === roleId);
        if (!role) return;

        this.data.editingRoleId = roleId;
        
        // Llenar formulario
        document.getElementById('roleName').value = role.name;
        document.getElementById('roleDisplayName').value = role.displayName;
        document.getElementById('roleLevel').value = role.level;
        document.getElementById('roleDescription').value = role.description;

        // Marcar permisos
        this.clearAllPermissions();
        if (role.permissions.includes('*')) {
            this.selectAllPermissions();
        } else {
            role.permissions.forEach(permission => {
                const checkbox = document.getElementById(permission.replace('.', '_'));
                if (checkbox) {
                    checkbox.checked = true;
                }
            });
            this.updateModuleCheckboxes();
        }

        document.getElementById('roleModalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Editar Rol';
        const modal = new bootstrap.Modal(document.getElementById('roleModal'));
        modal.show();
    },

    // Guardar rol
    saveRole() {
        const formData = {
            name: document.getElementById('roleName').value,
            displayName: document.getElementById('roleDisplayName').value,
            level: parseInt(document.getElementById('roleLevel').value),
            description: document.getElementById('roleDescription').value,
            permissions: this.getSelectedPermissions()
        };

        // Validación básica
        if (!formData.name || !formData.displayName || !formData.level) {
            App.showNotification('Por favor completa todos los campos obligatorios', 'error');
            return;
        }

        if (formData.permissions.length === 0) {
            App.showNotification('Debe seleccionar al menos un permiso', 'error');
            return;
        }

        // Simular guardado
        const action = this.data.editingRoleId ? 'actualizado' : 'creado';
        App.showNotification(`Rol ${action} exitosamente`, 'success');
        
        const modal = bootstrap.Modal.getInstance(document.getElementById('roleModal'));
        modal.hide();
        
        // Recargar datos
        this.loadData();
    },

    // Obtener permisos seleccionados
    getSelectedPermissions() {
        const checkboxes = document.querySelectorAll('.permission-checkbox:checked');
        return Array.from(checkboxes).map(cb => cb.value);
    },

    // Seleccionar todos los permisos
    selectAllPermissions() {
        document.querySelectorAll('.permission-checkbox').forEach(cb => {
            cb.checked = true;
        });
        this.updateModuleCheckboxes();
    },

    // Limpiar todos los permisos
    clearAllPermissions() {
        document.querySelectorAll('.permission-checkbox').forEach(cb => {
            cb.checked = false;
        });
        this.updateModuleCheckboxes();
    },

    // Toggle permisos de módulo
    toggleModulePermissions(module, checked) {
        document.querySelectorAll(`.${module}-permission`).forEach(cb => {
            cb.checked = checked;
        });
    },

    // Actualizar checkboxes de módulo
    updateModuleCheckboxes() {
        const modules = ['leads', 'users', 'desks', 'reports', 'kpis', 'settings', 'roles', 'audit'];
        
        modules.forEach(module => {
            const moduleCheckboxes = document.querySelectorAll(`.${module}-permission`);
            const checkedCount = document.querySelectorAll(`.${module}-permission:checked`).length;
            const moduleSelectAll = document.getElementById(`selectAll${module.charAt(0).toUpperCase() + module.slice(1)}`);
            
            if (moduleSelectAll) {
                if (checkedCount === 0) {
                    moduleSelectAll.checked = false;
                    moduleSelectAll.indeterminate = false;
                } else if (checkedCount === moduleCheckboxes.length) {
                    moduleSelectAll.checked = true;
                    moduleSelectAll.indeterminate = false;
                } else {
                    moduleSelectAll.checked = false;
                    moduleSelectAll.indeterminate = true;
                }
            }
        });
    },

    // Ver usuarios del rol
    viewRoleUsers(roleId) {
        const role = this.data.roles.find(r => r.id === roleId);
        if (!role) return;

        // Datos simulados de usuarios
        const users = [
            { name: 'María García', email: 'maria.garcia@iatrade.com', status: 'active', lastLogin: '2024-01-15 14:30' },
            { name: 'Carlos López', email: 'carlos.lopez@iatrade.com', status: 'active', lastLogin: '2024-01-15 13:45' },
            { name: 'Ana Martínez', email: 'ana.martinez@iatrade.com', status: 'active', lastLogin: '2024-01-15 15:20' }
        ].slice(0, role.usersCount);

        const content = document.getElementById('roleUsersContent');
        content.innerHTML = `
            <div class="mb-3">
                <h6>Rol: ${role.displayName}</h6>
                <p class="text-muted">${role.description}</p>
            </div>
            
            ${users.length > 0 ? `
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Email</th>
                                <th>Estado</th>
                                <th>Último Login</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${users.map(user => `
                                <tr>
                                    <td>${user.name}</td>
                                    <td>${user.email}</td>
                                    <td><span class="badge bg-${user.status === 'active' ? 'success' : 'secondary'}">${user.status === 'active' ? 'Activo' : 'Inactivo'}</span></td>
                                    <td>${user.lastLogin}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            ` : `
                <div class="text-center text-muted">
                    <i class="fas fa-users fa-3x mb-3"></i>
                    <p>No hay usuarios asignados a este rol</p>
                </div>
            `}
        `;

        const modal = new bootstrap.Modal(document.getElementById('roleUsersModal'));
        modal.show();
    },

    // Duplicar rol
    duplicateRole(roleId) {
        const role = this.data.roles.find(r => r.id === roleId);
        if (!role) return;

        App.showNotification(`Duplicando rol "${role.displayName}"...`, 'info');
        // Aquí se implementaría la lógica de duplicación
    },

    // Cambiar estado del rol
    toggleRoleStatus(roleId) {
        const role = this.data.roles.find(r => r.id === roleId);
        if (!role) return;

        if (role.level === 5) {
            App.showNotification('No se puede desactivar el rol de Super Administrador', 'error');
            return;
        }

        const newStatus = role.status === 'active' ? 'inactive' : 'active';
        role.status = newStatus;
        
        App.showNotification(`Rol ${newStatus === 'active' ? 'activado' : 'desactivado'}`, 'success');
        this.renderRoles();
        this.updateStats();
    },

    // Eliminar rol
    deleteRole(roleId) {
        const role = this.data.roles.find(r => r.id === roleId);
        if (!role) return;

        if (role.level === 5) {
            App.showNotification('No se puede eliminar el rol de Super Administrador', 'error');
            return;
        }

        if (role.usersCount > 0) {
            App.showNotification('No se puede eliminar un rol que tiene usuarios asignados', 'error');
            return;
        }

        if (confirm(`¿Estás seguro de eliminar el rol "${role.displayName}"?`)) {
            this.data.roles = this.data.roles.filter(r => r.id !== roleId);
            App.showNotification('Rol eliminado exitosamente', 'success');
            this.renderRoles();
            this.updateStats();
        }
    },

    // Funciones auxiliares
    getModuleColor(module) {
        const colors = {
            leads: 'primary',
            users: 'success',
            desks: 'warning',
            reports: 'info',
            kpis: 'danger',
            settings: 'secondary',
            roles: 'dark',
            audit: 'muted'
        };
        return colors[module] || 'secondary';
    },

    getModuleName(module) {
        const names = {
            leads: 'Leads',
            users: 'Usuarios',
            desks: 'Desks',
            reports: 'Reportes',
            kpis: 'KPIs',
            settings: 'Config',
            roles: 'Roles',
            audit: 'Auditoría'
        };
        return names[module] || module;
    }
};

// Exportar el módulo para uso global
window.RolesModule = RolesModule;