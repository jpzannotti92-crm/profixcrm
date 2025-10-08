# Configuración Estable del Sistema - iaTrade CRM

## ⚠️ IMPORTANTE: NO MODIFICAR ESTA CONFIGURACIÓN SIN AUTORIZACIÓN

Esta documentación establece la configuración estable y definitiva del sistema. **NO realizar cambios constantes** en estos parámetros.

## Configuración del Servidor PHP

### Servidor Principal (Puerto 8000)
- **PHP Version**: 8.2.29
- **Comando**: `C:\xampp\php82\php.exe -S localhost:8000 -t public`
- **Puerto**: 8000
- **Document Root**: `public/`
- **Estado**: ✅ ACTIVO Y ESTABLE

### Configuración de Dotenv
Todos los archivos API deben usar esta configuración estándar:

```php
// Cargar variables de entorno con prioridad local
try {
    $dotenv = Dotenv\Dotenv::createMutable(__DIR__ . '/../../');
    $dotenv->overload(); // Prioridad a .env local
} catch (Exception $e) {
    // Fallback si overload() no está disponible
    $dotenv = Dotenv\Dotenv::createMutable(__DIR__ . '/../../');
    $dotenv->load();
}
```

## Archivos Actualizados con Configuración Estable

### APIs con Dotenv Configurado:
- ✅ `public/api/auth/login.php`
- ✅ `public/api/auth/verify.php` 
- ✅ `public/api/auth/logout.php`
- ✅ `public/api/leads.php`
- ✅ `public/api/roles.php`

### Pendientes de Actualizar:
- ⏳ `public/api/config.php` - Remover credenciales hardcodeadas
- ⏳ `public/api/users.php` - Verificar Dotenv loading

## Configuración de Base de Datos

### Variables de Entorno (.env)
```
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=iatrade_crm
DB_USERNAME=root
DB_PASSWORD=
```

### Configuración en config/database.php
- ✅ Lee correctamente de $_ENV
- ✅ Fallbacks apropiados configurados
- ✅ Opciones PDO optimizadas

## Frontend Configuration

### Vite Dev Server
- **Puerto**: 3000
- **Proxy API**: `http://localhost:8000/api`
- **Configuración**: `frontend/vite.config.ts`

### URLs Dinámicas
- **Base**: Detectada automáticamente por UrlHelper
- **API**: `http://localhost:8000/api` (desarrollo)
- **Frontend**: `http://localhost:3000` (desarrollo)

## Reglas de Mantenimiento

### ❌ NO HACER:
1. Cambiar constantemente la configuración del servidor
2. Modificar puertos sin documentar
3. Alterar la configuración de Dotenv establecida
4. Cambiar credenciales de BD sin actualizar .env

### ✅ HACER:
1. Mantener esta configuración estable
2. Documentar cualquier cambio necesario
3. Probar cambios en entorno separado primero
4. Actualizar esta documentación si hay cambios aprobados

## Estado Actual del Sistema

### Servidores Activos:
- ✅ PHP 8.2 Server: `localhost:8000` (PRINCIPAL)
- ✅ Vite Dev Server: `localhost:3000` (Frontend)

### Próximos Pasos (Sin Cambiar Configuración Base):
1. Completar actualización de config.php
2. Verificar credenciales de BD
3. Probar flujo de login completo
4. Validar esquema desk_users

---

**Fecha de Establecimiento**: $(Get-Date -Format "yyyy-MM-dd HH:mm:ss")
**Versión PHP**: 8.2.29
**Estado**: CONFIGURACIÓN ESTABLE - NO MODIFICAR