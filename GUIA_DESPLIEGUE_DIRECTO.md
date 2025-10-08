# Guía de Despliegue Directo en public_html

## Preparación para Despliegue Directo

### 1. Archivos que DEBES subir a public_html:

```
public_html/
├── index.php (copiar desde /public/index.php)
├── api/ (todo el directorio)
├── assets/ (desde /public/assets/)
├── js/ (desde /public/js/)
├── views/ (desde /public/views/)
├── src/ (todo el directorio)
├── vendor/ (todo el directorio)
├── app/ (todo el directorio)
├── config/ (todo el directorio)
├── .env (renombrar .env.production a .env)
├── .htaccess (usar configuración especial)
└── composer.json
```

### 2. Archivos que NO debes subir:
- frontend/ (ya compilado en assets/)
- logs/ (se crearán automáticamente)
- storage/cache/ (se recreará)
- .env.production (usar como .env)
- archivos de desarrollo (.md, .zip, etc.)

### 3. Configuración del .htaccess para public_html directo:

```apache
# iaTrade CRM - Configuración para public_html directo
Options -Indexes +FollowSymLinks
DirectoryIndex index.php index.html

RewriteEngine On

# Proteger archivos sensibles
<Files ~ "\.(env|log|ini|conf|sql|json)$">
    Order allow,deny
    Deny from all
</Files>

# Proteger directorios sensibles
RewriteRule ^(src|vendor|config|storage|logs)/ - [F,L]

# Cabeceras de seguridad
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>

# Configuración PHP
<IfModule mod_php.c>
    php_value upload_max_filesize 10M
    php_value post_max_size 10M
    php_value memory_limit 256M
    php_value max_execution_time 300
</IfModule>

# Reescritura de URLs para API
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^api/(.*)$ api/index.php [QSA,L]

# Compresión
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
</IfModule>

# Cache
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType image/jpg "access plus 1 month"
    ExpiresByType image/jpeg "access plus 1 month"
    ExpiresByType image/gif "access plus 1 month"
</IfModule>
```

### 4. Pasos para el despliegue:

#### Paso 1: Compilar el Frontend
```bash
cd frontend
npm run build
```

#### Paso 2: Preparar archivos
1. Copia el contenido de `frontend/dist/` a `public/assets/`
2. Renombra `.env.production` a `.env`
3. Actualiza las credenciales de base de datos en `.env`

#### Paso 3: Subir archivos
Sube estos directorios/archivos a public_html:
- `index.php` (desde public/)
- `api/`
- `assets/` (con el frontend compilado)
- `js/`
- `views/`
- `src/`
- `vendor/`
- `app/`
- `config/`
- `.env`
- `.htaccess` (con la configuración de arriba)
- `composer.json`

#### Paso 4: Configurar permisos
```bash
chmod 755 public_html/
chmod 644 public_html/.env
chmod 755 public_html/api/
chmod 755 public_html/assets/
```

#### Paso 5: Crear directorios necesarios
```bash
mkdir public_html/storage
mkdir public_html/storage/logs
mkdir public_html/storage/cache
mkdir public_html/storage/sessions
chmod 777 public_html/storage/
chmod 777 public_html/storage/logs/
chmod 777 public_html/storage/cache/
chmod 777 public_html/storage/sessions/
```

### 5. Verificaciones post-despliegue:

1. **Verificar que funciona:** `https://tudominio.com`
2. **Probar API:** `https://tudominio.com/api/permissions.php`
3. **Verificar aplicación:** `https://tudominio.com/`
4. **Comprobar logs:** Revisar que no hay errores en los logs del servidor

### 6. URLs finales:
- **Aplicación principal:** `https://tudominio.com`
- **API:** `https://tudominio.com/api/`
- **Aplicación:** `https://tudominio.com/`
- **Dashboard:** `https://tudominio.com/views/dashboard.html`

## Ventajas del despliegue directo:
✅ URLs más profesionales y limpias
✅ Mejor SEO
✅ Menos configuración de servidor
✅ Acceso directo sin redirecciones
✅ Mejor rendimiento

## Notas importantes:
- Asegúrate de que tu hosting soporte PHP 7.4+
- Verifica que las extensiones PHP necesarias estén instaladas
- Mantén backups antes del despliegue
- Prueba en un subdominio primero si es posible