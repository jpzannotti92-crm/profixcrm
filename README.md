# iaTrade CRM - Sistema Profesional de Gestión de Leads Forex/CFD

## 🚀 Descripción

iaTrade CRM es un sistema completo y profesional de gestión de leads diseñado específicamente para empresas de Forex y CFD. Ofrece una solución integral para la gestión de clientes potenciales, seguimiento de conversiones, análisis de KPIs y administración de equipos de ventas.

## ✨ Características Principales

### 📊 Dashboard Avanzado
- KPIs en tiempo real con métricas de Forex/CFD
- Gráficos interactivos de conversiones y rendimiento
- Vista general del estado de leads y equipos
- Alertas y notificaciones inteligentes

### 👥 Gestión Completa de Leads
- **Estados de Lead**: Nuevo, Contactado, Interesado, Demo, FTD, Cliente, Perdido
- **Prioridades**: Baja, Media, Alta, Urgente
- **Seguimiento Completo**: Historial de actividades, notas, documentos
- **Asignación Automática**: Reglas configurables para distribución de leads
- **Filtros Dinámicos**: Búsqueda avanzada por múltiples criterios

### 🏢 Sistema de Desks (Equipos)
- Creación y gestión de equipos de trabajo
- Asignación de roles: Manager, Senior Sales, Sales, Retention
- Objetivos y metas por desk
- Ranking de rendimiento por miembro
- Métricas específicas por equipo

### 👤 Gestión de Usuarios
- Sistema completo de roles y permisos
- Autenticación segura con JWT
- Perfiles personalizables
- Historial de actividades por usuario

### 📈 KPIs Especializados en Forex
- **Tasa de Conversión**: Porcentaje de leads que se convierten en FTD
- **FTD Promedio**: Monto promedio del primer depósito
- **Costo por Lead**: Análisis de eficiencia de campañas
- **Valor de Vida del Cliente**: LTV calculado automáticamente
- **Tasa de Retención**: Seguimiento de clientes activos
- **Métricas por Fuente**: Google Ads, Facebook, Orgánico, Referidos

### 📊 Reportes Avanzados
- Reportes de conversión por período
- Análisis de rendimiento por usuario/desk
- Reportes financieros detallados
- Análisis de fuentes de leads
- Exportación a Excel/CSV

### 🔒 Seguridad y Permisos
- Sistema granular de permisos por módulo
- Roles predefinidos: Super Admin, Admin, Manager, Sales, Retention
- Auditoría completa de acciones
- Encriptación de datos sensibles

## 🛠️ Tecnologías Utilizadas

### Backend
- **PHP 8.0+**: Lenguaje principal
- **MySQL**: Base de datos relacional
- **PDO**: Capa de abstracción de base de datos
- **JWT**: Autenticación segura
- **Composer**: Gestión de dependencias

### Frontend
- **HTML5/CSS3**: Estructura y estilos
- **Bootstrap 5**: Framework CSS responsive
- **JavaScript ES6+**: Interactividad
- **Chart.js**: Gráficos y visualizaciones
- **Font Awesome**: Iconografía

### Herramientas de Desarrollo
- **PHPUnit**: Testing unitario
- **PHPStan**: Análisis estático de código
- **PHP CodeSniffer**: Estándares de código

## 📋 Requisitos del Sistema

- **PHP**: 8.0 o superior
- **MySQL**: 5.7 o superior
- **Apache/Nginx**: Servidor web
- **Composer**: Gestión de dependencias
- **Extensiones PHP**: PDO, JSON, mbstring, OpenSSL

## 🚀 Instalación

### 1. Clonar el Repositorio
```bash
git clone https://github.com/tu-usuario/iatrade-crm.git
cd iatrade-crm
```

### 2. Instalar Dependencias
```bash
composer install
```

### 3. Configurar Base de Datos
1. Crear base de datos MySQL:
```sql
CREATE DATABASE iatrade_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Configurar archivo `.env`:
```env
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=iatrade_crm
DB_USERNAME=root
DB_PASSWORD=tu_password
```

### 4. Ejecutar Migraciones
```bash
php database/install.php
```

### 5. Configurar Servidor Web

#### Para XAMPP:
1. Copiar el proyecto a `C:\xampp\htdocs\iatrade-crm`
2. Iniciar Apache y MySQL desde el panel de XAMPP
3. Acceder a `http://localhost/iatrade-crm`

#### Para desarrollo:
```bash
php -S localhost:8000 -t public
```

## 👤 Acceso Inicial

Después de la instalación, usa estas credenciales:
- **Usuario**: admin
- **Contraseña**: admin123

⚠️ **IMPORTANTE**: Cambia la contraseña después del primer acceso.

## 📁 Estructura del Proyecto

```
iatrade-crm/
├── config/                 # Configuraciones
├── database/
│   ├── migrations/         # Scripts de base de datos
│   └── install.php        # Instalador
├── public/                # Archivos públicos
│   ├── index.php         # Punto de entrada
│   ├── app.html          # Interfaz principal
│   └── assets/           # CSS, JS, imágenes
├── src/
│   ├── Controllers/      # Controladores
│   ├── Models/          # Modelos de datos
│   ├── Core/            # Núcleo del framework
│   ├── Middleware/      # Middleware
│   └── Database/        # Conexión DB
├── tests/               # Pruebas unitarias
├── vendor/              # Dependencias
├── .env                 # Variables de entorno
├── composer.json        # Configuración Composer
└── README.md           # Este archivo
```

## 🔧 Configuración Avanzada

### Variables de Entorno
```env
# Aplicación
APP_NAME="iaTrade CRM"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio.com

# Base de Datos
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=iatrade_crm
DB_USERNAME=usuario
DB_PASSWORD=contraseña

# Email
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tu-email@gmail.com
MAIL_PASSWORD=tu-password
MAIL_ENCRYPTION=tls

# Seguridad
APP_KEY=tu-clave-secreta-de-32-caracteres
JWT_SECRET=tu-jwt-secret-key
```

### Configuración de Permisos
El sistema incluye permisos granulares:
- `leads.*`: Gestión de leads
- `users.*`: Gestión de usuarios
- `desks.*`: Gestión de desks
- `reports.*`: Acceso a reportes
- `kpis.*`: Visualización de KPIs

## 📊 Módulos del Sistema

### 1. Dashboard
- Vista general de métricas
- Gráficos de rendimiento
- Leads recientes
- Alertas importantes

### 2. Gestión de Leads
- CRUD completo de leads
- Seguimiento de actividades
- Gestión de documentos
- Asignación automática/manual

### 3. Gestión de Desks
- Creación de equipos
- Asignación de miembros
- Configuración de objetivos
- Análisis de rendimiento

### 4. Gestión de Usuarios
- Administración de usuarios
- Roles y permisos
- Perfiles personalizables
- Auditoría de acciones

### 5. Reportes y KPIs
- Métricas en tiempo real
- Reportes personalizables
- Análisis comparativo
- Exportación de datos

## 🔍 API Endpoints

### Autenticación
```
POST /api/auth/login
POST /api/auth/logout
POST /api/auth/refresh
```

### Leads
```
GET    /api/leads
POST   /api/leads
GET    /api/leads/{id}
PUT    /api/leads/{id}
DELETE /api/leads/{id}
POST   /api/leads/{id}/assign
POST   /api/leads/{id}/status
```

### Usuarios
```
GET    /api/users
POST   /api/users
GET    /api/users/{id}
PUT    /api/users/{id}
DELETE /api/users/{id}
```

### Desks
```
GET    /api/desks
POST   /api/desks
GET    /api/desks/{id}
PUT    /api/desks/{id}
DELETE /api/desks/{id}
```

## 🧪 Testing

### Ejecutar Pruebas
```bash
composer test
```

### Análisis de Código
```bash
composer phpstan
composer cs-check
```

## 🚀 Despliegue en Producción

### 1. Configurar Servidor
- Apache/Nginx con PHP 8.0+
- MySQL 5.7+
- SSL/TLS habilitado

### 2. Optimizaciones
```bash
composer install --no-dev --optimize-autoloader
```

### 3. Configurar Cron Jobs
```bash
# Actualizar métricas diarias
0 1 * * * php /path/to/project/scripts/update-metrics.php

# Limpiar logs antiguos
0 2 * * 0 php /path/to/project/scripts/cleanup-logs.php
```

## 📞 Soporte

Para soporte técnico o consultas:
- **Email**: support@iatrade-crm.com
- **Documentación**: https://docs.iatrade-crm.com
- **Issues**: https://github.com/tu-usuario/iatrade-crm/issues

## 📄 Licencia

Este proyecto está licenciado bajo la Licencia MIT. Ver el archivo `LICENSE` para más detalles.

## 🤝 Contribuciones

Las contribuciones son bienvenidas. Por favor:
1. Fork el proyecto
2. Crea una rama para tu feature
3. Commit tus cambios
4. Push a la rama
5. Abre un Pull Request

## 📈 Roadmap

### Versión 2.0
- [ ] Integración con APIs de brokers
- [ ] Sistema de notificaciones push
- [ ] App móvil
- [ ] Inteligencia artificial para scoring de leads

### Versión 2.1
- [ ] Integración con WhatsApp Business
- [ ] Sistema de videoconferencias
- [ ] Análisis predictivo
- [ ] Multi-idioma completo

---

**iaTrade CRM** - El sistema más completo para la gestión de leads en Forex y CFD.

*Desarrollado con ❤️ para maximizar tus conversiones*