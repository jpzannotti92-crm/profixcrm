/**
 * Módulo de Gestión de Mesas (Desks)
 * Conectado a la base de datos real
 */

const DesksModule = {
    // Estado del módulo
    state: {
        desks: [],
        users: [],
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
        console.log('Inicializando módulo de Mesas');
        this.render();
        await this.loadUsers();
        await this.loadDesks();
        this.bindEvents();
    },

    // Renderizar interfaz
    render() {
        const content = document.getElementById('moduleContent');
        content.innerHTML = `
            <div class="desks-module">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2><i class="fas fa-table"></i> Gestión de Mesas</h2>
                        <p class="text-muted">Administra las mesas de trabajo y asignaciones</p>
                    </div>
                    <button class="btn btn-primary" onclick="DesksModule.showCreateModal()">
                        <i class="fas fa-plus"></i> Nueva Mesa
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
                                           placeholder="Nombre de mesa, descripción...">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Estado</label>
                                    <select class="form-control" id="statusFilter">
                                        <option value="">Todos los estados</option>
                                        <option value="active">Activa</option>
                                        <option value="inactive">Inactiva</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <button class="btn btn-outline-secondary btn-block" onclick="DesksModule.clearFilters()">
                                        <i class="fas fa-times"></i> Limpiar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Estadísticas rápidas -->
                <div class="row mb-4" id="desksStats">
                    <!-- Se cargarán dinámicamente -->
                </div>

                <!-- Tabla de mesas -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Lista de Mesas</h5>
                    </div>
                    <div class="card-body">
                        <div id="desksLoading" class="text-center py-4" style="display: none;">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                            <p class="mt-2">Cargando mesas...</p>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover" id="desksTable">
                                <thead>
                                    <tr>
                                        <th>Mesa</th>
                                        <th>Descripción</th>
                                        <th>Usuarios</th>
                                        <th>Leads</th>
                                        <th>Conversión</th>
                                        <th>Estado</th>
                                        <th>Fecha Creación</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="desksTableBody">
                                    <!-- Se cargarán dinámicamente -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginación -->
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div id="desksInfo">
                                <!-- Información de paginación -->
                            </div>
                            <nav>
                                <ul class="pagination mb-0" id="desksPagination">
                                    <!-- Paginación dinámica -->
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para crear/editar mesa -->
            <div class="modal fade" id="deskModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="deskModalTitle">Nueva Mesa</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="deskForm">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label>Nombre de la Mesa *</label>
                                            <input type="text" class="form-control" name="name" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label>Estado</label>
                                            <select class="form-control" name="status">
                                                <option value="active">Activa</option>
                                                <option value="inactive">Inactiva</option>
                                            </select>
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
                                        <div class="form-group mb-3">
                                            <label>Máximo Usuarios</label>
                                            <input type="number" class="form-control" name="max_users" value="10" min="1" max="50">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Usuarios asignados -->
                                <div class="form-group mb-3">
                                    <label>Usuarios Asignados</label>
                                    <div id="usersContainer" class="border rounded p-3" style="max-height: 200px; overflow-y: auto;">
                                        <div class="text-muted">Cargando usuarios...</div>
                                    </div>
                                </div>
                                
                                <input type="hidden" name="id" id="deskId">
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary" onclick="DesksModule.saveDesk()">
                                <span id="saveDeskText">Guardar Mesa</span>
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

    // Cargar usuarios
    async loadUsers() {
        try {
            const response = await App.apiRequest('/users.php?limit=100');
            
            if (response.success) {
                this.state.users = response.data.users;
                this.populateUsersContainer();
            }
        } catch (error) {
            console.error('Error loading users:', error);
        }
    },

    // Poblar contenedor de usuarios
    populateUsersContainer() {
        const container = document.getElementById('usersContainer');
        if (!container) return;

        if (this.state.users.length === 0) {
            container.innerHTML = '<div class="text-muted">No hay usuarios disponibles</div>';
            return;
        }

        container.innerHTML = this.state.users.map(user => `
            <div class="form-check">
                <input class="form-check-input" type="checkbox" value="${user.id}" id="user_${user.id}">
                <label class="form-check-label" for="user_${user.id}">
                    ${user.first_name} ${user.last_name} (${user.username})
                </label>
            </div>
        `).join('');
    },

    // Cargar mesas desde la API
    async loadDesks(page = 1) {
        this.state.loading = true;
        this.showLoading(true);

        try {
            const params = new URLSearchParams({
                page: page,
                limit: 20,
                ...this.state.filters
            });

            const response = await App.apiRequest(`/desks.php?${params}`);
            
            if (response.success) {
                this.state.desks = response.data.desks;
                this.state.currentPage = response.data.pagination.page;
                this.state.totalPages = response.data.pagination.pages;
                
                this.renderDesksTable();
                this.renderPagination();
                this.renderStats();
            } else {
                throw new Error(response.message || 'Error al cargar mesas');
            }
        } catch (error) {
            console.error('Error loading desks:', error);
            App.showNotification('Error al cargar mesas: ' + error.message, 'error');
            this.renderEmptyState();
        } finally {
            this.state.loading = false;
            this.showLoading(false);
        }
    },

    // Renderizar tabla de mesas
    renderDesksTable() {
        const tbody = document.getElementById('desksTableBody');
        
        if (this.state.desks.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-4">
                        <i class="fas fa-table fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No se encontraron mesas</p>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = this.state.desks.map(desk => `
            <tr>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="badge me-2" style="background-color: ${desk.color}; width: 12px; height: 12px;"></div>
                        <div>
                            <div class="fw-bold">${desk.name}</div>
                            <small class="text-muted">Máx: ${desk.max_users} usuarios</small>
                        </div>
                    </div>
                </td>
                <td>${desk.description || 'Sin descripción'}</td>
                <td>
                    <span class="badge bg-info">${desk.assigned_users}/${desk.max_users}</span>
                    ${desk.users && desk.users.length > 0 ? `
                        <div class="mt-1">
                            ${desk.users.slice(0, 3).map(user => 
                                `<small class="badge bg-secondary me-1">${user.first_name}</small>`
                            ).join('')}
                            ${desk.users.length > 3 ? `<small class="text-muted">+${desk.users.length - 3} más</small>` : ''}
                        </div>
                    ` : ''}
                </td>
                <td>
                    <div>
                        <span class="badge bg-primary">${desk.total_leads} total</span>
                        <span class="badge bg-success">${desk.converted_leads} convertidos</span>
                    </div>
                </td>
                <td>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar bg-success" role="progressbar" 
                             style="width: ${desk.conversion_rate}%" 
                             aria-valuenow="${desk.conversion_rate}" aria-valuemin="0" aria-valuemax="100">
                            ${desk.conversion_rate}%
                        </div>
                    </div>
                </td>
                <td>
                    <span class="badge bg-${this.getStatusColor(desk.status)}">
                        ${this.getStatusText(desk.status)}
                    </span>
                </td>
                <td>${App.formatDate(desk.created_at)}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="DesksModule.editDesk(${desk.id})" title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="DesksModule.deleteDesk(${desk.id})" title="Eliminar">
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
        const statsContainer = document.getElementById('desksStats');
        
        statsContainer.innerHTML = `
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4>${stats.total}</h4>
                                <p class="mb-0">Total Mesas</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-table fa-2x"></i>
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
                                <p class="mb-0">Activas</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-check-circle fa-2x"></i>
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
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4>${stats.totalLeads}</h4>
                                <p class="mb-0">Total Leads</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-user-friends fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    },

    // Calcular estadísticas
    calculateStats() {
        const total = this.state.desks.length;
        const active = this.state.desks.filter(desk => desk.status === 'active').length;
        const totalUsers = this.state.desks.reduce((sum, desk) => sum + (parseInt(desk.assigned_users) || 0), 0);
        const totalLeads = this.state.desks.reduce((sum, desk) => sum + (parseInt(desk.total_leads) || 0), 0);

        return { total, active, totalUsers, totalLeads };
    },

    // Aplicar filtros
    applyFilters() {
        this.state.filters.search = document.getElementById('searchInput').value;
        this.state.filters.status = document.getElementById('statusFilter').value;
        
        this.loadDesks(1);
    },

    // Limpiar filtros
    clearFilters() {
        document.getElementById('searchInput').value = '';
        document.getElementById('statusFilter').value = '';
        
        this.state.filters = { search: '', status: '' };
        this.loadDesks(1);
    },

    // Mostrar modal de creación
    showCreateModal() {
        document.getElementById('deskModalTitle').textContent = 'Nueva Mesa';
        document.getElementById('deskForm').reset();
        document.getElementById('deskId').value = '';
        
        // Limpiar checkboxes de usuarios
        const checkboxes = document.querySelectorAll('#usersContainer input[type="checkbox"]');
        checkboxes.forEach(cb => cb.checked = false);
        
        const modal = new bootstrap.Modal(document.getElementById('deskModal'));
        modal.show();
    },

    // Editar mesa
    async editDesk(id) {
        const desk = this.state.desks.find(d => d.id == id);
        if (!desk) return;

        document.getElementById('deskModalTitle').textContent = 'Editar Mesa';
        document.getElementById('deskId').value = desk.id;
        
        // Llenar formulario
        const form = document.getElementById('deskForm');
        form.name.value = desk.name;
        form.description.value = desk.description || '';
        form.color.value = desk.color || '#007bff';
        form.status.value = desk.status;
        form.max_users.value = desk.max_users || 10;

        // Marcar usuarios asignados
        const checkboxes = document.querySelectorAll('#usersContainer input[type="checkbox"]');
        checkboxes.forEach(cb => cb.checked = false);
        
        if (desk.users) {
            desk.users.forEach(user => {
                const checkbox = document.getElementById(`user_${user.id}`);
                if (checkbox) checkbox.checked = true;
            });
        }

        const modal = new bootstrap.Modal(document.getElementById('deskModal'));
        modal.show();
    },

    // Guardar mesa
    async saveDesk() {
        const form = document.getElementById('deskForm');
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);
        
        // Obtener usuarios seleccionados
        const selectedUsers = [];
        const checkboxes = document.querySelectorAll('#usersContainer input[type="checkbox"]:checked');
        checkboxes.forEach(cb => selectedUsers.push(parseInt(cb.value)));
        
        data.users = selectedUsers;
        
        const isEdit = !!data.id;
        
        try {
            const response = isEdit ? 
                await App.apiRequest(`/desks.php?id=${data.id}`, {
                    method: 'PUT',
                    body: JSON.stringify(data)
                }) :
                await App.apiRequest('/desks.php', {
                    method: 'POST',
                    body: JSON.stringify(data)
                });

            if (response.success) {
                App.showNotification(
                    isEdit ? 'Mesa actualizada correctamente' : 'Mesa creada correctamente', 
                    'success'
                );
                
                const modal = bootstrap.Modal.getInstance(document.getElementById('deskModal'));
                modal.hide();
                
                this.loadDesks(this.state.currentPage);
            } else {
                throw new Error(response.message || 'Error al guardar mesa');
            }
        } catch (error) {
            console.error('Error saving desk:', error);
            App.showNotification('Error al guardar mesa: ' + error.message, 'error');
        }
    },

    // Eliminar mesa
    async deleteDesk(id) {
        if (!confirm('¿Estás seguro de que quieres eliminar esta mesa?')) return;

        try {
            const response = await App.apiRequest(`/desks.php?id=${id}`, {
                method: 'DELETE'
            });

            if (response.success) {
                App.showNotification('Mesa eliminada correctamente', 'success');
                this.loadDesks(this.state.currentPage);
            } else {
                throw new Error(response.message || 'Error al eliminar mesa');
            }
        } catch (error) {
            console.error('Error deleting desk:', error);
            App.showNotification('Error al eliminar mesa: ' + error.message, 'error');
        }
    },

    // Utilidades
    getStatusColor(status) {
        const colors = {
            'active': 'success',
            'inactive': 'secondary'
        };
        return colors[status] || 'secondary';
    },

    getStatusText(status) {
        const texts = {
            'active': 'Activa',
            'inactive': 'Inactiva'
        };
        return texts[status] || status;
    },

    showLoading(show) {
        const loading = document.getElementById('desksLoading');
        if (loading) {
            loading.style.display = show ? 'block' : 'none';
        }
    },

    renderEmptyState() {
        const tbody = document.getElementById('desksTableBody');
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-5">
                    <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                    <p class="text-muted">Error al cargar los datos</p>
                    <button class="btn btn-primary" onclick="DesksModule.loadDesks()">
                        <i class="fas fa-refresh"></i> Reintentar
                    </button>
                </td>
            </tr>
        `;
    },

    renderPagination() {
        // Implementar paginación
        const pagination = document.getElementById('desksPagination');
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
window.DesksModule = DesksModule;