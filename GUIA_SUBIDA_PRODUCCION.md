# Guía de Subida a Producción - ProfixCRM v8

## 📋 **PASO 1: Preparar el Servidor de Producción**

### Antes de subir archivos, ejecuta en tu servidor:
```bash
# Crear respaldo completo de tu versión actual
cd /home/tu-usuario/public_html
tar -czf profix_v7_backup_$(date +%Y%m%d_%H%M%S).tar.gz public_html/

# Respaldar base de datos
mysqldump -u usuario_bd -p nombre_bd > profix_v7_db_$(date +%Y%m%d_%H%M%S).sql
```

## 📦 **PASO 2: Archivos que DEBES subir**

### **📁 Carpetas Principales (SUBIR COMPLETAS):**
```
✅ config/          → Toda la carpeta (incluye v8_config.php)
✅ src/             → Toda la carpeta (núcleo de v8)
✅ public/          → Toda la carpeta (assets, js, css)
✅ api/             → Toda la carpeta (endpoints API)
✅ vendor/          → Toda la carpeta (dependencias Composer)
✅ views/           → Toda la carpeta (vistas)
✅ storage/         → Toda la carpeta (almacenamiento)
✅ temp/            → Toda la carpeta (temporal)
✅ logs/            → Crear si no existe
✅ cache/           → Crear si no existe
```

### **📄 Archivos Individuales (SUBIR ESTOS ARCHIVOS):**
```
✅ index.php                    → Archivo principal actualizado
✅ validate_v8.php              → Validador del sistema
✅ deploy_v8.php                → Script de despliegue
✅ .htaccess                    → Configuración Apache
✅ composer.json                → Dependencias
✅ composer.lock                → Versiones exactas
```

## 🚫 **Archivos que NO debes subir:**
```
❌ *.csv                       → Archivos de prueba (500_leads.csv, etc)
❌ test_*.php                  → Todos los archivos de prueba
❌ debug_*.php                 → Archivos de depuración
❌ check_*.php                  → Scripts de verificación
❌ fix_*.php                    → Scripts de corrección
❌ add_*.php                    → Scripts de adición
❌ *.txt                        → Archivos de log/texto (excepto .htaccess)
❌ backups/                     → Respaldos locales
❌ backups-recovery/            → Respaldos de recuperación
❌ *.md                         → Archivos de documentación
❌ *.backup_*                   → Archivos de respaldo
❌ *.sql                        → Archivos SQL (excepto los que necesites)
```

## 🔄 **PASO 3: Proceso de Subida Recomendado**

### **Opción A: FTP Tradicional**
```
1. Comprimir archivos localmente:
   zip -r profix_v8_upload.zip [archivos_y_carpetas_necesarias]

2. Subir el ZIP por FTP

3. En el servidor, descomprimir:
   unzip profix_v8_upload.zip
```

### **Opción B: Subida por Capas (Más Segura)**
```
# Primero: Subir carpetas del núcleo
- config/
- src/
- vendor/

# Segundo: Subir archivos públicos
- public/
- views/
- index.php
- .htaccess

# Tercero: Subir API y utilidades
- api/
- validate_v8.php
- deploy_v8.php

# Final: Crear carpetas necesarias
mkdir -p logs/v8 storage/cache temp/v8 cache/v8
```

## ⚙️ **PASO 4: Configuración Post-Subida**

### **1. Permisos de Carpetas (Ejecutar en servidor):**
```bash
# Establecer permisos correctos
chmod 755 /home/tu-usuario/public_html/
chmod -R 755 config/
chmod -R 755 storage/
chmod -R 755 temp/
chmod -R 755 cache/
chmod -R 755 logs/

# Permisos especiales para carpetas de escritura
chmod -R 777 storage/cache/
chmod -R 777 temp/v8/
chmod -R 777 logs/v8/
```

### **2. Configuración de Base de Datos:**
```bash
# Actualizar configuración en config/v8_config.php
# Asegurarte de que tenga los datos correctos de producción
```

### **3. Variables de Entorno:**
```bash
# Copiar .env.production a .env si existe
cp .env.production .env

# O crear .env con configuración de producción
echo "APP_ENV=production" > .env
echo "APP_DEBUG=false" >> .env
```

## 🧪 **PASO 5: Validación en Producción**

### **Ejecutar validación:**
```bash
# En tu servidor de producción
cd /home/tu-usuario/public_html/
php validate_v8.php full
```

### **Verificar puntos críticos:**
```
✅ 1. La página principal carga sin errores
✅ 2. El login funciona correctamente
✅ 3. El dashboard se muestra
✅ 4. Los leads se cargan
✅ 5. La API responde
✅ 6. No hay errores 500
✅ 7. Los logs se están generando
```

## 📋 **Checklist Final de Subida:**

### **Antes de subir:**
- [ ] Tienes respaldo completo de archivos y BD
- [ ] Has probado localmente que v8 funciona
- [ ] Tienes acceso SSH/FTP al servidor
- [ ] Sabes cómo restaurar el respaldo si algo falla

### **Durante la subida:**
- [ ] Subes solo los archivos necesarios
- [ ] Mantienes el orden de carpetas
- [ ] No sobrescribes configuraciones importantes sin respaldar

### **Después de subir:**
- [ ] Ejecutas validate_v8.php
- [ ] Verificas que el sitio carga
- [ ] Pruebas login y funciones principales
- [ ] Revisas logs por errores
- [ ] Confirmas que todo está funcionando

## 🚨 **Si Algo Sale Mal:**

### **Restauración Rápida:**
```bash
# 1. Restaurar archivos
tar -xzf profix_v7_backup_YYYYMMDD_HHMMSS.tar.gz

# 2. Restaurar base de datos
mysql -u usuario_bd -p nombre_bd < profix_v7_db_YYYYMMDD_HHMMSS.sql

# 3. Verificar versión anterior
php -r "include 'config.php'; echo 'V7 restaurada';"
```

### **Soporte de Emergencia:**
```bash
# Contactar soporte técnico
# Tener listo: logs de error, mensajes específicos, hora del problema
```

## ⏱️ **Tiempo Estimado:**
- **Preparación y subida**: 30-60 minutos
- **Configuración y validación**: 15-30 minutos
- **Pruebas finales**: 15-30 minutos
- **Total**: 60-120 minutos

---

**⚠️ IMPORTANTE:** Siempre mantén una copia de seguridad antes de hacer cambios en producción.