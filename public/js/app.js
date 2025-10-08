/**
 * Aplicación Principal - iaTrade CRM
 * Sistema modular de gestión
 */
const App = {
    // Configuración de la aplicación
    config: {
        apiUrl: '/api',
        version: '1.0.0',
        modules: {
            leads: {
                path: '/views/leads/',
                title: 'Gestión de Leads',
                icon: 'fas fa-users'
            },
            desks: {
                path: '/views/desks/',
                title: 'Gestión de Desks',
                icon: 'fas fa-building'
            },
            users: {
                path: '/views/users/',
                title: 'Gestión de Usuarios',
                icon: 'fas fa-user-tie'
            },
            roles: {
                path: '/views/roles/',
                title: 'Gestión de Roles',
                icon: 'fas fa-shield-alt'
            },
            dashboard: {
                path: '/views/dashboard/',
                title: 'Dashboard',
                icon: 'fas fa-tachometer-alt'
            }
        }
    },

    // Estado de la aplicación
    state: {
        currentModule: 'dashboard',
        loadedModules: new Set(),
        user: null,
        isAuthenticated: false
    },

    // Inicialización de la aplicación
    init() {
        console.log('Inicializando iaTrade CRM v' + this.config.version);
        
        this.bindEvents();
        this.initSidebar();
        this.checkAuthentication();
        
        // Cargar módulo inicial
        this.loadModule('dashboard');
        
        this.showNotification('¡Sistema iaTrade CRM cargado! Navega por los módulos usando el menú lateral.', 'success');
    },

    // Vincular eventos globales
    bindEvents() {
        // Toggle sidebar
        const sidebarToggle = document.getElementById('sidebarToggle');
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', () => {
                this.toggleSidebar();
            });
        }

        // Enlaces de navegación
        document.querySelectorAll('.nav-link[data-module]').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const module = link.dataset.module;
                this.loadModule(module);
                
                // Actualizar estado activo
                document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
                link.classList.add('active');
                
                // Actualizar título
                this.updatePageTitle(module);
                
                // Ocultar sidebar en móvil
                if (window.innerWidth <= 768) {
                    this.hideSidebar();
                }
            });
        });

        // Responsive handling
        window.addEventListener('resize', () => {
            this.handleResize();
        });
    },

    // Inicializar sidebar
    initSidebar() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        
        if (window.innerWidth <= 768) {
            sidebar?.classList.add('collapsed');
            mainContent?.classList.add('expanded');
        }
    },

    // Toggle sidebar
    toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        
        if (window.innerWidth <= 768) {
            sidebar?.classList.toggle('show');
        } else {
            sidebar?.classList.toggle('collapsed');
            mainContent?.classList.toggle('expanded');
        }
    },

    // Ocultar sidebar
    hideSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar?.classList.remove('show');
    },

    // Manejar redimensionamiento
    handleResize() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        
        if (window.innerWidth <= 768) {
            sidebar?.classList.remove('collapsed');
            sidebar?.classList.remove('show');
            mainContent?.classList.add('expanded');
        } else {
            sidebar?.classList.remove('show');
            if (sidebar?.classList.contains('collapsed')) {
                mainContent?.classList.add('expanded');
            } else {
                mainContent?.classList.remove('expanded');
            }
        }
    },

    // Cargar módulo dinámicamente
    async loadModule(moduleName) {
        if (this.state.loadedModules.has(moduleName)) {
            await this.initializeModule(moduleName);
            return;
        }

        this.showLoading();

        try {
            // Cargar módulo específico
            if (moduleName === 'dashboard') {
                await this.showDashboard();
            } else {
                // Cargar script del módulo si existe
                const scriptPath = `/js/modules/${moduleName}.js`;
                await this.loadModuleScript(moduleName, scriptPath);
                
                // Marcar como cargado
                this.state.loadedModules.add(moduleName);
                
                // Inicializar módulo
                await this.initializeModule(moduleName);
            }

            this.state.currentModule = moduleName;
            
        } catch (error) {
            console.error(`Error loading module ${moduleName}:`, error);
            this.showNotification(`Error al cargar el módulo ${moduleName}`, 'error');
            
            // Fallback al dashboard
            if (moduleName !== 'dashboard') {
                this.loadModule('dashboard');
            }
        } finally {
            this.hideLoading();
        }
    },

    // Cargar script del módulo
    async loadModuleScript(moduleName, scriptPath) {
        return new Promise((resolve, reject) => {
            // Verificar si ya existe el script
            if (document.querySelector(`script[src="${scriptPath}"]`)) {
                resolve();
                return;
            }

            const script = document.createElement('script');
            script.src = scriptPath;
            script.onload = () => resolve();
            script.onerror = () => reject(new Error(`Failed to load script: ${scriptPath}`));
            document.head.appendChild(script);
        });
    },

    // Inicializar módulo específico
    async initializeModule(moduleName) {
        try {
            switch (moduleName) {
                case 'leads':
                    if (window.LeadsModule) {
                        await window.LeadsModule.init();
                    }
                    break;
                case 'users':
                    if (window.UsersModule) {
                        await window.UsersModule.init();
                    }
                    break;
                case 'roles':
                    if (window.RolesModule) {
                        await window.RolesModule.init();
                    }
                    break;
                case 'desks':
                    if (window.DesksModule) {
                        await window.DesksModule.init();
                    }
                    break;
                default:
                    console.warn(`No initializer found for module: ${moduleName}`);
            }
        } catch (error) {
            console.error(`Error initializing module ${moduleName}:`, error);
            throw error;
        }
    },

    // Mostrar dashboard con datos reales
    async showDashboard() {
        const contentArea = document.getElementById('moduleContent');
        if (!contentArea) return;

        contentArea.innerHTML = `
            <div class="dashboard-header mb-4">
                <h2><i class="fas fa-tachometer-alt"></i> Dashboard</h2>
                <p class="text-muted">Resumen general del sistema</p>
            </div>
            
            <div id="dashboard-loading" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <p class="mt-2">Cargando estadísticas...</p>
            </div>
            
            <div id="dashboard-content" style="display: none;">
                <!-- Las estadísticas se cargarán aquí -->
            </div>
        `;

        try {
            // Obtener estadísticas de la API
            const response = await this.apiRequest('/dashboard.php');
            
            if (response.success) {
                this.renderDashboardStats(response.data);
            } else {
                throw new Error(response.message || 'Error al cargar estadísticas');
            }
        } catch (error) {
            console.error('Error loading dashboard:', error);
            document.getElementById('dashboard-content').innerHTML = `
                <div class="alert alert-warning">
                    <h5><i class="fas fa-exclamation-triangle"></i> Error al cargar datos</h5>
                    <p>No se pudieron cargar las estadísticas del dashboard. Mostrando datos de ejemplo.</p>
                </div>
            `;
            this.renderDashboardDemo();
        } finally {
            document.getElementById('dashboard-loading').style.display = 'none';
            document.getElementById('dashboard-content').style.display = 'block';
        }
    },

    // Renderizar estadísticas reales del dashboard
    renderDashboardStats(data) {
        const dashboardContent = document.getElementById('dashboard-content');
        
        dashboardContent.innerHTML = `
            <!-- Tarjetas de estadísticas -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Total Leads</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">${data.total_leads || 0}</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Tasa de Conversión</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">${data.conversion_rate || 0}%</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-percentage fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Usuarios Activos</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">${data.total_users || 0}</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-user-tie fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Leads Recientes</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">${data.recent_leads || 0}</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clock fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráficos y tablas -->
            <div class="row">
                <!-- Leads por Estado -->
                <div class="col-xl-6 col-lg-7">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Leads por Estado</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-pie pt-4 pb-2">
                                ${this.renderLeadsStatusChart(data.leads_by_status || {})}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Leads por Mesa -->
                <div class="col-xl-6 col-lg-5">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Leads por Mesa</h6>
                        </div>
                        <div class="card-body">
                            ${this.renderLeadsByDeskTable(data.leads_by_desk || [])}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actividad Reciente -->
            <div class="row">
                <div class="col-12">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Actividad Reciente</h6>
                        </div>
                        <div class="card-body">
                            ${this.renderRecentActivity(data.recent_activity || [])}
                        </div>
                    </div>
                </div>
            </div>
        `;
    },

    // Renderizar gráfico de leads por estado
    renderLeadsStatusChart(leadsByStatus) {
        const statuses = Object.keys(leadsByStatus);
        const counts = Object.values(leadsByStatus);
        
        if (statuses.length === 0) {
            return '<p class="text-muted text-center">No hay datos disponibles</p>';
        }

        let html = '<div class="row">';
        const colors = ['primary', 'success', 'warning', 'danger', 'info'];
        
        statuses.forEach((status, index) => {
            const color = colors[index % colors.length];
            const percentage = counts.reduce((a, b) => a + b, 0) > 0 ? 
                Math.round((leadsByStatus[status] / counts.reduce((a, b) => a + b, 0)) * 100) : 0;
            
            html += `
                <div class="col-6 mb-3">
                    <div class="d-flex align-items-center">
                        <div class="bg-${color} rounded-circle me-2" style="width: 12px; height: 12px;"></div>
                        <div>
                            <div class="small text-muted">${status.charAt(0).toUpperCase() + status.slice(1)}</div>
                            <div class="font-weight-bold">${leadsByStatus[status]} (${percentage}%)</div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        return html;
    },

    // Renderizar tabla de leads por mesa
    renderLeadsByDeskTable(leadsByDesk) {
        if (leadsByDesk.length === 0) {
            return '<p class="text-muted text-center">No hay datos disponibles</p>';
        }

        let html = '<div class="table-responsive"><table class="table table-sm">';
        html += '<thead><tr><th>Mesa</th><th class="text-end">Leads</th></tr></thead><tbody>';
        
        leadsByDesk.forEach(item => {
            html += `
                <tr>
                    <td>${item.desk}</td>
                    <td class="text-end"><span class="badge bg-primary">${item.count}</span></td>
                </tr>
            `;
        });
        
        html += '</tbody></table></div>';
        return html;
    },

    // Renderizar actividad reciente
    renderRecentActivity(recentActivity) {
        if (recentActivity.length === 0) {
            return '<p class="text-muted text-center">No hay actividad reciente</p>';
        }

        let html = '<div class="list-group list-group-flush">';
        
        recentActivity.forEach(activity => {
            const date = new Date(activity.date);
            const timeAgo = this.getTimeAgo(date);
            
            html += `
                <div class="list-group-item list-group-item-action">
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1">${activity.description}</h6>
                        <small class="text-muted">${timeAgo}</small>
                    </div>
                    <p class="mb-1">Lead: ${activity.lead}</p>
                    <small class="text-muted">Por: ${activity.user}</small>
                </div>
            `;
        });
        
        html += '</div>';
        return html;
    },

    // Obtener tiempo transcurrido
    getTimeAgo(date) {
        const now = new Date();
        const diffTime = Math.abs(now - date);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        if (diffDays === 1) return 'Hace 1 día';
        if (diffDays < 7) return `Hace ${diffDays} días`;
        if (diffDays < 30) return `Hace ${Math.ceil(diffDays / 7)} semanas`;
        return `Hace ${Math.ceil(diffDays / 30)} meses`;
    },

    // Renderizar dashboard demo (fallback)
    renderDashboardDemo() {
        const dashboardContent = document.getElementById('dashboard-content');
        dashboardContent.innerHTML = `
            <div class="alert alert-info">
                <h5><i class="fas fa-info-circle"></i> Datos de Demostración</h5>
                <p>Mostrando datos de ejemplo. Instala la base de datos para ver estadísticas reales.</p>
            </div>
            <!-- Aquí iría el contenido demo original -->
        `;
    },

    // Actualizar título de página
    updatePageTitle(module) {
        const pageTitle = document.getElementById('pageTitle');
        const moduleConfig = this.config.modules[module];
        
        if (pageTitle && moduleConfig) {
            pageTitle.textContent = moduleConfig.title;
        }
    },

    // Verificar autenticación
    checkAuthentication() {
        const token = localStorage.getItem('auth_token');
        if (token) {
            this.state.isAuthenticated = true;
            this.state.user = {
                id: 1,
                username: 'admin',
                email: 'admin@iatrade.com',
                first_name: 'Admin',
                last_name: 'System'
            };
            this.updateUserDisplay();
        }
    },

    // Actualizar display del usuario
    updateUserDisplay() {
        const userMenu = document.querySelector('.user-name');
        if (userMenu && this.state.user) {
            userMenu.textContent = this.state.user.name;
        }
    },

    // Mostrar loading
    showLoading() {
        const contentArea = document.getElementById('moduleContent');
        if (contentArea) {
            contentArea.innerHTML = `
                <div class="d-flex justify-content-center align-items-center" style="height: 400px;">
                    <div class="text-center">
                        <div class="spinner-border text-primary mb-3" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p class="text-muted">Cargando módulo...</p>
                    </div>
                </div>
            `;
        }
    },

    // Ocultar loading
    hideLoading() {
        // El loading se oculta automáticamente al cargar el contenido del módulo
    },

    // Mostrar notificación
    showNotification(message, type = 'info', duration = 5000) {
        const alertClass = {
            'success': 'alert-success',
            'error': 'alert-danger',
            'warning': 'alert-warning',
            'info': 'alert-info'
        }[type] || 'alert-info';

        const icon = {
            'success': 'fas fa-check-circle',
            'error': 'fas fa-exclamation-circle',
            'warning': 'fas fa-exclamation-triangle',
            'info': 'fas fa-info-circle'
        }[type] || 'fas fa-info-circle';
        
        const notification = document.createElement('div');
        notification.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; max-width: 500px;';
        notification.innerHTML = `
            <i class="${icon} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(notification);
        
        // Auto-remove después del tiempo especificado
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, duration);
    },

    // Realizar petición API
    async apiRequest(endpoint, options = {}) {
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        };

        // Adjuntar Authorization si hay token, incluso si el estado isAuthenticated no está inicializado aún
        const token = this.getAuthToken();
        if (token) {
            defaultOptions.headers['Authorization'] = `Bearer ${token}`;
        }

        const finalOptions = { ...defaultOptions, ...options };
        
        try {
            const response = await fetch(`${this.config.apiUrl}${endpoint}`, finalOptions);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('API Request Error:', error);
            this.showNotification(`Error en la petición: ${error.message}`, 'error');
            throw error;
        }
    },

    // Obtener token de autenticación
    getAuthToken() {
        return localStorage.getItem('auth_token') || 'demo_token';
    },

    // Logout
    logout() {
        this.state.isAuthenticated = false;
        this.state.user = null;
        localStorage.removeItem('auth_token');
        this.showNotification('Sesión cerrada', 'info');
        // Redirigir al login
        window.location.reload();
    },

    // Utilidades
    formatCurrency(amount) {
        return new Intl.NumberFormat('es-ES', {
            style: 'currency',
            currency: 'USD'
        }).format(amount);
    },

    formatDate(date) {
        return new Intl.DateTimeFormat('es-ES').format(new Date(date));
    },

    formatDateTime(date) {
        return new Intl.DateTimeFormat('es-ES', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        }).format(new Date(date));
    }
};

// Inicializar aplicación cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    // Capturadores globales para evitar pantalla blanca silenciosa y mostrar errores
    window.addEventListener('error', (event) => {
        try {
            const msg = event?.message || 'Error de JavaScript desconocido';
            App.showNotification(`Error de JS: ${msg}`, 'error', 15000);
            console.error('Global JS Error:', event);
        } catch (_) {}
    });
    window.addEventListener('unhandledrejection', (event) => {
        try {
            const reason = event?.reason;
            const msg = (reason && (reason.message || reason.toString())) || 'Error de promesa no manejado';
            App.showNotification(`Error de promesa: ${msg}`, 'error', 15000);
            console.error('Unhandled Promise Rejection:', event);
        } catch (_) {}
    });
    App.init();
});

// Exportar App para uso global
window.App = App;