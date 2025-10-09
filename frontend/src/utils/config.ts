interface AppConfig {
  app: {
    name: string;
    version: string;
    environment: string;
  };
  urls: {
    base: string;
    api: string;
    frontend: string;
    login: string;
    dashboard: string;
  };
  features: {
    trading: boolean;
    reports: boolean;
    notifications: boolean;
  };
  limits: {
    max_upload_size: string;
    session_timeout: number;
  };
}

class ConfigManager {
  private config: AppConfig | null = null;
  private configPromise: Promise<AppConfig> | null = null;
  private maxRetries = 3;
  private retryDelay = 1000; // 1 segundo

  async getConfig(): Promise<AppConfig> {
    if (this.config) {
      return this.config;
    }

    if (this.configPromise) {
      return this.configPromise;
    }

    this.configPromise = this.fetchConfigWithRetry();
    return this.configPromise;
  }

  private async fetchConfigWithRetry(): Promise<AppConfig> {
    for (let attempt = 0; attempt <= this.maxRetries; attempt++) {
      try {
        // Construir URL del endpoint de config. Si hay VITE_API_URL, usar producción.
        const envApiBase = (import.meta as any).env?.VITE_API_URL as string | undefined
        const isLocalhost = window.location.hostname === 'localhost'
        const apiBase = envApiBase || '/api'
        const normalizedBase = typeof apiBase === 'string' ? apiBase.replace(/\/$/, '') : '/api'
        // Si estamos en localhost y no hay VITE_API_URL, usar producción directa
        const computedBase = (!envApiBase && isLocalhost) ? 'https://spin2pay.com/api' : normalizedBase
        const configUrl = `${computedBase}/config`

        const response = await fetch(configUrl, {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json',
          },
          cache: 'no-cache'
        });

        if (!response.ok) {
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const data = await response.json();
        
        if (!data.success) {
          throw new Error(data.message || 'Error en la respuesta del servidor');
        }

        // Validar estructura de datos
        if (!this.validateConfig(data.data)) {
          throw new Error('Estructura de configuración inválida');
        }

        this.config = data.data;
        
        // Log warning if using fallback
        if (data.warning) {
          console.warn('ConfigManager:', data.warning);
        }

        return this.config as AppConfig;

      } catch (error) {
        console.error(`ConfigManager: Intento ${attempt + 1} fallido:`, error);
        
        if (attempt === this.maxRetries) {
          // Último intento fallido, usar configuración de emergencia
          console.warn('ConfigManager: Usando configuración de emergencia');
          this.config = this.getEmergencyConfig();
          return this.config as AppConfig;
        }

        // Esperar antes del siguiente intento
        await this.delay(this.retryDelay * (attempt + 1));
      }
    }

    // Esto nunca debería ejecutarse, pero por seguridad
    this.config = this.getEmergencyConfig();
    return this.config as AppConfig;
  }

  private validateConfig(config: any): boolean {
    try {
      return (
        config &&
        typeof config === 'object' &&
        config.app &&
        config.urls &&
        config.features &&
        config.limits &&
        typeof config.urls.base === 'string' &&
        typeof config.urls.api === 'string' &&
        config.urls.base.length > 0 &&
        config.urls.api.length > 0
      );
    } catch {
      return false;
    }
  }

  private getEmergencyConfig(): AppConfig {
    // Si tenemos VITE_API_URL, usarlo para apuntar a producción.
    const viteApi = (import.meta as any).env?.VITE_API_URL as string | undefined;
    const baseUrl = (import.meta as any).env?.VITE_BASE_URL || window.location.origin;
    const apiUrl = viteApi || `${baseUrl}/api`;
    
    return {
      app: {
        name: 'IATrade CRM',
        version: '1.0.0',
        environment: 'production'
      },
      urls: {
        base: typeof baseUrl === 'string' ? baseUrl : window.location.origin,
        api: apiUrl,
        frontend: baseUrl,
        login: `${baseUrl}/auth/login`,
        dashboard: `${baseUrl}/dashboard`
      },
      features: {
        trading: true,
        reports: true,
        notifications: true
      },
      limits: {
        max_upload_size: '10MB',
        session_timeout: 3600
      }
    };
  }

  private delay(ms: number): Promise<void> {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

  // Métodos de conveniencia
  async getApiUrl(): Promise<string> {
    const config = await this.getConfig();
    return config.urls.api;
  }

  async getBaseUrl(): Promise<string> {
    const config = await this.getConfig();
    return config.urls.base;
  }

  async getLoginUrl(): Promise<string> {
    const config = await this.getConfig();
    return config.urls.login;
  }

  async getDashboardUrl(): Promise<string> {
    const config = await this.getConfig();
    return config.urls.dashboard;
  }

  // Método para refrescar la configuración
  async refreshConfig(): Promise<AppConfig> {
    this.config = null;
    this.configPromise = null;
    return this.getConfig();
  }

  // Método para verificar si una característica está habilitada
  async isFeatureEnabled(feature: keyof AppConfig['features']): Promise<boolean> {
    const config = await this.getConfig();
    return config.features[feature] || false;
  }
}

// Instancia singleton
export const configManager = new ConfigManager();

// Variables estáticas para Vite build time (fallback)
export const VITE_API_URL = import.meta.env.VITE_API_URL || `${window.location.origin}/api`;
export const VITE_BASE_URL = import.meta.env.VITE_BASE_URL || window.location.origin;
export type { AppConfig };