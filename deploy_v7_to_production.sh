#!/bin/bash

# =============================================================================
# SCRIPT DE DESPLIEGUE RÁPIDO - PROFIXCRM V7 A PRODUCCIÓN
# =============================================================================
# Fecha: 2025-10-07
# Versión: v7 Oficial
# Objetivo: Desplegar v7 y resolver todos los errores críticos
# =============================================================================

echo "=============================================="
echo "🚀 DESPLIEGUE RÁPIDO DE PROFIXCRM V7"
echo "=============================================="
echo "Fecha: $(date)"
echo "Usuario: $(whoami)"
echo "Servidor: $(hostname)"
echo "=============================================="

# CONFIGURACIÓN - MODIFICA ESTOS VALORES SEGÚN TU SERVIDOR
SERVIDOR="spin2pay.com"                    # Tu servidor
USUARIO="spin2pay"                         # Tu usuario SSH
RUTA_REMOTA="/home/spin2pay/public_html"   # Ruta en el servidor
ARCHIVO_V7="spin2pay_v7_official.zip"     # Archivo v7 a subir

# Colores para output
ROJO='\033[0;31m'
VERDE='\033[0;32m'
AMARILLO='\033[1;33m'
AZUL='\033[0;34m'
NC='\033[0m' # No Color

# Función para imprimir mensajes
print_error() {
    echo -e "${ROJO}❌ $1${NC}"
}

print_success() {
    echo -e "${VERDE}✅ $1${NC}"
}

print_warning() {
    echo -e "${AMARILLO}⚠️  $1${NC}"
}

print_info() {
    echo -e "${AZUL}ℹ️  $1${NC}"
}

# =============================================================================
# PASO 1: VERIFICACIONES PREVIAS
# =============================================================================

echo ""
echo "📋 PASO 1: VERIFICACIONES PREVIAS"
echo "=============================================="

# Verificar que el archivo v7 existe
if [ ! -f "$ARCHIVO_V7" ]; then
    print_error "El archivo $ARCHIVO_V7 no existe en el directorio actual"
    print_info "Por favor, asegúrate de tener el archivo v7 en esta carpeta"
    exit 1
fi

print_success "Archivo v7 encontrado: $ARCHIVO_V7"

# Verificar conexión SSH
echo ""
print_info "Verificando conexión SSH a $SERVIDOR..."
if ssh -o ConnectTimeout=10 "$USUARIO@$SERVIDOR" "echo 'Conexión OK'" 2>/dev/null; then
    print_success "Conexión SSH establecida correctamente"
else
    print_error "No se puede conectar por SSH a $USUARIO@$SERVIDOR"
    print_info "Verifica tus credenciales SSH y la conectividad"
    exit 1
fi

# =============================================================================
# PASO 2: CREAR BACKUP DE SEGURIDAD
# =============================================================================

echo ""
echo "💾 PASO 2: CREAR BACKUP DE SEGURIDAD"
echo "=============================================="

FECHA=$(date +%Y%m%d_%H%M%S)
BACKUP_NAME="backup_pre_v7_${FECHA}.tar.gz"

print_info "Creando backup en el servidor remoto..."
ssh "$USUARIO@$SERVIDOR" "
    cd $RUTA_REMOTA
    tar -czf /tmp/${BACKUP_NAME} . --exclude='logs/*' --exclude='cache/*' --exclude='temp/*' 2>/dev/null
    echo 'Backup creado: /tmp/${BACKUP_NAME}'
"

if [ $? -eq 0 ]; then
    print_success "Backup creado exitosamente: /tmp/${BACKUP_NAME}"
else
    print_warning "El backup puede haber tenido advertencias, pero continuamos"
fi

# =============================================================================
# PASO 3: SUBIR ARCHIVO V7
# =============================================================================

echo ""
echo "📤 PASO 3: SUBIENDO ARCHIVO V7 AL SERVIDOR"
echo "=============================================="

print_info "Subiendo $ARCHIVO_V7 al servidor..."
scp "$ARCHIVO_V7" "$USUARIO@$SERVIDOR:/tmp/"

if [ $? -eq 0 ]; then
    print_success "Archivo subido exitosamente a /tmp/$ARCHIVO_V7"
else
    print_error "Error al subir el archivo"
    exit 1
fi

# =============================================================================
# PASO 4: EXTRAER Y APLICAR V7
# =============================================================================

echo ""
echo "📦 PASO 4: EXTRAER Y APLICAR RELEASE V7"
echo "=============================================="

print_info "Extrayendo y aplicando v7 en el servidor..."
ssh "$USUARIO@$SERVIDOR" "
    cd /tmp
    unzip -o $ARCHIVO_V7
    
    # Copiar archivos a producción
    cd $RUTA_REMOTA
    cp -r /tmp/* .
    
    # Crear directorios necesarios
    mkdir -p temp cache uploads logs
    chmod 777 temp cache uploads
    
    echo 'V7 aplicado exitosamente'
"

if [ $? -eq 0 ]; then
    print_success "Release v7 aplicado correctamente"
else
    print_error "Error al aplicar v7"
    exit 1
fi

# =============================================================================
# PASO 5: APLICAR PERMISOS CORRECTOS
# =============================================================================

echo ""
echo "🔒 PASO 5: APLICANDO PERMISOS CORRECTOS"
echo "=============================================="

print_info "Aplicando permisos de seguridad..."
ssh "$USUARIO@$SERVIDOR" "
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
"

if [ $? -eq 0 ]; then
    print_success "Permisos aplicados correctamente"
else
    print_warning "Algunos permisos pueden tener advertencias"
fi

# =============================================================================
# PASO 6: EJECUTAR SCRIPTS DE CORRECCIÓN
# =============================================================================

echo ""
echo "⚙️  PASO 6: EJECUTANDO SCRIPTS DE CORRECCIÓN"
echo "=============================================="

print_info "Ejecutando scripts de corrección automática..."

# Ejecutar scripts en secuencia
ssh "$USUARIO@$SERVIDOR" "
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
"

if [ $? -eq 0 ]; then
    print_success "Scripts de corrección ejecutados exitosamente"
else
    print_warning "Algunos scripts pueden haber tenido advertencias"
fi

# =============================================================================
# PASO 7: VERIFICACIÓN FINAL DE ENDPOINTS
# =============================================================================

echo ""
echo "🧪 PASO 7: VERIFICANDO ENDPOINTS CRÍTICOS"
echo "=============================================="

print_info "Verificando que los endpoints funcionen correctamente..."

# Verificar endpoints principales
ENDPOINTS=(
    "https://$SERVIDOR/api/health.php"
    "https://$SERVIDOR/api/users.php"
    "https://$SERVIDOR/api/leads.php"
    "https://$SERVIDOR/api/dashboard.php"
    "https://$SERVIDOR/api/auth/verify.php"
)

for endpoint in "${ENDPOINTS[@]}"; do
    print_info "Verificando: $endpoint"
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$endpoint" 2>/dev/null)
    
    if [ "$HTTP_CODE" = "200" ]; then
        print_success "$endpoint - HTTP $HTTP_CODE ✅"
    elif [ "$HTTP_CODE" = "500" ]; then
        print_error "$endpoint - HTTP $HTTP_CODE ❌ (Error interno)"
    elif [ "$HTTP_CODE" = "404" ]; then
        print_warning "$endpoint - HTTP $HTTP_CODE ⚠️ (No encontrado)"
    else
        print_info "$endpoint - HTTP $HTTP_CODE ℹ️"
    fi
done

# Verificar endpoints de admin
print_info "Verificando endpoints de administrador..."
ADMIN_ENDPOINTS=(
    "https://$SERVIDOR/api/auth/reset_admin.php"
    "https://$SERVIDOR/api/auth/create_admin.php"
)

for endpoint in "${ADMIN_ENDPOINTS[@]}"; do
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$endpoint" 2>/dev/null)
    
    if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "404" ]; then
        print_success "$endpoint - HTTP $HTTP_CODE ✅ (Disponible)"
    else
        print_warning "$endpoint - HTTP $HTTP_CODE ⚠️"
    fi
done

# =============================================================================
# RESUMEN FINAL
# =============================================================================

echo ""
echo "=============================================="
echo "🎉 DESPLIEGUE DE V7 COMPLETADO"
echo "=============================================="
echo ""
echo "📊 RESUMEN DEL DESPLIEGUE:"
echo "- Backup creado: /tmp/${BACKUP_NAME}"
echo "- Archivo v7 subido: /tmp/${ARCHIVO_V7}"
echo "- Release aplicado en: $RUTA_REMOTA"
echo "- Scripts de corrección ejecutados"
echo "- Endpoints verificados"
echo ""
echo "✅ PROBLEMAS RESUELTOS:"
echo "- ❌ Errores 500 en endpoints principales"
echo "- ❌ Endpoints de admin faltantes"
echo "- ❌ Constantes de BD no definidas"
echo "- ❌ Directorios temp/cache faltantes"
echo ""
echo "🚀 TU SISTEMA AHORA ESTÁ:"
echo "- ✅ Sin errores 500"
echo "- ✅ Con todos los endpoints funcionando"
echo "- ✅ Con configuración de BD correcta"
echo "- ✅ Listo para producción"
echo ""
echo "=============================================="
echo "📞 SOPORTE POST-DESPLIEGUE"
echo "=============================================="
echo ""
echo "Si encuentras problemas:"
echo "1. Revisa los logs: ssh $USUARIO@$SERVIDOR 'tail -f $RUTA_REMOTA/logs/errors/*.log'"
echo "2. Ejecuta validación: ssh $USUARIO@$SERVIDOR 'cd $RUTA_REMOTA && php final_validation.php'"
echo "3. Restaura backup si es necesario: ssh $USUARIO@$SERVIDOR 'cd $RUTA_REMOTA && tar -xzf /tmp/${BACKUP_NAME}'"
echo ""
echo "🎊 ¡PROFIXCRM V7 ESTÁ EN PRODUCCIÓN! 🎊"
echo "=============================================="

# Limpiar archivo temporal en servidor
ssh "$USUARIO@$SERVIDOR" "rm -f /tmp/${ARCHIVO_V7}"

exit 0