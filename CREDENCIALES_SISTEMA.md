# Credenciales del Sistema - ProFix CRM

## Usuario Administrador

**Usuario:** `admin`  
**Contraseña:** `password`  
**Email:** `admin@iatrade.com`

## Endpoints de Autenticación

- **Login:** `POST /api/auth/login.php`
- **Perfil de Usuario:** `GET /api/user-permissions.php?action=user-profile`
- **Logout:** `POST /api/auth/logout.php`

## Estructura de Login Request

```json
{
    "username": "admin",
    "password": "password"
}
```

## Estructura de Login Response (Exitoso)

```json
{
    "success": true,
    "message": "Login exitoso",
    "token": "JWT_TOKEN_HERE",
    "user": {
        "id": 1,
        "username": "admin",
        "email": "admin@iatrade.com",
        "first_name": "Admin",
        "last_name": "System",
        "status": "active",
        "roles": ["super_admin"],
        "permissions": ["users.view", "users.create", "users.edit", "users.delete", ...]
    },
    "expires_in": 3600
}
```

## Notas Importantes

1. El campo de contraseña en la base de datos se llama `password_hash`, no `password`
2. La contraseña actual del admin es `password` (no `admin123`)
3. El token JWT debe incluirse en el header `Authorization: Bearer TOKEN` para requests autenticados
4. El usuario admin tiene rol `super_admin` con acceso completo al sistema

## Fecha de Verificación

**Última verificación:** 26 de septiembre de 2025  
**Estado:** ✅ Funcionando correctamente