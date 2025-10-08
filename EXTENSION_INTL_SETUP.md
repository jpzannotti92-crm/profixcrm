# Configuración de la Extensión PHP Intl en XAMPP

## ¿Qué es la extensión Intl?

La extensión **Intl** (Internationalization) proporciona funcionalidades de internacionalización para PHP, incluyendo:
- Formateo de números y monedas
- Formateo de fechas y horas
- Collation y comparación de cadenas
- Transliteración de caracteres
- Soporte para múltiples idiomas y locales

## ¿Por qué es necesaria?

Esta aplicación utiliza la extensión `intl` para:
- Formatear números y monedas en diferentes locales (español, inglés, etc.)
- Manejar fechas y horas según la configuración regional
- Procesar correctamente caracteres especiales y acentos
- Soporte para múltiples idiomas en la interfaz

## Instrucciones para XAMPP en Windows

### Paso 1: Localizar el archivo php.ini

1. Abre el **Panel de Control de XAMPP**
2. Haz clic en **Config** junto a Apache
3. Selecciona **PHP (php.ini)**

O alternativamente, busca el archivo en:
```
C:\xampp\php\php.ini
```

### Paso 2: Habilitar la extensión intl

1. Abre el archivo `php.ini` en un editor de texto
2. Busca la línea que contiene:
   ```
   ;extension=intl
   ```
3. Elimina el punto y coma (`;`) al inicio de la línea para descomentarla:
   ```
   extension=intl
   ```
4. Guarda el archivo

### Paso 3: Reiniciar Apache

1. En el Panel de Control de XAMPP, haz clic en **Stop** junto a Apache
2. Espera unos segundos
3. Haz clic en **Start** para reiniciar Apache

### Paso 4: Verificar la instalación

Ejecuta el siguiente comando en la terminal para verificar que la extensión está cargada:

```bash
php -m | findstr intl
```

O crea un archivo PHP temporal con el siguiente contenido:

```php
<?php
if (extension_loaded('intl')) {
    echo "✅ Extensión intl está cargada correctamente\n";
    echo "Versión ICU: " . INTL_ICU_VERSION . "\n";
} else {
    echo "❌ Extensión intl NO está cargada\n";
}
?>
```

## Solución de Problemas

### Error: "No se puede cargar la extensión intl"

Si recibes este error, verifica que:

1. **Los archivos DLL están presentes**: Asegúrate de que existen estos archivos en `C:\xampp\php\ext\`:
   - `php_intl.dll`

2. **Las librerías ICU están disponibles**: Verifica que existen en `C:\xampp\php\`:
   - `icudt*.dll`
   - `icuin*.dll` 
   - `icuio*.dll`
   - `icuuc*.dll`

3. **PATH del sistema**: Asegúrate de que `C:\xampp\php` está en tu variable de entorno PATH

### Si los archivos no existen

Si faltan los archivos DLL de ICU, puedes:

1. **Reinstalar XAMPP**: La forma más segura es reinstalar XAMPP completo
2. **Descargar manualmente**: Descargar los archivos ICU compatibles con tu versión de PHP

### Verificar versión de PHP

Ejecuta este comando para ver tu versión de PHP:
```bash
php -v
```

## Configuración Adicional (Opcional)

Para configuraciones avanzadas, puedes agregar estas líneas al final de tu `php.ini`:

```ini
; Configuración de Intl
intl.default_locale = es_ES
intl.use_exceptions = 1
```

## Verificación Final

Una vez completados todos los pasos, el asistente de configuración debería mostrar:

✅ **intl**: Extensión cargada correctamente

## Soporte

Si continúas teniendo problemas:

1. Verifica que estás editando el archivo `php.ini` correcto
2. Asegúrate de reiniciar Apache después de los cambios
3. Revisa los logs de error de Apache en `C:\xampp\apache\logs\error.log`
4. Considera reinstalar XAMPP si los archivos ICU están corruptos o faltantes

---

**Nota**: Esta extensión es ahora **crítica** para el funcionamiento de la aplicación. El asistente de configuración no permitirá continuar sin ella.