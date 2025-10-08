# Plantillas y ejemplos de .htaccess

Este directorio contiene variantes de `.htaccess` usadas como referencia para distintos escenarios (mínimo, backup, subcarpeta, producción, public_html).

No son archivos activos de configuración. Mantenerlos aquí evita confusiones y posibles conflictos en entornos locales y de producción.

Archivos activos recomendados:
- `/.htaccess` en la raíz (básico: seguridad mínima y redirecciones si aplica)
- `/public/.htaccess` (SPA, ErrorDocument 404/403, cabeceras, protección de sensibles)

Si se necesita habilitar una plantilla, copiar su contenido en el `.htaccess` correspondiente y validar en entorno controlado.