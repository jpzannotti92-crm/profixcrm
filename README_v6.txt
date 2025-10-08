PROFIXCRM RELEASE V6
====================
Fecha: 2024
Versión: 6.0

DESCRIPCIÓN
-----------
Release v6 incluye correcciones críticas para errores 500 y endpoints faltantes en producción.

CONTENIDO DEL RELEASE
---------------------
1. ENDPOINTS DE ADMINISTRADOR (Nuevos/Corregidos)
   - public/api/auth/reset_admin.php
   - public/api/auth/create_admin.php
   - api/auth/reset_admin.php (alias)
   - api/auth/create_admin.php (alias)

2. SCRIPTS DE DIAGNÓSTICO
   - diagnostic.php - Diagnóstico completo del sistema
   - test_api.php - Pruebas de todos los endpoints
   - validate_config.php - Validador de configuración
   - post_install_validation.php - Validación post-instalación

3. DOCUMENTACIÓN
   - README_v6.txt (este archivo)

INSTALACIÓN
-----------
1. Subir el archivo ZIP al servidor de producción
2. Extraer en el directorio raíz del proyecto:
   unzip spin2pay_v6.zip

3. Establecer permisos:
   chmod 755 *.php
   chmod 755 public/api/auth/*.php
   chmod 755 api/auth/*.php

4. Configurar token de seguridad (opcional):
   echo "tu_token_secreto" > .admin_token
   # O como variable de entorno:
   export ADMIN_TOKEN="tu_token_secreto"

USO DE LOS SCRIPTS
------------------

1. VALIDACIÓN DE CONFIGURACIÓN:
   php validate_config.php
   # Identifica problemas de conexión a base de datos

2. VALIDACIÓN POST-INSTALACIÓN:
   php post_install_validation.php
   # Verifica que todos los componentes de V6 estén correctamente instalados

3. DIAGNÓSTICO COMPLETO:
   php diagnostic.php
   # Revisa toda la instalación

4. PRUEBAS DE API:
   php test_api.php
   # Ejecuta pruebas en todos los endpoints

USO DE ENDPOINTS DE ADMINISTRADOR
----------------------------------

1. CREAR/ACTIVAR ADMINISTRADOR:
   # Con role=all para asignar todos los roles
   curl -X POST "https://tudominio.com/api/auth/create_admin.php" \\
     -H "Content-Type: application/x-www-form-urlencoded" \\
     -d "token=tu_token_secreto&username=jpzannotti92&email=jpzannotti92@gmail.com&password=Jean123@&role=all&first_name=Juan&last_name=Perez"

   # O por GET
   curl "https://tudominio.com/api/auth/create_admin.php?token=tu_token_secreto&username=jpzannotti92&email=jpzannotti92@gmail.com&password=Jean123@&role=all"

2. RESETEAR CONTRASEÑA DE ADMIN:
   curl -X POST "https://tudominio.com/api/auth/reset_admin.php" \\
     -H "Content-Type: application/x-www-form-urlencoded" \\
     -d "token=tu_token_secreto&username=admin&new_password=NuevaContraseña123@"

SOLUCIÓN DE PROBLEMAS
---------------------

SECUENCIA RECOMENDADA PARA CORREGIR ERRORES EN PRODUCCIÓN:

1. PRIMERO: Validar configuración
   php validate_config.php
   # Corregir cualquier problema de conexión a BD

2. SEGUNDO: Verificar instalación de V6
   php post_install_validation.php
   # Asegurar que todos los archivos estén presentes

3. TERCERO: Ejecutar diagnóstico completo
   php diagnostic.php
   # Identificar problemas restantes

4. CUARTO: Probar endpoints
   php test_api.php
   # Verificar que todos los endpoints funcionen

ERRORES COMUNES Y SOLUCIONES:

1. ERROR 500 EN ENDPOINTS PRINCIPALES:
   - Ejecutar validate_config.php
   - Verificar credenciales de BD en .env.production
   - Asegurar que las tablas existan

2. ERROR 404 EN ENDPOINTS DE ADMIN:
   - Verificar que los archivos estén en las rutas correctas
   - Revisar configuración de Apache/Nginx
   - Ejecutar post_install_validation.php

3. ERROR DE CONEXIÓN A BD:
   - Revisar .env.production o config/config.php
   - Verificar que el usuario de BD tenga permisos
   - Asegurar que MySQL esté ejecutándose

4. PERMISOS DE ARCHIVOS:
   - chmod 755 para archivos PHP
   - chmod 777 para directorios logs/, uploads/

SEGURIDAD
---------
- SIEMPRE usar HTTPS en producción
- Cambiar el token de seguridad por defecto
- No compartir credenciales de administrador
- Revisar logs regularmente

SOPORTE
-------
Para reportar problemas:
1. Ejecutar todos los scripts de validación
2. Guardar los resultados de los logs
3. Incluir información del servidor (PHP versión, MySQL versión)
4. Contactar con el equipo de desarrollo

HISTORIAL DE CAMBIOS
-------------------
v6.0 - Correcciones críticas para producción
     - Nuevos endpoints de administrador
     - Scripts de validación mejorados
     - Solución para errores 500
     - Validación post-instalación

¡IMPORTANTE!
------------
Este release SOLUCIONA los problemas identificados en producción.
Ejecutar TODOS los scripts de validación antes de usar el sistema.