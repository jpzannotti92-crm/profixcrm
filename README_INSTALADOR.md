# 🚀 Instalador Automático de Despliegue - iaTrade CRM

## ¿Qué hace este instalador?

El instalador automático **deploy-installer.php** realiza TODOS los pasos necesarios para preparar tu aplicación para producción de forma completamente automática.

## 🎯 Lo que automatiza:

### ✅ Verificación de Requisitos
- Verifica PHP 7.4+
- Verifica Node.js y npm
- Comprueba la estructura del proyecto

### ✅ Compilación del Frontend
- Instala dependencias automáticamente (`npm install`)
- Compila el frontend para producción (`npm run build`)
- Optimiza todos los assets

### ✅ Configuración Automática
- Te pide los datos de tu servidor (BD, dominio, etc.)
- Genera automáticamente las claves de seguridad
- Crea el archivo `.env` de producción personalizado

### ✅ Preparación de Archivos
- Copia SOLO los archivos necesarios para producción
- Excluye archivos de desarrollo innecesarios
- Organiza todo en la estructura correcta para public_html

### ✅ Configuración de Servidor
- Genera el `.htaccess` optimizado para public_html directo
- Configura permisos de archivos
- Crea directorios necesarios (storage, logs, cache)

### ✅ Instrucciones Personalizadas
- Genera instrucciones específicas para tu configuración
- Incluye todos los datos que necesitas para el despliegue

## 🚀 Cómo usar el instalador:

### Opción 1: Archivo Batch (Más fácil)
```bash
# Simplemente haz doble clic en:
deploy-quick.bat
```

### Opción 2: Línea de comandos
```bash
php deploy-installer.php
```

## 📋 Proceso paso a paso:

1. **Ejecuta el instalador**
2. **Responde las preguntas**:
   - Host de base de datos (ej: localhost)
   - Nombre de base de datos (ej: mi_crm)
   - Usuario de base de datos
   - Contraseña de base de datos
   - URL de tu sitio (ej: https://midominio.com)
   - Nombre de la aplicación

3. **El instalador hace todo automáticamente**:
   - Compila el frontend
   - Prepara todos los archivos
   - Genera configuraciones
   - Crea instrucciones personalizadas

4. **Resultado**: Carpeta `deployment-ready` con todo listo para subir

## 📁 Estructura generada:

```
deployment-ready/
├── index.php              # Página principal
├── index.php              # Punto de entrada principal
├── .env                   # Configuración de producción
├── .htaccess              # Configuración Apache optimizada
├── api/                   # API completa
├── assets/                # Frontend compilado y optimizado
├── src/                   # Código fuente del backend
├── vendor/                # Dependencias PHP
├── config/                # Configuraciones
├── storage/               # Logs, cache, sesiones
├── uploads/               # Archivos subidos
└── INSTRUCCIONES_DESPLIEGUE.txt  # Guía personalizada
```

## 🎯 Ventajas del instalador:

### ⚡ Rapidez
- **Sin instalador**: 30-60 minutos de trabajo manual
- **Con instalador**: 2-5 minutos automático

### 🎯 Precisión
- Cero errores humanos
- Configuración perfecta cada vez
- No olvidas ningún paso

### 🔒 Seguridad
- Genera claves únicas automáticamente
- Configuración de seguridad optimizada
- Excluye archivos sensibles

### 📱 Simplicidad
- Solo respondes unas preguntas
- El resto es automático
- Instrucciones personalizadas al final

## 🔧 Requisitos previos:

- PHP 7.4 o superior
- Node.js y npm instalados
- Proyecto iaTrade CRM completo
- Acceso a línea de comandos

## 📤 Después del instalador:

1. **Sube la carpeta `deployment-ready`** completa a tu public_html
2. **Configura tu base de datos** con los datos que proporcionaste
3. **Importa el esquema** de base de datos
4. **¡Listo!** Tu aplicación estará funcionando

## 🆘 Solución de problemas:

### Error: "Node.js no encontrado"
```bash
# Instala Node.js desde: https://nodejs.org
# O verifica que esté en el PATH
```

### Error: "PHP version"
```bash
# Actualiza PHP a 7.4 o superior
```

### Error: "npm install failed"
```bash
# Ejecuta manualmente:
cd frontend
npm install
```

## 🎉 ¡Eso es todo!

Con este instalador, desplegar tu aplicación es tan fácil como:
1. Ejecutar `deploy-quick.bat`
2. Responder unas preguntas
3. Subir los archivos generados
4. ¡Disfrutar tu aplicación en producción!

---

**¿Necesitas ayuda?** Revisa el archivo `INSTRUCCIONES_DESPLIEGUE.txt` que se genera automáticamente con tu configuración específica.