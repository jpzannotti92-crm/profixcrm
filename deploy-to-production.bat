@echo off
echo ========================================
echo    ASISTENTE DE DESPLIEGUE - iaTrade CRM
echo ========================================
echo.

:: Configuración
set DEV_PATH=%~dp0
set PROD_PATH=%DEV_PATH%..\iatrade-crm-production
set LOG_FILE=%DEV_PATH%logs\deployment.log
set TIMESTAMP=%date:~-4,4%-%date:~-10,2%-%date:~-7,2%_%time:~0,2%-%time:~3,2%-%time:~6,2%

:: Crear directorio de logs si no existe
if not exist "%DEV_PATH%logs" mkdir "%DEV_PATH%logs"

echo [%TIMESTAMP%] Iniciando proceso de despliegue... >> "%LOG_FILE%"
echo 🚀 Iniciando proceso de despliegue...
echo.

:: Verificar que existe el entorno de producción
if not exist "%PROD_PATH%" (
    echo ❌ Error: No se encuentra el entorno de producción en %PROD_PATH%
    echo [%TIMESTAMP%] ERROR: Entorno de producción no encontrado >> "%LOG_FILE%"
    pause
    exit /b 1
)

echo ✅ Entorno de producción encontrado
echo 🔍 Detectando cambios...
echo.

:: Ejecutar detección de cambios usando PHP
php -f "%DEV_PATH%production-deployment-assistant.php" -- --detect-changes

if %ERRORLEVEL% neq 0 (
    echo ❌ Error detectando cambios
    echo [%TIMESTAMP%] ERROR: Fallo en detección de cambios >> "%LOG_FILE%"
    pause
    exit /b 1
)

echo.
echo 📋 ¿Deseas continuar con la sincronización? (S/N)
set /p CONFIRM="> "

if /i "%CONFIRM%" neq "S" (
    echo ❌ Sincronización cancelada por el usuario
    echo [%TIMESTAMP%] INFO: Sincronización cancelada por el usuario >> "%LOG_FILE%"
    pause
    exit /b 0
)

echo.
echo 🚀 Iniciando sincronización...
echo [%TIMESTAMP%] Iniciando sincronización a producción >> "%LOG_FILE%"

:: Crear backup antes de sincronizar
echo 💾 Creando backup de seguridad...
set BACKUP_DIR=%PROD_PATH%\backups\%TIMESTAMP%
if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"

:: Backup de archivos críticos
if exist "%PROD_PATH%\config\production.php" copy "%PROD_PATH%\config\production.php" "%BACKUP_DIR%\" >nul
if exist "%PROD_PATH%\.env.production" copy "%PROD_PATH%\.env.production" "%BACKUP_DIR%\" >nul
if exist "%PROD_PATH%\.htaccess" copy "%PROD_PATH%\.htaccess" "%BACKUP_DIR%\" >nul

echo ✅ Backup creado en: %BACKUP_DIR%

:: Ejecutar sincronización usando PHP
php -f "%DEV_PATH%production-deployment-assistant.php" -- --sync-production

if %ERRORLEVEL% neq 0 (
    echo ❌ Error durante la sincronización
    echo [%TIMESTAMP%] ERROR: Fallo en sincronización >> "%LOG_FILE%"
    echo.
    echo 🔄 ¿Deseas restaurar el backup? (S/N)
    set /p RESTORE="> "
    
    if /i "%RESTORE%" equ "S" (
        echo 🔄 Restaurando backup...
        if exist "%BACKUP_DIR%\production.php" copy "%BACKUP_DIR%\production.php" "%PROD_PATH%\config\" >nul
        if exist "%BACKUP_DIR%\.env.production" copy "%BACKUP_DIR%\.env.production" "%PROD_PATH%\" >nul
        if exist "%BACKUP_DIR%\.htaccess" copy "%BACKUP_DIR%\.htaccess" "%PROD_PATH%\" >nul
        echo ✅ Backup restaurado
    )
    
    pause
    exit /b 1
)

echo.
echo ✅ Sincronización completada exitosamente
echo [%TIMESTAMP%] Sincronización completada exitosamente >> "%LOG_FILE%"

:: Verificar archivos críticos de producción
echo 🔍 Verificando configuración de producción...

if not exist "%PROD_PATH%\.env.production" (
    echo ⚠️ Creando archivo .env.production...
    call :create_env_production
)

if not exist "%PROD_PATH%\config\production.php" (
    echo ⚠️ Creando archivo config/production.php...
    call :create_config_production
)

if not exist "%PROD_PATH%\.htaccess" (
    echo ⚠️ Creando archivo .htaccess optimizado...
    call :create_htaccess_production
)

echo ✅ Configuración de producción verificada

:: Mostrar resumen
echo.
echo ========================================
echo           RESUMEN DE DESPLIEGUE
echo ========================================
echo 📁 Entorno de desarrollo: %DEV_PATH%
echo 🌐 Entorno de producción: %PROD_PATH%
echo 💾 Backup creado en: %BACKUP_DIR%
echo 🕒 Fecha y hora: %TIMESTAMP%
echo ✅ Estado: COMPLETADO EXITOSAMENTE
echo ========================================
echo.

echo 🌐 ¿Deseas abrir la interfaz web del asistente? (S/N)
set /p OPEN_WEB="> "

if /i "%OPEN_WEB%" equ "S" (
    echo 🌐 Abriendo interfaz web...
    start http://localhost:3001/production-deployment-assistant.php
)

echo.
echo 📋 Proceso completado. Presiona cualquier tecla para salir...
pause >nul
exit /b 0

:: Funciones para crear archivos de configuración
:create_env_production
(
echo # Configuración de Producción - iaTrade CRM
echo APP_NAME="iaTrade CRM - Production"
echo APP_ENV=production
echo APP_DEBUG=false
echo APP_URL=https://spin2pay.com
echo.
echo DB_CONNECTION=mysql
echo DB_HOST=localhost
echo DB_PORT=3306
echo DB_DATABASE=spin2pay_profixcrm
echo DB_USERNAME=spin2pay_profixadmin
echo DB_PASSWORD=Jeanpi9941991@
echo.
echo APP_KEY=prod_8f4e9d2a1b7c3e6f9a2d5b8c1e4f7a0b3c6e9f2a5b8d1e4f7a0b3c6e9f2a5b8d
echo JWT_SECRET=prod_jwt_9a8b7c6d5e4f3a2b1c9d8e7f6a5b4c3d2e1f9a8b7c6d5e4f3a2b1c9d8e7f6a5b4c3d
echo.
echo SESSION_LIFETIME=180
echo SESSION_SECURE=true
echo FORCE_HTTPS=true
) > "%PROD_PATH%\.env.production"
goto :eof

:create_config_production
(
echo ^<?php
echo return [
echo     "database" =^> [
echo         "host" =^> "localhost",
echo         "port" =^> "3306",
echo         "name" =^> "spin2pay_profixcrm",
echo         "username" =^> "spin2pay_profixadmin",
echo         "password" =^> "Jeanpi9941991@",
echo         "charset" =^> "utf8mb4",
echo         "collation" =^> "utf8mb4_unicode_ci"
echo     ],
echo     "app" =^> [
echo         "name" =^> "iaTrade CRM - Production",
echo         "url" =^> "https://spin2pay.com",
echo         "env" =^> "production",
echo         "debug" =^> false,
echo         "timezone" =^> "America/Mexico_City"
echo     ],
echo     "security" =^> [
echo         "key" =^> "prod_8f4e9d2a1b7c3e6f9a2d5b8c1e4f7a0b3c6e9f2a5b8d1e4f7a0b3c6e9f2a5b8d",
echo         "jwt_secret" =^> "prod_jwt_9a8b7c6d5e4f3a2b1c9d8e7f6a5b4c3d2e1f9a8b7c6d5e4f3a2b1c9d8e7f6a5b4c3d",
echo         "session_lifetime" =^> 180,
echo         "password_min_length" =^> 8,
echo         "force_https" =^> true
echo     ]
echo ];
) > "%PROD_PATH%\config\production.php"
goto :eof

:create_htaccess_production
(
echo # iaTrade CRM - Configuración de Producción
echo RewriteEngine On
echo.
echo # Forzar HTTPS
echo RewriteCond %%{HTTPS} off
echo RewriteRule ^^(.*)$ https://%%{HTTP_HOST}%%{REQUEST_URI} [L,R=301]
echo.
echo # Seguridad
echo Header always set X-Frame-Options DENY
echo Header always set X-Content-Type-Options nosniff
echo Header always set X-XSS-Protection "1; mode=block"
echo Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
echo Header always set Referrer-Policy "strict-origin-when-cross-origin"
echo.
echo # Ocultar información del servidor
echo ServerTokens Prod
echo Header unset Server
echo Header unset X-Powered-By
echo.
echo # Compresión
echo ^<IfModule mod_deflate.c^>
echo     AddOutputFilterByType DEFLATE text/plain
echo     AddOutputFilterByType DEFLATE text/html
echo     AddOutputFilterByType DEFLATE text/xml
echo     AddOutputFilterByType DEFLATE text/css
echo     AddOutputFilterByType DEFLATE application/xml
echo     AddOutputFilterByType DEFLATE application/xhtml+xml
echo     AddOutputFilterByType DEFLATE application/rss+xml
echo     AddOutputFilterByType DEFLATE application/javascript
echo     AddOutputFilterByType DEFLATE application/x-javascript
echo ^</IfModule^>
echo.
echo # Cache
echo ^<IfModule mod_expires.c^>
echo     ExpiresActive On
echo     ExpiresByType text/css "access plus 1 month"
echo     ExpiresByType application/javascript "access plus 1 month"
echo     ExpiresByType image/png "access plus 1 month"
echo     ExpiresByType image/jpg "access plus 1 month"
echo     ExpiresByType image/jpeg "access plus 1 month"
echo     ExpiresByType image/gif "access plus 1 month"
echo ^</IfModule^>
) > "%PROD_PATH%\.htaccess"
goto :eof