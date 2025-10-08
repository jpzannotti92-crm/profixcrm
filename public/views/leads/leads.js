/**
 * Módulo de Gestión de Leads
 * Sistema modular para iaTrade CRM
 */
const LeadsModule = {
    // Datos del módulo
    data: {
        leads: [],
        currentPage: 1,
        itemsPerPage: 10,
        totalItems: 0,
        filters: {},
        selectedLeads: []
    },

    // Inicialización del módulo
    init() {
        console.log('Inicializando módulo de Leads...');
        this.loadData();
        this.bindEvents();
        this.updateStats();
    },

    // Cargar datos de leads
    loadData() {
        // Datos de demostración
        this.data.leads = [
            {
                id: 1,
                firstName: 'John',
                lastName: 'Doe',
                email: 'john.doe@email.com',
                phone: '+1 234 567 8900',
                country: 'US',
                status: 'new',
                priority: 'high',
                source: 'google_ads',
                campaign: 'Google Ads - Forex Trading',
                assigned: 'María García',
                assignedId: 1,
                desk: 'Desk Alpha',
                deskId: 1,
                createdAt: '2024-01-15',
                lastComment: 'Lead muy interesado en trading automatizado',
                lastNoteDate: '2024-01-15 14:30:00',
                lastContactDate: '2024-01-15 14:30:00',
                notes: 'Interesado en trading de Forex'
            },
            {
                id: 2,
                firstName: 'Jane',
                lastName: 'Smith',
                email: 'jane.smith@email.com',
                phone: '+1 234 567 8901',
                country: 'CA',
                status: 'contacted',
                priority: 'medium',
                source: 'facebook',
                campaign: 'Facebook - Trading Básico',
                assigned: 'Carlos López',
                assignedId: 2,
                desk: 'Desk Beta',
                deskId: 2,
                createdAt: '2024-01-15',
                lastComment: 'Primera llamada realizada con éxito',
                lastNoteDate: '2024-01-15 10:15:00',
                lastContactDate: '2024-01-15 10:15:00',
                notes: 'Primera llamada realizada'
            },
            {
                id: 3,
                firstName: 'Mike',
                lastName: 'Johnson',
                email: 'mike.johnson@email.com',
                phone: '+1 234 567 8902',
                country: 'UK',
                status: 'interested',
                priority: 'high',
                source: 'organic',
                campaign: 'Orgánico - SEO Forex',
                assigned: 'Ana Martínez',
                assignedId: 3,
                desk: 'Desk Gamma',
                deskId: 3,
                createdAt: '2024-01-14',
                lastComment: 'Muy interesado en CFDs y cuenta VIP',
                lastNoteDate: '2024-01-14 16:45:00',
                lastContactDate: '2024-01-14 16:45:00',
                notes: 'Muy interesado en CFDs'
            },
            {
                id: 4,
                firstName: 'Sarah',
                lastName: 'Wilson',
                email: 'sarah.wilson@email.com',
                phone: '+1 234 567 8903',
                country: 'AU',
                status: 'demo',
                priority: 'urgent',
                source: 'referral',
                campaign: 'Referidos - Programa VIP',
                assigned: 'Pedro Rodríguez',
                assignedId: 4,
                desk: 'Desk Alpha',
                deskId: 1,
                createdAt: '2024-01-14',
                lastComment: 'Demo programada para mañana 15:00',
                lastNoteDate: '2024-01-14 11:20:00',
                lastContactDate: '2024-01-14 11:20:00',
                notes: 'Demo programada para mañana'
            },
            {
                id: 5,
                firstName: 'David',
                lastName: 'Brown',
                email: 'david.brown@email.com',
                phone: '+1 234 567 8904',
                country: 'US',
                status: 'ftd',
                priority: 'high',
                source: 'google_ads',
                campaign: 'Google Ads - Premium Trading',
                assigned: 'Laura Fernández',
                assignedId: 5,
                desk: 'Desk Beta',
                deskId: 2,
                createdAt: '2024-01-13',
                lastComment: 'FTD de $500 realizado exitosamente',
                lastNoteDate: '2024-01-13 09:30:00',
                lastContactDate: '2024-01-13 09:30:00',
                notes: 'FTD de $500 realizado'
            },
            {
                id: 6,
                firstName: 'Emma',
                lastName: 'Davis',
                email: 'emma.davis@email.com',
                phone: '+44 20 7946 0958',
                country: 'UK',
                status: 'contacted',
                priority: 'medium',
                source: 'facebook',
                campaign: 'Facebook - Crypto Trading',
                assigned: 'María García',
                assignedId: 1,
                desk: 'Desk Gamma',
                deskId: 3,
                createdAt: '2024-01-12',
                lastComment: 'Interesada en criptomonedas',
                lastNoteDate: '2024-01-12 15:20:00',
                lastContactDate: '2024-01-12 15:20:00',
                notes: 'Experiencia previa en crypto'
            },
            {
                id: 7,
                firstName: 'Robert',
                lastName: 'Miller',
                email: 'robert.miller@email.com',
                phone: '+1 555 123 4567',
                country: 'US',
                status: 'interested',
                priority: 'high',
                source: 'organic',
                campaign: 'Orgánico - Blog Trading',
                assigned: 'Carlos López',
                assignedId: 2,
                desk: 'Desk Alpha',
                deskId: 1,
                createdAt: '2024-01-11',
                lastComment: 'Leyó artículo sobre estrategias de trading',
                lastNoteDate: '2024-01-11 12:45:00',
                lastContactDate: '2024-01-11 12:45:00',
                notes: 'Viene del blog de trading'
            },
            {
                id: 8,
                firstName: 'Lisa',
                lastName: 'Anderson',
                email: 'lisa.anderson@email.com',
                phone: '+1 555 987 6543',
                country: 'CA',
                status: 'new',
                priority: 'medium',
                source: 'referral',
                campaign: 'Referidos - Cliente Existente',
                assigned: 'Ana Martínez',
                assignedId: 3,
                desk: 'Desk Beta',
                deskId: 2,
                createdAt: '2024-01-10',
                lastComment: 'Referido por cliente VIP existente',
                lastNoteDate: '2024-01-10 08:30:00',
                lastContactDate: '2024-01-10 08:30:00',
                notes: 'Referido por cliente VIP'
            }
        ];

        this.data.totalItems = this.data.leads.length;
        this.renderTable();
        this.renderPagination();
    },

    // Vincular eventos
    bindEvents() {
        // Checkbox select all
        const selectAll = document.getElementById('selectAll');
        if (selectAll) {
            selectAll.addEventListener('change', (e) => {
                this.toggleSelectAll(e.target.checked);
            });
        }

        // Filtros
        const filterInputs = ['filterStatus', 'filterPriority', 'filterSource', 'filterAssigned', 'filterSearch'];
        filterInputs.forEach(filterId => {
            const element = document.getElementById(filterId);
            if (element) {
                element.addEventListener('change', () => this.applyFilters());
            }
        });
    },

    // Renderizar tabla
    renderTable() {
        const tbody = document.getElementById('leadsTableBody');
        if (!tbody) return;

        const filteredLeads = this.getFilteredLeads();
        const startIndex = (this.data.currentPage - 1) * this.data.itemsPerPage;
        const endIndex = startIndex + this.data.itemsPerPage;
        const pageLeads = filteredLeads.slice(startIndex, endIndex);

        tbody.innerHTML = pageLeads.map(lead => `
            <tr>
                <td>
                    <input type="checkbox" class="form-check-input lead-checkbox" 
                           value="${lead.id}" onchange="LeadsModule.toggleLeadSelection(${lead.id})">
                </td>
                <td>
                    <div class="fw-semibold text-primary cursor-pointer" onclick="LeadsModule.viewLead(${lead.id})" style="cursor: pointer;" title="Ver detalles del lead">${lead.firstName}</div>
                </td>
                <td>
                    <div class="fw-semibold text-primary cursor-pointer" onclick="LeadsModule.viewLead(${lead.id})" style="cursor: pointer;" title="Ver detalles del lead">${lead.lastName}</div>
                </td>
                <td>
                    <div class="text-primary fw-medium">${lead.phone}</div>
                </td>
                <td>
                    <div class="text-info">${lead.email}</div>
                </td>
                <td>
                    <div class="text-muted">${this.formatDate(lead.createdAt)}</div>
                </td>
                <td>
                    <span class="badge bg-secondary">${lead.desk || 'Sin asignar'}</span>
                </td>
                <td>
                    <div class="text-truncate" style="max-width: 200px;" title="${lead.lastComment}">
                        ${lead.lastComment}
                    </div>
                </td>
                <td>
                    <div class="text-muted small">${this.formatDateTime(lead.lastNoteDate)}</div>
                </td>
                <td>
                    <div class="text-muted small">${this.formatDateTime(lead.lastContactDate)}</div>
                </td>
                <td>
                    <span class="badge bg-info text-truncate" style="max-width: 150px;" title="${lead.campaign}">
                        ${lead.campaign}
                    </span>
                </td>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="user-avatar bg-primary me-2" style="width: 24px; height: 24px; font-size: 0.7rem;">
                            ${lead.assigned ? lead.assigned.split(' ').map(n => n[0]).join('') : '?'}
                        </div>
                        <span class="text-truncate" style="max-width: 100px;">${lead.assigned}</span>
                    </div>
                </td>
                <td>
                    <div class="btn-group" role="group">
                        <button class="btn btn-sm btn-outline-primary" onclick="LeadsModule.viewLead(${lead.id})" title="Ver detalles">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-success" onclick="LeadsModule.callLead(${lead.id})" title="Llamar">
                            <i class="fas fa-phone"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-info" onclick="LeadsModule.editLead(${lead.id})" title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    },

    // Obtener leads filtrados
    getFilteredLeads() {
        let filtered = [...this.data.leads];

        // Aplicar filtros
        if (this.data.filters.status) {
            filtered = filtered.filter(lead => lead.status === this.data.filters.status);
        }
        if (this.data.filters.priority) {
            filtered = filtered.filter(lead => lead.priority === this.data.filters.priority);
        }
        if (this.data.filters.source) {
            filtered = filtered.filter(lead => lead.source === this.data.filters.source);
        }
        if (this.data.filters.assigned) {
            filtered = filtered.filter(lead => lead.assignedId == this.data.filters.assigned);
        }
        if (this.data.filters.search) {
            const search = this.data.filters.search.toLowerCase();
            filtered = filtered.filter(lead => 
                lead.firstName.toLowerCase().includes(search) ||
                lead.lastName.toLowerCase().includes(search) ||
                lead.email.toLowerCase().includes(search) ||
                lead.phone.includes(search)
            );
        }

        return filtered;
    },

    // Aplicar filtros
    applyFilters() {
        this.data.filters = {
            status: document.getElementById('filterStatus')?.value || '',
            priority: document.getElementById('filterPriority')?.value || '',
            source: document.getElementById('filterSource')?.value || '',
            assigned: document.getElementById('filterAssigned')?.value || '',
            search: document.getElementById('filterSearch')?.value || ''
        };

        this.data.currentPage = 1;
        this.renderTable();
        this.renderPagination();
        this.updateStats();
        
        App.showNotification('Filtros aplicados', 'info');
    },

    // Actualizar estadísticas
    updateStats() {
        const filtered = this.getFilteredLeads();
        const stats = {
            total: filtered.length,
            contacted: filtered.filter(l => l.status === 'contacted').length,
            demo: filtered.filter(l => l.status === 'demo').length,
            converted: filtered.filter(l => l.status === 'ftd').length
        };

        document.getElementById('totalLeads').textContent = stats.total;
        document.getElementById('contactedLeads').textContent = stats.contacted;
        document.getElementById('demoLeads').textContent = stats.demo;
        document.getElementById('convertedLeads').textContent = stats.converted;
    },

    // Renderizar paginación
    renderPagination() {
        const pagination = document.getElementById('leadsPagination');
        if (!pagination) return;

        const filtered = this.getFilteredLeads();
        const totalPages = Math.ceil(filtered.length / this.data.itemsPerPage);
        
        if (totalPages <= 1) {
            pagination.innerHTML = '';
            return;
        }

        let paginationHTML = '';
        
        // Botón anterior
        paginationHTML += `
            <li class="page-item ${this.data.currentPage === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="LeadsModule.goToPage(${this.data.currentPage - 1})">Anterior</a>
            </li>
        `;

        // Páginas
        for (let i = 1; i <= totalPages; i++) {
            paginationHTML += `
                <li class="page-item ${i === this.data.currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="LeadsModule.goToPage(${i})">${i}</a>
                </li>
            `;
        }

        // Botón siguiente
        paginationHTML += `
            <li class="page-item ${this.data.currentPage === totalPages ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="LeadsModule.goToPage(${this.data.currentPage + 1})">Siguiente</a>
            </li>
        `;

        pagination.innerHTML = paginationHTML;
    },

    // Ir a página
    goToPage(page) {
        const filtered = this.getFilteredLeads();
        const totalPages = Math.ceil(filtered.length / this.data.itemsPerPage);
        
        if (page < 1 || page > totalPages) return;
        
        this.data.currentPage = page;
        this.renderTable();
        this.renderPagination();
    },

    // Mostrar modal de importación
    showImportModal() {
        // Cargar el asistente de importación
        this.loadImportWizard();
    },

    // Cargar asistente de importación
    async loadImportWizard() {
        try {
            const contentArea = document.getElementById('moduleContent') || document.querySelector('.content');
            if (!contentArea) {
                throw new Error('Área de contenido no encontrada');
            }

            // Mostrar loading
            contentArea.innerHTML = `
                <div class="d-flex justify-content-center align-items-center" style="height: 400px;">
                    <div class="text-center">
                        <div class="spinner-border text-primary mb-3" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p class="text-muted">Cargando asistente de importación...</p>
                    </div>
                </div>
            `;

            // Cargar HTML del asistente
            const response = await fetch('/views/leads/import-wizard.html');
            if (!response.ok) {
                throw new Error('Error cargando asistente de importación');
            }
            const htmlContent = await response.text();
            
            // Insertar HTML
            contentArea.innerHTML = htmlContent;

            // Cargar script si no está cargado
            if (!window.ImportWizard) {
                await this.loadScript('/views/leads/import-wizard.js');
            }

            // Inicializar asistente
            if (window.ImportWizard) {
                ImportWizard.init();
            }

            // Actualizar título de página
            if (document.getElementById('pageTitle')) {
                document.getElementById('pageTitle').textContent = 'Importar Leads';
            }
            
            App.showNotification('Asistente de importación cargado', 'success');
            
        } catch (error) {
            console.error('Error cargando asistente:', error);
            App.showNotification(`Error: ${error.message}`, 'error');
        }
    },

    // Mostrar modal de creación
    showCreateModal() {
        document.getElementById('leadModalTitle').innerHTML = '<i class="fas fa-user-plus me-2"></i>Nuevo Lead';
        document.getElementById('leadForm').reset();
        const modal = new bootstrap.Modal(document.getElementById('leadModal'));
        modal.show();
    },

    // Ver lead
    viewLead(id) {
        const lead = this.data.leads.find(l => l.id === id);
        if (lead) {
            // Cargar la vista detallada del lead
            this.loadLeadDetailView(id);
        }
    },

    // Cargar vista detallada del lead
    async loadLeadDetailView(leadId) {
        try {
            const contentArea = document.getElementById('moduleContent') || document.querySelector('.content');
            if (!contentArea) {
                throw new Error('Área de contenido no encontrada');
            }

            // Mostrar loading
            contentArea.innerHTML = `
                <div class="d-flex justify-content-center align-items-center" style="height: 400px;">
                    <div class="text-center">
                        <div class="spinner-border text-primary mb-3" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p class="text-muted">Cargando vista detallada del lead...</p>
                    </div>
                </div>
            `;

            // Cargar HTML de la vista detallada
            const response = await fetch('/views/leads/lead-detail.html');
            if (!response.ok) {
                throw new Error('Error cargando vista detallada');
            }
            const htmlContent = await response.text();
            
            // Insertar HTML
            contentArea.innerHTML = htmlContent;

            // Cargar script si no está cargado
            if (!window.LeadDetail) {
                await this.loadScript('/views/leads/lead-detail.js');
            }

            // Inicializar vista detallada
            if (window.LeadDetail) {
                LeadDetail.init(leadId);
            }

            // Actualizar título de página
            if (document.getElementById('pageTitle')) {
                document.getElementById('pageTitle').textContent = 'Detalle del Lead';
            }
            
            App.showNotification('Vista detallada del lead cargada', 'success');
            
        } catch (error) {
            console.error('Error cargando vista detallada:', error);
            App.showNotification(`Error: ${error.message}`, 'error');
        }
    },

    // Cargar script dinámicamente
    loadScript(src) {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = src;
            script.onload = resolve;
            script.onerror = () => reject(new Error(`Error cargando script ${src}`));
            document.head.appendChild(script);
        });
    },

    // Llamar lead
    callLead(id) {
        const lead = this.data.leads.find(l => l.id === id);
        if (lead) {
            App.showNotification(`Iniciando llamada a ${lead.firstName} ${lead.lastName} (${lead.phone})`, 'success');
            // Aquí se integraría con el sistema de llamadas
        }
    },

    // Editar lead
    editLead(id) {
        const lead = this.data.leads.find(l => l.id === id);
        if (lead) {
            // Llenar el formulario con los datos del lead
            document.getElementById('leadFirstName').value = lead.firstName;
            document.getElementById('leadLastName').value = lead.lastName;
            document.getElementById('leadEmail').value = lead.email;
            document.getElementById('leadPhone').value = lead.phone;
            document.getElementById('leadCountry').value = lead.country;
            document.getElementById('leadSource').value = lead.source;
            document.getElementById('leadPriority').value = lead.priority;
            document.getElementById('leadAssigned').value = lead.assignedId;
            document.getElementById('leadNotes').value = lead.notes;

            document.getElementById('leadModalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Editar Lead';
            const modal = new bootstrap.Modal(document.getElementById('leadModal'));
            modal.show();
        }
    },

    // Guardar lead
    saveLead() {
        const formData = {
            firstName: document.getElementById('leadFirstName').value,
            lastName: document.getElementById('leadLastName').value,
            email: document.getElementById('leadEmail').value,
            phone: document.getElementById('leadPhone').value,
            country: document.getElementById('leadCountry').value,
            source: document.getElementById('leadSource').value,
            priority: document.getElementById('leadPriority').value,
            assigned: document.getElementById('leadAssigned').value,
            notes: document.getElementById('leadNotes').value
        };

        // Validación básica
        if (!formData.firstName || !formData.lastName || !formData.email) {
            App.showNotification('Por favor completa los campos obligatorios', 'error');
            return;
        }

        // Simular guardado
        App.showNotification('Lead guardado exitosamente', 'success');
        const modal = bootstrap.Modal.getInstance(document.getElementById('leadModal'));
        modal.hide();
        
        // Recargar datos
        this.loadData();
    },

    // Exportar datos
    exportData() {
        const filtered = this.getFilteredLeads();
        App.showNotification(`Exportando ${filtered.length} leads...`, 'info');
        // Aquí se implementaría la exportación real
    },

    // Seleccionar/deseleccionar todos
    toggleSelectAll(checked) {
        const checkboxes = document.querySelectorAll('.lead-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = checked;
            this.toggleLeadSelection(parseInt(cb.value), checked);
        });
    },

    // Seleccionar/deseleccionar lead individual
    toggleLeadSelection(leadId, checked = null) {
        const checkbox = document.querySelector(`.lead-checkbox[value="${leadId}"]`);
        const isChecked = checked !== null ? checked : checkbox?.checked;

        if (isChecked) {
            if (!this.data.selectedLeads.includes(leadId)) {
                this.data.selectedLeads.push(leadId);
            }
        } else {
            this.data.selectedLeads = this.data.selectedLeads.filter(id => id !== leadId);
        }

        // Actualizar estado del checkbox "select all"
        const selectAll = document.getElementById('selectAll');
        const totalCheckboxes = document.querySelectorAll('.lead-checkbox').length;
        if (selectAll) {
            selectAll.checked = this.data.selectedLeads.length === totalCheckboxes;
            selectAll.indeterminate = this.data.selectedLeads.length > 0 && this.data.selectedLeads.length < totalCheckboxes;
        }
    },

    // Acciones en lote
    bulkAssign() {
        if (this.data.selectedLeads.length === 0) {
            App.showNotification('Selecciona al menos un lead', 'warning');
            return;
        }
        
        // Crear modal de reasignación masiva dinámicamente
        const modalHtml = `
            <div class="modal fade" id="bulkAssignModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Reasignación Masiva</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Se reasignarán <strong>${this.data.selectedLeads.length}</strong> leads seleccionados
                            </div>
                            <div class="mb-3">
                                <label for="bulkAssignToUser" class="form-label">Asignar a:</label>
                                <select class="form-select" id="bulkAssignToUser">
                                    <option value="">Seleccionar usuario...</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="bulkAssignReason" class="form-label">Motivo (opcional):</label>
                                <textarea class="form-control" id="bulkAssignReason" rows="3" placeholder="Describe el motivo de la reasignación masiva..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary" onclick="LeadsModule.executeBulkAssign()">
                                 <i class="fas fa-users me-1"></i>Reasignar ${this.data.selectedLeads.length} Leads
                             </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Agregar modal al DOM si no existe
        if (!document.getElementById('bulkAssignModal')) {
            document.body.insertAdjacentHTML('beforeend', modalHtml);
        }
        
        // Cargar usuarios disponibles
        this.loadUsersForBulkAssign();
        
        // Mostrar modal
        const modal = new bootstrap.Modal(document.getElementById('bulkAssignModal'));
        modal.show();
    },

    async loadUsersForBulkAssign() {
        try {
            const response = await fetch('/api/users.php');
            const data = await response.json();
            
            if (data.success) {
                const select = document.getElementById('bulkAssignToUser');
                select.innerHTML = '<option value="">Seleccionar usuario...</option>';
                
                data.data.forEach(user => {
                    select.innerHTML += `<option value="${user.id}">${user.first_name} ${user.last_name}</option>`;
                });
            }
        } catch (error) {
            console.error('Error cargando usuarios:', error);
            App.showNotification('Error cargando usuarios', 'error');
        }
    },

    async executeBulkAssign() {
        const userId = document.getElementById('bulkAssignToUser').value;
        const reason = document.getElementById('bulkAssignReason').value.trim();
        
        if (!userId) {
            App.showNotification('Por favor selecciona un usuario', 'warning');
            return;
        }
        
        const selectedLeads = [...this.data.selectedLeads];
        const totalLeads = selectedLeads.length;
        
        try {
            // Cerrar modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('bulkAssignModal'));
            modal.hide();
            
            // Mostrar progreso
            App.showNotification(`Reasignando ${totalLeads} leads...`, 'info');
            
            // Obtener información del nuevo usuario asignado
            const userResponse = await fetch(`/api/users.php?id=${userId}`);
            const userData = await userResponse.json();
            const newUser = userData.success ? userData.data : { first_name: 'Usuario', last_name: 'Desconocido' };
            
            let successCount = 0;
            let errorCount = 0;
            
            // Procesar cada lead individualmente
            for (const leadId of selectedLeads) {
                try {
                    const response = await fetch(`/api/leads.php?id=${leadId}`, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
                        },
                        body: JSON.stringify({
                            assigned_user_id: userId,
                            reassign_reason: reason,
                            bulk_operation: true
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        successCount++;
                        
                        // Crear actividad para este lead específico
                        const activityDescription = reason 
                            ? `Lead reasignado masivamente a ${newUser.first_name} ${newUser.last_name}. Motivo: ${reason}`
                            : `Lead reasignado masivamente a ${newUser.first_name} ${newUser.last_name}`;
                        
                        // Registrar actividad en el backend
                        await this.createLeadActivity(leadId, 'assignment', activityDescription);
                        
                    } else {
                        errorCount++;
                        console.error(`Error reasignando lead ${leadId}:`, result.message);
                    }
                } catch (error) {
                    errorCount++;
                    console.error(`Error procesando lead ${leadId}:`, error);
                }
            }
            
            // Mostrar resultado final
            if (successCount === totalLeads) {
                App.showNotification(`${successCount} leads reasignados exitosamente`, 'success');
            } else if (successCount > 0) {
                App.showNotification(`${successCount} leads reasignados, ${errorCount} con errores`, 'warning');
            } else {
                App.showNotification('Error en la reasignación masiva', 'error');
            }
            
            // Limpiar selección y recargar datos
            this.data.selectedLeads = [];
            this.loadData();
            
        } catch (error) {
            console.error('Error en reasignación masiva:', error);
            App.showNotification('Error en la reasignación masiva', 'error');
        }
    },

    async createLeadActivity(leadId, type, description) {
        try {
            const response = await fetch('/api/lead_activities.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
                },
                body: JSON.stringify({
                    lead_id: leadId,
                    type: type,
                    description: description,
                    status: 'done',
                    priority: 'medium',
                    visibility: 'public'
                })
            });
            
            const result = await response.json();
            
            if (!result.success) {
                console.error(`Error creando actividad para lead ${leadId}:`, result.message);
            }
            
            return result.success;
        } catch (error) {
            console.error(`Error creando actividad para lead ${leadId}:`, error);
            return false;
        }
    },

    bulkStatusChange() {
        if (this.data.selectedLeads.length === 0) {
            App.showNotification('Selecciona al menos un lead', 'warning');
            return;
        }
        App.showNotification(`Cambiando estado de ${this.data.selectedLeads.length} leads...`, 'info');
    },

    bulkDelete() {
        if (this.data.selectedLeads.length === 0) {
            App.showNotification('Selecciona al menos un lead', 'warning');
            return;
        }
        if (confirm(`¿Estás seguro de eliminar ${this.data.selectedLeads.length} leads?`)) {
            App.showNotification(`Eliminando ${this.data.selectedLeads.length} leads...`, 'success');
            this.data.selectedLeads = [];
            this.loadData();
        }
    },

    // Procesar importación
    processImport() {
        const fileInput = document.getElementById('importFile');
        if (!fileInput.files.length) {
            App.showNotification('Selecciona un archivo para importar', 'warning');
            return;
        }

        App.showNotification('Procesando importación...', 'info');
        
        // Simular procesamiento
        setTimeout(() => {
            App.showNotification('Leads importados exitosamente', 'success');
            const modal = bootstrap.Modal.getInstance(document.getElementById('importLeadsModal'));
            modal.hide();
            this.loadData();
        }, 2000);
    },

    // Funciones auxiliares
    formatDateTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString('es-ES', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    },
    getStatusLabel(status) {
        const labels = {
            'new': 'Nuevo',
            'contacted': 'Contactado',
            'interested': 'Interesado',
            'demo': 'Demo',
            'ftd': 'FTD',
            'client': 'Cliente',
            'lost': 'Perdido'
        };
        return labels[status] || status;
    },

    getPriorityLabel(priority) {
        const labels = {
            'low': 'Baja',
            'medium': 'Media',
            'high': 'Alta',
            'urgent': 'Urgente'
        };
        return labels[priority] || priority;
    },

    getSourceLabel(source) {
        const labels = {
            'google_ads': 'Google Ads',
            'facebook': 'Facebook',
            'organic': 'Orgánico',
            'referral': 'Referidos'
        };
        return labels[source] || source;
    },

    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('es-ES');
    }
};

// Exportar el módulo para uso global
window.LeadsModule = LeadsModule;