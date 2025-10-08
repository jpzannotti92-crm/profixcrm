# 🚀 GUÍA DE IMPLEMENTACIÓN - PROFIXCRM V8

## 📋 REQUISITOS PREVIOS DEL SERVIDOR

### **Requisitos Mínimos:**
- **PHP**: 8.0.0 - 8.3.99
- **MySQL**: 5.7+ o MariaDB 10.2+
- **Apache**: 2.4+ con mod_rewrite o Nginx
- **Espacio en disco**: 200MB mínimo
- **Memoria**: 256MB mínimo para PHP

### **Extensiones PHP Requeridas:**
```
pdo, pdo_mysql, json, mbstring, openssl, curl, gd, 
fileinfo, tokenizer, xml, ctype, session, zip, zlib, bcmath, intl
```

### **Extensiones PHP Recomendadas:**
```
opcache, redis, memcached, apcu, xdebug
```

---

## 📁 ESTRUCTURA DE ARCHIVOS PARA SUBIR

### **1. Archivos en la Raíz (public_html/)**
```
index.php
.htaccess
.env.production
composer.json
composer.lock
validate_v8.php
deploy_v8.php
nginx.conf (opcional)
```

### **2. Carpetas a Subir**
```
api/
config/
src/
public/
views/
vendor/
storage/
logs/
temp/
uploads/
```

### **3. Carpetas de Despliegue (deploy/)**
```
deploy/
├── deploy_system.php (sistema de despliegue)
├── logs/ (logs del despliegue)
├── storage/ (archivos temporales)
└── releases/ (paquetes de actualización)
```

---

## 🔧 CONFIGURACIÓN PASO A PASO

### **PASO 1: Subir Archivos**
1. **Comprimir localmente** los archivos en partes de 50MB máximo
2. **Subir por FTP/cPanel** a public_html/
3. **Descomprimir** en el servidor
4. **Verificar permisos** (755 para carpetas, 644 para archivos)

### **PASO 2: Configurar Base de Datos**
1. **Crear base de datos** en cPanel
2. **Crear usuario** con todos los privilegios
3. **Anotar credenciales** (host, usuario, contraseña, nombre BD)

### **PASO 3: Configurar .env.production**
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tudominio.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=tu_base_de_datos
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_contraseña

# Seguridad
JWT_SECRET=generar_con_jwt_secret_generator
ENCRYPTION_KEY=generar_clave_aleatoria_32_caracteres

# Email
MAIL_HOST=smtp.tudominio.com
MAIL_PORT=587
MAIL_USERNAME=tu_email
MAIL_PASSWORD=tu_contraseña_email

# Redis/Cache (opcional)
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

### **PASO 4: Ejecutar Sistema de Despliegue**
Acceder a: `https://tudominio.com/deploy_system.php`

---

## 🛡️ SEGURIDAD EN PRODUCCIÓN

### **1. Configuración .htaccess Principal**
```apache
# Proteger archivos sensibles
<FilesMatch "\.(env|json|lock|md|yml|yaml)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Proteger carpetas sensibles
RedirectMatch 403 ^/?(\.git|vendor|src|config|storage|logs|temp|backups)/.*$

# Compresión Gzip
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json
</IfModule>

# Seguridad de headers
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>

# Desactivar server signature
ServerTokens Prod
ServerSignature Off
```

### **2. Proteger Carpeta deploy/**
```apache
# En deploy/.htaccess
Order deny,allow
Deny from all
```

### **3. Configuración PHP (php.ini)**
```ini
; Seguridad
display_errors = Off
expose_php = Off
allow_url_fopen = Off
allow_url_include = Off

; Rendimiento
memory_limit = 256M
max_execution_time = 300
max_input_vars = 3000
upload_max_filesize = 10M
post_max_size = 10M

; Sesiones
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1
```

---

## 🔍 VERIFICACIÓN POST-IMPLEMENTACIÓN

### **1. Ejecutar Validaciones**
```bash
# Acceder a validaciones
https://tudominio.com/validate_v8.php
https://tudominio.com/deploy_system.php (modo verificación)
```

### **2. Verificar Funcionalidades**
- ✅ Login de administrador
- ✅ API endpoints
- ✅ Dashboard principal
- ✅ Gestión de leads
- ✅ Gestión de usuarios
- ✅ Reportes y exportaciones
- ✅ Envío de emails

### **3. Monitoreo Inicial**
- Revisar logs de errores
- Verificar consumo de recursos
- Probar respaldo automático
- Validar certificado SSL

---

## 🚨 SOLUCIÓN DE PROBLEMAS COMUNES

### **Error 500 - Internal Server Error**
1. Verificar logs en `logs/errors/`
2. Revisar permisos de archivos/carpetas
3. Verificar PHP version y extensiones
4. Comprobar .htaccess configuration

### **Error de Base de Datos**
1. Verificar credenciales en .env
2. Comprobar conectividad MySQL
3. Revisar privilegios del usuario
4. Verificar charset/collation

### **Error 403 - Forbidden**
1. Revisar permisos de carpetas (755)
2. Verificar .htaccess configuration
3. Comprobar propietario de archivos

---

## 📞 SOPORTE Y MANTENIMIENTO

### **Backups Automáticos**
- Configurar cron job para backups diarios
- Guardar backups en ubicación segura
- Probar restauración periódicamente

### **Actualizaciones**
- Usar sistema de despliegue para updates
- Verificar compatibilidad antes de actualizar
- Mantener backups antes de cambios mayores

### **Monitoreo**
- Revisar logs semanalmente
- Monitorear espacio en disco
- Verificar actualizaciones de seguridad

---

## ⚡ RESUMEN RÁPIDO

1. **Subir archivos** a public_html/
2. **Configurar .env** con credenciales reales
3. **Ejecutar deploy_system.php** para instalación
4. **Verificar con validate_v8.php**
5. **Configurar seguridad y backups**
6. **Monitorear logs y rendimiento**

**🔗 Acceso al sistema de despliegue:**
```
https://tudominio.com/deploy_system.php
```

**📧 Soporte:** Verifica los logs en `deploy/logs/` si tienes problemas.