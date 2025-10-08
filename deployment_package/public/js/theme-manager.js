/**
 * Sistema de Gestión de Temas para CRM Forex/CFD
 * Paleta profesional: Azul oscuro y blanco
 */

class ThemeManager {
    constructor() {
        this.currentTheme = this.getStoredTheme() || 'light';
        this.themes = {
            light: {
                name: 'Light',
                icon: 'fas fa-sun',
                colors: {
                    // Colores principales
                    primary: '#1e3a8a',           // Azul oscuro principal
                    primaryDark: '#1e40af',       // Azul más oscuro
                    primaryLight: '#3b82f6',      // Azul claro
                    secondary: '#64748b',         // Gris azulado
                    
                    // Colores de fondo
                    background: '#ffffff',        // Blanco puro
                    backgroundSecondary: '#f8fafc', // Gris muy claro
                    backgroundTertiary: '#f1f5f9', // Gris claro
                    
                    // Colores de superficie
                    surface: '#ffffff',           // Blanco
                    surfaceElevated: '#ffffff',   // Blanco con sombra
                    
                    // Colores de texto
                    textPrimary: '#0f172a',       // Negro azulado
                    textSecondary: '#475569',     // Gris oscuro
                    textMuted: '#94a3b8',         // Gris medio
                    textInverse: '#ffffff',       // Blanco
                    
                    // Colores de estado
                    success: '#059669',           // Verde
                    warning: '#d97706',           // Naranja
                    danger: '#dc2626',            // Rojo
                    info: '#0284c7',              // Azul info
                    
                    // Colores de borde
                    border: '#e2e8f0',           // Gris claro
                    borderLight: '#f1f5f9',       // Gris muy claro
                    borderDark: '#cbd5e1',        // Gris medio
                    
                    // Colores específicos Forex
                    profit: '#059669',            // Verde para ganancias
                    loss: '#dc2626',              // Rojo para pérdidas
                    neutral: '#64748b',           // Gris para neutral
                    
                    // Sombras
                    shadowSm: '0 1px 2px 0 rgba(0, 0, 0, 0.05)',
                    shadowMd: '0 4px 6px -1px rgba(0, 0, 0, 0.1)',
                    shadowLg: '0 10px 15px -3px rgba(0, 0, 0, 0.1)',
                    shadowXl: '0 20px 25px -5px rgba(0, 0, 0, 0.1)'
                }
            },
            dark: {
                name: 'Dark',
                icon: 'fas fa-moon',
                colors: {
                    // Colores principales
                    primary: '#3b82f6',           // Azul brillante
                    primaryDark: '#2563eb',       // Azul medio
                    primaryLight: '#60a5fa',      // Azul claro
                    secondary: '#94a3b8',         // Gris claro
                    
                    // Colores de fondo
                    background: '#0f172a',        // Azul muy oscuro
                    backgroundSecondary: '#1e293b', // Azul oscuro
                    backgroundTertiary: '#334155', // Azul gris
                    
                    // Colores de superficie
                    surface: '#1e293b',           // Azul oscuro
                    surfaceElevated: '#334155',   // Azul gris
                    
                    // Colores de texto
                    textPrimary: '#f8fafc',       // Blanco casi puro
                    textSecondary: '#cbd5e1',     // Gris claro
                    textMuted: '#94a3b8',         // Gris medio
                    textInverse: '#0f172a',       // Azul oscuro
                    
                    // Colores de estado
                    success: '#10b981',           // Verde brillante
                    warning: '#f59e0b',           // Naranja brillante
                    danger: '#ef4444',            // Rojo brillante
                    info: '#06b6d4',              // Cyan
                    
                    // Colores de borde
                    border: '#475569',            // Gris oscuro
                    borderLight: '#64748b',       // Gris medio
                    borderDark: '#334155',        // Azul gris
                    
                    // Colores específicos Forex
                    profit: '#10b981',            // Verde brillante
                    loss: '#ef4444',              // Rojo brillante
                    neutral: '#94a3b8',           // Gris claro
                    
                    // Sombras
                    shadowSm: '0 1px 2px 0 rgba(0, 0, 0, 0.3)',
                    shadowMd: '0 4px 6px -1px rgba(0, 0, 0, 0.4)',
                    shadowLg: '0 10px 15px -3px rgba(0, 0, 0, 0.4)',
                    shadowXl: '0 20px 25px -5px rgba(0, 0, 0, 0.4)'
                }
            }
        };
        
        this.init();
    }

    init() {
        this.applyTheme(this.currentTheme);
        this.createThemeToggle();
        this.bindEvents();
    }

    getStoredTheme() {
        return localStorage.getItem('crm-theme');
    }

    setStoredTheme(theme) {
        localStorage.setItem('crm-theme', theme);
    }

    applyTheme(themeName) {
        const theme = this.themes[themeName];
        if (!theme) return;

        const root = document.documentElement;
        
        // Aplicar variables CSS
        Object.entries(theme.colors).forEach(([key, value]) => {
            root.style.setProperty(`--color-${this.camelToKebab(key)}`, value);
        });

        // Actualizar clase del body
        document.body.className = document.body.className.replace(/theme-\w+/g, '');
        document.body.classList.add(`theme-${themeName}`);

        // Actualizar meta theme-color para móviles
        let metaThemeColor = document.querySelector('meta[name="theme-color"]');
        if (!metaThemeColor) {
            metaThemeColor = document.createElement('meta');
            metaThemeColor.name = 'theme-color';
            document.head.appendChild(metaThemeColor);
        }
        metaThemeColor.content = theme.colors.primary;

        this.currentTheme = themeName;
        this.setStoredTheme(themeName);
        this.updateToggleButton();
    }

    createThemeToggle() {
        // Crear botón de toggle si no existe
        if (document.getElementById('themeToggle')) return;

        const toggle = document.createElement('button');
        toggle.id = 'themeToggle';
        toggle.className = 'theme-toggle btn btn-outline-secondary';
        toggle.innerHTML = `
            <i class="${this.themes[this.currentTheme].icon}"></i>
            <span class="ms-2 d-none d-md-inline">${this.themes[this.currentTheme].name}</span>
        `;
        toggle.title = `Cambiar a tema ${this.currentTheme === 'light' ? 'oscuro' : 'claro'}`;

        // Buscar un lugar apropiado para insertar el botón
        const navbar = document.querySelector('.navbar');
        const header = document.querySelector('.header');
        const headerRight = document.querySelector('.header-right');
        const topBar = document.querySelector('.top-bar');
        
        if (headerRight) {
            // Insertar antes del botón de notificaciones
            const notificationBtn = headerRight.querySelector('.notification-btn');
            if (notificationBtn) {
                headerRight.insertBefore(toggle, notificationBtn);
            } else {
                headerRight.appendChild(toggle);
            }
        } else if (navbar) {
            const navbarNav = navbar.querySelector('.navbar-nav') || navbar.querySelector('.navbar-collapse');
            if (navbarNav) {
                const li = document.createElement('li');
                li.className = 'nav-item';
                li.appendChild(toggle);
                navbarNav.appendChild(li);
            } else {
                navbar.appendChild(toggle);
            }
        } else if (header) {
            header.appendChild(toggle);
        } else if (topBar) {
            topBar.appendChild(toggle);
        } else {
            // Fallback: agregar al body
            document.body.appendChild(toggle);
            toggle.style.position = 'fixed';
            toggle.style.top = '20px';
            toggle.style.right = '20px';
            toggle.style.zIndex = '9999';
        }
    }

    updateToggleButton() {
        const toggle = document.getElementById('themeToggle');
        if (!toggle) return;

        const theme = this.themes[this.currentTheme];
        toggle.innerHTML = `
            <i class="${theme.icon}"></i>
            <span class="ms-2 d-none d-md-inline">${theme.name}</span>
        `;
        toggle.title = `Cambiar a tema ${this.currentTheme === 'light' ? 'oscuro' : 'claro'}`;
    }

    bindEvents() {
        document.addEventListener('click', (e) => {
            if (e.target.closest('#themeToggle')) {
                e.preventDefault();
                this.toggleTheme();
            }
        });

        // Escuchar cambios de tema desde otros tabs
        window.addEventListener('storage', (e) => {
            if (e.key === 'crm-theme' && e.newValue !== this.currentTheme) {
                this.applyTheme(e.newValue);
            }
        });
    }

    toggleTheme() {
        const newTheme = this.currentTheme === 'light' ? 'dark' : 'light';
        this.applyTheme(newTheme);
        
        // Animación suave
        document.body.style.transition = 'background-color 0.3s ease, color 0.3s ease';
        setTimeout(() => {
            document.body.style.transition = '';
        }, 300);
    }

    getCurrentTheme() {
        return this.currentTheme;
    }

    getThemeColors() {
        return this.themes[this.currentTheme].colors;
    }

    camelToKebab(str) {
        return str.replace(/([a-z0-9]|(?=[A-Z]))([A-Z])/g, '$1-$2').toLowerCase();
    }

    // Métodos para componentes específicos
    getCardClasses() {
        return this.currentTheme === 'dark' 
            ? 'bg-dark text-light border-secondary' 
            : 'bg-white text-dark border-light';
    }

    getButtonClasses(variant = 'primary') {
        const baseClasses = 'btn';
        const variantClasses = {
            primary: this.currentTheme === 'dark' ? 'btn-primary' : 'btn-primary',
            secondary: this.currentTheme === 'dark' ? 'btn-outline-light' : 'btn-outline-dark',
            success: 'btn-success',
            warning: 'btn-warning',
            danger: 'btn-danger',
            info: 'btn-info'
        };
        
        return `${baseClasses} ${variantClasses[variant] || variantClasses.primary}`;
    }

    getTableClasses() {
        return this.currentTheme === 'dark' 
            ? 'table table-dark table-hover' 
            : 'table table-light table-hover';
    }
}

// Inicializar el gestor de temas cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    window.ThemeManager = new ThemeManager();
});

// Exportar para uso en módulos
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ThemeManager;
}