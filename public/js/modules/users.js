/**
 * Módulo de Gestión de Usuarios
 * Conectado a la base de datos real
 */

const UsersModule = {
    // Estado del módulo
    state: {
        users: [],
        roles: [],
        currentPage: 1,
        totalPages: 1,
        loading: false,
        filters: {
            search: '',
            status: ''
        }
    },

    // Inicializar módulo
    async init() {
        console.log('Inicializando módulo de Usuarios');
        this.render();
        await this.loadRoles();
        await this.loadUsers();
        this.bindEvents();
    },

    // Renderizar interfaz
    render() {
        const content = document.getElementById('moduleContent');
        content.innerHTML = `
            <div class="users-module">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2><i class="fas fa-user-tie"></i> Gestión de Usuarios</h2>
                        <p class="text-muted">Administra usuarios y sus permisos en el sistema</p>
                    </div>
                    <button class="btn btn-primary" onclick="UsersModule.showCreateModal()">
                        <i class="fas fa-plus"></i> Nuevo Usuario
                    </button>
                </div>

                <!-- Filtros -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Buscar</label>
                                    <input type="text" class="form-control" id="searchInput" 
                                           placeholder="Nombre, email, usuario...">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Estado</label>
                                    <select class="form-control" id="statusFilter">
                                        <option value="">Todos los estados</option>
                                        <option value="active">Activo</option>
                                        <option value="inactive">Inactivo</option>
                                        <option value="suspended">Suspendido</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <button class="btn btn-outline-secondary btn-block" onclick="UsersModule.clearFilters()">
                                        <i class="fas fa-times"></i> Limpiar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Estadísticas rápidas -->
                <div class="row mb-4" id="usersStats">
                    <!-- Se cargarán dinámicamente -->
                </div>

                <!-- Tabla de usuarios -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Lista de Usuarios</h5>
                    </div>
                    <div class="card-body">
                        <div id="usersLoading" class="text-center py-4" style="display: none;">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                            <p class="mt-2">Cargando usuarios...</p>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover" id="usersTable">
                                <thead>
                                    <tr>
                                        <th>Usuario</th>
                                        <th>Email</th>
                                        <th>Teléfono</th>
                                        <th>Roles</th>
                                        <th>Estado</th>
                                        <th>Último Login</th>
                                        <th>Fecha Registro</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="usersTableBody">
                                    <!-- Se cargarán dinámicamente -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginación -->
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div id="usersInfo">
                                <!-- Información de paginación -->
                            </div>
                            <nav>
                                <ul class="pagination mb-0" id="usersPagination">
                                    <!-- Paginación dinámica -->
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para crear/editar usuario -->
            <div class="modal fade" id="userModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="userModalTitle">Nuevo Usuario</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="userForm">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label>Nombre de Usuario *</label>
                                            <input type="text" class="form-control" name="username" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label>Email *</label>
                                            <input type="email" class="form-control" name="email" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label>Nombre *</label>
                                            <input type="text" class="form-control" name="first_name" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label>Apellido *</label>
                                            <input type="text" class="form-control" name="last_name" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label>Teléfono</label>
                                            <input type="tel" class="form-control" name="phone">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label>Estado</label>
                                            <select class="form-control" name="status">
                                                <option value="active">Activo</option>
                                                <option value="inactive">Inactivo</option>
                                                <option value="suspended">Suspendido</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label>Contraseña <span id="passwordRequired">*</span></label>
                                            <input type="password" class="form-control" name="password" id="passwordField">
                                            <small class="text-muted" id="passwordHelp">Deja en blanco para mantener la actual</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label>Rol Principal</label>
                                            <select class="form-control" name="role_id" id="roleSelect">
                                                <option value="">Seleccionar rol...</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" name="id" id="userId">
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary" onclick="UsersModule.saveUser()">
                                <span id="saveUserText">Guardar Usuario</span>
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
        
        document.getElementById('statusFilter').addEventListener('change', () => this.applyFilters());
    },

    // Cargar roles
    async loadRoles() {
        try {
            const response = await App.apiRequest('/roles.php');
            
            if (response.success) {
                this.state.roles = response.data.roles;
                this.populateRoleSelect();
            }
        } catch (error) {
            console.error('Error loading roles:', error);
        }
    },

    // Poblar select de roles
    populateRoleSelect() {
        const select = document.getElementById('roleSelect');
        if (!select) return;

        select.innerHTML = '<option value="">Seleccionar rol...</option>';
        
        this.state.roles.forEach(role => {
            const option = document.createElement('option');
            option.value = role.id;
            option.textContent = role.display_name;
            select.appendChild(option);
        });
    },

    // Cargar usuarios desde la API
    async loadUsers(page = 1) {
        this.state.loading = true;
        this.showLoading(true);

        try {
            const params = new URLSearchParams({
                page: page,
                limit: 20,
                ...this.state.filters
            });

            const response = await App.apiRequest(`/users.php?${params}`);
            
            if (response.success) {
                this.state.users = response.data.users;
                this.state.currentPage = response.data.pagination.page;
                this.state.totalPages = response.data.pagination.pages;
                
                this.renderUsersTable();
                this.renderPagination();
                this.renderStats();
            } else {
                throw new Error(response.message || 'Error al cargar usuarios');
            }
        } catch (error) {
            console.error('Error loading users:', error);
            App.showNotification('Error al cargar usuarios: ' + error.message, 'error');
            this.renderEmptyState();
        } finally {
            this.state.loading = false;
            this.showLoading(false);
        }
    },

    // Renderizar tabla de usuarios
    renderUsersTable() {
        const tbody = document.getElementById('usersTableBody');
        
        if (this.state.users.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-4">
                        <i class="fas fa-user-tie fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No se encontraron usuarios</p>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = this.state.users.map(user => `
            <tr>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2">
                            ${user.first_name.charAt(0)}${user.last_name.charAt(0)}
                        </div>
                        <div>
                            <div class="fw-bold">${user.first_name} ${user.last_name}</div>
                            <small class="text-muted">@${user.username}</small>
                        </div>
                    </div>
                </td>
                <td>${user.email}</td>
                <td>${user.phone || 'N/A'}</td>
                <td>
                    ${user.roles && user.roles.length > 0 ? 
                        user.roles.map(role => `<span class="badge bg-secondary me-1">${role}</span>`).join('') :
                        '<span class="text-muted">Sin roles</span>'
                    }
                </td>
                <td>
                    <span class="badge bg-${this.getStatusColor(user.status)}">
                        ${this.getStatusText(user.status)}
                    </span>
                </td>
                <td>${user.last_login ? App.formatDateTime(user.last_login) : 'Nunca'}</td>
                <td>${App.formatDate(user.created_at)}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-info" onclick="UsersModule.viewUser(${user.id})" title="Ver Usuario">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-outline-success" onclick="UsersModule.showAssignDeskModal(${user.id})" title="Asignar Desk">
                            <i class="fas fa-desktop"></i>
                        </button>
                        <button class="btn btn-outline-primary" onclick="UsersModule.showAssignRoleModal(${user.id})" title="Asignar Rol">
                            <i class="fas fa-user-tag"></i>
                        </button>
                        <button class="btn btn-outline-warning" onclick="UsersModule.toggleUserStatus(${user.id})" title="${user.status === 'active' ? 'Desactivar' : 'Activar'}">
                            <i class="fas fa-${user.status === 'active' ? 'user-slash' : 'user-check'}"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="UsersModule.deleteUser(${user.id})" title="Eliminar">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    },

    // Renderizar estadísticas
    renderStats() {
        const stats = this.calculateStats();
        const statsContainer = document.getElementById('usersStats');
        
        statsContainer.innerHTML = `
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4>${stats.total}</h4>
                                <p class="mb-0">Total Usuarios</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-users fa-2x"></i>
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
                                <h4>${stats.active}</h4>
                                <p class="mb-0">Activos</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-user-check fa-2x"></i>
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
                                <h4>${stats.inactive}</h4>
                                <p class="mb-0">Inactivos</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-user-times fa-2x"></i>
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
                                <h4>${stats.recentLogins}</h4>
                                <p class="mb-0">Logins Recientes</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-sign-in-alt fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    },

    // Calcular estadísticas
    calculateStats() {
        const total = this.state.users.length;
        const active = this.state.users.filter(user => user.status === 'active').length;
        const inactive = this.state.users.filter(user => user.status !== 'active').length;
        
        // Usuarios con login en los últimos 7 días
        const weekAgo = new Date();
        weekAgo.setDate(weekAgo.getDate() - 7);
        const recentLogins = this.state.users.filter(user => 
            user.last_login && new Date(user.last_login) > weekAgo
        ).length;

        return { total, active, inactive, recentLogins };
    },

    // Aplicar filtros
    applyFilters() {
        this.state.filters.search = document.getElementById('searchInput').value;
        this.state.filters.status = document.getElementById('statusFilter').value;
        
        this.loadUsers(1);
    },

    // Limpiar filtros
    clearFilters() {
        document.getElementById('searchInput').value = '';
        document.getElementById('statusFilter').value = '';
        
        this.state.filters = { search: '', status: '' };
        this.loadUsers(1);
    },

    // Mostrar modal de creación
    showCreateModal() {
        document.getElementById('userModalTitle').textContent = 'Nuevo Usuario';
        document.getElementById('userForm').reset();
        document.getElementById('userId').value = '';
        document.getElementById('passwordField').required = true;
        document.getElementById('passwordRequired').style.display = 'inline';
        document.getElementById('passwordHelp').style.display = 'none';
        
        const modal = new bootstrap.Modal(document.getElementById('userModal'));
        modal.show();
    },

    // Editar usuario
    async editUser(id) {
        const user = this.state.users.find(u => u.id == id);
        if (!user) return;

        document.getElementById('userModalTitle').textContent = 'Editar Usuario';
        document.getElementById('userId').value = user.id;
        document.getElementById('passwordField').required = false;
        document.getElementById('passwordRequired').style.display = 'none';
        document.getElementById('passwordHelp').style.display = 'block';
        
        // Llenar formulario
        const form = document.getElementById('userForm');
        form.username.value = user.username;
        form.email.value = user.email;
        form.first_name.value = user.first_name;
        form.last_name.value = user.last_name;
        form.phone.value = user.phone || '';
        form.status.value = user.status;
        form.password.value = '';

        const modal = new bootstrap.Modal(document.getElementById('userModal'));
        modal.show();
    },

    // Guardar usuario
    async saveUser() {
        const form = document.getElementById('userForm');
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);
        
        // Remover password si está vacío en edición
        if (!data.password && data.id) {
            delete data.password;
        }
        
        const isEdit = !!data.id;
        
        try {
            const response = isEdit ? 
                await App.apiRequest(`/users.php?id=${data.id}`, {
                    method: 'PUT',
                    body: JSON.stringify(data)
                }) :
                await App.apiRequest('/users.php', {
                    method: 'POST',
                    body: JSON.stringify(data)
                });

            if (response.success) {
                App.showNotification(
                    isEdit ? 'Usuario actualizado correctamente' : 'Usuario creado correctamente', 
                    'success'
                );
                
                const modal = bootstrap.Modal.getInstance(document.getElementById('userModal'));
                modal.hide();
                
                this.loadUsers(this.state.currentPage);
            } else {
                throw new Error(response.message || 'Error al guardar usuario');
            }
        } catch (error) {
            console.error('Error saving user:', error);
            App.showNotification('Error al guardar usuario: ' + error.message, 'error');
        }
    },

    // Eliminar usuario
    // Ver usuario
    async viewUser(id) {
        try {
            const response = await App.apiRequest(`/users.php?id=${id}`);
            if (response.success) {
                this.showUserDetailsModal(response.data);
            }
        } catch (error) {
            App.showNotification('Error al cargar usuario', 'error');
        }
    },

    // Mostrar modal de detalles del usuario
    showUserDetailsModal(user) {
        const modal = document.getElementById('userDetailsModal');
        if (!modal) {
            this.createUserDetailsModal();
        }
        
        document.getElementById('userDetailsName').textContent = `${user.first_name} ${user.last_name}`;
        document.getElementById('userDetailsEmail').textContent = user.email;
        document.getElementById('userDetailsPhone').textContent = user.phone || 'N/A';
        document.getElementById('userDetailsStatus').innerHTML = `<span class="badge bg-${this.getStatusColor(user.status)}">${this.getStatusText(user.status)}</span>`;
        document.getElementById('userDetailsRoles').innerHTML = user.roles && user.roles.length > 0 ? 
            user.roles.map(role => `<span class="badge bg-secondary me-1">${role}</span>`).join('') :
            '<span class="text-muted">Sin roles asignados</span>';
        document.getElementById('userDetailsCreated').textContent = App.formatDate(user.created_at);
        document.getElementById('userDetailsLastLogin').textContent = user.last_login ? App.formatDateTime(user.last_login) : 'Nunca';
        
        const modalInstance = new bootstrap.Modal(document.getElementById('userDetailsModal'));
        modalInstance.show();
    },

    // Crear modal de detalles del usuario
    createUserDetailsModal() {
        const modalHtml = `
            <div class="modal fade" id="userDetailsModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Detalles del Usuario</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Nombre:</strong>
                                    <p id="userDetailsName"></p>
                                </div>
                                <div class="col-md-6">
                                    <strong>Email:</strong>
                                    <p id="userDetailsEmail"></p>
                                </div>
                                <div class="col-md-6">
                                    <strong>Teléfono:</strong>
                                    <p id="userDetailsPhone"></p>
                                </div>
                                <div class="col-md-6">
                                    <strong>Estado:</strong>
                                    <p id="userDetailsStatus"></p>
                                </div>
                                <div class="col-md-12">
                                    <strong>Roles:</strong>
                                    <p id="userDetailsRoles"></p>
                                </div>
                                <div class="col-md-6">
                                    <strong>Creado:</strong>
                                    <p id="userDetailsCreated"></p>
                                </div>
                                <div class="col-md-6">
                                    <strong>Último acceso:</strong>
                                    <p id="userDetailsLastLogin"></p>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
    },

    // Mostrar modal para asignar desk
    async showAssignDeskModal(userId) {
        try {
            // Cargar desks disponibles
            const desksResponse = await App.apiRequest('/desks.php?limit=100');
            if (!desksResponse.success) {
                throw new Error('Error al cargar desks');
            }

            // Obtener usuario actual
            const userResponse = await App.apiRequest(`/users.php?id=${userId}`);
            if (!userResponse.success) {
                throw new Error('Error al cargar usuario');
            }

            this.currentUserId = userId;
            this.availableDesks = desksResponse.data;
            this.currentUser = userResponse.data;

            const modal = document.getElementById('assignDeskModal');
            if (!modal) {
                this.createAssignDeskModal();
            }

            // Poblar select de desks
            const deskSelect = document.getElementById('assignDeskSelect');
            deskSelect.innerHTML = '<option value="">Seleccionar desk...</option>';
            
            this.availableDesks.forEach(desk => {
                const option = document.createElement('option');
                option.value = desk.id;
                option.textContent = desk.name;
                deskSelect.appendChild(option);
            });

            document.getElementById('assignDeskUserName').textContent = `${this.currentUser.first_name} ${this.currentUser.last_name}`;

            const modalInstance = new bootstrap.Modal(document.getElementById('assignDeskModal'));
            modalInstance.show();
        } catch (error) {
            App.showNotification('Error al cargar datos para asignación', 'error');
        }
    },

    // Crear modal de asignación de desk
    createAssignDeskModal() {
        const modalHtml = `
            <div class="modal fade" id="assignDeskModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Asignar Desk</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Asignar desk al usuario: <strong id="assignDeskUserName"></strong></p>
                            <div class="mb-3">
                                <label for="assignDeskSelect" class="form-label">Seleccionar Desk</label>
                                <select class="form-select" id="assignDeskSelect" required>
                                    <option value="">Seleccionar desk...</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary" onclick="UsersModule.assignDesk()">Asignar</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
    },

    // Asignar desk al usuario
    async assignDesk() {
        const deskId = document.getElementById('assignDeskSelect').value;
        
        if (!deskId) {
            App.showNotification('Selecciona un desk', 'warning');
            return;
        }

        try {
            const response = await App.apiRequest('/users.php', {
                method: 'PUT',
                body: JSON.stringify({
                    id: this.currentUserId,
                    action: 'assign_desk',
                    desk_id: deskId
                })
            });

            if (response.success) {
                App.showNotification('Desk asignado exitosamente', 'success');
                bootstrap.Modal.getInstance(document.getElementById('assignDeskModal')).hide();
                this.loadUsers(); // Recargar tabla
            } else {
                throw new Error(response.message || 'Error al asignar desk');
            }
        } catch (error) {
            App.showNotification('Error al asignar desk: ' + error.message, 'error');
        }
    },

    // Mostrar modal para asignar rol
    async showAssignRoleModal(userId) {
        try {
            // Obtener usuario actual
            const userResponse = await App.apiRequest(`/users.php?id=${userId}`);
            if (!userResponse.success) {
                throw new Error('Error al cargar usuario');
            }

            this.currentUserId = userId;
            this.currentUser = userResponse.data;

            const modal = document.getElementById('assignRoleModal');
            if (!modal) {
                this.createAssignRoleModal();
            }

            // Poblar select de roles
            const roleSelect = document.getElementById('assignRoleSelect');
            roleSelect.innerHTML = '<option value="">Seleccionar rol...</option>';
            
            this.state.roles.forEach(role => {
                const option = document.createElement('option');
                option.value = role.id;
                option.textContent = role.display_name;
                roleSelect.appendChild(option);
            });

            document.getElementById('assignRoleUserName').textContent = `${this.currentUser.first_name} ${this.currentUser.last_name}`;

            const modalInstance = new bootstrap.Modal(document.getElementById('assignRoleModal'));
            modalInstance.show();
        } catch (error) {
            App.showNotification('Error al cargar datos para asignación', 'error');
        }
    },

    // Crear modal de asignación de rol
    createAssignRoleModal() {
        const modalHtml = `
            <div class="modal fade" id="assignRoleModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Asignar Rol</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Asignar rol al usuario: <strong id="assignRoleUserName"></strong></p>
                            <div class="mb-3">
                                <label for="assignRoleSelect" class="form-label">Seleccionar Rol</label>
                                <select class="form-select" id="assignRoleSelect" required>
                                    <option value="">Seleccionar rol...</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary" onclick="UsersModule.assignRole()">Asignar</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
    },

    // Asignar rol al usuario
    async assignRole() {
        const roleId = document.getElementById('assignRoleSelect').value;
        
        if (!roleId) {
            App.showNotification('Selecciona un rol', 'warning');
            return;
        }

        try {
            const response = await App.apiRequest('/users.php', {
                method: 'PUT',
                body: JSON.stringify({
                    id: this.currentUserId,
                    action: 'assign_role',
                    role_id: roleId
                })
            });

            if (response.success) {
                App.showNotification('Rol asignado exitosamente', 'success');
                bootstrap.Modal.getInstance(document.getElementById('assignRoleModal')).hide();
                this.loadUsers(); // Recargar tabla
            } else {
                throw new Error(response.message || 'Error al asignar rol');
            }
        } catch (error) {
            App.showNotification('Error al asignar rol: ' + error.message, 'error');
        }
    },

    // Cambiar estado del usuario (activar/desactivar)
    async toggleUserStatus(id) {
        const user = this.state.users.find(u => u.id === id);
        if (!user) return;

        const newStatus = user.status === 'active' ? 'inactive' : 'active';
        const action = newStatus === 'active' ? 'activar' : 'desactivar';

        if (!confirm(`¿Estás seguro de que quieres ${action} este usuario?`)) {
            return;
        }

        try {
            const response = await App.apiRequest('/users.php', {
                method: 'PUT',
                body: JSON.stringify({
                    id: id,
                    action: 'toggle_status',
                    status: newStatus
                })
            });

            if (response.success) {
                App.showNotification(`Usuario ${action === 'activar' ? 'activado' : 'desactivado'} exitosamente`, 'success');
                this.loadUsers(); // Recargar tabla
            } else {
                throw new Error(response.message || `Error al ${action} usuario`);
            }
        } catch (error) {
            App.showNotification(`Error al ${action} usuario: ` + error.message, 'error');
        }
    },

    // Eliminar usuario
    async deleteUser(id) {
        if (!confirm('¿Estás seguro de que quieres eliminar este usuario?')) return;

        try {
            const response = await App.apiRequest(`/users.php?id=${id}`, {
                method: 'DELETE'
            });

            if (response.success) {
                App.showNotification('Usuario eliminado correctamente', 'success');
                this.loadUsers(this.state.currentPage);
            } else {
                throw new Error(response.message || 'Error al eliminar usuario');
            }
        } catch (error) {
            console.error('Error deleting user:', error);
            App.showNotification('Error al eliminar usuario: ' + error.message, 'error');
        }
    },

    // Utilidades
    getStatusColor(status) {
        const colors = {
            'active': 'success',
            'inactive': 'secondary',
            'suspended': 'danger'
        };
        return colors[status] || 'secondary';
    },

    getStatusText(status) {
        const texts = {
            'active': 'Activo',
            'inactive': 'Inactivo',
            'suspended': 'Suspendido'
        };
        return texts[status] || status;
    },

    showLoading(show) {
        const loading = document.getElementById('usersLoading');
        if (loading) {
            loading.style.display = show ? 'block' : 'none';
        }
    },

    renderEmptyState() {
        const tbody = document.getElementById('usersTableBody');
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-5">
                    <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                    <p class="text-muted">Error al cargar los datos</p>
                    <button class="btn btn-primary" onclick="UsersModule.loadUsers()">
                        <i class="fas fa-refresh"></i> Reintentar
                    </button>
                </td>
            </tr>
        `;
    },

    renderPagination() {
        // Implementar paginación
        const pagination = document.getElementById('usersPagination');
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
window.UsersModule = UsersModule;