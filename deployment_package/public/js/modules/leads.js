/**
 * Módulo de Gestión de Leads
 * Conectado a la base de datos real
 */

const LeadsModule = {
    // Estado del módulo
    state: {
        leads: [],
        currentPage: 1,
        totalPages: 1,
        loading: false,
        filters: {
            search: '',
            status: '',
            desk_id: ''
        },
        import: {
            fileData: null,
            columns: [],
            sampleData: [],
            mapping: {}
        }
    },

    // Inicializar módulo
    async init() {
        console.log('Inicializando módulo de Leads');
        this.render();
        await this.loadLeads();
        this.bindEvents();
    },

    // Renderizar interfaz
    render() {
        const content = document.getElementById('moduleContent');
        content.innerHTML = `
            <div class="leads-module">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2><i class="fas fa-users"></i> Gestión de Leads</h2>
                        <p class="text-muted">Administra y gestiona todos los leads del sistema</p>
                    </div>
                    <button class="btn btn-primary" onclick="LeadsModule.showCreateModal()">
                        <i class="fas fa-plus"></i> Nuevo Lead
                    </button>
                    <button class="btn btn-success ms-2" onclick="LeadsModule.showImportModal()">
                        <i class="fas fa-upload"></i> Importar Leads
                    </button>
                </div>

                <!-- Filtros -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Buscar</label>
                                    <input type="text" class="form-control" id="searchInput" 
                                           placeholder="Nombre, email, teléfono...">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Estado</label>
                                    <select class="form-control" id="statusFilter">
                                        <option value="">Todos los estados</option>
                                        <option value="new">Nuevo</option>
                                        <option value="contacted">Contactado</option>
                                        <option value="qualified">Calificado</option>
                                        <option value="converted">Convertido</option>
                                        <option value="lost">Perdido</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Mesa</label>
                                    <select class="form-control" id="deskFilter">
                                        <option value="">Todas las mesas</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <button class="btn btn-outline-secondary btn-block" onclick="LeadsModule.clearFilters()">
                                        <i class="fas fa-times"></i> Limpiar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Estadísticas rápidas -->
                <div class="row mb-4" id="leadsStats">
                    <!-- Se cargarán dinámicamente -->
                </div>

                <!-- Tabla de leads -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Lista de Leads</h5>
                    </div>
                    <div class="card-body">
                        <div id="leadsLoading" class="text-center py-4" style="display: none;">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                            <p class="mt-2">Cargando leads...</p>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover" id="leadsTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Apellido</th>
                                        <th>Teléfono</th>
                                        <th>País</th>
                                        <th>Email</th>
                                        <th>Desk</th>
                                        <th>Último Comentario</th>
                                        <th>Último Contacto</th>
                                        <th>Campaña</th>
                                        <th>Responsable</th>
                                        <th>Fecha Creación</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="leadsTableBody">
                                    <!-- Se cargarán dinámicamente -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginación -->
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div id="leadsInfo">
                                <!-- Información de paginación -->
                            </div>
                            <nav>
                                <ul class="pagination mb-0" id="leadsPagination">
                                    <!-- Paginación dinámica -->
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para crear/editar lead -->
            <div class="modal fade" id="leadModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="leadModalTitle">Nuevo Lead</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="leadForm">
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
                                            <label>Email *</label>
                                            <input type="email" class="form-control" name="email" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label>Teléfono</label>
                                            <input type="tel" class="form-control" name="phone">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group mb-3">
                                            <label>País</label>
                                            <input type="text" class="form-control" name="country">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group mb-3">
                                            <label>Estado</label>
                                            <select class="form-control" name="status">
                                                <option value="new">Nuevo</option>
                                                <option value="contacted">Contactado</option>
                                                <option value="qualified">Calificado</option>
                                                <option value="converted">Convertido</option>
                                                <option value="lost">Perdido</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group mb-3">
                                            <label>Fuente</label>
                                            <select class="form-control" name="source">
                                                <option value="manual">Manual</option>
                                                <option value="website">Sitio Web</option>
                                                <option value="facebook">Facebook</option>
                                                <option value="google">Google</option>
                                                <option value="referral">Referido</option>
                                                <option value="import">Importación</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" name="id" id="leadId">
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary" onclick="LeadsModule.saveLead()">
                                <span id="saveLeadText">Guardar Lead</span>
                            </button>
                        </div>
                    </div>
                </div>
            <!-- Modal para importar leads -->
            <div class="modal fade" id="importModal" tabindex="-1">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Importar Leads</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <!-- Paso 1: Subir archivo -->
                            <div id="importStep1">
                                <div class="row">
                                    <div class="col-md-8">
                                        <h6>Paso 1: Seleccionar archivo CSV</h6>
                                        <div class="mb-3">
                                            <input type="file" class="form-control" id="importFile" accept=".csv" required>
                                            <div class="form-text">
                                                Formatos soportados: CSV (máximo 10MB)
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-primary" onclick="LeadsModule.uploadFile()">
                                            <i class="fas fa-upload"></i> Subir y Analizar
                                        </button>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <h6>Formato requerido:</h6>
                                                <ul class="small">
                                                    <li>Archivo CSV con headers</li>
                                                    <li><strong>Email es obligatorio</strong></li>
                                                    <li>Nombre o apellido requerido</li>
                                                    <li>Separador: coma (,) o punto y coma (;)</li>
                                                    <li><strong>Columnas disponibles:</strong></li>
                                                    <li>• ID, Nombre, Apellido</li>
                                                    <li>• Teléfono, País, Email</li>
                                                    <li>• Desk, Último Comentario</li>
                                                    <li>• Último Contacto, Campaña</li>
                                                    <li>• Responsable</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Paso 2: Mapear columnas -->
                            <div id="importStep2" style="display: none;">
                                <h6>Paso 2: Mapear columnas</h6>
                                <p class="text-muted">Relaciona las columnas de tu archivo con los campos del sistema:</p>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Columnas del archivo:</h6>
                                        <div id="fileColumns" class="list-group">
                                            <!-- Se llenarán dinámicamente -->
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Mapeo de campos:</h6>
                                        <div id="fieldMapping">
                                            <div class="mb-3">
                                                <label>Nombre *</label>
                                                <select class="form-control" name="first_name">
                                                    <option value="">Seleccionar columna...</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label>Apellido</label>
                                                <select class="form-control" name="last_name">
                                                    <option value="">Seleccionar columna...</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label>Teléfono</label>
                                                <select class="form-control" name="phone">
                                                    <option value="">Seleccionar columna...</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label>País</label>
                                                <select class="form-control" name="country">
                                                    <option value="">Seleccionar columna...</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label>Email *</label>
                                                <select class="form-control" name="email">
                                                    <option value="">Seleccionar columna...</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label>Desk</label>
                                                <select class="form-control" name="desk_id">
                                                    <option value="">Seleccionar columna...</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label>Último Comentario</label>
                                                <select class="form-control" name="last_comment">
                                                    <option value="">Seleccionar columna...</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label>Último Contacto</label>
                                                <select class="form-control" name="last_contact">
                                                    <option value="">Seleccionar columna...</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label>Campaña</label>
                                                <select class="form-control" name="campaign">
                                                    <option value="">Seleccionar columna...</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label>Responsable</label>
                                                <select class="form-control" name="assigned_user_id">
                                                    <option value="">Seleccionar columna...</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Vista previa -->
                                <div class="mt-4">
                                    <h6>Vista previa de datos:</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered" id="previewTable">
                                            <!-- Se llenará dinámicamente -->
                                        </table>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <button type="button" class="btn btn-secondary" onclick="LeadsModule.backToStep1()">
                                        <i class="fas fa-arrow-left"></i> Volver
                                    </button>
                                    <button type="button" class="btn btn-success" onclick="LeadsModule.importLeads()">
                                        <i class="fas fa-download"></i> Importar Leads
                                    </button>
                                </div>
                            </div>

                            <!-- Paso 3: Resultado -->
                            <div id="importStep3" style="display: none;">
                                <h6>Resultado de la importación</h6>
                                <div id="importResults">
                                    <!-- Se llenará con los resultados -->
                                </div>
                                <div class="mt-3">
                                    <button type="button" class="btn btn-primary" onclick="LeadsModule.finishImport()">
                                        <i class="fas fa-check"></i> Finalizar
                                    </button>
                                </div>
                            </div>
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
        document.getElementById('deskFilter').addEventListener('change', () => this.applyFilters());
    },

    // Cargar leads desde la API
    async loadLeads(page = 1) {
        this.state.loading = true;
        this.showLoading(true);

        try {
            const params = new URLSearchParams({
                page: page,
                limit: 20,
                ...this.state.filters
            });

            const response = await App.apiRequest(`/leads.php?${params}`);
            
            if (response.success) {
                this.state.leads = response.data.leads;
                this.state.currentPage = response.data.pagination.page;
                this.state.totalPages = response.data.pagination.pages;
                
                this.renderLeadsTable();
                this.renderPagination();
                this.renderStats();
            } else {
                throw new Error(response.message || 'Error al cargar leads');
            }
        } catch (error) {
            console.error('Error loading leads:', error);
            App.showNotification('Error al cargar leads: ' + error.message, 'error');
            this.renderEmptyState();
        } finally {
            this.state.loading = false;
            this.showLoading(false);
        }
    },

    // Renderizar tabla de leads
    renderLeadsTable() {
        const tbody = document.getElementById('leadsTableBody');
        
        if (this.state.leads.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="13" class="text-center py-4">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No se encontraron leads</p>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = this.state.leads.map(lead => `
            <tr>
                <td>${lead.id}</td>
                <td>${lead.first_name || ''}</td>
                <td>${lead.last_name || ''}</td>
                <td>${lead.phone || 'N/A'}</td>
                <td>${lead.country || 'N/A'}</td>
                <td>${lead.email}</td>
                <td>${lead.desk_name || 'Sin asignar'}</td>
                <td>${lead.last_comment ? (lead.last_comment.length > 50 ? lead.last_comment.substring(0, 50) + '...' : lead.last_comment) : 'N/A'}</td>
                <td>${lead.last_contact ? App.formatDateTime(lead.last_contact) : 'N/A'}</td>
                <td>${lead.campaign || 'N/A'}</td>
                <td>${lead.assigned_user_first_name && lead.assigned_user_last_name ? 
                    `${lead.assigned_user_first_name} ${lead.assigned_user_last_name}` : 
                    'Sin asignar'}</td>
                <td>${App.formatDate(lead.created_at)}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="LeadsModule.editLead(${lead.id})" title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="LeadsModule.deleteLead(${lead.id})" title="Eliminar">
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
        const statsContainer = document.getElementById('leadsStats');
        
        statsContainer.innerHTML = `
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4>${stats.total}</h4>
                                <p class="mb-0">Total Leads</p>
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
                                <h4>${stats.converted}</h4>
                                <p class="mb-0">Convertidos</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-check-circle fa-2x"></i>
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
                                <h4>${stats.pending}</h4>
                                <p class="mb-0">Pendientes</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-clock fa-2x"></i>
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
                                <h4>${stats.conversionRate}%</h4>
                                <p class="mb-0">Tasa Conversión</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-percentage fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    },

    // Calcular estadísticas
    calculateStats() {
        const total = this.state.leads.length;
        const converted = this.state.leads.filter(lead => lead.status === 'converted').length;
        const pending = this.state.leads.filter(lead => ['new', 'contacted', 'qualified'].includes(lead.status)).length;
        const conversionRate = total > 0 ? Math.round((converted / total) * 100) : 0;

        return { total, converted, pending, conversionRate };
    },

    // Aplicar filtros
    applyFilters() {
        this.state.filters.search = document.getElementById('searchInput').value;
        this.state.filters.status = document.getElementById('statusFilter').value;
        this.state.filters.desk_id = document.getElementById('deskFilter').value;
        
        this.loadLeads(1);
    },

    // Limpiar filtros
    clearFilters() {
        document.getElementById('searchInput').value = '';
        document.getElementById('statusFilter').value = '';
        document.getElementById('deskFilter').value = '';
        
        this.state.filters = { search: '', status: '', desk_id: '' };
        this.loadLeads(1);
    },

    // Mostrar modal de creación
    showCreateModal() {
        document.getElementById('leadModalTitle').textContent = 'Nuevo Lead';
        document.getElementById('leadForm').reset();
        document.getElementById('leadId').value = '';
        
        const modal = new bootstrap.Modal(document.getElementById('leadModal'));
        modal.show();
    },

    // Editar lead
    async editLead(id) {
        const lead = this.state.leads.find(l => l.id == id);
        if (!lead) return;

        document.getElementById('leadModalTitle').textContent = 'Editar Lead';
        document.getElementById('leadId').value = lead.id;
        
        // Llenar formulario
        const form = document.getElementById('leadForm');
        form.first_name.value = lead.first_name;
        form.last_name.value = lead.last_name;
        form.email.value = lead.email;
        form.phone.value = lead.phone || '';
        form.country.value = lead.country || '';
        form.status.value = lead.status;
        form.source.value = lead.source || 'manual';

        const modal = new bootstrap.Modal(document.getElementById('leadModal'));
        modal.show();
    },

    // Guardar lead
    async saveLead() {
        const form = document.getElementById('leadForm');
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);
        
        const isEdit = !!data.id;
        
        try {
            const response = isEdit ? 
                await App.apiRequest(`/leads.php?id=${data.id}`, {
                    method: 'PUT',
                    body: JSON.stringify(data)
                }) :
                await App.apiRequest('/leads.php', {
                    method: 'POST',
                    body: JSON.stringify(data)
                });

            if (response.success) {
                App.showNotification(
                    isEdit ? 'Lead actualizado correctamente' : 'Lead creado correctamente', 
                    'success'
                );
                
                const modal = bootstrap.Modal.getInstance(document.getElementById('leadModal'));
                modal.hide();
                
                this.loadLeads(this.state.currentPage);
            } else {
                throw new Error(response.message || 'Error al guardar lead');
            }
        } catch (error) {
            console.error('Error saving lead:', error);
            App.showNotification('Error al guardar lead: ' + error.message, 'error');
        }
    },

    // Eliminar lead
    async deleteLead(id) {
        if (!confirm('¿Estás seguro de que quieres eliminar este lead?')) return;

        try {
            const response = await App.apiRequest(`/leads.php?id=${id}`, {
                method: 'DELETE'
            });

            if (response.success) {
                App.showNotification('Lead eliminado correctamente', 'success');
                this.loadLeads(this.state.currentPage);
            } else {
                throw new Error(response.message || 'Error al eliminar lead');
            }
        } catch (error) {
            console.error('Error deleting lead:', error);
            App.showNotification('Error al eliminar lead: ' + error.message, 'error');
        }
    },

    // Utilidades
    getStatusColor(status) {
        const colors = {
            'new': 'primary',
            'contacted': 'info',
            'qualified': 'warning',
            'converted': 'success',
            'lost': 'danger'
        };
        return colors[status] || 'secondary';
    },

    getStatusText(status) {
        const texts = {
            'new': 'Nuevo',
            'contacted': 'Contactado',
            'qualified': 'Calificado',
            'converted': 'Convertido',
            'lost': 'Perdido'
        };
        return texts[status] || status;
    },

    showLoading(show) {
        const loading = document.getElementById('leadsLoading');
        if (loading) {
            loading.style.display = show ? 'block' : 'none';
        }
    },

    renderEmptyState() {
        const tbody = document.getElementById('leadsTableBody');
        tbody.innerHTML = `
            <tr>
                <td colspan="13" class="text-center py-5">
                    <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                    <p class="text-muted">Error al cargar los datos</p>
                    <button class="btn btn-primary" onclick="LeadsModule.loadLeads()">
                        <i class="fas fa-refresh"></i> Reintentar
                    </button>
                </td>
            </tr>
        `;
    },

    renderPagination() {
        // Implementar paginación
        const pagination = document.getElementById('leadsPagination');
        // ... código de paginación
    },

    // Mostrar modal de importación
    showImportModal() {
        console.log('Mostrando modal de importación');
        
        // Resetear estado de importación
        this.state.import = {
            fileData: null,
            columns: [],
            sampleData: [],
            mapping: {}
        };
        
        // Mostrar paso 1
        document.getElementById('importStep1').style.display = 'block';
        document.getElementById('importStep2').style.display = 'none';
        document.getElementById('importStep3').style.display = 'none';
        
        // Limpiar archivo
        document.getElementById('importFile').value = '';
        
        try {
            const modal = new bootstrap.Modal(document.getElementById('importModal'));
            modal.show();
            console.log('Modal de importación mostrado');
        } catch (error) {
            console.error('Error mostrando modal:', error);
            // Fallback: mostrar modal manualmente
            const modalElement = document.getElementById('importModal');
            if (modalElement) {
                modalElement.style.display = 'block';
                modalElement.classList.add('show');
                document.body.classList.add('modal-open');
                
                // Crear backdrop
                const backdrop = document.createElement('div');
                backdrop.className = 'modal-backdrop fade show';
                backdrop.id = 'importModalBackdrop';
                document.body.appendChild(backdrop);
            }
        }
    },

    // Subir archivo para análisis
    async uploadFile() {
        const fileInput = document.getElementById('importFile');
        const file = fileInput.files[0];
        
        if (!file) {
            App.showNotification('Por favor selecciona un archivo', 'warning');
            return;
        }
        
        if (file.size > 10 * 1024 * 1024) {
            App.showNotification('El archivo es demasiado grande. Máximo 10MB', 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('file', file);
        
        try {
            const response = await fetch('/api/import-simple.php', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${App.getAuthToken()}`
                },
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success && result.data.requires_mapping) {
                this.state.import.columns = result.data.columns;
                this.state.import.sampleData = result.data.sample_data;
                
                this.showMappingStep();
            } else {
                throw new Error(result.message || 'Error al procesar archivo');
            }
            
        } catch (error) {
            console.error('Error uploading file:', error);
            App.showNotification('Error al subir archivo: ' + error.message, 'error');
        }
    },

    // Mostrar paso de mapeo
    showMappingStep() {
        document.getElementById('importStep1').style.display = 'none';
        document.getElementById('importStep2').style.display = 'block';
        
        // Llenar columnas del archivo
        const fileColumns = document.getElementById('fileColumns');
        fileColumns.innerHTML = this.state.import.columns.map(col => 
            `<div class="list-group-item">${col}</div>`
        ).join('');
        
        // Llenar selects de mapeo
        const selects = document.querySelectorAll('#fieldMapping select');
        selects.forEach(select => {
            select.innerHTML = '<option value="">Seleccionar columna...</option>' +
                this.state.import.columns.map(col => 
                    `<option value="${col}">${col}</option>`
                ).join('');
            
            // Auto-mapeo inteligente
            const fieldName = select.name;
            const smartMatch = this.findSmartMatch(fieldName, this.state.import.columns);
            if (smartMatch) {
                select.value = smartMatch;
            }
        });
        
        // Mostrar vista previa
        this.updatePreview();
        
        // Agregar eventos de cambio
        selects.forEach(select => {
            select.addEventListener('change', () => this.updatePreview());
        });
    },

    // Auto-mapeo inteligente
    findSmartMatch(fieldName, columns) {
        const patterns = {
            'first_name': ['nombre', 'first_name', 'firstname', 'name', 'primer_nombre'],
            'last_name': ['apellido', 'last_name', 'lastname', 'surname', 'apellidos'],
            'phone': ['telefono', 'phone', 'tel', 'celular', 'movil', 'telephone'],
            'country': ['pais', 'country', 'nacionalidad'],
            'email': ['email', 'correo', 'e-mail', 'mail'],
            'desk_id': ['desk', 'mesa', 'desk_id', 'escritorio'],
            'last_comment': ['ultimo_comentario', 'last_comment', 'comentario', 'comment', 'observaciones'],
            'last_contact': ['ultimo_contacto', 'last_contact', 'fecha_contacto', 'contact_date'],
            'campaign': ['campaña', 'campaign', 'campana', 'source_campaign'],
            'assigned_user_id': ['responsable', 'assigned_user', 'usuario_asignado', 'agent', 'agente']
        };
        
        const fieldPatterns = patterns[fieldName] || [];
        
        for (const column of columns) {
            const columnLower = column.toLowerCase();
            for (const pattern of fieldPatterns) {
                if (columnLower.includes(pattern)) {
                    return column;
                }
            }
        }
        
        return null;
    },

    // Actualizar vista previa
    updatePreview() {
        const selects = document.querySelectorAll('#fieldMapping select');
        const mapping = {};
        
        selects.forEach(select => {
            if (select.value) {
                mapping[select.name] = select.value;
            }
        });
        
        this.state.import.mapping = mapping;
        
        // Crear tabla de vista previa
        const previewTable = document.getElementById('previewTable');
        
        if (Object.keys(mapping).length === 0) {
            previewTable.innerHTML = '<tr><td colspan="100%" class="text-center text-muted">Selecciona al menos un campo para ver la vista previa</td></tr>';
            return;
        }
        
        let html = '<thead><tr>';
        Object.keys(mapping).forEach(field => {
            html += `<th>${this.getFieldLabel(field)}</th>`;
        });
        html += '</tr></thead><tbody>';
        
        this.state.import.sampleData.forEach(row => {
            html += '<tr>';
            Object.keys(mapping).forEach(field => {
                const column = mapping[field];
                html += `<td>${row[column] || ''}</td>`;
            });
            html += '</tr>';
        });
        
        html += '</tbody>';
        previewTable.innerHTML = html;
    },

    // Obtener etiqueta del campo
    getFieldLabel(field) {
        const labels = {
            'first_name': 'Nombre',
            'last_name': 'Apellido',
            'phone': 'Teléfono',
            'country': 'País',
            'email': 'Email',
            'desk_id': 'Desk',
            'last_comment': 'Último Comentario',
            'last_contact': 'Último Contacto',
            'campaign': 'Campaña',
            'assigned_user_id': 'Responsable'
        };
        return labels[field] || field;
    },

    // Volver al paso 1
    backToStep1() {
        document.getElementById('importStep1').style.display = 'block';
        document.getElementById('importStep2').style.display = 'none';
    },

    // Importar leads
    async importLeads() {
        if (Object.keys(this.state.import.mapping).length === 0) {
            App.showNotification('Debes mapear al menos un campo', 'warning');
            return;
        }
        
        if (!this.state.import.mapping.email) {
            App.showNotification('El campo Email es obligatorio', 'warning');
            return;
        }
        
        const fileInput = document.getElementById('importFile');
        const file = fileInput.files[0];
        
        const formData = new FormData();
        formData.append('file', file);
        formData.append('mapping', JSON.stringify(this.state.import.mapping));
        
        try {
            const response = await fetch('/api/import-simple.php', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${App.getAuthToken()}`
                },
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showImportResults(result.data);
            } else {
                throw new Error(result.message || 'Error en la importación');
            }
            
        } catch (error) {
            console.error('Error importing leads:', error);
            App.showNotification('Error en la importación: ' + error.message, 'error');
        }
    },

    // Mostrar resultados de importación
    showImportResults(data) {
        document.getElementById('importStep2').style.display = 'none';
        document.getElementById('importStep3').style.display = 'block';
        
        const resultsDiv = document.getElementById('importResults');
        
        let html = `
            <div class="row">
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h3>${data.imported}</h3>
                            <p class="mb-0">Importados</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body text-center">
                            <h3>${data.duplicates}</h3>
                            <p class="mb-0">Duplicados</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body text-center">
                            <h3>${data.errors_count}</h3>
                            <p class="mb-0">Errores</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h3>${data.success_rate}%</h3>
                            <p class="mb-0">Éxito</p>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        if (data.errors && data.errors.length > 0) {
            html += `
                <div class="mt-4">
                    <h6>Errores encontrados:</h6>
                    <div class="alert alert-warning">
                        <ul class="mb-0">
                            ${data.errors.map(error => `<li>${error}</li>`).join('')}
                        </ul>
                        ${data.errors_count > 10 ? `<p class="mt-2 mb-0"><small>... y ${data.errors_count - 10} errores más</small></p>` : ''}
                    </div>
                </div>
            `;
        }
        
        resultsDiv.innerHTML = html;
    },

    // Finalizar importación
    finishImport() {
        // Cerrar modal manualmente si es necesario
        const modalElement = document.getElementById('importModal');
        if (modalElement) {
            modalElement.style.display = 'none';
            modalElement.classList.remove('show');
            document.body.classList.remove('modal-open');
            
            // Remover backdrop si existe
            const backdrop = document.getElementById('importModalBackdrop');
            if (backdrop) {
                backdrop.remove();
            }
        }
        
        // Intentar cerrar con Bootstrap también
        try {
            const modal = bootstrap.Modal.getInstance(document.getElementById('importModal'));
            if (modal) {
                modal.hide();
            }
        } catch (error) {
            console.log('Bootstrap modal no disponible, usando método manual');
        }
        
        // Recargar leads
        this.loadLeads(1);
        
        App.showNotification('Importación completada exitosamente', 'success');
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
window.LeadsModule = LeadsModule;