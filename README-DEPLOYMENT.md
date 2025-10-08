# 🚀 Sistema de Despliegue Automático - iaTrade CRM

## 📋 Descripción General

Este sistema proporciona herramientas automatizadas para sincronizar cambios entre el entorno de desarrollo y producción del iaTrade CRM, garantizando despliegues seguros y eficientes.

## 🛠️ Componentes del Sistema

### 1. **Asistente Principal de Despliegue**
- **Archivo**: `production-deployment-assistant.php`
- **Función**: Motor principal del sistema de sincronización
- **Características**:
  - Detección automática de cambios
  - Sincronización inteligente de archivos
  - Creación de backups automáticos
  - Configuración específica para producción
  - API REST para integración

### 2. **Interfaz Web**
- **Archivo**: `views/deployment-assistant.html`
- **URL**: `http://localhost:3001/production-deployment-assistant.php`
- **Características**:
  - Dashboard visual en tiempo real
  - Monitoreo de cambios
  - Controles de sincronización
  - Logs de actividad
  - Reportes de estado

### 3. **Script de Despliegue por Lotes**
- **Archivo**: `deploy-to-production.bat`
- **Función**: Automatización desde línea de comandos
- **Características**:
  - Proceso guiado paso a paso
  - Confirmaciones de seguridad
  - Creación automática de backups
  - Restauración en caso de error

### 4. **Verificador Rápido**
- **Archivo**: `sync-check.php`
- **Función**: Verificación rápida del estado de sincronización
- **Características**:
  - Comparación rápida de archivos
  - Salida en terminal con colores
  - Modo JSON para integración
  - Verificación de archivos críticos

## 🔧 Configuración del Entorno

### Entorno de Desarrollo
```
Ruta: C:\xampp\htdocs\iatrade crm\
URL: http://localhost:3001/
Base de Datos: iatrade_crm (desarrollo)
```

### Entorno de Producción Local
```
Ruta: C:\xampp\htdocs\iatrade-crm-production\
URL: https://spin2pay.com/
Base de Datos: spin2pay_profixcrm
Usuario: spin2pay_profixadmin
```

## 🚀 Guía de Uso

### Método 1: Interfaz Web (Recomendado)

1. **Acceder a la interfaz**:
   ```
   http://localhost:3001/production-deployment-assistant.php
   ```

2. **Detectar cambios**:
   - Clic en "🔍 Detectar Cambios"
   - Revisar el resumen de cambios

3. **Sincronizar**:
   - Clic en "🚀 Sincronizar a Producción"
   - Confirmar la operación
   - Monitorear el progreso

### Método 2: Script por Lotes

1. **Ejecutar el script**:
   ```batch
   deploy-to-production.bat
   ```

2. **Seguir las instrucciones**:
   - El script detectará cambios automáticamente
   - Confirmará antes de sincronizar
   - Creará backups de seguridad

### Método 3: Verificación Rápida

1. **Verificar estado**:
   ```bash
   php sync-check.php
   ```

2. **Ver detalles completos**:
   ```bash
   php sync-check.php --details
   ```

3. **Salida JSON**:
   ```bash
   php sync-check.php --json
   ```

## 📁 Archivos Excluidos de la Sincronización

### Directorios Excluidos:
- `logs/` - Logs del sistema
- `storage/logs/` - Logs de almacenamiento
- `storage/cache/` - Archivos de caché
- `storage/sessions/` - Sesiones de usuario
- `node_modules/` - Dependencias de Node.js
- `.git/` - Control de versiones
- `vendor/` - Dependencias de Composer
- `deploy/` - Archivos de despliegue
- `backups/` - Backups automáticos

### Archivos Excluidos:
- `.env`, `.env.local`, `.env.development` - Configuraciones de entorno
- `config.php`, `database.php` - Configuraciones específicas
- `*.log` - Archivos de log
- `debug_*`, `test_*`, `check_*` - Archivos de desarrollo
- `.installed` - Marcador de instalación
- `composer.lock` - Lock de dependencias

## 🔐 Configuración de Seguridad

### Claves de Producción Generadas:
```php
APP_KEY=prod_8f4e9d2a1b7c3e6f9a2d5b8c1e4f7a0b3c6e9f2a5b8d1e4f7a0b3c6e9f2a5b8d
JWT_SECRET=prod_jwt_9a8b7c6d5e4f3a2b1c9d8e7f6a5b4c3d2e1f9a8b7c6d5e4f3a2b1c9d8e7f6a5b4c3d
```

### Configuraciones de Seguridad:
- **HTTPS Forzado**: Activado
- **Sesiones Seguras**: 180 minutos
- **Headers de Seguridad**: Configurados
- **Compresión**: Habilitada
- **Cache**: Optimizado

## 📊 API del Sistema

### Endpoints Disponibles:

#### Detectar Cambios
```
GET /production-deployment-assistant.php?api=detect
```

#### Sincronizar
```
GET /production-deployment-assistant.php?api=sync
```

#### Estado del Sistema
```
GET /production-deployment-assistant.php?api=status
```

### Respuesta JSON Ejemplo:
```json
{
  "timestamp": "2024-01-15 14:30:00",
  "total_changes": 5,
  "changes": {
    "new": ["new-feature.php"],
    "modified": ["index.php", "config.php"],
    "deleted": ["old-file.php"]
  },
  "sync_needed": true
}
```

## 🔄 Proceso de Sincronización

### 1. Detección de Cambios
- Escaneo recursivo de archivos
- Generación de hashes MD5
- Comparación entre entornos
- Identificación de cambios

### 2. Creación de Backup
- Backup automático de archivos críticos
- Timestamp único para cada backup
- Almacenamiento en `/backups/`

### 3. Sincronización
- Copia de archivos nuevos y modificados
- Eliminación controlada de archivos obsoletos
- Actualización de configuraciones de producción
- Verificación de integridad

### 4. Verificación Post-Sincronización
- Validación de archivos críticos
- Verificación de configuraciones
- Generación de reportes
- Logs de actividad

## 📝 Logs y Monitoreo

### Archivo de Log Principal:
```
logs/deployment.log
```

### Formato de Log:
```
[2024-01-15 14:30:00] 🚀 Iniciando sincronización a producción...
[2024-01-15 14:30:05] 📄 Sincronizado: index.php
[2024-01-15 14:30:10] ✅ Sincronización completada: 15 archivos sincronizados, 0 errores
```

## 🛡️ Seguridad y Backups

### Backups Automáticos:
- Se crean antes de cada sincronización
- Incluyen archivos críticos de configuración
- Organizados por timestamp
- Restauración automática en caso de error

### Archivos Críticos Respaldados:
- `config/production.php`
- `.env.production`
- `.htaccess`
- Configuraciones de base de datos

## 🔧 Solución de Problemas

### Error: "Entorno de producción no encontrado"
**Solución**: Verificar que existe el directorio `iatrade-crm-production`

### Error: "Permisos insuficientes"
**Solución**: Ejecutar como administrador o verificar permisos de escritura

### Error: "Fallo en sincronización"
**Solución**: 
1. Verificar logs en `logs/deployment.log`
2. Restaurar backup si es necesario
3. Verificar conectividad de red

### Archivos críticos faltantes
**Solución**: El sistema los creará automáticamente durante la sincronización

## 📈 Mejores Prácticas

### Antes del Despliegue:
1. ✅ Probar cambios en desarrollo
2. ✅ Ejecutar verificación rápida
3. ✅ Revisar lista de cambios
4. ✅ Confirmar backup automático

### Durante el Despliegue:
1. ✅ Monitorear logs en tiempo real
2. ✅ Verificar progreso en interfaz web
3. ✅ No interrumpir el proceso
4. ✅ Confirmar finalización exitosa

### Después del Despliegue:
1. ✅ Verificar funcionamiento en producción
2. ✅ Revisar logs de errores
3. ✅ Confirmar configuraciones
4. ✅ Documentar cambios realizados

## 🆘 Contacto y Soporte

Para soporte técnico o reportar problemas:
- Revisar logs en `logs/deployment.log`
- Verificar estado con `sync-check.php`
- Usar interfaz web para diagnóstico visual

---

## 📋 Checklist de Despliegue

- [ ] Entorno de desarrollo funcionando
- [ ] Cambios probados localmente
- [ ] Backup de producción actual
- [ ] Verificación de cambios pendientes
- [ ] Sincronización ejecutada
- [ ] Verificación post-despliegue
- [ ] Documentación actualizada

---

*Sistema de Despliegue Automático v1.0 - iaTrade CRM*
*Desarrollado para garantizar despliegues seguros y eficientes*