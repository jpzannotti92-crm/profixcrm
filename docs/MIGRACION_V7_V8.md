# Guía de Migración de ProfixCRM v7 a v8

## Resumen de Cambios

La versión 8 de ProfixCRM introduce mejoras significativas en seguridad, rendimiento y funcionalidad. Esta guía te ayudará a migrar tu instalación existente de v7 a v8 de manera segura.

## 📋 Requisitos Previos

- PHP 8.0 o superior (recomendado PHP 8.1+)
- MySQL 5.7+ o MariaDB 10.2+
- Composer instalado
- Acceso SSH/FTP al servidor
- Copia de seguridad completa del sistema

## 🔒 Paso 1: Crear Respaldo Completo

### Respaldar Archivos
```bash
# Crear respaldo de archivos
tar -czf profix_v7_backup_$(date +%Y%m%d).tar.gz /ruta/a/profixcrm/

# O si usas FTP, descarga todos los archivos
```

### Respaldar Base de Datos
```bash
# Crear respaldo de base de datos
mysqldump -u usuario -p profixdb > profix_v7_db_$(date +%Y%m%d).sql
```

### Verificar Respaldos
- [ ] Archivos respaldados exitosamente
- [ ] Base de datos respaldada exitosamente
- [ ] Respaldos almacenados en ubicación segura

## 🔄 Paso 2: Preparar el Sistema

### Verificar Requisitos del Sistema
```bash
# Verificar versión de PHP
php -v

# Verificar extensiones PHP requeridas
php -m | grep -E "(pdo|mysqli|curl|gd|mbstring|openssl)"

# Verificar versión de MySQL
mysql -V
```

### Actualizar Composer (si es necesario)
```bash
# Actualizar Composer globalmente
composer self-update

# Verificar versión
composer --version
```

## 📦 Paso 3: Descargar e Instalar v8

### Opción A: Instalación Automática (Recomendada)
```bash
# Descargar el script de despliegue
curl -O https://raw.githubusercontent.com/tu-repo/profixcrm/main/deploy_v8.php

# Ejecutar despliegue en modo desarrollo (dry-run)
php deploy_v8.php development --dry-run

# Si todo está bien, ejecutar despliegue real
php deploy_v8.php production
```

### Opción B: Instalación Manual
```bash
# Crear directorio temporal
mkdir /tmp/profix_v8_install
cd /tmp/profix_v8_install

# Clonar repositorio (o descargar ZIP)
git clone https://github.com/tu-repo/profixcrm.git .

# Instalar dependencias
composer install --no-dev --optimize-autoloader

# Copiar archivos al directorio de producción
cp -r * /ruta/a/profixcrm/
```

## ⚙️ Paso 4: Configurar v8

### 1. Actualizar Archivo de Configuración
El archivo `config/v8_config.php` reemplaza al antiguo `config.php`. Copia tus configuraciones importantes:

```php
// config/v8_config.php
return [
    'app' => [
        'name' => 'ProfixCRM',
        'version' => '8.0.0',
        'environment' => 'production', // development, staging, production
        'debug' => false,
        'timezone' => 'America/Mexico_City',
        'locale' => 'es_MX',
        'url' => 'https://tudominio.com',
        'maintenance' => false,
    ],
    
    'database' => [
        'default' => 'mysql',
        'connections' => [
            'mysql' => [
                'driver' => 'mysql',
                'host' => 'localhost',
                'port' => 3306,
                'database' => 'profixdb',
                'username' => 'usuario',
                'password' => 'contraseña',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
            ],
        ],
    ],
    
    'security' => [
        'encryption_key' => 'tu-clave-de-encriptacion-32-caracteres',
        'jwt_secret' => 'tu-secreto-jwt-aqui',
        'session_lifetime' => 120,
        'session_encrypt' => true,
        'csrf_protection' => true,
        'rate_limit' => 1000,
    ],
    
    'redirects' => [
        'mode' => 'smart', // none, smart, strict
        'login_required' => true,
        'maintenance_bypass' => ['127.0.0.1', '192.168.1.1'],
        'development_ips' => ['127.0.0.1', '::1'],
    ],
    
    'logging' => [
        'enabled' => true,
        'level' => 'info', // debug, info, warning, error
        'channels' => ['file', 'database'],
        'max_files' => 30,
        'max_size' => 10485760, // 10MB
    ],
    
    'api' => [
        'enabled' => true,
        'rate_limit' => 1000,
        'cors_enabled' => true,
        'cors_origins' => ['*'],
    ],
];
```

### 2. Configurar Variables de Entorno (Opcional)
Crea un archivo `.env` para configuraciones sensibles:

```env
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:tu-clave-secreta-aqui

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=profixdb
DB_USERNAME=usuario
DB_PASSWORD=contraseña

JWT_SECRET=tu-secreto-jwt-aqui
ENCRYPTION_KEY=tu-clave-de-encriptacion-32-caracteres
```

### 3. Actualizar .htaccess
El archivo `.htaccess` ha sido mejorado para v8:

```apache
# .htaccess para ProfixCRM v8
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    
    # Redirigir a HTTPS (si es necesario)
    # RewriteCond %{HTTPS} off
    # RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]
    
    # Proteger archivos sensibles
    RewriteRule ^(config|logs|temp|vendor)/ - [F,L]
    RewriteRule \.(ini|log|sh|sql)$ - [F,L]
    
    # Manejar redirecciones V8
    RewriteRule ^v8/validate/?$ validate_v8.php [L]
    RewriteRule ^v8/deploy/?$ deploy_v8.php [L]
    
    # Redirigir a index.php si no existe el archivo
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php [QSA,L]
</IfModule>

# Seguridad
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "DENY"
    Header set X-XSS-Protection "1; mode=block"
    Header set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>

# Compresión
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json
</IfModule>

# Cache
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg "access plus 1 month"
    ExpiresByType image/jpeg "access plus 1 month"
    ExpiresByType image/gif "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType text/css "access plus 1 week"
    ExpiresByType application/javascript "access plus 1 week"
</IfModule>
```

## 🗄️ Paso 5: Actualizar Base de Datos

### Ejecutar Script de Migración
```bash
# Ejecutar script de migración (si existe)
php scripts/migrate_v7_to_v8.php
```

### Cambios de Base de Datos Comunes
Las siguientes tablas/campos pueden necesitar actualización:

```sql
-- Ejemplo de cambios típicos (ajustar según tu versión)
-- Agregar campo de última validación
ALTER TABLE users ADD COLUMN last_validation datetime DEFAULT NULL;

-- Agregar campo de versión de API
ALTER TABLE api_tokens ADD COLUMN version VARCHAR(10) DEFAULT 'v7';

-- Actualizar configuraciones
UPDATE settings SET value = '8.0.0' WHERE key = 'version';
```

## 🧪 Paso 6: Validar la Instalación

### Ejecutar Validación Completa
```bash
# Ejecutar validación V8
php validate_v8.php full

# Verificar logs
tail -f logs/v8/validation_*.log
```

### Verificar Funcionalidades
- [ ] Acceso al panel de administración
- [ ] Funcionamiento de redirecciones
- [ ] Sistema de autenticación
- [ ] API (si está habilitada)
- [ ] Sistema de logs
- [ ] Funciones críticas del negocio

## 🚀 Paso 7: Configuración Post-Migración

### 1. Limpiar Caché
```bash
# Limpiar caché de Composer
composer clear-cache

# Limpiar archivos temporales
rm -rf temp/cache/*
rm -rf temp/sessions/*
rm -rf temp/logs/*
```

### 2. Configurar Cron Jobs (si aplica)
```bash
# Agregar a crontab
crontab -e

# Ejemplo de tareas programadas
# Limpiar logs antiguos cada día a las 2 AM
0 2 * * * php /ruta/a/profixcrm/scripts/cleanup_logs.php

# Rotar logs cada semana
0 0 * * 0 php /ruta/a/profixcrm/scripts/rotate_logs.php
```

### 3. Configurar Monitoreo
```bash
# Verificar que los logs se estén generando
ls -la logs/v8/

# Verificar últimas entradas de log
tail -n 50 logs/v8/system.log
```

## 📊 Paso 8: Verificación Final

### Checklist de Verificación
- [ ] Todos los usuarios pueden iniciar sesión
- [ ] Las redirecciones funcionan correctamente
- [ ] El sistema de logs está activo
- [ ] La API responde correctamente
- [ ] Los archivos críticos están protegidos
- [ ] No hay errores en los logs
- [ ] El rendimiento es aceptable
- [ ] Las copias de seguridad funcionan

### Comandos de Verificación
```bash
# Verificar versión instalada
php -r "require 'config/v8_config.php'; echo 'V8 Instalada';"

# Verificar logs recientes
ls -la logs/v8/ | head -10

# Verificar espacio en disco
df -h

# Verificar errores PHP
grep -i error logs/v8/system.log | tail -20
```

## 🔄 Rollback (Si es Necesario)

Si necesitas volver a v7:

```bash
# 1. Restaurar base de datos
mysql -u usuario -p profixdb < profix_v7_db_YYYY-MM-DD.sql

# 2. Restaurar archivos
cd /ruta/a/profixcrm/
tar -xzf /ruta/a/respaldo/profix_v7_backup_YYYY-MM-DD.tar.gz

# 3. Verificar versión
php -r "require 'config.php'; echo 'V7 Restaurada';"
```

## 📞 Soporte y Ayuda

Si encuentras problemas durante la migración:

1. **Verifica los logs**: `logs/v8/`
2. **Ejecuta validación**: `php validate_v8.php full`
3. **Consulta la documentación**: `docs/`
4. **Contacta soporte**: [información de contacto]

## 📈 Beneficios de la Migración a V8

- ✅ **Mejor Seguridad**: Sistema de redirecciones inteligentes y protección mejorada
- ✅ **Rendimiento**: Optimizaciones en carga y procesamiento
- ✅ **Logs Mejorados**: Sistema de logging estructurado y rotación automática
- ✅ **API Mejorada**: Límites de tasa y CORS configurables
- ✅ **Mantenimiento**: Modo de mantenimiento con IPs de bypass
- ✅ **Validación**: Sistema de validación completo del sistema

## ⏱️ Tiempo Estimado de Migración

- **Migración automática**: 30-60 minutos
- **Migración manual**: 2-4 horas
- **Verificación completa**: 30 minutos
- **Rollback (si necesario)**: 15-30 minutos

---

**Nota**: Esta guía es para migraciones estándar. Las instalaciones personalizadas pueden requerir pasos adicionales.