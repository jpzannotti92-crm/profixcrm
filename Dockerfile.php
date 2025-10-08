# ========================================
# IATRADE CRM - Dockerfile PHP para Producción
# ========================================

FROM php:8.1-fpm-alpine

# Información del mantenedor
LABEL maintainer="IATRADE CRM Team"
LABEL description="PHP-FPM optimizado para IATRADE CRM"

# ========================================
# INSTALACIÓN DE DEPENDENCIAS DEL SISTEMA
# ========================================

RUN apk add --no-cache \
    # Dependencias básicas
    curl \
    wget \
    zip \
    unzip \
    git \
    # Dependencias para extensiones PHP
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    # Dependencias para MySQL
    mysql-client \
    # Dependencias para Redis
    redis \
    # Herramientas de sistema
    supervisor \
    cron

# ========================================
# INSTALACIÓN DE EXTENSIONES PHP
# ========================================

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        # Extensiones básicas
        pdo \
        pdo_mysql \
        mysqli \
        # Extensiones para imágenes
        gd \
        # Extensiones para archivos
        zip \
        # Extensiones para internacionalización
        intl \
        # Extensiones para strings
        mbstring \
        # Extensiones para matemáticas
        bcmath \
        # Extensiones para tiempo
        calendar \
        # Extensiones para procesos
        pcntl \
        # Extensiones para sockets
        sockets

# ========================================
# INSTALACIÓN DE EXTENSIONES PECL
# ========================================

RUN apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        redis-dev \
    && pecl install \
        redis \
        opcache \
    && docker-php-ext-enable \
        redis \
        opcache \
    && apk del .build-deps

# ========================================
# CONFIGURACIÓN DE PHP
# ========================================

# Copiar configuración personalizada de PHP
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-custom.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# Crear configuración PHP si no existe
RUN if [ ! -f /usr/local/etc/php/conf.d/99-custom.ini ]; then \
    echo "Creating default PHP configuration..." && \
    cat > /usr/local/etc/php/conf.d/99-custom.ini << 'EOF'
; ========================================
; IATRADE CRM - Configuración PHP Producción
; ========================================

; Configuración básica
memory_limit = 512M
max_execution_time = 300
max_input_time = 300
post_max_size = 10M
upload_max_filesize = 10M
max_file_uploads = 20

; Configuración de sesiones
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1
session.cookie_samesite = "Strict"

; Configuración de errores (producción)
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /var/log/php/error.log

; Configuración de OPcache
opcache.enable = 1
opcache.enable_cli = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 0
opcache.revalidate_freq = 0
opcache.save_comments = 0

; Configuración de seguridad
expose_php = Off
allow_url_fopen = Off
allow_url_include = Off

; Configuración de timezone
date.timezone = UTC
EOF
fi

# ========================================
# INSTALACIÓN DE COMPOSER
# ========================================

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer --version

# ========================================
# CONFIGURACIÓN DEL USUARIO
# ========================================

# Crear usuario para la aplicación
RUN addgroup -g 1000 -S www && \
    adduser -u 1000 -D -S -G www www

# ========================================
# CONFIGURACIÓN DE DIRECTORIOS
# ========================================

# Crear directorios necesarios
RUN mkdir -p \
    /var/www/html/iatrade-crm \
    /var/log/php \
    /var/log/supervisor \
    /etc/supervisor/conf.d

# Establecer permisos
RUN chown -R www:www /var/www/html/iatrade-crm \
    && chown -R www:www /var/log/php

# ========================================
# CONFIGURACIÓN DE SUPERVISOR
# ========================================

RUN cat > /etc/supervisor/conf.d/supervisord.conf << 'EOF'
[supervisord]
nodaemon=true
user=root
logfile=/var/log/supervisor/supervisord.log
pidfile=/var/run/supervisord.pid

[program:php-fpm]
command=php-fpm -F
stdout_logfile=/var/log/supervisor/php-fpm.log
stderr_logfile=/var/log/supervisor/php-fpm.log
autorestart=true
user=root

[program:cron]
command=crond -f
stdout_logfile=/var/log/supervisor/cron.log
stderr_logfile=/var/log/supervisor/cron.log
autorestart=true
user=root
EOF

# ========================================
# CONFIGURACIÓN DE CRON JOBS
# ========================================

RUN echo "# IATRADE CRM Cron Jobs" > /etc/crontabs/www && \
    echo "0 2 * * * /usr/local/bin/php /var/www/html/iatrade-crm/scripts/backup.php" >> /etc/crontabs/www && \
    echo "*/5 * * * * /usr/local/bin/php /var/www/html/iatrade-crm/scripts/cleanup.php" >> /etc/crontabs/www && \
    echo "0 0 * * 0 /usr/local/bin/php /var/www/html/iatrade-crm/scripts/maintenance.php" >> /etc/crontabs/www

# ========================================
# CONFIGURACIÓN FINAL
# ========================================

# Establecer directorio de trabajo
WORKDIR /var/www/html/iatrade-crm

# Copiar código de la aplicación
COPY --chown=www:www . .

# Instalar dependencias de Composer
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Establecer permisos finales
RUN chown -R www:www /var/www/html/iatrade-crm \
    && chmod -R 755 /var/www/html/iatrade-crm \
    && chmod -R 777 /var/www/html/iatrade-crm/storage \
    && chmod -R 777 /var/www/html/iatrade-crm/logs

# Exponer puerto
EXPOSE 9000

# Comando de inicio
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

# ========================================
# HEALTH CHECK
# ========================================

HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD php-fpm-healthcheck || exit 1

# Script de health check
RUN cat > /usr/local/bin/php-fpm-healthcheck << 'EOF' && chmod +x /usr/local/bin/php-fpm-healthcheck
#!/bin/sh
if [ -f /var/run/php-fpm.pid ]; then
    exit 0
else
    exit 1
fi
EOF