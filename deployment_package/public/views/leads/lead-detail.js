/**
 * Módulo de Vista Detallada del Lead
 * Sistema profesional con KPIs completos
 */
const LeadDetail = {
    // Datos del lead actual
    data: {
        currentLead: null,
        activities: [],
        notes: [],
        tasks: [],
        documents: [],
        relatedLeads: []
    },

    // Inicialización del módulo
    init(leadId = 1) {
        console.log('Inicializando vista detallada del lead:', leadId);
        this.loadLeadData(leadId);
        this.loadFinancialData(leadId); // Cargar datos financieros
        this.bindEvents();
        this.initCharts();
    },

    // Cargar actividades desde el servidor
    loadActivities() {
        console.log('🔄 Cargando actividades para lead:', this.leadId);
        if (!this.leadId) {
            console.warn('⚠️ No hay leadId definido');
            return;
        }
        
        fetch(`/api/lead_activities.php?lead_id=${this.leadId}&limit=10&_=${Date.now()}`, {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                'Cache-Control': 'no-cache'
            }
        })
        .then(response => {
            console.log('📡 Respuesta del servidor:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('📊 Datos recibidos:', data);
            if (data.success && data.data) {
                console.log('✅ Actividades encontradas:', data.data.length);
                // Convertir las actividades del servidor al formato esperado por el frontend
                this.data.activities = data.data.map(activity => {
                    console.log('🔄 Procesando actividad:', activity.type, activity.description);
                    return {
                        id: activity.id,
                        type: activity.type || 'note',
                        description: activity.description || activity.subject,
                        result: activity.outcome || 'neutral',
                        date: activity.created_at,
                        user: activity.user_name || 'Sistema',
                        duration: activity.duration_minutes ? `${activity.duration_minutes} min` : null,
                        scheduled: activity.scheduled_at
                    };
                });
                
                console.log('🎨 Re-renderizando timeline con', this.data.activities.length, 'actividades');
                // Re-renderizar el timeline con las nuevas actividades
                this.renderTimeline();
            } else {
                console.error('❌ Error cargando actividades:', data.message);
            }
        })
        .catch(error => {
            console.error('💥 Error cargando actividades:', error);
        });
    },

    // Cargar datos del lead
    loadLeadData(leadId) {
        this.leadId = leadId; // Guardar el leadId para uso posterior
        
        // Datos de demostración del lead
        this.data.currentLead = {
            id: leadId,
            firstName: 'John',
            lastName: 'Doe',
            email: 'john.doe@email.com',
            phone: '+1 234 567 8900',
            country: 'Estados Unidos',
            status: 'interested',
            priority: 'high',
            source: 'google_ads',
            assignedTo: 'María García',
            assignedId: 1,
            createdAt: '2024-01-15',
            leadScore: 85,
            potentialValue: 2500,
            conversionProbability: 78,
            totalCalls: 12,
            totalEmails: 8,
            daysSinceContact: 3,
            lastActivity: '2024-01-15 14:30:00'
        };

        // Actividades del lead
        this.data.activities = [
            {
                id: 1,
                type: 'call',
                description: 'Llamada inicial - Lead muy interesado en trading de Forex',
                result: 'positive',
                date: '2024-01-15 14:30:00',
                user: 'María García',
                duration: '15 min'
            },
            {
                id: 2,
                type: 'email',
                description: 'Envío de material informativo sobre plataforma de trading',
                result: 'neutral',
                date: '2024-01-15 10:15:00',
                user: 'María García'
            },
            {
                id: 3,
                type: 'demo',
                description: 'Demo programada para mañana a las 15:00',
                result: 'positive',
                date: '2024-01-14 16:45:00',
                user: 'María García',
                scheduled: '2024-01-16 15:00:00'
            },
            {
                id: 4,
                type: 'call',
                description: 'Intento de llamada - No contestó',
                result: 'no_answer',
                date: '2024-01-14 11:20:00',
                user: 'María García'
            },
            {
                id: 5,
                type: 'note',
                description: 'Lead proviene de campaña de Google Ads "Forex Trading". Mostró interés inmediato.',
                result: 'neutral',
                date: '2024-01-13 09:30:00',
                user: 'Sistema'
            }
        ];

        // Notas del lead
        this.data.notes = [
            {
                id: 1,
                content: 'Cliente muy interesado en trading automatizado. Tiene experiencia previa con otras plataformas.',
                author: 'María García',
                date: '2024-01-15 14:35:00'
            },
            {
                id: 2,
                content: 'Prefiere comunicación por WhatsApp. Disponible entre 14:00-18:00 hora local.',
                author: 'María García',
                date: '2024-01-15 10:20:00'
            },
            {
                id: 3,
                content: 'Interesado en cuenta VIP. Mencionó depósito inicial de $5000.',
                author: 'María García',
                date: '2024-01-14 16:50:00'
            }
        ];

        // Tareas pendientes
        this.data.tasks = [
            {
                id: 1,
                title: 'Demo de plataforma',
                description: 'Realizar demo personalizada',
                dueDate: '2024-01-16 15:00:00',
                priority: 'high',
                status: 'pending'
            },
            {
                id: 2,
                title: 'Seguimiento post-demo',
                description: 'Llamar 2 horas después de la demo',
                dueDate: '2024-01-16 17:00:00',
                priority: 'medium',
                status: 'pending'
            },
            {
                id: 3,
                title: 'Enviar contrato VIP',
                description: 'Preparar documentación para cuenta VIP',
                dueDate: '2024-01-17 10:00:00',
                priority: 'medium',
                status: 'pending'
            }
        ];

        // Documentos
        this.data.documents = [
            {
                id: 1,
                name: 'Identificación - Pasaporte',
                type: 'pdf',
                size: '2.4 MB',
                uploadDate: '2024-01-15 12:00:00'
            },
            {
                id: 2,
                name: 'Comprobante de ingresos',
                type: 'pdf',
                size: '1.8 MB',
                uploadDate: '2024-01-15 12:05:00'
            },
            {
                id: 3,
                name: 'Formulario KYC completado',
                type: 'pdf',
                size: '856 KB',
                uploadDate: '2024-01-15 12:10:00'
            }
        ];

        // Leads relacionados
        this.data.relatedLeads = [
            {
                id: 2,
                name: 'Jane Doe',
                email: 'jane.doe@email.com',
                status: 'contacted',
                relation: 'Mismo dominio de email'
            },
            {
                id: 3,
                name: 'Michael Johnson',
                email: 'mike.j@email.com',
                status: 'demo',
                relation: 'Misma campaña de Google Ads'
            }
        ];

        // Cargar el sistema de notificaciones
        if (typeof NotificationSystem !== 'undefined') {
            // Suscribirse a notificaciones relacionadas con leads
            window.notificationSystem.subscribe('lead_activity', (notification) => {
                // Si la notificación es sobre este lead, recargar datos
                if (notification.lead_id && notification.lead_id == this.leadId) {
                    this.loadActivities();
                    this.renderTradingAccounts();
                }
            });
        }

        this.renderLeadData();
        
        // Cargar actividades reales desde el servidor
        this.loadActivities();
    },

    // Renderizar datos del lead
    renderLeadData() {
        const lead = this.data.currentLead;
        
        // Header del lead
        document.getElementById('leadInitials').textContent = 
            lead.firstName.charAt(0) + lead.lastName.charAt(0);
        document.getElementById('leadFullName').textContent = 
            `${lead.firstName} ${lead.lastName}`;
        document.getElementById('leadId').textContent = `#L-${String(lead.id).padStart(6, '0')}`;
        document.getElementById('leadCreatedDate').textContent = 
            `Creado: ${this.formatDate(lead.createdAt)}`;

        // KPIs
        document.getElementById('leadScore').textContent = lead.leadScore;
        document.getElementById('totalCalls').textContent = lead.totalCalls;
        document.getElementById('totalEmails').textContent = lead.totalEmails;
        document.getElementById('daysSinceContact').textContent = lead.daysSinceContact;
        document.getElementById('potentialValue').textContent = `$${(lead.potentialValue / 1000).toFixed(1)}K`;
        document.getElementById('conversionProbability').textContent = `${lead.conversionProbability}%`;

        // Información del lead
        document.getElementById('currentStatus').textContent = this.getStatusLabel(lead.status);
        document.getElementById('currentStatus').className = `status-badge status-${lead.status} me-2`;
        document.getElementById('currentPriority').textContent = this.getPriorityLabel(lead.priority);
        document.getElementById('currentPriority').className = `priority-badge priority-${lead.priority} me-2`;
        document.getElementById('leadEmail').textContent = lead.email;
        document.getElementById('leadPhone').textContent = lead.phone;
        document.getElementById('leadCountry').textContent = lead.country;
        document.getElementById('leadSource').textContent = this.getSourceLabel(lead.source);
        document.getElementById('assignedTo').textContent = lead.assignedTo;

        // Renderizar secciones
        this.renderTimeline();
        this.renderNotes();
        this.renderTasks();
        this.renderDocuments();
        this.renderRelatedLeads();
        this.renderTradingAccounts();
    },

    // Renderizar timeline de actividades
    renderTimeline() {
        const container = document.getElementById('activityTimeline');
        
        container.innerHTML = this.data.activities.map(activity => `
            <div class="timeline-item ${activity.result}">
                <div class="timeline-header">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-${this.getActivityIcon(activity.type)} me-2"></i>
                        <span class="timeline-type">${this.getActivityLabel(activity.type)}</span>
                        ${activity.duration ? `<span class="badge bg-secondary ms-2">${activity.duration}</span>` : ''}
                    </div>
                    <span class="timeline-date">${this.formatDateTime(activity.date)}</span>
                </div>
                <div class="timeline-description">${activity.description}</div>
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <span class="timeline-result result-${activity.result}">
                        ${this.getResultLabel(activity.result)}
                    </span>
                    <small class="text-muted">por ${activity.user}</small>
                </div>
                ${activity.scheduled ? `
                    <div class="mt-2">
                        <small class="text-info">
                            <i class="fas fa-calendar me-1"></i>
                            Programado: ${this.formatDateTime(activity.scheduled)}
                        </small>
                    </div>
                ` : ''}
            </div>
        `).join('');
    },

    // Renderizar notas
    renderNotes() {
        const container = document.getElementById('notesSection');
        
        container.innerHTML = this.data.notes.map(note => `
            <div class="note-item">
                <div class="note-header">
                    <span class="note-author">${note.author}</span>
                    <span class="note-date">${this.formatDateTime(note.date)}</span>
                </div>
                <div class="note-content">${note.content}</div>
            </div>
        `).join('');
    },

    // Renderizar tareas
    renderTasks() {
        const container = document.getElementById('nextActions');
        
        container.innerHTML = this.data.tasks.map(task => `
            <div class="next-action-item">
                <div class="action-icon bg-${this.getPriorityColor(task.priority)}">
                    <i class="fas fa-${task.status === 'completed' ? 'check' : 'clock'}"></i>
                </div>
                <div class="action-content">
                    <div class="action-title">${task.title}</div>
                    <div class="action-date">
                        <i class="fas fa-calendar me-1"></i>
                        ${this.formatDateTime(task.dueDate)}
                    </div>
                </div>
                <button class="btn btn-sm btn-outline-success" onclick="LeadDetail.completeTask(${task.id})">
                    <i class="fas fa-check"></i>
                </button>
            </div>
        `).join('');
    },

    // Renderizar documentos
    renderDocuments() {
        const container = document.getElementById('documentsList');
        
        container.innerHTML = this.data.documents.map(doc => `
            <div class="document-item">
                <div class="document-icon">
                    <i class="fas fa-file-${doc.type}"></i>
                </div>
                <div class="document-info">
                    <div class="document-name">${doc.name}</div>
                    <div class="document-size">${doc.size} • ${this.formatDate(doc.uploadDate)}</div>
                </div>
                <button class="btn btn-sm btn-outline-primary" onclick="LeadDetail.downloadDocument(${doc.id})">
                    <i class="fas fa-download"></i>
                </button>
            </div>
        `).join('');
    },

    // Renderizar leads relacionados
    renderRelatedLeads() {
        const container = document.getElementById('relatedLeads');
        
        container.innerHTML = this.data.relatedLeads.map(lead => `
            <div class="related-lead-item" onclick="LeadDetail.viewRelatedLead(${lead.id})">
                <div class="user-avatar-small bg-primary me-3">
                    ${lead.name.split(' ').map(n => n[0]).join('')}
                </div>
                <div class="flex-grow-1">
                    <div class="fw-semibold">${lead.name}</div>
                    <small class="text-muted">${lead.relation}</small>
                </div>
                <span class="status-badge status-${lead.status}">${this.getStatusLabel(lead.status)}</span>
            </div>
        `).join('');
    },

    // Inicializar gráficos
    initCharts() {
        setTimeout(() => {
            this.initEngagementChart();
            this.initChannelsChart();
        }, 300);
    },

    // Gráfico de engagement
    initEngagementChart() {
        const ctx = document.getElementById('engagementChart');
        if (!ctx) return;

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4'],
                datasets: [{
                    label: 'Interacciones',
                    data: [3, 7, 12, 8],
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#f3f4f6' } },
                    x: { grid: { display: false } }
                }
            }
        });
    },

    // Gráfico de canales
    initChannelsChart() {
        const ctx = document.getElementById('channelsChart');
        if (!ctx) return;

        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Llamadas', 'Emails', 'WhatsApp', 'Reuniones'],
                datasets: [{
                    data: [12, 8, 5, 2],
                    backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    },

    // Vincular eventos
    bindEvents() {
        // Los eventos se vinculan automáticamente a través de onclick en el HTML
    },

    // Renderizar cuentas de trading vinculadas
    renderTradingAccounts() {
        const container = document.getElementById('tradingAccounts');
        const noAccountsMessage = document.getElementById('noTradingAccounts');
        
        // Obtener cuentas reales del API
        fetch(`/api/lead-trading-link.php?action=lead_accounts&lead_id=${this.data.currentLead.id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    const tradingAccounts = data.data;
                    
                    if (tradingAccounts.length === 0) {
                        noAccountsMessage.style.display = 'block';
                        container.innerHTML = '';
                    } else {
                        noAccountsMessage.style.display = 'none';
                        container.innerHTML = tradingAccounts.map(account => `
                            <div class="trading-account-item mb-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fas fa-chart-line text-primary me-2"></i>
                                            <strong>${account.account_number}</strong>
                                            <span class="badge bg-${account.status === 'active' ? 'success' : 'secondary'} ms-2">
                                                ${account.status === 'active' ? 'Activa' : 'Inactiva'}
                                            </span>
                                            ${account.account_type === 'demo' ? '<span class="badge bg-info ms-1">Demo</span>' : '<span class="badge bg-warning ms-1">Real</span>'}
                                        </div>
                                        <div class="text-muted small mb-2">${account.platform} - Apalancamiento: ${account.leverage}</div>
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <div class="text-muted small">Balance</div>
                                                <div class="fw-semibold">$${parseFloat(account.balance).toLocaleString('en-US', {minimumFractionDigits: 2})}</div>
                                            </div>
                                            <div class="col-6">
                                                <div class="text-muted small">Equity</div>
                                                <div class="fw-semibold">$${parseFloat(account.equity).toLocaleString('en-US', {minimumFractionDigits: 2})}</div>
                                            </div>
                                            <div class="col-6">
                                                <div class="text-muted small">Margen Libre</div>
                                                <div class="fw-semibold">$${parseFloat(account.free_margin).toLocaleString('en-US', {minimumFractionDigits: 2})}</div>
                                            </div>
                                            <div class="col-6">
                                                <div class="text-muted small">Operaciones</div>
                                                <div class="fw-semibold">${account.trades_count || 0}</div>
                                            </div>
                                        </div>
                                        ${account.user_email ? `
                                            <div class="mt-2 pt-2 border-top">
                                                <small class="text-muted">
                                                    <i class="fas fa-user me-1"></i>
                                                    Usuario: ${account.first_name} ${account.last_name} (${account.user_email})
                                                </small>
                                            </div>
                                        ` : ''}
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="#" onclick="LeadDetail.viewTradingAccount(${account.id})">
                                                <i class="fas fa-eye me-2"></i>Ver Detalles
                                            </a></li>
                                            <li><a class="dropdown-item" href="#" onclick="LeadDetail.openWebTrader('${account.account_number}')">
                                                <i class="fas fa-external-link-alt me-2"></i>Abrir WebTrader
                                            </a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="#" onclick="LeadDetail.unlinkTradingAccount(${account.id})">
                                                <i class="fas fa-unlink me-2"></i>Desvincular
                                            </a></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        `).join('');
                    }
                } else {
                    noAccountsMessage.style.display = 'block';
                    container.innerHTML = `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error al cargar las cuentas de trading
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error cargando cuentas de trading:', error);
                noAccountsMessage.style.display = 'block';
                container.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Error de conexión al cargar las cuentas
                    </div>
                `;
            });
    },

    // Funciones de acción
    goBack() {
        App.loadModule('leads');
    },

    callLead() {
        const lead = this.data.currentLead;
        App.showNotification(`Iniciando llamada a ${lead.firstName} ${lead.lastName} (${lead.phone})`, 'info');
        
        // Agregar actividad de llamada
        this.addActivityToTimeline('call', 'Llamada iniciada desde vista detallada', 'neutral');
    },

    sendEmail() {
        const lead = this.data.currentLead;
        App.showNotification(`Abriendo cliente de email para ${lead.email}`, 'info');
        
        // Agregar actividad de email
        this.addActivityToTimeline('email', 'Email enviado desde vista detallada', 'neutral');
    },

    scheduleCallback() {
        App.showNotification('Abriendo calendario para agendar callback...', 'info');
    },

    editLead() {
        App.showNotification('Abriendo formulario de edición...', 'info');
    },

    duplicateLead() {
        App.showNotification('Duplicando lead...', 'info');
    },

    transferLead() {
        App.showNotification('Abriendo asistente de transferencia...', 'info');
    },

    deleteLead() {
        if (confirm('¿Estás seguro de eliminar este lead?')) {
            App.showNotification('Lead eliminado exitosamente', 'success');
            this.goBack();
        }
    },

    changeStatus() {
        const modal = new bootstrap.Modal(document.getElementById('changeStatusModal'));
        modal.show();
    },

    changePriority() {
        App.showNotification('Abriendo selector de prioridad...', 'info');
    },

    reassign() {
        // Crear modal de reasignación dinámicamente
        const modalHtml = `
            <div class="modal fade" id="reassignModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Reasignar Lead</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="assignToUser" class="form-label">Asignar a:</label>
                                <select class="form-select" id="assignToUser">
                                    <option value="">Seleccionar usuario...</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="reassignReason" class="form-label">Motivo (opcional):</label>
                                <textarea class="form-control" id="reassignReason" rows="3" placeholder="Describe el motivo de la reasignación..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary" onclick="LeadDetail.executeReassign()">Reasignar</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Agregar modal al DOM si no existe
        if (!document.getElementById('reassignModal')) {
            document.body.insertAdjacentHTML('beforeend', modalHtml);
        }
        
        // Cargar usuarios disponibles
        this.loadUsersForReassign();
        
        // Mostrar modal
        const modal = new bootstrap.Modal(document.getElementById('reassignModal'));
        modal.show();
    },

    async loadUsersForReassign() {
        try {
            const response = await fetch('/api/users.php');
            const data = await response.json();
            
            if (data.success) {
                const select = document.getElementById('assignToUser');
                select.innerHTML = '<option value="">Seleccionar usuario...</option>';
                
                data.data.forEach(user => {
                    // No mostrar el usuario actual asignado
                    if (user.id !== this.data.currentLead.assignedTo) {
                        select.innerHTML += `<option value="${user.id}">${user.first_name} ${user.last_name}</option>`;
                    }
                });
            }
        } catch (error) {
            console.error('Error cargando usuarios:', error);
            App.showNotification('Error cargando usuarios', 'error');
        }
    },

    async executeReassign() {
        const userId = document.getElementById('assignToUser').value;
        const reason = document.getElementById('reassignReason').value.trim();
        
        if (!userId) {
            App.showNotification('Por favor selecciona un usuario', 'warning');
            return;
        }
        
        // Mostrar indicador de carga
        const reassignBtn = document.querySelector('#reassignModal .btn-primary');
        const originalText = reassignBtn.textContent;
        reassignBtn.disabled = true;
        reassignBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Reasignando...';
        
        try {
            // Actualizar el lead
            const response = await fetch(`/api/leads.php?id=${this.data.currentLead.id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
                },
                body: JSON.stringify({
                    assigned_user_id: userId,
                    reassign_reason: reason
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Obtener información del nuevo usuario asignado
                const userResponse = await fetch(`/api/users.php?id=${userId}`);
                const userData = await userResponse.json();
                const newUser = userData.success ? userData.data : { first_name: 'Usuario', last_name: 'Desconocido' };
                
                // Actualizar datos locales
                const oldAssignedTo = this.data.currentLead.assignedTo;
                this.data.currentLead.assignedTo = parseInt(userId);
                this.data.currentLead.assignedToName = `${newUser.first_name} ${newUser.last_name}`;
                
                // Actualizar UI
                this.updateAssignedUserDisplay();
                
                // Crear actividad en el backend
                const activityDescription = reason 
                    ? `Lead reasignado a ${newUser.first_name} ${newUser.last_name}. Motivo: ${reason}`
                    : `Lead reasignado a ${newUser.first_name} ${newUser.last_name}`;
                
                const activityResponse = await fetch('/api/lead_activities.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
                    },
                    body: JSON.stringify({
                        lead_id: this.leadId,
                        type: 'assignment',
                        subject: 'Reasignación de Lead',
                        description: activityDescription,
                        status: 'completed',
                        priority: 'medium',
                        visibility: 'internal',
                        is_system_generated: 1
                    })
                });
                
                const activityResult = await activityResponse.json();
                
                if (activityResult.success) {
                    console.log('✅ Actividad de reasignación creada exitosamente');
                    
                    // Agregar la actividad inmediatamente al timeline local
                    if (!Array.isArray(this.data.activities)) {
                        this.data.activities = [];
                    }
                    const newActivity = {
                        id: activityResult.data?.id || Date.now(),
                        type: 'assignment',
                        description: activityDescription,
                        result: 'neutral',
                        date: new Date().toISOString(),
                        user: this.getCurrentUserName() || 'Usuario Actual',
                        duration: null,
                        scheduled: null
                    };
                    
                    // Agregar al inicio del array de actividades
                    this.data.activities.unshift(newActivity);
                    
                    // Re-renderizar inmediatamente
                    this.renderTimeline();
                    
                    // También recargar desde el servidor para sincronizar
                    setTimeout(() => {
                        this.loadActivities();
                    }, 500);
                    
                    App.showNotification('Lead reasignado exitosamente y actividad registrada', 'success');
                } else {
                    console.error('Error creando actividad:', activityResult.message);
                    // Fallback: agregar actividad localmente
                    this.addActivityToTimeline('assignment', activityDescription, 'neutral');
                    App.showNotification('Lead reasignado exitosamente (actividad registrada localmente)', 'success');
                }
                
                // Cerrar modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('reassignModal'));
                modal.hide();
                
            } else {
                App.showNotification(result.message || 'Error al reasignar lead', 'error');
            }
            
        } catch (error) {
            console.error('Error reasignando lead:', error);
            App.showNotification('Error de conexión al reasignar lead', 'error');
        } finally {
            // Restaurar botón
            reassignBtn.disabled = false;
            reassignBtn.innerHTML = originalText;
        }
    },

    updateAssignedUserDisplay() {
        // Actualizar el display del usuario asignado en la UI
        const assignedUserElement = document.querySelector('.assigned-user-name');
        if (assignedUserElement) {
            assignedUserElement.textContent = this.data.currentLead.assignedToName || 'Sin asignar';
        }
        
        // Actualizar botón de reasignación
        const reassignButton = document.querySelector('[onclick="LeadDetail.reassign()"]');
        if (reassignButton) {
            reassignButton.innerHTML = '<i class="fas fa-user-edit me-1"></i> Reasignar';
        }
    },

    addActivity() {
        const modal = new bootstrap.Modal(document.getElementById('addActivityModal'));
        modal.show();
    },

    addNote() {
        const noteText = document.getElementById('newNote').value.trim();
        if (!noteText) {
            App.showNotification('Por favor escribe una nota', 'warning');
            return;
        }

        const newNote = {
            id: this.data.notes.length + 1,
            content: noteText,
            author: 'Usuario Actual',
            date: new Date().toISOString()
        };

        this.data.notes.unshift(newNote);
        document.getElementById('newNote').value = '';
        this.renderNotes();
        
        App.showNotification('Nota agregada exitosamente', 'success');
    },

    addTask() {
        App.showNotification('Abriendo formulario de nueva tarea...', 'info');
    },

    completeTask(taskId) {
        const task = this.data.tasks.find(t => t.id === taskId);
        if (task) {
            task.status = 'completed';
            this.renderTasks();
            App.showNotification(`Tarea "${task.title}" completada`, 'success');
        }
    },

    uploadDocument() {
        App.showNotification('Abriendo selector de archivos...', 'info');
    },

    downloadDocument(docId) {
        const doc = this.data.documents.find(d => d.id === docId);
        if (doc) {
            App.showNotification(`Descargando ${doc.name}...`, 'info');
        }
    },

    viewRelatedLead(leadId) {
        App.showNotification(`Cargando lead relacionado #${leadId}...`, 'info');
        this.init(leadId);
    },

    // Funciones para cuentas de trading
    // Abrir WebTrader para una cuenta específica
    openWebTrader(accountNumber) {
        const webTraderUrl = `/webtrader?account=${accountNumber}`;
        window.open(webTraderUrl, '_blank', 'width=1400,height=900,scrollbars=yes,resizable=yes');
        
        // Registrar actividad en el lead
        this.registerLeadActivity(`Acceso a WebTrader para cuenta ${accountNumber}`);
    },

    // Registrar actividad en el lead
    registerLeadActivity(description) {
        if (!this.leadId) return;
        
        fetch('/api/lead-activities.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
            },
            body: JSON.stringify({
                lead_id: this.leadId,
                description: description
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Recargar actividades para mostrar la nueva
                this.loadActivities();
            } else {
                console.error('Error al crear actividad:', data.error);
                App.showNotification('Error al crear la actividad', 'error');
            }
        })
        .catch(error => {
            console.error('Error registrando actividad:', error);
            App.showNotification('Error al crear la actividad', 'error');
        });
    },

    linkTradingAccount() {
        App.showNotification('Abriendo asistente para vincular cuenta de trading...', 'info');
        // Aquí se abriría un modal para vincular una nueva cuenta
    },

    viewTradingAccount(accountId) {
        App.showNotification('Cargando detalles de la cuenta...', 'info');
        // Aquí se abriría un modal con los detalles completos de la cuenta
    },

    // Cargar datos financieros del lead
    loadFinancialData(leadId) {
        fetch(`/api/lead-finances.php?lead_id=${leadId}`, {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.renderFinancialData(data.data);
            } else {
                console.error('Error cargando datos financieros:', data.message);
                this.renderEmptyFinancialData();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            this.renderEmptyFinancialData();
        });
    },

    // Renderizar datos financieros
    renderFinancialData(financialData) {
        const { summary, transactions } = financialData;
        
        // Actualizar resumen financiero
        document.getElementById('totalDeposits').textContent = `$${summary.total_deposits.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
        document.getElementById('totalWithdrawals').textContent = `$${summary.total_withdrawals.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
        document.getElementById('netBalance').textContent = `$${summary.net_balance.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
        
        // Renderizar lista de transacciones
        const transactionsContainer = document.getElementById('financesTransactions');
        
        if (transactions.length === 0) {
            transactionsContainer.innerHTML = `
                <div class="text-center text-muted py-4">
                    <i class="fas fa-receipt fa-2x mb-2"></i>
                    <p class="mb-0">No hay transacciones registradas</p>
                </div>
            `;
            return;
        }
        
        const transactionsHtml = transactions.map(transaction => {
            const typeIcon = transaction.type === 'deposit' ? 'arrow-down' : 'arrow-up';
            const typeColor = transaction.type === 'deposit' ? 'success' : 'danger';
            const statusBadge = this.getTransactionStatusBadge(transaction.status);
            
            return `
                <div class="border-bottom py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <i class="fas fa-${typeIcon} text-${typeColor}"></i>
                            </div>
                            <div>
                                <div class="fw-semibold">
                                    ${transaction.type === 'deposit' ? 'Depósito' : 'Retiro'}
                                    <span class="text-muted small ms-2">#${transaction.account_number}</span>
                                </div>
                                <div class="text-muted small">
                                    ${transaction.method} • ${transaction.platform}
                                    ${transaction.agent_name ? ` • Agente: ${transaction.agent_name}` : ''}
                                </div>
                                <div class="text-muted small">
                                    ${this.formatDateTime(transaction.created_at)}
                                </div>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold text-${typeColor}">
                                ${transaction.type === 'deposit' ? '+' : '-'}$${transaction.amount.toLocaleString('en-US', {minimumFractionDigits: 2})}
                            </div>
                            ${statusBadge}
                        </div>
                    </div>
                    ${transaction.notes ? `<div class="text-muted small mt-2 ms-5">${transaction.notes}</div>` : ''}
                </div>
            `;
        }).join('');
        
        transactionsContainer.innerHTML = transactionsHtml;
    },

    // Renderizar datos financieros vacíos
    renderEmptyFinancialData() {
        document.getElementById('totalDeposits').textContent = '$0.00';
        document.getElementById('totalWithdrawals').textContent = '$0.00';
        document.getElementById('netBalance').textContent = '$0.00';
        
        document.getElementById('financesTransactions').innerHTML = `
            <div class="text-center text-muted py-4">
                <i class="fas fa-receipt fa-2x mb-2"></i>
                <p class="mb-0">No hay transacciones registradas</p>
            </div>
        `;
    },

    // Obtener badge de estado de transacción
    getTransactionStatusBadge(status) {
        const badges = {
            'pending': '<span class="badge bg-warning">Pendiente</span>',
            'processing': '<span class="badge bg-info">Procesando</span>',
            'completed': '<span class="badge bg-success">Completado</span>',
            'failed': '<span class="badge bg-danger">Fallido</span>',
            'cancelled': '<span class="badge bg-secondary">Cancelado</span>'
        };
        return badges[status] || '<span class="badge bg-secondary">Desconocido</span>';
    },

    unlinkTradingAccount(accountId) {
        if (confirm('¿Estás seguro de que quieres desvincular esta cuenta de trading del lead?')) {
            fetch(`/api/lead-trading-link.php?account_id=${accountId}`, {
                method: 'DELETE',
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    App.showNotification('Cuenta desvinculada exitosamente', 'success');
                    this.renderTradingAccounts(); // Recargar la lista
                    this.registerLeadActivity(`Cuenta de trading desvinculada`);
                } else {
                    App.showNotification(data.message || 'Error al desvincular la cuenta', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                App.showNotification('Error de conexión', 'error');
            });
        }
    },

    saveStatusChange() {
        const newStatus = document.getElementById('newStatus').value;
        const comment = document.getElementById('statusComment').value;
        
        this.data.currentLead.status = newStatus;
        document.getElementById('currentStatus').textContent = this.getStatusLabel(newStatus);
        document.getElementById('currentStatus').className = `status-badge status-${newStatus} me-2`;
        
        // Agregar actividad de cambio de estado
        this.addActivityToTimeline('note', `Estado cambiado a ${this.getStatusLabel(newStatus)}. ${comment}`, 'neutral');
        
        const modal = bootstrap.Modal.getInstance(document.getElementById('changeStatusModal'));
        modal.hide();
        
        App.showNotification('Estado actualizado exitosamente', 'success');
    },

    async saveActivity() {
        const type = document.getElementById('activityType').value;
        const description = document.getElementById('activityDescription').value;
        const result = document.getElementById('activityResult').value;
        
        if (!description.trim()) {
            App.showNotification('Por favor describe la actividad', 'warning');
            return;
        }

        // Mostrar indicador de carga
        const saveBtn = document.querySelector('#addActivityModal .btn-primary');
        const originalText = saveBtn.textContent;
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Guardando...';
        
        try {
            // Crear actividad en el backend igual que en la reasignación
            const activityResponse = await fetch('/api/lead_activities.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
                },
                body: JSON.stringify({
                    lead_id: this.leadId,
                    type: type,
                    subject: `Actividad: ${type}`,
                    description: description,
                    status: 'completed',
                    outcome: result,
                    priority: 'medium',
                    visibility: 'public',
                    is_system_generated: 0
                })
            });
            
            const activityResult = await activityResponse.json();
            
            if (activityResult.success) {
                // Recargar actividades desde el servidor para mostrar la nueva (tiempo real)
                this.loadActivities();
                
                // Cerrar modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('addActivityModal'));
                modal.hide();
                
                // Limpiar formulario
                document.getElementById('activityDescription').value = '';
                document.getElementById('activityType').selectedIndex = 0;
                document.getElementById('activityResult').selectedIndex = 0;
                
                App.showNotification('Actividad agregada exitosamente', 'success');
            } else {
                console.error('Error creando actividad:', activityResult.message);
                // Fallback: agregar actividad localmente
                this.addActivityToTimeline(type, description, result);
                App.showNotification('Actividad agregada localmente (error en servidor)', 'warning');
            }
            
        } catch (error) {
            console.error('Error guardando actividad:', error);
            // Fallback: agregar actividad localmente
            this.addActivityToTimeline(type, description, result);
            App.showNotification('Actividad agregada localmente (error de conexión)', 'warning');
        } finally {
            // Restaurar botón
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalText;
        }
    },

    // Agregar actividad al timeline
    addActivityToTimeline(type, description, result) {
        const newActivity = {
            id: this.data.activities.length + 1,
            type: type,
            description: description,
            result: result,
            date: new Date().toISOString(),
            user: 'Usuario Actual'
        };
        
        this.data.activities.unshift(newActivity);
        this.renderTimeline();
        
        // Actualizar KPIs
        if (type === 'call') {
            this.data.currentLead.totalCalls++;
            document.getElementById('totalCalls').textContent = this.data.currentLead.totalCalls;
        } else if (type === 'email') {
            this.data.currentLead.totalEmails++;
            document.getElementById('totalEmails').textContent = this.data.currentLead.totalEmails;
        }
        
        // Resetear días sin contacto
        this.data.currentLead.daysSinceContact = 0;
        document.getElementById('daysSinceContact').textContent = '0';
    },

    // Funciones auxiliares
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

    getPriorityColor(priority) {
        const colors = {
            'low': 'secondary',
            'medium': 'warning',
            'high': 'danger',
            'urgent': 'dark'
        };
        return colors[priority] || 'secondary';
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

    getActivityIcon(type) {
        const icons = {
            'call': 'phone',
            'email': 'envelope',
            'meeting': 'handshake',
            'demo': 'desktop',
            'note': 'sticky-note',
            'assignment': 'user-check'  // Icono claro para actividades de reasignación
        };
        return icons[type] || 'circle';
    },

    getActivityLabel(type) {
        const labels = {
            'call': 'Llamada',
            'email': 'Email',
            'meeting': 'Reunión',
            'demo': 'Demo',
            'note': 'Nota',
            'assignment': 'Reasignación'
        };
        return labels[type] || type;
    },

    getResultLabel(result) {
        const labels = {
            'positive': 'Positivo',
            'neutral': 'Neutral',
            'negative': 'Negativo',
            'no_answer': 'Sin respuesta'
        };
        return labels[result] || result;
    },

    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('es-ES');
    },

    formatDateTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString('es-ES');
    }
};

// Exportar el módulo para uso global
window.LeadDetail = LeadDetail;