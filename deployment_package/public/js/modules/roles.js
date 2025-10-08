/**
 * Módulo de Gestión de Roles
 * Conectado a la base de datos real
 */

const RolesModule = {
    // Estado del módulo
    state: {
        roles: [],
        permissions: [],
        currentPage: 1,
        totalPages: 1,
        loading: false,
        filters: {
            search: ''
        }
    },

    // Inicializar módulo
    async init() {
        console.log('Inicializando módulo de Roles');
        this.render();
        await this.loadRoles();
        this.bindEvents();
        // Auto-actualización cada 10s
        if (this._rolesAutoRefresh) clearInterval(this._rolesAutoRefresh);
        this._rolesAutoRefresh = setInterval(() => {
            // No recargar si el modal está abierto para evitar romper edición
            const modalEl = document.getElementById('roleModal');
            const isOpen = modalEl && modalEl.classList.contains('show');
            if (!isOpen) {
                this.loadRoles(this.state.currentPage);
            }
        }, 10000);
    },

    // Renderizar interfaz
    render() {
        const content = document.getElementById('moduleContent');
        content.innerHTML = `
            <div class="roles-module">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2><i class="fas fa-user-shield"></i> Gestión de Roles</h2>
                        <p class="text-muted">Administra roles y permisos del sistema</p>
                    </div>
                    <button class="btn btn-primary" onclick="RolesModule.showCreateModal()">
                        <i class="fas fa-plus"></i> Nuevo Rol
                    </button>
                </div>

                <!-- Filtros -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label>Buscar</label>
                                    <input type="text" class="form-control" id="searchInput" 
                                           placeholder="Nombre del rol, descripción...">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <button class="btn btn-outline-secondary btn-block" onclick="RolesModule.clearFilters()">
                                        <i class="fas fa-times"></i> Limpiar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Estadísticas rápidas -->
                <div class="row mb-4" id="rolesStats">
                    <!-- Se cargarán dinámicamente -->
                </div>

                <!-- Tabla de roles -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Lista de Roles</h5>
                    </div>
                    <div class="card-body">
                        <div id="rolesLoading" class="text-center py-4" style="display: none;">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                            <p class="mt-2">Cargando roles...</p>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover" id="rolesTable">
                                <thead>
                                    <tr>
                                        <th>Rol</th>
                                        <th>Descripción</th>
                                        <th>Permisos</th>
                                        <th>Usuarios</th>
                                        <th>Tipo</th>
                                        <th>Fecha Creación</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="rolesTableBody">
                                    <!-- Se cargarán dinámicamente -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginación -->
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div id="rolesInfo">
                                <!-- Información de paginación -->
                            </div>
                            <nav>
                                <ul class="pagination mb-0" id="rolesPagination">
                                    <!-- Paginación dinámica -->
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para crear/editar rol -->
            <div class="modal fade" id="roleModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="roleModalTitle">Nuevo Rol</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="roleForm">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label>Nombre del Rol *</label>
                                            <input type="text" class="form-control" name="name" required>
                                            <small class="text-muted">Nombre técnico (sin espacios)</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label>Nombre para Mostrar *</label>
                                            <input type="text" class="form-control" name="display_name" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="form-group mb-3">
                                            <label>Descripción</label>
                                            <textarea class="form-control" name="description" rows="3"></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group mb-3">
                                            <label>Color</label>
                                            <input type="color" class="form-control" name="color" value="#007bff">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Permisos -->
                                <div class="form-group mb-3">
                                    <label>Permisos</label>
                                    <div id="permissionsContainer" class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                                        <p class="text-muted">Los permisos se cargarán cuando estén disponibles</p>
                                    </div>
                                </div>
                                
                                <input type="hidden" name="id" id="roleId">
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary" onclick="RolesModule.saveRole()">
                                <span id="saveRoleText">Guardar Rol</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    },

    // Vincular eventos
    bindEvents() {
        // Filtros
        document.getElementById('searchInput').addEventListener('input', 
            this.debounce(() => this.applyFilters(), 500));
    },

    // Cargar roles desde la API
    async loadRoles(page = 1) {
        this.state.loading = true;
        this.showLoading(true);

        try {
            const params = new URLSearchParams({
                page: page,
                limit: 20,
                ...this.state.filters
            });

            const response = await App.apiRequest(`/roles.php?${params}`);
            
            if (response.success) {
                this.state.roles = response.data.roles;
                this.state.currentPage = response.data.pagination.page;
                this.state.totalPages = response.data.pagination.pages;
                
                this.renderRolesTable();
                this.renderPagination();
                this.renderStats();
            } else {
                throw new Error(response.message || 'Error al cargar roles');
            }
        } catch (error) {
            console.error('Error loading roles:', error);
            App.showNotification('Error al cargar roles: ' + error.message, 'error');
            this.renderEmptyState();
        } finally {
            this.state.loading = false;
            this.showLoading(false);
        }
    },

    // Renderizar tabla de roles
    renderRolesTable() {
        const tbody = document.getElementById('rolesTableBody');
        
        if (this.state.roles.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-4">
                        <i class="fas fa-user-shield fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No se encontraron roles</p>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = this.state.roles.map(role => `
            <tr>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="badge me-2" style="background-color: ${role.color}; width: 12px; height: 12px;"></div>
                        <div>
                            <div class="fw-bold">${role.display_name}</div>
                            <small class="text-muted">${role.name}</small>
                        </div>
                    </div>
                </td>
                <td>${role.description || 'Sin descripción'}</td>
                <td>
                    <span class="badge bg-info">${role.permissions_count || 0} permisos</span>
                </td>
                <td>
                    <span class="badge bg-secondary">${role.users_count || 0} usuarios</span>
                </td>
                <td>
                    ${role.is_system ? 
                        '<span class="badge bg-warning">Sistema</span>' : 
                        '<span class="badge bg-success">Personalizado</span>'
                    }
                </td>
                <td>${App.formatDate(role.created_at)}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="RolesModule.editRole(${role.id})" title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        ${!role.is_system ? `
                            <button class="btn btn-outline-danger" onclick="RolesModule.deleteRole(${role.id})" title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `).join('');
    },

    // Renderizar estadísticas
    renderStats() {
        const stats = this.calculateStats();
        const statsContainer = document.getElementById('rolesStats');
        
        statsContainer.innerHTML = `
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4>${stats.total}</h4>
                                <p class="mb-0">Total Roles</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-user-shield fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4>${stats.custom}</h4>
                                <p class="mb-0">Personalizados</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-user-cog fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4>${stats.system}</h4>
                                <p class="mb-0">Del Sistema</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-cog fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4>${stats.totalUsers}</h4>
                                <p class="mb-0">Usuarios Asignados</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-users fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    },

    // Calcular estadísticas
    calculateStats() {
        const total = this.state.roles.length;
        const custom = this.state.roles.filter(role => !role.is_system).length;
        const system = this.state.roles.filter(role => role.is_system).length;
        const totalUsers = this.state.roles.reduce((sum, role) => sum + (parseInt(role.users_count) || 0), 0);

        return { total, custom, system, totalUsers };
    },

    // Aplicar filtros
    applyFilters() {
        this.state.filters.search = document.getElementById('searchInput').value;
        this.loadRoles(1);
    },

    // Limpiar filtros
    clearFilters() {
        document.getElementById('searchInput').value = '';
        this.state.filters = { search: '' };
        this.loadRoles(1);
    },

    // Mostrar modal de creación
    showCreateModal() {
        document.getElementById('roleModalTitle').textContent = 'Nuevo Rol';
        document.getElementById('roleForm').reset();
        document.getElementById('roleId').value = '';
        // Cargar permisos para selección
        this.loadPermissions();
        
        const modal = new bootstrap.Modal(document.getElementById('roleModal'));
        modal.show();
    },

    // Editar rol
    async editRole(id) {
        const role = this.state.roles.find(r => r.id == id);
        if (!role) return;

        document.getElementById('roleModalTitle').textContent = 'Editar Rol';
        document.getElementById('roleId').value = role.id;
        
        // Llenar formulario
        const form = document.getElementById('roleForm');
        form.name.value = role.name;
        form.display_name.value = role.display_name;
        form.description.value = role.description || '';
        form.color.value = role.color || '#007bff';
        // Cargar permisos y marcar los del rol
        await this.loadPermissions();
        try {
            const roleDetail = await App.apiRequest(`/roles.php?id=${id}`);
            const existing = roleDetail && roleDetail.success ? (roleDetail.data.permissions || []) : [];
            // Marcar checkboxes existentes
            existing.forEach(p => {
                const cb = document.querySelector(`#permissionsContainer input.permission-checkbox[value="${p}"]`);
                if (cb) cb.checked = true;
            });
        } catch (e) {
            console.error('Error obteniendo permisos del rol:', e);
        }
        const modal = new bootstrap.Modal(document.getElementById('roleModal'));
        modal.show();
    },

    // Guardar rol
    async saveRole() {
        const form = document.getElementById('roleForm');
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);
        // Adjuntar permisos seleccionados
        data.permissions = this.getSelectedPermissions();
        
        const isEdit = !!data.id;
        
        try {
            const response = isEdit ? 
                await App.apiRequest(`/roles.php?id=${data.id}`, {
                    method: 'PUT',
                    body: JSON.stringify(data)
                }) :
                await App.apiRequest('/roles.php', {
                    method: 'POST',
                    body: JSON.stringify(data)
                });

            if (response.success) {
                App.showNotification(
                    isEdit ? 'Rol actualizado correctamente' : 'Rol creado correctamente', 
                    'success'
                );
                
                const modal = bootstrap.Modal.getInstance(document.getElementById('roleModal'));
                modal.hide();
                
                this.loadRoles(this.state.currentPage);
            } else {
                throw new Error(response.message || 'Error al guardar rol');
            }
        } catch (error) {
            console.error('Error saving role:', error);
            App.showNotification('Error al guardar rol: ' + error.message, 'error');
        }
    },

    // Cargar todos los permisos desde la API y renderizar
    async loadPermissions() {
        const container = document.getElementById('permissionsContainer');
        if (!container) return;
        container.innerHTML = `
            <div class="d-flex align-items-center text-muted">
                <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                <span>Cargando permisos...</span>
            </div>`;

        try {
            // Preferimos el endpoint dedicado de permisos
            const resp = await App.apiRequest('/permissions.php');
            const items = resp && resp.success ? (resp.data || []) : [];
            // Fallback a /roles.php?scope=permissions si el anterior falla
            if (!resp || !resp.success) {
                const alt = await App.apiRequest('/roles.php?scope=permissions');
                const altItems = alt && alt.success ? (alt.data || []) : [];
                this.state.permissions = altItems.map(p => ({
                    name: p.name,
                    display_name: p.display_name || p.name,
                    module: p.module || (p.name.includes('.') ? p.name.split('.')[0] : 'otros')
                }));
            } else {
                this.state.permissions = items.map(p => ({
                    name: p.name,
                    display_name: p.display_name || p.name,
                    module: p.module || (p.name.includes('.') ? p.name.split('.')[0] : 'otros')
                }));
            }
            this.renderPermissions();
        } catch (error) {
            console.error('Error cargando permisos:', error);
            container.innerHTML = '<p class="text-danger">No se pudieron cargar los permisos.</p>';
        }
    },

    // Renderizar lista de permisos en el modal, agrupados por módulo
    renderPermissions() {
        const container = document.getElementById('permissionsContainer');
        if (!container) return;
        const byModule = {};
        this.state.permissions.forEach(p => {
            if (!byModule[p.module]) byModule[p.module] = [];
            byModule[p.module].push(p);
        });
        const modules = Object.keys(byModule).sort();
        container.innerHTML = `
            <div class="d-flex mb-2">
                <button class="btn btn-sm btn-outline-primary me-2" onclick="RolesModule.selectAllPermissions()">Seleccionar todos</button>
                <button class="btn btn-sm btn-outline-secondary" onclick="RolesModule.clearAllPermissions()">Limpiar</button>
            </div>
            <div class="row">
                ${modules.map(m => `
                    <div class="col-md-4 mb-3">
                        <div class="card">
                            <div class="card-header">
                                <strong>${m.charAt(0).toUpperCase() + m.slice(1)}</strong>
                            </div>
                            <div class="card-body">
                                ${byModule[m].map(p => `
                                    <div class="form-check">
                                        <input class="form-check-input permission-checkbox" type="checkbox" id="perm_${p.name}" value="${p.name}">
                                        <label class="form-check-label" for="perm_${p.name}">${p.display_name}</label>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    </div>
                `).join('')}
            </div>`;
    },

    // Obtener permisos seleccionados del modal
    getSelectedPermissions() {
        const container = document.getElementById('permissionsContainer');
        if (!container) return [];
        return Array.from(container.querySelectorAll('input.permission-checkbox:checked')).map(el => el.value);
    },

    // Seleccionar todos los permisos visibles
    selectAllPermissions() {
        const container = document.getElementById('permissionsContainer');
        if (!container) return;
        container.querySelectorAll('input.permission-checkbox').forEach(el => el.checked = true);
    },

    // Limpiar selección de permisos
    clearAllPermissions() {
        const container = document.getElementById('permissionsContainer');
        if (!container) return;
        container.querySelectorAll('input.permission-checkbox').forEach(el => el.checked = false);
    },

    // Eliminar rol
    async deleteRole(id) {
        if (!confirm('¿Estás seguro de que quieres eliminar este rol?')) return;

        try {
            const response = await App.apiRequest(`/roles.php?id=${id}`, {
                method: 'DELETE'
            });

            if (response.success) {
                App.showNotification('Rol eliminado correctamente', 'success');
                this.loadRoles(this.state.currentPage);
            } else {
                throw new Error(response.message || 'Error al eliminar rol');
            }
        } catch (error) {
            console.error('Error deleting role:', error);
            App.showNotification('Error al eliminar rol: ' + error.message, 'error');
        }
    },

    // Utilidades
    showLoading(show) {
        const loading = document.getElementById('rolesLoading');
        if (loading) {
            loading.style.display = show ? 'block' : 'none';
        }
    },

    renderEmptyState() {
        const tbody = document.getElementById('rolesTableBody');
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-5">
                    <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                    <p class="text-muted">Error al cargar los datos</p>
                    <button class="btn btn-primary" onclick="RolesModule.loadRoles()">
                        <i class="fas fa-refresh"></i> Reintentar
                    </button>
                </td>
            </tr>
        `;
    },

    renderPagination() {
        // Implementar paginación
        const pagination = document.getElementById('rolesPagination');
        // ... código de paginación
    },

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
};

// Registrar módulo globalmente
window.RolesModule = RolesModule;