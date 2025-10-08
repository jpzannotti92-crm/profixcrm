# Archivos Necesarios para Producción - iaTrade CRM

## 📋 Resumen
Esta guía detalla los archivos que deben subirse a producción para que el sistema iaTrade CRM funcione correctamente con el nuevo frontend React.

## 🚀 Archivos Principales del Backend (PHP)

### 1. Directorio `public/` (COMPLETO)
```
public/
├── api/
│   ├── auth/
│   │   ├── index.php
│   │   ├── logout.php
│   │   └── profile.php
│   ├── leads/
│   │   ├── index.php
│   │   ├── create.php
│   │   ├── update.php
│   │   ├── delete.php
│   │   ├── activities.php
│   │   ├── assign.php
│   │   └── bulk-assign.php
│   ├── users/
│   │   ├── index.php
│   │   ├── create.php
│   │   ├── update.php
│   │   └── delete.php
│   ├── roles/
│   │   ├── index.php
│   │   ├── create.php
│   │   ├── update.php
│   │   └── delete.php
│   ├── desks/
│   │   ├── index.php
│   │   ├── create.php
│   │   ├── update.php
│   │   └── delete.php
│   ├── trading/
│   │   ├── accounts.php
│   │   ├── deposits-withdrawals.php
│   │   └── positions.php
│   ├── states/
│   │   └── index.php
│   ├── config.php (ACTUALIZADO - formato JSON corregido)
│   └── middleware/
│       └── auth.php
└── index.php
```

### 2. Directorio `config/`
```
config/
├── config.php
└── database.php
```

### 3. Directorio `src/`
```
src/
├── Controllers/
├── Models/
├── Services/
├── Middleware/
└── Utils/
```

### 4. Archivos de Configuración Raíz
```
.htaccess (configuración Apache)
composer.json
composer.lock
index.php
```

## 🎨 Frontend React (Construido)

### Directorio `frontend/dist/` (Resultado del build)
```
frontend/dist/
├── index.html
├── assets/
│   ├── index-8c92729e.css
│   ├── index-ddc99694.js
│   └── index-ddc99694.js.map
```

## 📁 Estructura de Producción Recomendada

### Opción 1: Frontend y Backend Separados
```
/public_html/
├── api/                    # Backend PHP
│   ├── public/
│   ├── config/
│   ├── src/
│   ├── vendor/
│   ├── .htaccess
│   └── index.php
└── app/                    # Frontend React
    ├── index.html
    └── assets/
```

### Opción 2: Frontend Integrado (Recomendado)
```
/public_html/
├── index.html              # Frontend React principal
├── assets/                 # Assets del frontend
├── api/                    # Backend PHP
├── config/
├── src/
├── vendor/
└── .htaccess
```

## 🔧 Archivos de Configuración Críticos

### 1. `.htaccess` (Configuración Apache)
- Maneja el routing del frontend React
- Configura las rutas de la API
- Configuración de CORS si es necesario

### 2. `config/config.php`
- Configuración de base de datos
- URLs de producción
- Configuración de CORS

### 3. `public/api/config.php` (ACTUALIZADO)
- **IMPORTANTE**: Este archivo fue actualizado para devolver el formato JSON correcto
- Debe incluir las URLs de producción correctas

## 🗄️ Base de Datos

### Archivos SQL Necesarios
```
database/
├── complete_schema.sql     # Esquema completo de la base de datos
├── initial_data.sql        # Datos iniciales (roles, permisos, etc.)
└── migrations/             # Migraciones adicionales si las hay
```

## 📦 Dependencias

### Backend (PHP)
```bash
# Ejecutar en el servidor de producción
composer install --no-dev --optimize-autoloader
```

### Frontend (React)
- Los archivos ya están construidos en `frontend/dist/`
- No requiere Node.js en producción

## ⚙️ Configuración de Servidor

### Variables de Entorno
Crear archivo `.env` en producción con:
```
DB_HOST=localhost
DB_NAME=iatrade_crm_prod
DB_USER=tu_usuario_db
DB_PASS=tu_password_db
APP_ENV=production
APP_URL=https://tu-dominio.com
```

### Permisos de Archivos
```bash
# Permisos recomendados
chmod 755 public/
chmod 644 public/api/*.php
chmod 600 config/*.php
chmod 600 .env
```

## 🚨 Archivos Críticos Actualizados

### 1. `public/api/config.php`
**ESTADO**: ✅ ACTUALIZADO
- Ahora devuelve formato JSON correcto: `{ success: true, data: {...} }`
- Compatible con el frontend React

### 2. `frontend/vite.config.ts`
**ESTADO**: ✅ ACTUALIZADO
- Proxy configurado correctamente
- Reescritura de rutas `/api` → `/public/api`

## 📋 Lista de Verificación Pre-Producción

- [ ] Construir frontend React (`npm run build`)
- [ ] Verificar configuración de base de datos
- [ ] Actualizar URLs en `config/config.php`
- [ ] Configurar `.htaccess` para producción
- [ ] Instalar dependencias PHP (`composer install`)
- [ ] Importar esquema de base de datos
- [ ] Configurar permisos de archivos
- [ ] Probar endpoints de API
- [ ] Verificar funcionamiento del frontend

## 🔗 URLs de Producción

### Frontend
- Página principal: `https://tu-dominio.com/`
- Login (SPA): `https://tu-dominio.com/assets/#/auth/login`

### Frontend en subcarpeta (ejemplo: `profixcrm`)
- Página principal: `https://tu-dominio.com/profixcrm/`
- Login (SPA): `https://tu-dominio.com/profixcrm/assets/#/auth/login`

### API Backend
- Configuración: `https://tu-dominio.com/api/config.php`
- Aplicación: `https://tu-dominio.com/`
- Otros endpoints: `https://tu-dominio.com/api/...`

## 📞 Soporte

Si encuentras problemas durante el despliegue:
1. Verificar logs del servidor web
2. Comprobar configuración de base de datos
3. Validar permisos de archivos
4. Revisar configuración de `.htaccess`

---
**Nota**: Esta guía asume que el servidor de producción tiene PHP 7.4+ y soporte para Apache con mod_rewrite habilitado.