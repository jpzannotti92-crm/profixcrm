@echo off
echo.
echo ========================================
echo  iaTrade CRM - Instalador de Despliegue
echo ========================================
echo.
echo Este script preparara tu aplicacion para produccion
echo automaticamente. Solo necesitas seguir las instrucciones.
echo.
pause
echo.
echo Ejecutando instalador...
echo.
php deploy-installer.php
echo.
echo.
echo ========================================
echo  Instalacion completada!
echo ========================================
echo.
echo Los archivos listos para subir estan en:
echo %CD%\deployment-ready
echo.
echo Lee las instrucciones en:
echo %CD%\deployment-ready\INSTRUCCIONES_DESPLIEGUE.txt
echo.
pause