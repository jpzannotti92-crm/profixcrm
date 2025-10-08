class NotificationSystem {
    constructor() {
        this.notifications = [];
        this.subscribers = new Map();
        this.eventSource = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectDelay = 1000;
        
        this.init();
    }

    init() {
        this.createNotificationContainer();
        this.setupEventSource();
        this.setupPeriodicCheck();
    }

    createNotificationContainer() {
        if (document.getElementById('notification-container')) return;

        const container = document.createElement('div');
        container.id = 'notification-container';
        container.className = 'notification-container';
        container.innerHTML = `
            <style>
                .notification-container {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    z-index: 10000;
                    max-width: 400px;
                }
                
                .notification-item {
                    background: white;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    margin-bottom: 10px;
                    padding: 16px;
                    border-left: 4px solid #007bff;
                    animation: slideIn 0.3s ease-out;
                    position: relative;
                }
                
                .notification-item.success {
                    border-left-color: #28a745;
                }
                
                .notification-item.warning {
                    border-left-color: #ffc107;
                }
                
                .notification-item.error {
                    border-left-color: #dc3545;
                }
                
                .notification-item.info {
                    border-left-color: #17a2b8;
                }
                
                .notification-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 8px;
                }
                
                .notification-title {
                    font-weight: 600;
                    color: #333;
                }
                
                .notification-time {
                    font-size: 12px;
                    color: #666;
                }
                
                .notification-body {
                    color: #555;
                    font-size: 14px;
                    line-height: 1.4;
                }
                
                .notification-actions {
                    margin-top: 12px;
                    display: flex;
                    gap: 8px;
                }
                
                .notification-btn {
                    padding: 4px 12px;
                    border: none;
                    border-radius: 4px;
                    font-size: 12px;
                    cursor: pointer;
                    transition: background-color 0.2s;
                }
                
                .notification-btn.primary {
                    background: #007bff;
                    color: white;
                }
                
                .notification-btn.secondary {
                    background: #6c757d;
                    color: white;
                }
                
                .notification-close {
                    position: absolute;
                    top: 8px;
                    right: 8px;
                    background: none;
                    border: none;
                    font-size: 18px;
                    cursor: pointer;
                    color: #999;
                    width: 24px;
                    height: 24px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                
                .notification-close:hover {
                    color: #333;
                }
                
                @keyframes slideIn {
                    from {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }
                
                @keyframes slideOut {
                    from {
                        transform: translateX(0);
                        opacity: 1;
                    }
                    to {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                }
            </style>
        `;
        
        document.body.appendChild(container);
    }

    setupEventSource() {
        if (!window.EventSource) {
            console.warn('EventSource no soportado, usando polling');
            return;
        }

        try {
            this.eventSource = new EventSource('/api/notifications-stream.php');
            
            this.eventSource.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    this.handleNotification(data);
                } catch (e) {
                    console.error('Error parsing notification:', e);
                }
            };

            this.eventSource.onerror = (error) => {
                console.error('EventSource error:', error);
                this.handleReconnect();
            };

            this.eventSource.onopen = () => {
                this.reconnectAttempts = 0;
                console.log('Notification stream connected');
            };

        } catch (error) {
            console.error('Error setting up EventSource:', error);
            this.setupPeriodicCheck();
        }
    }

    handleReconnect() {
        if (this.reconnectAttempts < this.maxReconnectAttempts) {
            this.reconnectAttempts++;
            setTimeout(() => {
                console.log(`Reconnecting... attempt ${this.reconnectAttempts}`);
                this.setupEventSource();
            }, this.reconnectDelay * this.reconnectAttempts);
        } else {
            console.warn('Max reconnection attempts reached, falling back to polling');
            this.setupPeriodicCheck();
        }
    }

    setupPeriodicCheck() {
        // Verificar notificaciones cada 30 segundos
        setInterval(() => {
            this.checkForNotifications();
        }, 30000);
    }

    async checkForNotifications() {
        try {
            const response = await fetch('/api/notifications.php', {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.success && data.notifications) {
                    data.notifications.forEach(notification => {
                        this.handleNotification(notification);
                    });
                }
            }
        } catch (error) {
            console.error('Error checking notifications:', error);
        }
    }

    handleNotification(notification) {
        // Evitar duplicados
        if (this.notifications.find(n => n.id === notification.id)) {
            return;
        }

        this.notifications.push(notification);
        this.showNotification(notification);
        this.notifySubscribers(notification);
    }

    showNotification(notification) {
        const container = document.getElementById('notification-container');
        if (!container) return;

        const notificationEl = document.createElement('div');
        notificationEl.className = `notification-item ${notification.type || 'info'}`;
        notificationEl.dataset.id = notification.id;

        const timeStr = notification.created_at ? 
            new Date(notification.created_at).toLocaleTimeString() : 
            new Date().toLocaleTimeString();

        notificationEl.innerHTML = `
            <button class="notification-close" onclick="notificationSystem.closeNotification('${notification.id}')">&times;</button>
            <div class="notification-header">
                <div class="notification-title">${notification.title || 'Notificación'}</div>
                <div class="notification-time">${timeStr}</div>
            </div>
            <div class="notification-body">${notification.message}</div>
            ${notification.actions ? this.renderActions(notification.actions, notification.id) : ''}
        `;

        container.appendChild(notificationEl);

        // Auto-cerrar después de 8 segundos (excepto errores)
        if (notification.type !== 'error') {
            setTimeout(() => {
                this.closeNotification(notification.id);
            }, 8000);
        }
    }

    renderActions(actions, notificationId) {
        if (!actions || actions.length === 0) return '';

        const actionsHtml = actions.map(action => `
            <button class="notification-btn ${action.type || 'secondary'}" 
                    onclick="notificationSystem.handleAction('${notificationId}', '${action.action}', '${action.url || ''}')">
                ${action.label}
            </button>
        `).join('');

        return `<div class="notification-actions">${actionsHtml}</div>`;
    }

    handleAction(notificationId, action, url) {
        switch (action) {
            case 'view_lead':
                if (url) window.location.href = url;
                break;
            case 'open_webtrader':
                if (url) window.open(url, '_blank');
                break;
            case 'refresh':
                window.location.reload();
                break;
            default:
                if (url) window.open(url, '_blank');
                break;
        }
        
        this.closeNotification(notificationId);
    }

    closeNotification(id) {
        const notificationEl = document.querySelector(`[data-id="${id}"]`);
        if (notificationEl) {
            notificationEl.style.animation = 'slideOut 0.3s ease-in';
            setTimeout(() => {
                notificationEl.remove();
            }, 300);
        }

        // Remover de la lista
        this.notifications = this.notifications.filter(n => n.id !== id);
    }

    // Suscribirse a tipos específicos de notificaciones
    subscribe(type, callback) {
        if (!this.subscribers.has(type)) {
            this.subscribers.set(type, []);
        }
        this.subscribers.get(type).push(callback);
    }

    // Notificar a suscriptores
    notifySubscribers(notification) {
        const typeSubscribers = this.subscribers.get(notification.type) || [];
        const allSubscribers = this.subscribers.get('*') || [];
        
        [...typeSubscribers, ...allSubscribers].forEach(callback => {
            try {
                callback(notification);
            } catch (error) {
                console.error('Error in notification subscriber:', error);
            }
        });
    }

    // Crear notificación manual
    create(title, message, type = 'info', actions = null) {
        const notification = {
            id: 'manual_' + Date.now(),
            title,
            message,
            type,
            actions,
            created_at: new Date().toISOString()
        };

        this.handleNotification(notification);
        return notification.id;
    }

    // Limpiar todas las notificaciones
    clearAll() {
        const container = document.getElementById('notification-container');
        if (container) {
            container.querySelectorAll('.notification-item').forEach(el => {
                el.style.animation = 'slideOut 0.3s ease-in';
                setTimeout(() => el.remove(), 300);
            });
        }
        this.notifications = [];
    }

    // Destruir el sistema
    destroy() {
        if (this.eventSource) {
            this.eventSource.close();
        }
        
        const container = document.getElementById('notification-container');
        if (container) {
            container.remove();
        }
        
        this.notifications = [];
        this.subscribers.clear();
    }
}

// Instancia global
window.notificationSystem = new NotificationSystem();

// Integración con el sistema App existente
if (window.App) {
    // Sobrescribir el método showNotification existente para usar el nuevo sistema
    const originalShowNotification = window.App.showNotification;
    
    window.App.showNotification = function(message, type = 'info', title = null) {
        // Usar el nuevo sistema si está disponible
        if (window.notificationSystem) {
            window.notificationSystem.create(title || 'Notificación', message, type);
        } else {
            // Fallback al sistema original
            originalShowNotification.call(this, message, type);
        }
    };
}

export default NotificationSystem;