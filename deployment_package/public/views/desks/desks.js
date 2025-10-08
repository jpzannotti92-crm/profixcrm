/**
 * Módulo de Gestión de Desks
 * Sistema modular para iaTrade CRM
 */
const DesksModule = {
    // Datos del módulo
    data: {
        desks: [],
        employees: [],
        currentStep: 1,
        selectedEmployees: [],
        editingDeskId: null,
        filters: {}
    },

    // Inicialización del módulo
    init() {
        console.log('Inicializando módulo de Desks...');
        this.loadData();
        this.bindEvents();
        this.updateStats();
    },

    // Cargar datos
    loadData() {
        // Datos de demostración - Desks
        this.data.desks = [
            {
                id: 1,
                name: 'Desk Alpha',
                description: 'Equipo principal de ventas',
                manager: 'María García',
                managerId: 1,
                type: 'sales',
                status: 'active',
                members: 8,
                leads: 156,
                ftds: 23,
                revenue: 47250,
                target: 50000,
                commission: 2.5,
                progress: 75,
                workHours: '09:00-18:00',
                timezone: 'Europe/Madrid',
                autoAssignment: 'round_robin',
                createdAt: '2024-01-01'
            },
            {
                id: 2,
                name: 'Desk Beta',
                description: 'Equipo de retención',
                manager: 'Carlos López',
                managerId: 2,
                type: 'retention',
                status: 'active',
                members: 6,
                leads: 89,
                ftds: 18,
                revenue: 32100,
                target: 40000,
                commission: 3.0,
                progress: 62,
                workHours: '10:00-19:00',
                timezone: 'Europe/Madrid',
                autoAssignment: 'performance',
                createdAt: '2024-01-15'
            },
            {
                id: 3,
                name: 'Desk Gamma',
                description: 'Equipo VIP',
                manager: 'Ana Martínez',
                managerId: 3,
                type: 'vip',
                status: 'active',
                members: 4,
                leads: 45,
                ftds: 12,
                revenue: 28500,
                target: 30000,
                commission: 4.0,
                progress: 95,
                workHours: '08:00-17:00',
                timezone: 'Europe/Madrid',
                autoAssignment: 'manual',
                createdAt: '2024-02-01'
            },
            {
                id: 4,
                name: 'Desk Delta',
                description: 'Equipo de recovery',
                manager: 'Pedro Rodríguez',
                managerId: 4,
                type: 'recovery',
                status: 'inactive',
                members: 3,
                leads: 67,
                ftds: 8,
                revenue: 15600,
                target: 25000,
                commission: 2.0,
                progress: 45,
                workHours: '11:00-20:00',
                timezone: 'Europe/Madrid',
                autoAssignment: 'random',
                createdAt: '2024-02-15'
            }
        ];

        // Datos de demostración - Empleados
        this.data.employees = [
            { id: 1, name: 'María García', role: 'Manager', desk: null, available: false },
            { id: 2, name: 'Carlos López', role: 'Manager', desk: null, available: false },
            { id: 3, name: 'Ana Martínez', role: 'Senior Sales', desk: null, available: true },
            { id: 4, name: 'Pedro Rodríguez', role: 'Sales', desk: null, available: true },
            { id: 5, name: 'Laura Fernández', role: 'Sales', desk: null, available: true },
            { id: 6, name: 'Miguel Santos', role: 'Retention', desk: null, available: true },
            { id: 7, name: 'Carmen Ruiz', role: 'Senior Sales', desk: null, available: true },
            { id: 8, name: 'Javier Moreno', role: 'Sales', desk: null, available: true },
            { id: 9, name: 'Isabel Torres', role: 'VIP Manager', desk: null, available: true },
            { id: 10, name: 'Roberto Silva', role: 'Recovery', desk: null, available: true }
        ];

        this.renderDesks();
    },

    // Vincular eventos
    bindEvents() {
        // Filtros
        const filterInputs = ['filterDeskStatus', 'filterDeskManager', 'filterDeskSearch'];
        filterInputs.forEach(filterId => {
            const element = document.getElementById(filterId);
            if (element) {
                element.addEventListener('change', () => this.applyFilters());
                element.addEventListener('input', () => this.applyFilters());
            }
        });

        // Búsqueda de empleados en el wizard
        const searchEmployees = document.getElementById('searchEmployees');
        if (searchEmployees) {
            searchEmployees.addEventListener('input', (e) => {
                this.filterAvailableEmployees(e.target.value);
            });
        }
    },

    // Renderizar desks
    renderDesks() {
        const container = document.getElementById('desksContainer');
        if (!container) return;

        const filteredDesks = this.getFilteredDesks();

        container.innerHTML = filteredDesks.map(desk => `
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card desk-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-${this.getDeskIcon(desk.type)} me-2 text-${this.getDeskColor(desk.type)}"></i>
                            ${desk.name}
                        </h5>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" onclick="DesksModule.editDesk(${desk.id})">
                                    <i class="fas fa-edit me-2"></i>Editar
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="DesksModule.manageMembers(${desk.id})">
                                    <i class="fas fa-users me-2"></i>Gestionar Miembros
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="DesksModule.viewStats(${desk.id})">
                                    <i class="fas fa-chart-bar me-2"></i>Ver Estadísticas
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item ${desk.status === 'active' ? 'text-warning' : 'text-success'}" href="#" onclick="DesksModule.toggleStatus(${desk.id})">
                                    <i class="fas fa-${desk.status === 'active' ? 'pause' : 'play'} me-2"></i>
                                    ${desk.status === 'active' ? 'Desactivar' : 'Activar'}
                                </a></li>
                                <li><a class="dropdown-item text-danger" href="#" onclick="DesksModule.deleteDesk(${desk.id})">
                                    <i class="fas fa-trash me-2"></i>Eliminar
                                </a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">${desk.description}</p>
                        <div class="row text-center mb-3">
                            <div class="col-4">
                                <h4 class="text-primary mb-1">${desk.members}</h4>
                                <small class="text-muted">Miembros</small>
                            </div>
                            <div class="col-4">
                                <h4 class="text-success mb-1">${desk.leads}</h4>
                                <small class="text-muted">Leads</small>
                            </div>
                            <div class="col-4">
                                <h4 class="text-warning mb-1">${desk.ftds}</h4>
                                <small class="text-muted">FTDs</small>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <small class="text-muted">Meta Mensual</small>
                                <small class="fw-bold">${desk.progress}%</small>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-${this.getProgressColor(desk.progress)}" 
                                     style="width: ${desk.progress}%"></div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">Manager: <strong>${desk.manager}</strong></small>
                            <span class="badge bg-${desk.status === 'active' ? 'success' : 'secondary'}">
                                ${desk.status === 'active' ? 'Activo' : 'Inactivo'}
                            </span>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">Revenue: <strong>$${desk.revenue.toLocaleString()}</strong></small>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    },

    // Obtener desks filtrados
    getFilteredDesks() {
        let filtered = [...this.data.desks];

        if (this.data.filters.status) {
            filtered = filtered.filter(desk => desk.status === this.data.filters.status);
        }
        if (this.data.filters.manager) {
            filtered = filtered.filter(desk => desk.managerId == this.data.filters.manager);
        }
        if (this.data.filters.search) {
            const search = this.data.filters.search.toLowerCase();
            filtered = filtered.filter(desk => 
                desk.name.toLowerCase().includes(search) ||
                desk.description.toLowerCase().includes(search) ||
                desk.manager.toLowerCase().includes(search)
            );
        }

        return filtered;
    },

    // Aplicar filtros
    applyFilters() {
        this.data.filters = {
            status: document.getElementById('filterDeskStatus')?.value || '',
            manager: document.getElementById('filterDeskManager')?.value || '',
            search: document.getElementById('filterDeskSearch')?.value || ''
        };

        this.renderDesks();
        this.updateStats();
        App.showNotification('Filtros aplicados', 'info');
    },

    // Actualizar estadísticas
    updateStats() {
        const filtered = this.getFilteredDesks();
        const stats = {
            total: filtered.length,
            active: filtered.filter(d => d.status === 'active').length,
            members: filtered.reduce((sum, d) => sum + d.members, 0),
            revenue: filtered.reduce((sum, d) => sum + d.revenue, 0)
        };

        document.getElementById('totalDesks').textContent = stats.total;
        document.getElementById('activeDesks').textContent = stats.active;
        document.getElementById('totalMembers').textContent = stats.members;
        document.getElementById('totalRevenue').textContent = `$${stats.revenue.toLocaleString()}`;
    },

    // Mostrar wizard de creación
    showCreateWizard() {
        this.data.editingDeskId = null;
        this.data.currentStep = 1;
        this.data.selectedEmployees = [];
        
        document.getElementById('deskModalTitle').innerHTML = '<i class="fas fa-building me-2"></i>Asistente para Crear Desk';
        this.resetWizard();
        this.loadAvailableEmployees();
        
        const modal = new bootstrap.Modal(document.getElementById('deskModal'));
        modal.show();
    },

    // Resetear wizard
    resetWizard() {
        // Limpiar formulario
        document.getElementById('deskName').value = '';
        document.getElementById('deskManager').value = '';
        document.getElementById('deskDescription').value = '';
        document.getElementById('deskTarget').value = '';
        document.getElementById('deskCommission').value = '';
        document.getElementById('deskType').value = 'sales';

        // Resetear pasos
        this.updateStepIndicators();
        this.showStep(1);
    },

    // Cargar empleados disponibles
    loadAvailableEmployees() {
        const container = document.getElementById('availableEmployees');
        if (!container) return;

        const available = this.data.employees.filter(emp => emp.available);
        
        container.innerHTML = available.map(emp => `
            <div class="list-group-item d-flex justify-content-between align-items-center" data-employee-id="${emp.id}">
                <div>
                    <strong>${emp.name}</strong>
                    <br><small class="text-muted">${emp.role}</small>
                </div>
                <button class="btn btn-sm btn-outline-primary" onclick="DesksModule.addEmployeeToDesk(${emp.id})">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
        `).join('');
    },

    // Filtrar empleados disponibles
    filterAvailableEmployees(search) {
        const container = document.getElementById('availableEmployees');
        if (!container) return;

        const available = this.data.employees.filter(emp => 
            emp.available && 
            emp.name.toLowerCase().includes(search.toLowerCase())
        );
        
        container.innerHTML = available.map(emp => `
            <div class="list-group-item d-flex justify-content-between align-items-center" data-employee-id="${emp.id}">
                <div>
                    <strong>${emp.name}</strong>
                    <br><small class="text-muted">${emp.role}</small>
                </div>
                <button class="btn btn-sm btn-outline-primary" onclick="DesksModule.addEmployeeToDesk(${emp.id})">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
        `).join('');
    },

    // Agregar empleado al desk
    addEmployeeToDesk(employeeId) {
        if (this.data.selectedEmployees.includes(employeeId)) return;

        this.data.selectedEmployees.push(employeeId);
        this.updateDeskMembers();
        
        // Ocultar empleado de la lista disponible
        const employeeElement = document.querySelector(`[data-employee-id="${employeeId}"]`);
        if (employeeElement) {
            employeeElement.style.display = 'none';
        }
    },

    // Remover empleado del desk
    removeEmployeeFromDesk(employeeId) {
        this.data.selectedEmployees = this.data.selectedEmployees.filter(id => id !== employeeId);
        this.updateDeskMembers();
        
        // Mostrar empleado en la lista disponible
        const employeeElement = document.querySelector(`[data-employee-id="${employeeId}"]`);
        if (employeeElement) {
            employeeElement.style.display = 'flex';
        }
    },

    // Actualizar miembros del desk
    updateDeskMembers() {
        const container = document.getElementById('deskMembers');
        if (!container) return;

        const noMembersMessage = document.getElementById('noMembersMessage');
        
        if (this.data.selectedEmployees.length === 0) {
            if (noMembersMessage) {
                noMembersMessage.style.display = 'block';
            }
            return;
        }

        if (noMembersMessage) {
            noMembersMessage.style.display = 'none';
        }

        const selectedEmps = this.data.employees.filter(emp => 
            this.data.selectedEmployees.includes(emp.id)
        );

        const membersHTML = selectedEmps.map(emp => `
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <strong>${emp.name}</strong>
                    <br><small class="text-muted">${emp.role}</small>
                </div>
                <button class="btn btn-sm btn-outline-danger" onclick="DesksModule.removeEmployeeFromDesk(${emp.id})">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `).join('');

        container.innerHTML = membersHTML + (noMembersMessage ? noMembersMessage.outerHTML : '');
    },

    // Navegación del wizard
    nextStep() {
        if (this.data.currentStep === 1) {
            if (!this.validateStep1()) return;
            this.data.currentStep = 2;
        } else if (this.data.currentStep === 2) {
            this.data.currentStep = 3;
        }
        
        this.updateStepIndicators();
        this.showStep(this.data.currentStep);
    },

    previousStep() {
        if (this.data.currentStep > 1) {
            this.data.currentStep--;
            this.updateStepIndicators();
            this.showStep(this.data.currentStep);
        }
    },

    // Validar paso 1
    validateStep1() {
        const name = document.getElementById('deskName').value;
        const manager = document.getElementById('deskManager').value;

        if (!name.trim()) {
            App.showNotification('El nombre del desk es obligatorio', 'error');
            return false;
        }

        if (!manager) {
            App.showNotification('Debe seleccionar un manager', 'error');
            return false;
        }

        return true;
    },

    // Mostrar paso específico
    showStep(step) {
        // Ocultar todos los pasos
        document.querySelectorAll('.wizard-step').forEach(stepEl => {
            stepEl.style.display = 'none';
        });

        // Mostrar paso actual
        document.getElementById(`deskStep${step}`).style.display = 'block';

        // Actualizar botones
        const prevBtn = document.getElementById('prevStepBtn');
        const nextBtn = document.getElementById('nextStepBtn');
        const createBtn = document.getElementById('createDeskBtn');

        prevBtn.style.display = step > 1 ? 'inline-block' : 'none';
        nextBtn.style.display = step < 3 ? 'inline-block' : 'none';
        createBtn.style.display = step === 3 ? 'inline-block' : 'none';
    },

    // Actualizar indicadores de paso
    updateStepIndicators() {
        for (let i = 1; i <= 3; i++) {
            const indicator = document.getElementById(`step${i}Indicator`);
            if (indicator) {
                if (i <= this.data.currentStep) {
                    indicator.classList.add('active');
                } else {
                    indicator.classList.remove('active');
                }
            }
        }
    },

    // Guardar desk
    saveDesk() {
        const formData = {
            name: document.getElementById('deskName').value,
            manager: document.getElementById('deskManager').value,
            description: document.getElementById('deskDescription').value,
            target: document.getElementById('deskTarget').value,
            commission: document.getElementById('deskCommission').value,
            type: document.getElementById('deskType').value,
            workStartTime: document.getElementById('workStartTime').value,
            workEndTime: document.getElementById('workEndTime').value,
            timezone: document.getElementById('deskTimezone').value,
            autoAssignment: document.getElementById('autoAssignment').value,
            enableNotifications: document.getElementById('enableNotifications').checked,
            enableReports: document.getElementById('enableReports').checked,
            members: this.data.selectedEmployees
        };

        // Validación final
        if (!formData.name.trim()) {
            App.showNotification('El nombre del desk es obligatorio', 'error');
            return;
        }

        // Simular guardado
        App.showNotification('Desk creado exitosamente', 'success');
        
        const modal = bootstrap.Modal.getInstance(document.getElementById('deskModal'));
        modal.hide();
        
        // Recargar datos
        this.loadData();
    },

    // Editar desk
    editDesk(deskId) {
        const desk = this.data.desks.find(d => d.id === deskId);
        if (!desk) return;

        this.data.editingDeskId = deskId;
        App.showNotification(`Editando ${desk.name}`, 'info');
        // Aquí se implementaría la lógica de edición
    },

    // Gestionar miembros
    manageMembers(deskId) {
        const desk = this.data.desks.find(d => d.id === deskId);
        if (!desk) return;

        App.showNotification(`Gestionando miembros de ${desk.name}`, 'info');
        const modal = new bootstrap.Modal(document.getElementById('manageMembersModal'));
        modal.show();
    },

    // Ver estadísticas
    viewStats(deskId) {
        const desk = this.data.desks.find(d => d.id === deskId);
        if (!desk) return;

        App.showNotification(`Viendo estadísticas de ${desk.name}`, 'info');
        // Aquí se implementaría la vista de estadísticas detalladas
    },

    // Cambiar estado del desk
    toggleStatus(deskId) {
        const desk = this.data.desks.find(d => d.id === deskId);
        if (!desk) return;

        const newStatus = desk.status === 'active' ? 'inactive' : 'active';
        desk.status = newStatus;
        
        App.showNotification(`Desk ${newStatus === 'active' ? 'activado' : 'desactivado'}`, 'success');
        this.renderDesks();
        this.updateStats();
    },

    // Eliminar desk
    deleteDesk(deskId) {
        const desk = this.data.desks.find(d => d.id === deskId);
        if (!desk) return;

        if (confirm(`¿Estás seguro de eliminar el desk "${desk.name}"?`)) {
            this.data.desks = this.data.desks.filter(d => d.id !== deskId);
            App.showNotification('Desk eliminado exitosamente', 'success');
            this.renderDesks();
            this.updateStats();
        }
    },

    // Funciones auxiliares
    getDeskIcon(type) {
        const icons = {
            sales: 'handshake',
            retention: 'user-shield',
            vip: 'crown',
            recovery: 'redo'
        };
        return icons[type] || 'building';
    },

    getDeskColor(type) {
        const colors = {
            sales: 'primary',
            retention: 'success',
            vip: 'warning',
            recovery: 'info'
        };
        return colors[type] || 'secondary';
    },

    getProgressColor(progress) {
        if (progress >= 90) return 'success';
        if (progress >= 70) return 'primary';
        if (progress >= 50) return 'warning';
        return 'danger';
    }
};

// Exportar el módulo para uso global
window.DesksModule = DesksModule;