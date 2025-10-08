# 🚀 ACTUALIZACIÓN PROFIXCRM V8

## 📋 DESCRIPCIÓN
Este paquete contiene la actualización de ProfixCRM de V7 a V8 con mejoras significativas en:
- ✅ Sistema de autenticación mejorado
- ✅ Gestión avanzada de leads
- ✅ Sistema de permisos RBAC
- ✅ Control de acceso por escritorios
- ✅ API REST optimizada
- ✅ Interfaz de usuario renovada

## 📁 ARCHIVOS INCLUIDOS

### 🔧 CONFIGURACIÓN
- `config/v8_config.php` - Nueva configuración de V8
- `config/config.php` - Configuración actualizada
- `config/.htaccess` - Seguridad mejorada

### 🏗️ NÚCLEO DEL SISTEMA
- `src/Core/Config.php` - Gestión de configuración
- `src/Core/ErrorHandler.php` - Manejo de errores mejorado
- `src/Core/Request.php` - Procesamiento de peticiones
- `src/Core/Response.php` - Respuestas HTTP optimizadas
- `src/Core/Router.php` - Enrutamiento avanzado
- `src/Core/V8RedirectHandler.php` - Control de redirecciones V8
- `src/Core/V8Validator.php` - Validaciones mejoradas

### 🎮 CONTROLADORES
- `src/Controllers/AuthController.php` - Autenticación mejorada
- `src/Controllers/BaseController.php` - Controlador base
- `src/Controllers/DashboardController.php` - Dashboard optimizado

### 📊 MODELOS
- `src/Models/BaseModel.php` - Modelo base con mejoras
- `src/Models/DailyUserMetric.php` - Métricas de usuario
- `src/Models/Desk.php` - Gestión de escritorios
- `src/Models/DeskState.php` - Estados de escritorio
- `src/Models/Lead.php` - Modelo de leads mejorado
- `src/Models/LeadStatusHistory.php` - Historial de leads
- `src/Models/Permission.php` - Sistema de permisos
- `src/Models/Role.php` - Gestión de roles
- `src/Models/StateTransition.php` - Transiciones de estado
- `src/Models/User.php` - Modelo de usuario actualizado

### 🔒 MIDDLEWARE
- `src/Middleware/AuthMiddleware.php` - Autenticación por middleware
- `src/Middleware/CorsMiddleware.php` - Control CORS
- `src/Middleware/DeskAccessMiddleware.php` - Acceso por escritorio
- `src/Middleware/RBACMiddleware.php` - Control RBAC

### 🗄️ BASE DE DATOS
- `src/Database/Connection.php` - Conexión optimizada
- `src/Database/MySQLCompatibility.php` - Compatibilidad MySQL

### 🛠️ HELPERS
- `src/helpers.php` - Funciones auxiliares
- `src/helpers/UrlHelper.php` - Utilidades de URLs

### 🌐 API ENDPOINTS
- `api/leads.php` - Gestión de leads por API
- `api/users.php` - Gestión de usuarios por API
- `api/auth/login.php` - Login por API
- `api/dashboard.php` - Dashboard por API
- `api/desks.php` - Gestión de escritorios por API
- `api/roles.php` - Gestión de roles por API
- `api/config.php` - Configuración por API
- `api/health.php` - Estado del sistema
- `api/index.php` - Punto de entrada API

### 🎨 FRONTEND
- `public/js/app.js` - Aplicación JavaScript principal
- `public/js/modules/leads.js` - Módulo de leads
- `public/js/modules/users.js` - Módulo de usuarios
- `public/index.php` - Punto de entrada público
- `public/.htaccess` - Configuración Apache
- `public/router.php` - Enrutamiento público

### 📄 ARCHIVOS PRINCIPALES
- `index.php` - Punto de entrada principal
- `validate_v8.php` - Validador del sistema V8
- `deploy_v8.php` - Script de despliegue V8
- `.htaccess` - Configuración principal Apache

### 📦 DEPENDENCIAS
- `composer.json` - Dependencias PHP
- `composer.lock` - Versiones exactas
- `vendor/autoload.php` - Autoload de Composer

## 🔄 PROCESO DE ACTUALIZACIÓN

### PASO 1: BACKUP (OBLIGATORIO)
```bash
# Backup de base de datos
mysqldump -u usuario -p profixcrm > backup_v7_$(date +%Y%m%d).sql

# Backup de archivos
tar -czf backup_v7_files_$(date +%Y%m%d).tar.gz /ruta/a/profixcrm/
```

### PASO 2: PREPARAR AMBIENTE
```bash
# Activar modo mantenimiento (si aplica)
touch maintenance.flag

# Detener servicios si es necesario
sudo systemctl stop apache2
```

### PASO 3: APLICAR ACTUALIZACIÓN
```bash
# Extraer actualización
unzip -o profixcrm_v8_update_*.zip

# Actualizar dependencias
composer install --no-dev --optimize-autoloader

# Limpiar caché
rm -rf cache/* storage/cache/* temp/*
```

### PASO 4: CONFIGURAR
```bash
# Verificar configuración
cp config/config.php config/config.php.backup
cp config/v8_config.php config/

# Ajustar permisos
chmod -R 755 src/ api/ public/
chmod 644 *.php config/*.php
chmod 777 storage/ cache/ temp/ logs/
```

### PASO 5: VALIDAR
```bash
# Ejecutar validación
php validate_v8.php

# Verificar logs
tail -f logs/error.log
```

### PASO 6: ACTIVAR
```bash
# Desactivar modo mantenimiento
rm maintenance.flag

# Reiniciar servicios
sudo systemctl start apache2
```

## ⚠️ NOTAS IMPORTANTES

### CAMBIOS EN BASE DE DATOS
La actualización V8 puede requerir cambios en la estructura de la base de datos. Asegúrate de:
1. Ejecutar las migraciones necesarias
2. Actualizar los permisos y roles
3. Verificar la integridad de datos

### COMPATIBILIDAD
- ✅ PHP 8.0 o superior (recomendado 8.2+)
- ✅ MySQL 5.7+ o MariaDB 10.2+
- ✅ Extensiones PHP: pdo, pdo_mysql, openssl, mbstring, json, curl

### ROLLBACK
En caso de problemas, puedes restaurar:
1. Los archivos desde el backup
2. La base de datos desde el backup
3. La configuración anterior

## 🆘 SOPORTE

Si encuentras problemas durante la actualización:
1. Revisa los logs en `logs/error.log`
2. Ejecuta `php validate_v8.php` para diagnóstico
3. Consulta la documentación en `docs/`
4. Restaura desde backup si es necesario

---
**Versión del paquete:** V8  
**Fecha de creación:** " . date('Y-m-d H:i:s') . "  
**Tamaño del paquete:** 127.29 KB