# 🚀 IATRADE CRM - Guía de Despliegue en Producción

## 📋 Tabla de Contenidos

1. [Requisitos del Sistema](#requisitos-del-sistema)
2. [Instalación Rápida](#instalación-rápida)
3. [Configuración Manual](#configuración-manual)
4. [Despliegue con Docker](#despliegue-con-docker)
5. [Configuración de Servidor Web](#configuración-de-servidor-web)
6. [Seguridad](#seguridad)
7. [Monitoreo y Mantenimiento](#monitoreo-y-mantenimiento)
8. [Troubleshooting](#troubleshooting)

---

## 🔧 Requisitos del Sistema

### Mínimos
- **PHP**: 8.1 o superior
- **MySQL**: 8.0 o superior
- **Node.js**: 18.x o superior
- **Composer**: 2.x
- **Servidor Web**: Apache 2.4+ o Nginx 1.18+
- **RAM**: 2GB mínimo, 4GB recomendado
- **Almacenamiento**: 10GB mínimo

### Recomendados para Producción
- **CPU**: 4 cores
- **RAM**: 8GB
- **Almacenamiento**: SSD 50GB
- **SSL**: Certificado válido
- **CDN**: Para assets estáticos
- **Backup**: Sistema automatizado

---

## ⚡ Instalación Rápida

### 1. Setup Wizard (Recomendado)

```bash
# 1. Clonar o subir el proyecto
git clone <repository-url> iatrade-crm
cd iatrade-crm

# 2. Acceder al setup wizard
http://tu-dominio.com/deploy/setup_wizard.php
```

El setup wizard te guiará a través de:
- ✅ Verificación de requisitos del sistema
- ✅ Configuración de base de datos
- ✅ Instalación de dependencias PHP y Node.js
- ✅ Build del frontend
- ✅ Configuración de seguridad
- ✅ Creación de usuario administrador

### 2. Instalación Manual Rápida

```bash
# 1. Instalar dependencias PHP
composer install --no-dev --optimize-autoloader

# 2. Configurar entorno
cp .env.example .env
# Editar .env con tus configuraciones

# 3. Instalar dependencias frontend
cd frontend
npm ci --only=production
npm run build
cd ..

# 4. Configurar base de datos
php database/install.php

# 5. Configurar permisos
chmod -R 755 .
chmod -R 777 storage logs
```

---

## 🔧 Configuración Manual

### 1. Configuración de Base de Datos

```sql
-- Crear base de datos
CREATE DATABASE iatrade_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Crear usuario
CREATE USER 'iatrade_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON iatrade_crm.* TO 'iatrade_user'@'localhost';
FLUSH PRIVILEGES;
```

### 2. Configuración del Archivo .env

```env
# Base de datos
DB_HOST=localhost
DB_NAME=iatrade_crm
DB_USER=iatrade_user
DB_PASSWORD=secure_password

# Aplicación
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio.com

# Seguridad
JWT_SECRET=tu_jwt_secret_muy_seguro
ENCRYPTION_KEY=tu_clave_de_encriptacion

# Email (opcional)
MAIL_HOST=smtp.tu-proveedor.com
MAIL_PORT=587
MAIL_USERNAME=tu_email
MAIL_PASSWORD=tu_password
```

### 3. Migraciones de Base de Datos

```bash
# Ejecutar migraciones
php run_migration.php

# O manualmente
mysql -u iatrade_user -p iatrade_crm < database/migrations/001_create_base_tables.sql
mysql -u iatrade_user -p iatrade_crm < database/migrations/002_create_desks_table.sql
# ... continuar con todas las migraciones
```

---

## 🐳 Despliegue con Docker

### 1. Configuración Rápida

```bash
# 1. Configurar variables de entorno
cp .env.example .env.docker
# Editar .env.docker

# 2. Construir y ejecutar
docker-compose -f docker-compose.prod.yml up -d

# 3. Verificar servicios
docker-compose -f docker-compose.prod.yml ps
```

### 2. Servicios Incluidos

- **nginx**: Servidor web con SSL
- **php-fpm**: Procesador PHP optimizado
- **mysql**: Base de datos con configuración de producción
- **redis**: Cache y sesiones
- **certbot**: Certificados SSL automáticos
- **prometheus**: Monitoreo
- **grafana**: Dashboards

### 3. Comandos Útiles

```bash
# Ver logs
docker-compose -f docker-compose.prod.yml logs -f nginx
docker-compose -f docker-compose.prod.yml logs -f php-fpm

# Backup de base de datos
docker-compose -f docker-compose.prod.yml exec mysql mysqldump -u root -p iatrade_crm > backup.sql

# Acceder al contenedor PHP
docker-compose -f docker-compose.prod.yml exec php-fpm sh
```

---

## 🌐 Configuración de Servidor Web

### Apache (.htaccess)

El archivo `.htaccess` ya está incluido en la raíz del proyecto con:
- ✅ Redirección HTTPS forzada
- ✅ Headers de seguridad
- ✅ Protección de archivos sensibles
- ✅ Optimizaciones de cache
- ✅ Configuración CORS

### Nginx

```bash
# 1. Copiar configuración
sudo cp nginx.conf /etc/nginx/sites-available/iatrade-crm
sudo ln -s /etc/nginx/sites-available/iatrade-crm /etc/nginx/sites-enabled/

# 2. Verificar configuración
sudo nginx -t

# 3. Recargar Nginx
sudo systemctl reload nginx
```

### SSL con Let's Encrypt

```bash
# 1. Instalar Certbot
sudo apt install certbot python3-certbot-nginx

# 2. Obtener certificado
sudo certbot --nginx -d tu-dominio.com -d www.tu-dominio.com

# 3. Renovación automática
sudo crontab -e
# Agregar: 0 12 * * * /usr/bin/certbot renew --quiet
```

---

## 🔒 Seguridad

### 1. Configuraciones Esenciales

- ✅ **HTTPS**: Forzado en toda la aplicación
- ✅ **Headers de Seguridad**: CSP, HSTS, X-Frame-Options
- ✅ **Protección de Archivos**: .env, config, logs
- ✅ **Validación de Entrada**: Sanitización de datos
- ✅ **Autenticación JWT**: Tokens seguros
- ✅ **Rate Limiting**: Protección contra ataques

### 2. Checklist de Seguridad

```bash
# Verificar permisos
find . -type f -name "*.php" -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
chmod -R 777 storage logs

# Verificar archivos sensibles
ls -la .env config/ database/

# Verificar configuración PHP
php -i | grep -E "(expose_php|allow_url_fopen|display_errors)"
```

### 3. Firewall (UFW)

```bash
# Configurar firewall básico
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow ssh
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

---

## 📊 Monitoreo y Mantenimiento

### 1. Logs Importantes

```bash
# Logs de aplicación
tail -f logs/app.log
tail -f logs/error.log

# Logs de servidor web
tail -f /var/log/nginx/access.log
tail -f /var/log/nginx/error.log

# Logs de PHP
tail -f /var/log/php8.1-fpm.log
```

### 2. Backup Automatizado

```bash
# Script de backup (agregar a cron)
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups"

# Backup de base de datos
mysqldump -u iatrade_user -p iatrade_crm > $BACKUP_DIR/db_$DATE.sql

# Backup de archivos
tar -czf $BACKUP_DIR/files_$DATE.tar.gz /var/www/html/iatrade-crm

# Limpiar backups antiguos (más de 30 días)
find $BACKUP_DIR -name "*.sql" -mtime +30 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +30 -delete
```

### 3. Cron Jobs

```bash
# Editar crontab
crontab -e

# Agregar tareas
# Backup diario a las 2 AM
0 2 * * * /path/to/backup.sh

# Limpieza de logs cada semana
0 0 * * 0 find /var/www/html/iatrade-crm/logs -name "*.log" -mtime +7 -delete

# Optimización de base de datos mensual
0 3 1 * * mysqlcheck -o -u iatrade_user -p iatrade_crm
```

---

## 🔧 Troubleshooting

### Problemas Comunes

#### 1. Error 500 - Internal Server Error

```bash
# Verificar logs
tail -f logs/error.log
tail -f /var/log/nginx/error.log

# Verificar permisos
ls -la storage/ logs/

# Verificar configuración PHP
php -m | grep -E "(pdo|mysql|gd)"
```

#### 2. Frontend no carga

```bash
# Verificar build del frontend
cd frontend
npm run build
ls -la dist/

# Verificar configuración del servidor web
curl -I http://tu-dominio.com/frontend/dist/index.html
```

#### 3. Base de datos no conecta

```bash
# Verificar conexión
mysql -u iatrade_user -p -h localhost iatrade_crm

# Verificar configuración
cat .env | grep DB_

# Verificar logs de MySQL
tail -f /var/log/mysql/error.log
```

#### 4. Problemas de SSL

```bash
# Verificar certificado
openssl x509 -in /etc/ssl/certs/tu-dominio.crt -text -noout

# Renovar certificado
sudo certbot renew --dry-run

# Verificar configuración SSL
curl -I https://tu-dominio.com
```

### Comandos de Diagnóstico

```bash
# Estado del sistema
systemctl status nginx php8.1-fpm mysql

# Uso de recursos
htop
df -h
free -h

# Conexiones de red
netstat -tulpn | grep -E "(80|443|3306)"

# Procesos PHP
ps aux | grep php-fpm
```

---

## 📞 Soporte

### Información del Sistema

```bash
# Generar reporte del sistema
echo "=== IATRADE CRM System Report ===" > system_report.txt
echo "Date: $(date)" >> system_report.txt
echo "PHP Version: $(php -v | head -1)" >> system_report.txt
echo "MySQL Version: $(mysql --version)" >> system_report.txt
echo "Nginx Version: $(nginx -v 2>&1)" >> system_report.txt
echo "Disk Usage:" >> system_report.txt
df -h >> system_report.txt
echo "Memory Usage:" >> system_report.txt
free -h >> system_report.txt
```

### Contacto

- **Documentación**: [Wiki del proyecto]
- **Issues**: [GitHub Issues]
- **Email**: support@iatrade-crm.com

---

## 📝 Notas Adicionales

### Optimizaciones de Rendimiento

1. **OPcache**: Habilitado por defecto
2. **Gzip**: Configurado en servidor web
3. **Cache de Assets**: Headers de cache configurados
4. **CDN**: Recomendado para assets estáticos
5. **Database**: Índices optimizados

### Escalabilidad

1. **Load Balancer**: Nginx como proxy reverso
2. **Database**: Master-Slave replication
3. **Cache**: Redis para sesiones y cache
4. **Storage**: NFS o S3 para archivos compartidos

---

*Última actualización: $(date)*