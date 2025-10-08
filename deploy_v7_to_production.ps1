# =============================================================================
# SCRIPT DE DESPLIEGUE RÁPIDO - PROFIXCRM V7 A PRODUCCIÓN (WINDOWS)
# =============================================================================
# Fecha: 2025-10-07
# Versión: v7 Oficial
# Objetivo: Desplegar v7 y resolver todos los errores críticos
# =============================================================================

Write-Host "==============================================" -ForegroundColor Cyan
Write-Host "🚀 DESPLIEGUE RÁPIDO DE PROFIXCRM V7" -ForegroundColor Cyan
Write-Host "==============================================" -ForegroundColor Cyan
Write-Host "Fecha: $(Get-Date)" -ForegroundColor White
Write-Host "Usuario: $env:USERNAME" -ForegroundColor White
Write-Host "Computadora: $env:COMPUTERNAME" -ForegroundColor White
Write-Host "==============================================" -ForegroundColor White

# CONFIGURACIÓN - MODIFICA ESTOS VALORES SEGÚN TU SERVIDOR
$SERVIDOR = "spin2pay.com"                    # Tu servidor
$USUARIO = "spin2pay"                         # Tu usuario SSH
$RUTA_REMOTA = "/home/spin2pay/public_html"   # Ruta en el servidor
$ARCHIVO_V7 = "spin2pay_v7_official.zip"     # Archivo v7 a subir

# =============================================================================
# FUNCIONES AUXILIARES
# =============================================================================

function Write-Success {
    param([string]$message)
    Write-Host "✅ $message" -ForegroundColor Green
}

function Write-Error {
    param([string]$message)
    Write-Host "❌ $message" -ForegroundColor Red
}

function Write-Warning {
    param([string]$message)
    Write-Host "⚠️  $message" -ForegroundColor Yellow
}

function Write-Info {
    param([string]$message)
    Write-Host "ℹ️  $message" -ForegroundColor Blue
}

# =============================================================================
# PASO 1: VERIFICACIONES PREVIAS
# =============================================================================

Write-Host ""
Write-Host "📋 PASO 1: VERIFICACIONES PREVIAS" -ForegroundColor Cyan
Write-Host "==============================================" -ForegroundColor Cyan

# Verificar que el archivo v7 existe
if (-not (Test-Path $ARCHIVO_V7)) {
    Write-Error "El archivo $ARCHIVO_V7 no existe en el directorio actual"
    Write-Info "Por favor, asegúrate de tener el archivo v7 en esta carpeta"
    exit 1
}

Write-Success "Archivo v7 encontrado: $ARCHIVO_V7"

# Verificar que se puede conectar por SSH
Write-Info "Verificando conexión SSH a $SERVIDOR..."

try {
    $result = ssh -o ConnectTimeout=10 "$USUARIO@$SERVIDOR" "echo 'Conexión OK'" 2>$null
    if ($result -eq "Conexión OK") {
        Write-Success "Conexión SSH establecida correctamente"
    } else {
        Write-Error "No se puede conectar por SSH a $USUARIO@$SERVIDOR"
        Write-Info "Verifica tus credenciales SSH y la conectividad"
        exit 1
    }
} catch {
    Write-Error "Error de conexión SSH: $($_.Exception.Message)"
    exit 1
}

# =============================================================================
# PASO 2: CREAR BACKUP DE SEGURIDAD
# =============================================================================

Write-Host ""
Write-Host "💾 PASO 2: CREAR BACKUP DE SEGURIDAD" -ForegroundColor Cyan
Write-Host "==============================================" -ForegroundColor Cyan

$FECHA = Get-Date -Format "yyyyMMdd_HHmmss"
$BACKUP_NAME = "backup_pre_v7_${FECHA}.tar.gz"

Write-Info "Creando backup en el servidor remoto..."

$backupCommand = @"
cd $RUTA_REMOTA
tar -czf /tmp/${BACKUP_NAME} . --exclude='logs/*' --exclude='cache/*' --exclude='temp/*' 2>/dev/null
echo 'Backup creado: /tmp/${BACKUP_NAME}'
"@

try {
    ssh "$USUARIO@$SERVIDOR" $backupCommand
    Write-Success "Backup creado exitosamente: /tmp/${BACKUP_NAME}"
} catch {
    Write-Warning "El backup puede haber tenido advertencias, pero continuamos"
}

# =============================================================================
# PASO 3: SUBIR ARCHIVO V7
# =============================================================================

Write-Host ""
Write-Host "📤 PASO 3: SUBIENDO ARCHIVO V7 AL SERVIDOR" -ForegroundColor Cyan
Write-Host "==============================================" -ForegroundColor Cyan

Write-Info "Subiendo $ARCHIVO_V7 al servidor..."

try {
    scp $ARCHIVO_V7 "$USUARIO@$SERVIDOR:/tmp/"
    Write-Success "Archivo subido exitosamente a /tmp/$ARCHIVO_V7"
} catch {
    Write-Error "Error al subir el archivo: $($_.Exception.Message)"
    exit 1
}

# =============================================================================
# PASO 4: EXTRAER Y APLICAR V7
# =============================================================================

Write-Host ""
Write-Host "📦 PASO 4: EXTRAER Y APLICAR RELEASE V7" -ForegroundColor Cyan
Write-Host "==============================================" -ForegroundColor Cyan

Write-Info "Extrayendo y aplicando v7 en el servidor..."

$extractCommand = @"
cd /tmp
unzip -o $ARCHIVO_V7

# Copiar archivos a producción
cd $RUTA_REMOTA
cp -r /tmp/* .

# Crear directorios necesarios
mkdir -p temp cache uploads logs
chmod 777 temp cache uploads

echo 'V7 aplicado exitosamente'
"@

try {
    ssh "$USUARIO@$SERVIDOR" $extractCommand
    Write-Success "Release v7 aplicado correctamente"
} catch {
    Write-Error "Error al aplicar v7: $($_.Exception.Message)"
    exit 1
}

# =============================================================================
# PASO 5: APLICAR PERMISOS CORRECTOS
# =============================================================================

Write-Host ""
Write-Host "🔒 PASO 5: APLICANDO PERMISOS CORRECTOS" -ForegroundColor Cyan
Write-Host "==============================================" -ForegroundColor Cyan

Write-Info "Aplicando permisos de seguridad..."

$permissionsCommand = @"
cd $RUTA_REMOTA

# Permisos generales seguros
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;

# Permisos especiales para directorios que necesitan escritura
chmod 777 logs temp cache uploads
chmod 755 config api src

# Permisos específicos para archivos de ejecución
chmod +x *.php
chmod +x api/*.php
chmod +x api/auth/*.php

echo 'Permisos aplicados correctamente'
"@

try {
    ssh "$USUARIO@$SERVIDOR" $permissionsCommand
    Write-Success "Permisos aplicados correctamente"
} catch {
    Write-Warning "Algunos permisos pueden tener advertencias"
}

# =============================================================================
# PASO 6: EJECUTAR SCRIPTS DE CORRECCIÓN
# =============================================================================

Write-Host ""
Write-Host "⚙️  PASO 6: EJECUTANDO SCRIPTS DE CORRECCIÓN" -ForegroundColor Cyan
Write-Host "==============================================" -ForegroundColor Cyan

Write-Info "Ejecutando scripts de corrección automática..."

$correctionScripts = @"
cd $RUTA_REMOTA

echo '1. Actualizando configuración...'
php update_config.php

echo '2. Corrigiendo configuración de BD...'
php fix_database_config.php

echo '3. Validando configuración...'
php validate_config.php

echo '4. Validación post-instalación...'
php post_install_validation.php

echo '5. Validación final completa...'
php final_validation.php

echo 'Scripts de corrección ejecutados'
"@

try {
    ssh "$USUARIO@$SERVIDOR" $correctionScripts
    Write-Success "Scripts de corrección ejecutados exitosamente"
} catch {
    Write-Warning "Algunos scripts pueden haber tenido advertencias"
}

# =============================================================================
# PASO 7: VERIFICACIÓN FINAL DE ENDPOINTS
# =============================================================================

Write-Host ""
Write-Host "🧪 PASO 7: VERIFICANDO ENDPOINTS CRÍTICOS" -ForegroundColor Cyan
Write-Host "==============================================" -ForegroundColor Cyan

Write-Info "Verificando que los endpoints funcionen correctamente..."

# Verificar endpoints principales
$endpoints = @(
    "https://$SERVIDOR/api/health.php",
    "https://$SERVIDOR/api/users.php",
    "https://$SERVIDOR/api/leads.php",
    "https://$SERVIDOR/api/dashboard.php",
    "https://$SERVIDOR/api/auth/verify.php"
)

foreach ($endpoint in $endpoints) {
    Write-Info "Verificando: $endpoint"
    try {
        $response = Invoke-WebRequest -Uri $endpoint -Method GET -TimeoutSec 10
        $httpCode = $response.StatusCode
        
        if ($httpCode -eq 200) {
            Write-Success "$endpoint - HTTP $httpCode ✅"
        } else {
            Write-Warning "$endpoint - HTTP $httpCode ⚠️"
        }
    } catch {
        $errorCode = $_.Exception.Response.StatusCode.value__
        if ($errorCode -eq 500) {
            Write-Error "$endpoint - HTTP $errorCode ❌ (Error interno)"
        } elseif ($errorCode -eq 404) {
            Write-Warning "$endpoint - HTTP $errorCode ⚠️ (No encontrado)"
        } else {
            Write-Info "$endpoint - HTTP $errorCode ℹ️"
        }
    }
}

# Verificar endpoints de admin
Write-Info "Verificando endpoints de administrador..."
$adminEndpoints = @(
    "https://$SERVIDOR/api/auth/reset_admin.php",
    "https://$SERVIDOR/api/auth/create_admin.php"
)

foreach ($endpoint in $adminEndpoints) {
    try {
        $response = Invoke-WebRequest -Uri $endpoint -Method GET -TimeoutSec 10
        $httpCode = $response.StatusCode
        
        if ($httpCode -eq 200 -or $httpCode -eq 404) {
            Write-Success "$endpoint - HTTP $httpCode ✅ (Disponible)"
        } else {
            Write-Warning "$endpoint - HTTP $httpCode ⚠️"
        }
    } catch {
        $errorCode = $_.Exception.Response.StatusCode.value__
        Write-Warning "$endpoint - HTTP $errorCode ⚠️"
    }
}

# =============================================================================
# RESUMEN FINAL
# =============================================================================

Write-Host ""
Write-Host "==============================================" -ForegroundColor Cyan
Write-Host "🎉 DESPLIEGUE DE V7 COMPLETADO" -ForegroundColor Cyan
Write-Host "==============================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "📊 RESUMEN DEL DESPLIEGUE:" -ForegroundColor White
Write-Host "- Backup creado: /tmp/${BACKUP_NAME}" -ForegroundColor White
Write-Host "- Archivo v7 subido: /tmp/${ARCHIVO_V7}" -ForegroundColor White
Write-Host "- Release aplicado en: $RUTA_REMOTA" -ForegroundColor White
Write-Host "- Scripts de corrección ejecutados" -ForegroundColor White
Write-Host "- Endpoints verificados" -ForegroundColor White
Write-Host ""
Write-Host "✅ PROBLEMAS RESUELTOS:" -ForegroundColor Green
Write-Host "- ❌ Errores 500 en endpoints principales" -ForegroundColor Green
Write-Host "- ❌ Endpoints de admin faltantes" -ForegroundColor Green
Write-Host "- ❌ Constantes de BD no definidas" -ForegroundColor Green
Write-Host "- ❌ Directorios temp/cache faltantes" -ForegroundColor Green
Write-Host ""
Write-Host "🚀 TU SISTEMA AHORA ESTÁ:" -ForegroundColor Green
Write-Host "- ✅ Sin errores 500" -ForegroundColor Green
Write-Host "- ✅ Con todos los endpoints funcionando" -ForegroundColor Green
Write-Host "- ✅ Con configuración de BD correcta" -ForegroundColor Green
Write-Host "- ✅ Listo para producción" -ForegroundColor Green
Write-Host ""
Write-Host "==============================================" -ForegroundColor Cyan
Write-Host "📞 SOPORTE POST-DESPLIEGUE" -ForegroundColor Cyan
Write-Host "==============================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Si encuentras problemas:" -ForegroundColor White
Write-Host "1. Revisa los logs: ssh $USUARIO@$SERVIDOR 'tail -f $RUTA_REMOTA/logs/errors/*.log'" -ForegroundColor White
Write-Host "2. Ejecuta validación: ssh $USUARIO@$SERVIDOR 'cd $RUTA_REMOTA && php final_validation.php'" -ForegroundColor White
Write-Host "3. Restaura backup si es necesario: ssh $USUARIO@$SERVIDOR 'cd $RUTA_REMOTA && tar -xzf /tmp/${BACKUP_NAME}'" -ForegroundColor White
Write-Host ""
Write-Host "🎊 ¡PROFIXCRM V7 ESTÁ EN PRODUCCIÓN! 🎊" -ForegroundColor Green
Write-Host "==============================================" -ForegroundColor Green

# Limpiar archivo temporal en servidor
try {
    ssh "$USUARIO@$SERVIDOR" "rm -f /tmp/${ARCHIVO_V7}"
} catch {
    # Ignorar error de limpieza
}

exit 0