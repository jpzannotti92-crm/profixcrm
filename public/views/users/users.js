/**
 * Módulo de Gestión de Usuarios
 * Sistema modular para iaTrade CRM
 */
const UsersModule = {
    // Datos del módulo
    data: {
        users: [],
        currentPage: 1,
        itemsPerPage: 10,
        totalItems: 0,
        filters: {},
        selectedUsers: [],
        editingUserId: null
    },

    // Inicialización del módulo
    init() {
        console.log('Inicializando módulo de Usuarios...');
        this.loadData();
        this.bindEvents();
        this.updateStats();
    },

    // Cargar datos de usuarios
    loadData() {
        // Datos de demostración
        this.data.users = [
            {
                id: 1,
                firstName: 'María',
                lastName: 'García',
                email: 'maria.garcia@iatrade.com',
                username: 'mgarcia',
                phone: '+34 600 123 456',
                role: 'manager',
                desk: 'Desk Alpha',
                deskId: 1,
                status: 'active',
                lastLogin: '2024-01-15 14:30:00',
                salary: 65000,
                commission: 3.0,
                startDate: '2023-06-01',
                leads: 45,
                ftds: 12,
                revenue: 28500,
                performance: 92,
                online: true,
                avatar: 'MG',
                notes: 'Manager experimentada con excelente performance'
            },
            {
                id: 2,
                firstName: 'Carlos',
                lastName: 'López',
                email: 'carlos.lopez@iatrade.com',
                username: 'clopez',
                phone: '+34 600 234 567',
                role: 'manager',
                desk: 'Desk Beta',
                deskId: 2,
                status: 'active',
                lastLogin: '2024-01-15 13:45:00',
                salary: 62000,
                commission: 2.8,
                startDate: '2023-07-15',
                leads: 38,
                ftds: 9,
                revenue: 22100,
                performance: 85,
                online: true,
                avatar: 'CL',
                notes: 'Especialista en retención de clientes'
            },
            {
                id: 3,
                firstName: 'Ana',
                lastName: 'Martínez',
                email: 'ana.martinez@iatrade.com',
                username: 'amartinez',
                phone: '+34 600 345 678',
                role: 'senior_sales',
                desk: 'Desk Alpha',
                deskId: 1,
                status: 'active',
                lastLogin: '2024-01-15 15:20:00',
                salary: 48000,
                commission: 2.5,
                startDate: '2023-08-01',
                leads: 52,
                ftds: 15,
                revenue: 35200,
                performance: 96,
                online: true,
                avatar: 'AM',
                notes: 'Top performer del equipo'
            },
            {
                id: 4,
                firstName: 'Pedro',
                lastName: 'Rodríguez',
                email: 'pedro.rodriguez@iatrade.com',
                username: 'prodriguez',
                phone: '+34 600 456 789',
                role: 'sales',
                desk: 'Desk Gamma',
                deskId: 3,
                status: 'active',
                lastLogin: '2024-01-15 12:10:00',
                salary: 42000,
                commission: 2.0,
                startDate: '2023-09-15',
                leads: 34,
                ftds: 8,
                revenue: 18400,
                performance: 78,
                online: false,
                avatar: 'PR',
                notes: 'Vendedor junior con buen potencial'
            },
            {
                id: 5,
                firstName: 'Laura',
                lastName: 'Fernández',
                email: 'laura.fernandez@iatrade.com',
                username: 'lfernandez',
                phone: '+34 600 567 890',
                role: 'retention',
                desk: 'Desk Beta',
                deskId: 2,
                status: 'active',
                lastLogin: '2024-01-15 16:00:00',
                salary: 45000,
                commission: 2.2,
                startDate: '2023-10-01',
                leads: 28,
                ftds: 6,
                revenue: 14200,
                performance: 82,
                online: true,
                avatar: 'LF',
                notes: 'Especialista en retención VIP'
            },
            {
                id: 6,
                firstName: 'Miguel',
                lastName: 'Santos',
                email: 'miguel.santos@iatrade.com',
                username: 'msantos',
                phone: '+34 600 678 901',
                role: 'sales',
                desk: 'Desk Alpha',
                deskId: 1,
                status: 'inactive',
                lastLogin: '2024-01-10 09:30:00',
                salary: 40000,
                commission: 1.8,
                startDate: '2023-11-01',
                leads: 15,
                ftds: 2,
                revenue: 4800,
                performance: 45,
                online: false,
                avatar: 'MS',
                notes: 'En período de prueba'
            }
        ];

        this.data.totalItems = this.data.users.length;
        this.renderTable();
        this.renderPagination();
    },

    // Vincular eventos
    bindEvents() {
        // Checkbox select all
        const selectAll = document.getElementById('selectAllUsers');
        if (selectAll) {
            selectAll.addEventListener('change', (e) => {
                this.toggleSelectAll(e.target.checked);
            });
        }

        // Filtros
        const filterInputs = ['filterUserRole', 'filterUserDesk', 'filterUserStatus', 'filterUserSearch'];
        filterInputs.forEach(filterId => {
            const element = document.getElementById(filterId);
            if (element) {
                element.addEventListener('change', () => this.applyFilters());
                element.addEventListener('input', () => this.applyFilters());
            }
        });
    },

    // Renderizar tabla
    renderTable() {
        const tbody = document.getElementById('usersTableBody');
        if (!tbody) return;

        const filteredUsers = this.getFilteredUsers();
        const startIndex = (this.data.currentPage - 1) * this.data.itemsPerPage;
        const endIndex = startIndex + this.data.itemsPerPage;
        const pageUsers = filteredUsers.slice(startIndex, endIndex);

        tbody.innerHTML = pageUsers.map(user => `
            <tr>
                <td>
                    <input type="checkbox" class="form-check-input user-checkbox" 
                           value="${user.id}" onchange="UsersModule.toggleUserSelection(${user.id})">
                </td>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="user-avatar bg-${this.getAvatarColor(user.role)} me-3 position-relative" 
                             style="width: 40px; height: 40px; font-size: 0.875rem;">
                            ${user.avatar}
                            ${user.online ? '<span class="position-absolute bottom-0 end-0 bg-success border border-white rounded-circle" style="width: 12px; height: 12px;"></span>' : ''}
                        </div>
                        <div>
                            <div class="fw-semibold">${user.firstName} ${user.lastName}</div>
                            <small class="text-muted">@${user.username}</small>
                        </div>
                    </div>
                </td>
                <td>
                    <div>${user.email}</div>
                    <small class="text-muted">${user.phone}</small>
                </td>
                <td>
                    <span class="badge bg-${this.getRoleColor(user.role)}">${this.getRoleLabel(user.role)}</span>
                </td>
                <td>${user.desk || '<span class="text-muted">Sin asignar</span>'}</td>
                <td>
                    <span class="badge bg-${this.getStatusColor(user.status)}">${this.getStatusLabel(user.status)}</span>
                </td>
                <td>
                    <div>${this.formatDateTime(user.lastLogin)}</div>
                    <small class="text-muted">${this.getTimeAgo(user.lastLogin)}</small>
                </td>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="progress me-2" style="width: 60px; height: 8px;">
                            <div class="progress-bar bg-${this.getPerformanceColor(user.performance)}" 
                                 style="width: ${user.performance}%"></div>
                        </div>
                        <small class="fw-bold">${user.performance}%</small>
                    </div>
                </td>
                <td>
                    <div class="btn-group" role="group">
                        <button class="btn btn-sm btn-outline-primary" onclick="UsersModule.viewUser(${user.id})" title="Ver perfil">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-info" onclick="UsersModule.editUser(${user.id})" title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-success" onclick="UsersModule.viewStats(${user.id})" title="Estadísticas">
                            <i class="fas fa-chart-bar"></i>
                        </button>
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" onclick="UsersModule.resetPassword(${user.id})">
                                    <i class="fas fa-key me-2"></i>Reset Password
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="UsersModule.sendWelcomeEmail(${user.id})">
                                    <i class="fas fa-envelope me-2"></i>Enviar Bienvenida
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item ${user.status === 'active' ? 'text-warning' : 'text-success'}" 
                                       href="#" onclick="UsersModule.toggleUserStatus(${user.id})">
                                    <i class="fas fa-${user.status === 'active' ? 'pause' : 'play'} me-2"></i>
                                    ${user.status === 'active' ? 'Desactivar' : 'Activar'}
                                </a></li>
                                <li><a class="dropdown-item text-danger" href="#" onclick="UsersModule.deleteUser(${user.id})">
                                    <i class="fas fa-trash me-2"></i>Eliminar
                                </a></li>
                            </ul>
                        </div>
                    </div>
                </td>
            </tr>
        `).join('');
    },

    // Obtener usuarios filtrados
    getFilteredUsers() {
        let filtered = [...this.data.users];

        if (this.data.filters.role) {
            filtered = filtered.filter(user => user.role === this.data.filters.role);
        }
        if (this.data.filters.desk) {
            filtered = filtered.filter(user => user.deskId == this.data.filters.desk);
        }
        if (this.data.filters.status) {
            filtered = filtered.filter(user => user.status === this.data.filters.status);
        }
        if (this.data.filters.search) {
            const search = this.data.filters.search.toLowerCase();
            filtered = filtered.filter(user => 
                user.firstName.toLowerCase().includes(search) ||
                user.lastName.toLowerCase().includes(search) ||
                user.email.toLowerCase().includes(search) ||
                user.username.toLowerCase().includes(search)
            );
        }

        return filtered;
    },

    // Aplicar filtros
    applyFilters() {
        this.data.filters = {
            role: document.getElementById('filterUserRole')?.value || '',
            desk: document.getElementById('filterUserDesk')?.value || '',
            status: document.getElementById('filterUserStatus')?.value || '',
            search: document.getElementById('filterUserSearch')?.value || ''
        };

        this.data.currentPage = 1;
        this.renderTable();
        this.renderPagination();
        this.updateStats();
        
        App.showNotification('Filtros aplicados', 'info');
    },

    // Limpiar filtros
    clearFilters() {
        document.getElementById('filterUserRole').value = '';
        document.getElementById('filterUserDesk').value = '';
        document.getElementById('filterUserStatus').value = '';
        document.getElementById('filterUserSearch').value = '';
        
        this.data.filters = {};
        this.data.currentPage = 1;
        this.renderTable();
        this.renderPagination();
        this.updateStats();
        
        App.showNotification('Filtros limpiados', 'info');
    },

    // Actualizar estadísticas
    updateStats() {
        const filtered = this.getFilteredUsers();
        const stats = {
            total: filtered.length,
            active: filtered.filter(u => u.status === 'active').length,
            online: filtered.filter(u => u.online).length,
            new: filtered.filter(u => {
                const startDate = new Date(u.startDate);
                const weekAgo = new Date();
                weekAgo.setDate(weekAgo.getDate() - 7);
                return startDate >= weekAgo;
            }).length
        };

        document.getElementById('totalUsers').textContent = stats.total;
        document.getElementById('activeUsers').textContent = stats.active;
        document.getElementById('onlineUsers').textContent = stats.online;
        document.getElementById('newUsers').textContent = stats.new;
    },

    // Renderizar paginación
    renderPagination() {
        const pagination = document.getElementById('usersPagination');
        if (!pagination) return;

        const filtered = this.getFilteredUsers();
        const totalPages = Math.ceil(filtered.length / this.data.itemsPerPage);
        
        if (totalPages <= 1) {
            pagination.innerHTML = '';
            return;
        }

        let paginationHTML = '';
        
        // Botón anterior
        paginationHTML += `
            <li class="page-item ${this.data.currentPage === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="UsersModule.goToPage(${this.data.currentPage - 1})">Anterior</a>
            </li>
        `;

        // Páginas
        for (let i = 1; i <= totalPages; i++) {
            paginationHTML += `
                <li class="page-item ${i === this.data.currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="UsersModule.goToPage(${i})">${i}</a>
                </li>
            `;
        }

        // Botón siguiente
        paginationHTML += `
            <li class="page-item ${this.data.currentPage === totalPages ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="UsersModule.goToPage(${this.data.currentPage + 1})">Siguiente</a>
            </li>
        `;

        pagination.innerHTML = paginationHTML;
    },

    // Ir a página
    goToPage(page) {
        const filtered = this.getFilteredUsers();
        const totalPages = Math.ceil(filtered.length / this.data.itemsPerPage);
        
        if (page < 1 || page > totalPages) return;
        
        this.data.currentPage = page;
        this.renderTable();
        this.renderPagination();
    },

    // Mostrar modal de creación
    showCreateModal() {
        this.data.editingUserId = null;
        document.getElementById('userModalTitle').innerHTML = '<i class="fas fa-user-plus me-2"></i>Crear Empleado';
        document.getElementById('userForm').reset();
        document.getElementById('passwordSection').style.display = 'block';
        document.getElementById('userPassword').required = true;
        
        const modal = new bootstrap.Modal(document.getElementById('userModal'));
        modal.show();
    },

    // Ver usuario
    viewUser(userId) {
        const user = this.data.users.find(u => u.id === userId);
        if (!user) return;

        const profileContent = document.getElementById('userProfileContent');
        profileContent.innerHTML = `
            <div class="row">
                <div class="col-md-4 text-center">
                    <div class="user-avatar bg-${this.getAvatarColor(user.role)} mx-auto mb-3" 
                         style="width: 100px; height: 100px; font-size: 2rem;">
                        ${user.avatar}
                    </div>
                    <h4>${user.firstName} ${user.lastName}</h4>
                    <p class="text-muted">@${user.username}</p>
                    <span class="badge bg-${this.getRoleColor(user.role)} mb-2">${this.getRoleLabel(user.role)}</span>
                    <br>
                    <span class="badge bg-${this.getStatusColor(user.status)}">${this.getStatusLabel(user.status)}</span>
                </div>
                <div class="col-md-8">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <strong>Email:</strong><br>
                            <span class="text-muted">${user.email}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Teléfono:</strong><br>
                            <span class="text-muted">${user.phone}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Desk:</strong><br>
                            <span class="text-muted">${user.desk || 'Sin asignar'}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Fecha de Inicio:</strong><br>
                            <span class="text-muted">${this.formatDate(user.startDate)}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Último Login:</strong><br>
                            <span class="text-muted">${this.formatDateTime(user.lastLogin)}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Performance:</strong><br>
                            <div class="d-flex align-items-center">
                                <div class="progress me-2" style="width: 100px; height: 10px;">
                                    <div class="progress-bar bg-${this.getPerformanceColor(user.performance)}" 
                                         style="width: ${user.performance}%"></div>
                                </div>
                                <span class="fw-bold">${user.performance}%</span>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 text-center">
                            <h5 class="text-primary">${user.leads}</h5>
                            <small class="text-muted">Leads</small>
                        </div>
                        <div class="col-md-4 text-center">
                            <h5 class="text-success">${user.ftds}</h5>
                            <small class="text-muted">FTDs</small>
                        </div>
                        <div class="col-md-4 text-center">
                            <h5 class="text-warning">$${user.revenue.toLocaleString()}</h5>
                            <small class="text-muted">Revenue</small>
                        </div>
                    </div>
                    ${user.notes ? `
                        <div class="mt-3">
                            <strong>Notas:</strong><br>
                            <span class="text-muted">${user.notes}</span>
                        </div>
                    ` : ''}
                </div>
            </div>
        `;

        const modal = new bootstrap.Modal(document.getElementById('userProfileModal'));
        modal.show();
    },

    // Editar usuario
    editUser(userId) {
        const user = this.data.users.find(u => u.id === userId);
        if (!user) return;

        this.data.editingUserId = userId;
        
        // Llenar formulario
        document.getElementById('userFirstName').value = user.firstName;
        document.getElementById('userLastName').value = user.lastName;
        document.getElementById('userEmail').value = user.email;
        document.getElementById('username').value = user.username;
        document.getElementById('userPhone').value = user.phone;
        document.getElementById('userRole').value = user.role;
        document.getElementById('userDesk').value = user.deskId || '';
        document.getElementById('userSalary').value = user.salary;
        document.getElementById('userCommission').value = user.commission;
        document.getElementById('userStartDate').value = user.startDate;
        document.getElementById('userStatus').value = user.status;
        document.getElementById('userNotes').value = user.notes || '';

        document.getElementById('userModalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Editar Usuario';
        document.getElementById('passwordSection').style.display = 'none';
        document.getElementById('userPassword').required = false;

        const modal = new bootstrap.Modal(document.getElementById('userModal'));
        modal.show();
    },

    // Editar desde perfil
    editUserFromProfile() {
        const modal = bootstrap.Modal.getInstance(document.getElementById('userProfileModal'));
        modal.hide();
        
        // Obtener el ID del usuario desde el contexto
        // En una implementación real, esto se manejaría mejor
        setTimeout(() => {
            this.editUser(this.data.editingUserId || 1);
        }, 300);
    },

    // Guardar usuario
    saveUser() {
        const formData = {
            firstName: document.getElementById('userFirstName').value,
            lastName: document.getElementById('userLastName').value,
            email: document.getElementById('userEmail').value,
            username: document.getElementById('username').value,
            phone: document.getElementById('userPhone').value,
            role: document.getElementById('userRole').value,
            desk: document.getElementById('userDesk').value,
            salary: document.getElementById('userSalary').value,
            commission: document.getElementById('userCommission').value,
            startDate: document.getElementById('userStartDate').value,
            status: document.getElementById('userStatus').value,
            notes: document.getElementById('userNotes').value,
            password: document.getElementById('userPassword').value,
            sendWelcome: document.getElementById('sendWelcomeEmail').checked
        };

        // Validación básica
        if (!formData.firstName || !formData.lastName || !formData.email || !formData.username || !formData.role) {
            App.showNotification('Por favor completa todos los campos obligatorios', 'error');
            return;
        }

        // Simular guardado
        const action = this.data.editingUserId ? 'actualizado' : 'creado';
        App.showNotification(`Usuario ${action} exitosamente`, 'success');
        
        const modal = bootstrap.Modal.getInstance(document.getElementById('userModal'));
        modal.hide();
        
        // Recargar datos
        this.loadData();
    },

    // Ver estadísticas
    viewStats(userId) {
        const user = this.data.users.find(u => u.id === userId);
        if (!user) return;

        const statsContent = document.getElementById('userStatsContent');
        statsContent.innerHTML = `
            <div class="row mb-4">
                <div class="col-md-3 text-center">
                    <div class="card">
                        <div class="card-body">
                            <h3 class="text-primary">${user.leads}</h3>
                            <p class="mb-0">Total Leads</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 text-center">
                    <div class="card">
                        <div class="card-body">
                            <h3 class="text-success">${user.ftds}</h3>
                            <p class="mb-0">FTDs</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 text-center">
                    <div class="card">
                        <div class="card-body">
                            <h3 class="text-warning">$${user.revenue.toLocaleString()}</h3>
                            <p class="mb-0">Revenue</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 text-center">
                    <div class="card">
                        <div class="card-body">
                            <h3 class="text-info">${user.performance}%</h3>
                            <p class="mb-0">Performance</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Performance Mensual</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="userPerformanceChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Distribución de Leads</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="userLeadsChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        `;

        const modal = new bootstrap.Modal(document.getElementById('userStatsModal'));
        modal.show();

        // Inicializar gráficos (simulado)
        setTimeout(() => {
            this.initUserCharts();
        }, 300);
    },

    // Inicializar gráficos de usuario
    initUserCharts() {
        // Aquí se inicializarían los gráficos reales con Chart.js
        console.log('Inicializando gráficos de usuario...');
    },

    // Generar contraseña
    generatePassword() {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
        let password = '';
        for (let i = 0; i < 12; i++) {
            password += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        document.getElementById('userPassword').value = password;
    },

    // Cambiar estado de usuario
    toggleUserStatus(userId) {
        const user = this.data.users.find(u => u.id === userId);
        if (!user) return;

        const newStatus = user.status === 'active' ? 'inactive' : 'active';
        user.status = newStatus;
        
        App.showNotification(`Usuario ${newStatus === 'active' ? 'activado' : 'desactivado'}`, 'success');
        this.renderTable();
        this.updateStats();
    },

    // Eliminar usuario
    deleteUser(userId) {
        const user = this.data.users.find(u => u.id === userId);
        if (!user) return;

        if (confirm(`¿Estás seguro de eliminar al usuario "${user.firstName} ${user.lastName}"?`)) {
            this.data.users = this.data.users.filter(u => u.id !== userId);
            App.showNotification('Usuario eliminado exitosamente', 'success');
            this.renderTable();
            this.updateStats();
        }
    },

    // Reset password
    resetPassword(userId) {
        const user = this.data.users.find(u => u.id === userId);
        if (!user) return;

        if (confirm(`¿Resetear la contraseña de ${user.firstName} ${user.lastName}?`)) {
            App.showNotification('Contraseña reseteada. Se ha enviado email con la nueva contraseña.', 'success');
        }
    },

    // Enviar email de bienvenida
    sendWelcomeEmail(userId) {
        const user = this.data.users.find(u => u.id === userId);
        if (!user) return;

        App.showNotification(`Email de bienvenida enviado a ${user.email}`, 'success');
    },

    // Exportar usuarios
    exportUsers() {
        const filtered = this.getFilteredUsers();
        App.showNotification(`Exportando ${filtered.length} usuarios...`, 'info');
    },

    // Seleccionar/deseleccionar todos
    toggleSelectAll(checked) {
        const checkboxes = document.querySelectorAll('.user-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = checked;
            this.toggleUserSelection(parseInt(cb.value), checked);
        });
    },

    // Seleccionar/deseleccionar usuario individual
    toggleUserSelection(userId, checked = null) {
        const checkbox = document.querySelector(`.user-checkbox[value="${userId}"]`);
        const isChecked = checked !== null ? checked : checkbox?.checked;

        if (isChecked) {
            if (!this.data.selectedUsers.includes(userId)) {
                this.data.selectedUsers.push(userId);
            }
        } else {
            this.data.selectedUsers = this.data.selectedUsers.filter(id => id !== userId);
        }

        // Actualizar estado del checkbox "select all"
        const selectAll = document.getElementById('selectAllUsers');
        const totalCheckboxes = document.querySelectorAll('.user-checkbox').length;
        if (selectAll) {
            selectAll.checked = this.data.selectedUsers.length === totalCheckboxes;
            selectAll.indeterminate = this.data.selectedUsers.length > 0 && this.data.selectedUsers.length < totalCheckboxes;
        }
    },

    // Acciones en lote
    bulkActivate() {
        if (this.data.selectedUsers.length === 0) {
            App.showNotification('Selecciona al menos un usuario', 'warning');
            return;
        }
        App.showNotification(`Activando ${this.data.selectedUsers.length} usuarios...`, 'success');
        this.data.selectedUsers = [];
        this.renderTable();
    },

    bulkDeactivate() {
        if (this.data.selectedUsers.length === 0) {
            App.showNotification('Selecciona al menos un usuario', 'warning');
            return;
        }
        App.showNotification(`Desactivando ${this.data.selectedUsers.length} usuarios...`, 'success');
        this.data.selectedUsers = [];
        this.renderTable();
    },

    bulkAssignDesk() {
        if (this.data.selectedUsers.length === 0) {
            App.showNotification('Selecciona al menos un usuario', 'warning');
            return;
        }
        App.showNotification(`Asignando ${this.data.selectedUsers.length} usuarios a desk...`, 'info');
    },

    bulkDelete() {
        if (this.data.selectedUsers.length === 0) {
            App.showNotification('Selecciona al menos un usuario', 'warning');
            return;
        }
        if (confirm(`¿Estás seguro de eliminar ${this.data.selectedUsers.length} usuarios?`)) {
            App.showNotification(`Eliminando ${this.data.selectedUsers.length} usuarios...`, 'success');
            this.data.selectedUsers = [];
            this.loadData();
        }
    },

    // Funciones auxiliares
    getRoleLabel(role) {
        const labels = {
            admin: 'Administrador',
            manager: 'Manager',
            senior_sales: 'Senior Sales',
            sales: 'Sales',
            retention: 'Retention',
            vip: 'VIP'
        };
        return labels[role] || role;
    },

    getRoleColor(role) {
        const colors = {
            admin: 'danger',
            manager: 'primary',
            senior_sales: 'success',
            sales: 'info',
            retention: 'warning',
            vip: 'dark'
        };
        return colors[role] || 'secondary';
    },

    getStatusLabel(status) {
        const labels = {
            active: 'Activo',
            inactive: 'Inactivo',
            suspended: 'Suspendido'
        };
        return labels[status] || status;
    },

    getStatusColor(status) {
        const colors = {
            active: 'success',
            inactive: 'secondary',
            suspended: 'danger'
        };
        return colors[status] || 'secondary';
    },

    getAvatarColor(role) {
        const colors = {
            admin: 'danger',
            manager: 'primary',
            senior_sales: 'success',
            sales: 'info',
            retention: 'warning',
            vip: 'dark'
        };
        return colors[role] || 'secondary';
    },

    getPerformanceColor(performance) {
        if (performance >= 90) return 'success';
        if (performance >= 70) return 'primary';
        if (performance >= 50) return 'warning';
        return 'danger';
    },

    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('es-ES');
    },

    formatDateTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString('es-ES');
    },

    getTimeAgo(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
        const diffDays = Math.floor(diffHours / 24);

        if (diffDays > 0) return `Hace ${diffDays} día${diffDays > 1 ? 's' : ''}`;
        if (diffHours > 0) return `Hace ${diffHours} hora${diffHours > 1 ? 's' : ''}`;
        return 'Hace menos de 1 hora';
    }
};

// Exportar el módulo para uso global
window.UsersModule = UsersModule;