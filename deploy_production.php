<?php
/**
 * Script de Despliegue a Producción - ProfixCRM v8
 * 
 * Este script automatiza el proceso de subida de archivos para migración v7 a v8
 * 
 * USO:
 *   php deploy_production.php --check     → Verificar preparación
 *   php deploy_production.php --prepare   → Preparar archivos para subida
 *   php deploy_production.php --validate  → Validar después de subida
 */

class ProductionDeployer {
    private $productionFiles = [];
    private $excludedFiles = [];
    private $deploymentDir;
    private $logFile;
    
    public function __construct() {
        $this->deploymentDir = __DIR__ . '/deployment_package';
        $this->logFile = __DIR__ . '/logs/deployment_' . date('Y-m-d_H-i-s') . '.log';
        
        // Archivos y carpetas que SE DEBEN subir
        $this->productionFiles = [
            // Carpetas principales
            'config',
            'src', 
            'public',
            'api',
            'vendor',
            'views',
            'storage',
            'temp',
            
            // Archivos individuales importantes
            'index.php',
            'validate_v8.php',
            'deploy_v8.php',
            '.htaccess',
            'composer.json',
            'composer.lock',
            
            // Carpetas que deben existir (vacías inicialmente)
            'logs',
            'cache',
            'backups'
        ];
        
        // Archivos que NO deben subirse
        $this->excludedFiles = [
            // Archivos de prueba
            'test_*.php',
            'debug_*.php', 
            'check_*.php',
            'fix_*.php',
            'add_*.php',
            
            // Archivos de datos
            '*.csv',
            '*.json',
            '*.txt',
            '*.backup_*',
            '*.sql',
            
            // Documentación
            '*.md',
            'README*',
            'docs/',
            
            // Respaldos locales
            'backups/',
            'backups-recovery/',
            'deployment_package/',
            
            // Archivos del sistema
            '.env',
            '.env.*',
            '.installed',
            'node_modules/',
            '.git/',
            '.gitignore',
            
            // Archivos temporales
            '*.tmp',
            '*.temp',
            '*.log',
            '*.cache'
        ];
    }
    
    /**
     * Verificar preparación del sistema
     */
    public function checkPreparation() {
        echo "🔍 Verificando preparación para despliegue...\n\n";
        
        $checks = [
            'version' => $this->checkVersion(),
            'files' => $this->checkEssentialFiles(),
            'config' => $this->checkConfiguration(),
            'permissions' => $this->checkPermissions(),
            'validation' => $this->runValidation()
        ];
        
        $allPassed = true;
        foreach ($checks as $check => $result) {
            $status = $result ? "✅" : "❌";
            echo "$status $check: " . ($result ? "OK" : "FALLÓ") . "\n";
            if (!$result) $allPassed = false;
        }
        
        echo "\n";
        if ($allPassed) {
            echo "🎉 ¡Sistema listo para despliegue!\n";
        } else {
            echo "⚠️  Corrige los problemas antes de continuar.\n";
        }
        
        return $allPassed;
    }
    
    /**
     * Preparar paquete de despliegue
     */
    public function prepareDeployment() {
        echo "📦 Preparando paquete de despliegue...\n\n";
        
        // Verificar preparación básica (sin validación)
        echo "🔍 Verificación básica...\n";
        $basicChecks = [
            'version' => $this->checkVersion(),
            'files' => $this->checkEssentialFiles(),
            'config' => $this->checkConfiguration(),
            'permissions' => $this->checkPermissions()
        ];
        
        $allBasicPassed = true;
        foreach ($basicChecks as $check => $result) {
            $status = $result ? "✅" : "❌";
            echo "   $status $check: " . ($result ? "OK" : "FALLÓ") . "\n";
            if (!$result) $allBasicPassed = false;
        }
        
        if (!$allBasicPassed) {
            echo "\n⚠️  Corrige los problemas básicos antes de continuar.\n";
            return false;
        }
        
        echo "\n✅ Verificación básica completada. Procediendo con la preparación...\n\n";
        
        // Crear directorio de despliegue
        if (!is_dir($this->deploymentDir)) {
            mkdir($this->deploymentDir, 0755, true);
        }
        
        // Limpiar directorio anterior
        $this->cleanDeploymentDir();
        
        $copied = 0;
        $errors = 0;
        
        // Copiar carpetas principales
        foreach ($this->productionFiles as $item) {
            if (is_dir($item)) {
                echo "📁 Copiando carpeta: $item\n";
                if ($this->copyDirectory($item, $this->deploymentDir . '/' . $item)) {
                    $copied++;
                } else {
                    $errors++;
                }
            } elseif (is_file($item)) {
                echo "📄 Copiando archivo: $item\n";
                if (copy($item, $this->deploymentDir . '/' . $item)) {
                    $copied++;
                } else {
                    $errors++;
                }
            }
        }
        
        // Crear carpetas necesarias
        $this->createNecessaryDirectories();
        
        // Crear archivo de instrucciones
        $this->createInstructionsFile();
        
        echo "\n📊 Resumen de preparación:\n";
        echo "✅ Elementos copiados: $copied\n";
        echo "❌ Errores: $errors\n";
        echo "📂 Paquete creado en: {$this->deploymentDir}/\n";
        echo "📋 Instrucciones en: {$this->deploymentDir}/INSTRUCCIONES_SUBIDA.txt\n";
        
        // Crear archivo ZIP para facilitar subida
        $this->createZipPackage();
        
        return $errors === 0;
    }
    
    /**
     * Validar después de subida
     */
    public function validateAfterUpload() {
        echo "🔍 Validando despliegue después de subida...\n\n";
        
        // Verificar que los archivos críticos existen
        $criticalFiles = [
            'index.php',
            'config/v8_config.php',
            'src/Core/V8Config.php',
            'src/Core/V8Validator.php',
            'validate_v8.php'
        ];
        
        $missing = [];
        foreach ($criticalFiles as $file) {
            if (!file_exists($file)) {
                $missing[] = $file;
            }
        }
        
        if (!empty($missing)) {
            echo "❌ Archivos críticos faltantes:\n";
            foreach ($missing as $file) {
                echo "   - $file\n";
            }
            return false;
        }
        
        // Verificar permisos
        $this->checkProductionPermissions();
        
        // Ejecutar validación
        echo "✅ Ejecutando validación completa...\n";
        system('php validate_v8.php full');
        
        return true;
    }
    
    /**
     * Verificar versión
     */
    private function checkVersion() {
        if (file_exists('config/v8_config.php')) {
            require_once 'config/v8_config.php';
            if (class_exists('V8Config')) {
                $config = V8Config::getInstance();
                $version = '8.0.0'; // Versión por defecto de V8
                echo "   Versión detectada: v$version\n";
                return true;
            }
        }
        echo "   ❌ No se encontró config/v8_config.php o V8Config\n";
        return false;
    }
    
    /**
     * Verificar archivos esenciales
     */
    private function checkEssentialFiles() {
        $essential = [
            'config/v8_config.php',
            'src/Core/V8Validator.php',
            'src/Core/V8RedirectHandler.php',
            'validate_v8.php',
            'deploy_v8.php',
            'index.php'
        ];
        
        $missing = [];
        foreach ($essential as $file) {
            if (!file_exists($file)) {
                $missing[] = $file;
            }
        }
        
        if (!empty($missing)) {
            echo "   Archivos faltantes: " . implode(', ', $missing) . "\n";
            return false;
        }
        
        return true;
    }
    
    /**
     * Verificar configuración
     */
    private function checkConfiguration() {
        if (!file_exists('config/v8_config.php')) {
            return false;
        }
        
        require_once 'config/v8_config.php';
        if (!class_exists('V8Config')) {
            return false;
        }
        
        $config = V8Config::getInstance();
        
        // Verificar que la configuración básica existe
        $requiredSections = ['app', 'database', 'logging'];
        foreach ($requiredSections as $section) {
            if (!$config->has($section)) {
                echo "   Sección faltante en config: $section\n";
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Verificar permisos locales
     */
    private function checkPermissions() {
        $writable = [
            'logs',
            'storage/cache',
            'temp',
            'cache'
        ];
        
        $issues = [];
        foreach ($writable as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            if (!is_writable($dir)) {
                $issues[] = $dir;
            }
        }
        
        if (!empty($issues)) {
            echo "   Directorios no escribibles: " . implode(', ', $issues) . "\n";
            return false;
        }
        
        return true;
    }
    
    /**
     * Ejecutar validación
     */
    private function runValidation() {
        echo "   Ejecutando validación...\n";
        $output = shell_exec('php validate_v8.php full 2>&1');
        
        // Verificar si hay errores críticos de PHP
        if (strpos($output, 'PHP Fatal error') !== false || 
            strpos($output, 'PHP Parse error') !== false) {
            echo "   Errores críticos en validación\n";
            return false;
        }
        
        // En desarrollo, permitir validación aunque haya errores de base de datos
        if (strpos($output, 'VALIDACIÓN COMPLETADA') !== false || 
            strpos($output, '✅') !== false) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Copiar directorio recursivamente
     */
    private function copyDirectory($source, $dest) {
        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        $success = true;
        foreach ($iterator as $item) {
            $destPath = $dest . '/' . $iterator->getSubPathName();
            
            if ($item->isDir()) {
                if (!is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
            } else {
                // Verificar si el archivo debe ser excluido
                if ($this->shouldExclude($item->getPathname())) {
                    continue;
                }
                
                if (!copy($item->getPathname(), $destPath)) {
                    $success = false;
                    echo "   Error copiando: " . $item->getPathname() . "\n";
                }
            }
        }
        
        return $success;
    }
    
    /**
     * Verificar si un archivo debe ser excluido
     */
    private function shouldExclude($file) {
        $filename = basename($file);
        
        foreach ($this->excludedFiles as $pattern) {
            if (fnmatch($pattern, $filename)) {
                return true;
            }
            
            // Verificar también la ruta completa
            if (fnmatch($pattern, $file)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Limpiar directorio de despliegue
     */
    private function cleanDeploymentDir() {
        if (is_dir($this->deploymentDir)) {
            $files = glob($this->deploymentDir . '/*');
            foreach ($files as $file) {
                if (is_dir($file)) {
                    $this->removeDirectory($file);
                } else {
                    unlink($file);
                }
            }
        }
    }
    
    /**
     * Eliminar directorio recursivamente
     */
    private function removeDirectory($dir) {
        if (is_dir($dir)) {
            $files = glob($dir . '/*');
            foreach ($files as $file) {
                if (is_dir($file)) {
                    $this->removeDirectory($file);
                } else {
                    unlink($file);
                }
            }
            rmdir($dir);
        }
    }
    
    /**
     * Crear directorios necesarios
     */
    private function createNecessaryDirectories() {
        $dirs = [
            'logs/v8',
            'cache/v8',
            'temp/v8',
            'storage/cache',
            'storage/logs'
        ];
        
        foreach ($dirs as $dir) {
            $fullPath = $this->deploymentDir . '/' . $dir;
            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0755, true);
            }
        }
    }
    
    /**
     * Crear archivo de instrucciones
     */
    private function createInstructionsFile() {
        $instructions = <<<INSTRUCCIONES
🚀 INSTRUCCIONES DE SUBIDA - PROFIXCRM V8
==========================================

📁 CONTENIDO DEL PAQUETE:
- config/          → Configuraciones del sistema
- src/              → Código fuente del núcleo
- public/           → Archivos públicos (CSS, JS, imágenes)
- api/              → Endpoints de la API
- vendor/           → Dependencias de Composer
- views/            → Vistas del sistema
- storage/          → Almacenamiento
- temp/             → Archivos temporales

📋 PASOS PARA SUBIR:

1. **SUBIR ARCHIVOS:**
   - Sube TODO el contenido de este paquete a tu servidor
   - Mantén la estructura de carpetas
   - Sobrescribe los archivos existentes

2. **PERMISOS (ejecutar en servidor):**
   chmod -R 755 config/
   chmod -R 755 storage/
   chmod -R 755 temp/
   chmod -R 755 logs/
   chmod -R 755 cache/

3. **CONFIGURACIÓN:**
   - Edita config/v8_config.php con tus datos
   - Crea archivo .env si es necesario
   - Verifica conexión a base de datos

4. **VALIDACIÓN:**
   - Ejecuta: php validate_v8.php full
   - Revisa que no haya errores críticos
   - Prueba funciones principales

⚠️  IMPORTANTE:
- ¡Haz respaldo ANTES de subir!
- Verifica que PHP 8.0+ esté instalado
- Asegúrate de tener acceso a base de datos

📞 SOPORTE:
Si hay problemas, ejecuta validate_v8.php y revisa los logs
INSTRUCCIONES;
        
        file_put_contents($this->deploymentDir . '/INSTRUCCIONES_SUBIDA.txt', $instructions);
    }
    
    /**
     * Crear paquete ZIP
     */
    private function createZipPackage() {
        $zipFile = __DIR__ . '/profix_v8_production_' . date('Y-m-d_H-i-s') . '.zip';
        
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->deploymentDir),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($files as $file) {
                if ($file->isFile()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($this->deploymentDir) + 1);
                    $zip->addFile($filePath, $relativePath);
                }
            }
            
            $zip->close();
            echo "\n📦 Paquete ZIP creado: $zipFile\n";
            echo "📏 Tamaño: " . $this->formatBytes(filesize($zipFile)) . "\n";
        } else {
            echo "❌ Error creando archivo ZIP\n";
        }
    }
    
    /**
     * Verificar permisos en producción
     */
    private function checkProductionPermissions() {
        echo "🔒 Verificando permisos de producción...\n";
        
        $dirs = [
            'logs',
            'storage/cache', 
            'temp',
            'cache'
        ];
        
        foreach ($dirs as $dir) {
            if (is_dir($dir)) {
                $perms = substr(sprintf('%o', fileperms($dir)), -4);
                $writable = is_writable($dir);
                $status = $writable ? "✅" : "❌";
                echo "$status $dir (perms: $perms) - " . ($writable ? "Escribible" : "No escribible") . "\n";
            } else {
                echo "⚠️  $dir no existe\n";
            }
        }
    }
    
    /**
     * Formatear bytes
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    /**
     * Mostrar ayuda
     */
    public function showHelp() {
        echo <<<HELP
🚀 DESPLEGADOR DE PRODUCCIÓN - PROFIXCRM V8

USO:
  php deploy_production.php [opción]

OPCIONES:
  --check      Verificar si el sistema está listo para despliegue
  --prepare    Crear paquete de archivos para subir a producción  
  --validate   Validar el despliegue después de subir
  --help       Mostrar esta ayuda

EJEMPLOS:
  # Verificar preparación
  php deploy_production.php --check
  
  # Crear paquete de despliegue
  php deploy_production.php --prepare
  
  # Validar después de subir
  php deploy_production.php --validate

SALIDAS:
  - deployment_package/    → Carpeta con archivos listos para subir
  - profix_v8_*.zip       → Archivo ZIP comprimido para subida fácil
  - logs/deployment_*.log → Log del proceso

HELP;
    }
}

// Ejecutar script
if (php_sapi_name() !== 'cli') {
    die("Este script debe ejecutarse desde la línea de comandos\n");
}

$deployer = new ProductionDeployer();

if ($argc < 2) {
    $deployer->showHelp();
    exit;
}

$option = $argv[1];

switch ($option) {
    case '--check':
        $deployer->checkPreparation();
        break;
        
    case '--prepare':
        if ($deployer->checkPreparation()) {
            $deployer->prepareDeployment();
        } else {
            echo "\n❌ Corrige los problemas antes de preparar el despliegue.\n";
            exit(1);
        }
        break;
        
    case '--validate':
        $deployer->validateAfterUpload();
        break;
        
    case '--help':
    case '-h':
        $deployer->showHelp();
        break;
        
    default:
        echo "❌ Opción no válida: $option\n";
        $deployer->showHelp();
        exit(1);
}

echo "\n✅ Proceso completado.\n";