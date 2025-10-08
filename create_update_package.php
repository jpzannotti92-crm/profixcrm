<?php
/**
 * Script para crear paquete de actualización por versiones
 * Genera un ZIP con solo los archivos que han cambiado entre versiones
 */

class UpdatePackageCreator {
    
    private $version;
    private $baseDir;
    private $updateFiles = [];
    private $excludedPatterns = [
        '*.log',
        '*.cache',
        'temp/*',
        'logs/*',
        'cache/*',
        'storage/logs/*',
        'storage/cache/*',
        'storage/sessions/*',
        'backups/*',
        'uploads/*',
        'node_modules/*',
        '.git/*',
        '.env',
        '*.zip',
        'create_update_package.php',
        'prepare_package.php',
        'deploy_production.php',
        'deployment_package/*',
        'deploy/releases/*'
    ];
    
    public function __construct($version = 'v8') {
        $this->version = $version;
        $this->baseDir = __DIR__;
    }
    
    /**
     * Identificar archivos de actualización específicos por versión
     */
    private function identifyUpdateFiles() {
        echo "🔍 Identificando archivos de actualización para {$this->version}...\n";
        
        // Archivos específicos de V8 (basados en estructura real)
        if ($this->version === 'v8') {
            $this->updateFiles = [
                // Configuración
                'config/v8_config.php',
                'config/config.php',
                'config/.htaccess',
                
                // Núcleo del sistema
                'src/Core/Config.php',
                'src/Core/ErrorHandler.php',
                'src/Core/Request.php',
                'src/Core/Response.php',
                'src/Core/ResponseFormatter.php',
                'src/Core/Router.php',
                'src/Core/V8RedirectHandler.php',
                'src/Core/V8Validator.php',
                
                // Controladores actualizados
                'src/Controllers/AuthController.php',
                'src/Controllers/BaseController.php',
                'src/Controllers/DashboardController.php',
                
                // Modelos actualizados
                'src/Models/BaseModel.php',
                'src/Models/DailyUserMetric.php',
                'src/Models/Desk.php',
                'src/Models/DeskState.php',
                'src/Models/Lead.php',
                'src/Models/LeadStatusHistory.php',
                'src/Models/Permission.php',
                'src/Models/Role.php',
                'src/Models/StateTransition.php',
                'src/Models/User.php',
                
                // Middleware
                'src/Middleware/AuthMiddleware.php',
                'src/Middleware/CorsMiddleware.php',
                'src/Middleware/DeskAccessMiddleware.php',
                'src/Middleware/RBACMiddleware.php',
                
                // Database
                'src/Database/Connection.php',
                'src/Database/MySQLCompatibility.php',
                
                // Helpers
                'src/helpers.php',
                'src/helpers/UrlHelper.php',
                
                // API endpoints
                'api/leads.php',
                'api/users.php',
                'api/auth/login.php',
                'api/dashboard.php',
                'api/desks.php',
                'api/roles.php',
                'api/config.php',
                'api/health.php',
                'api/index.php',
                
                // Frontend actualizado
                'public/js/app.js',
                'public/js/modules/leads.js',
                'public/js/modules/users.js',
                'public/index.php',
                'public/.htaccess',
                'public/router.php',
                
                // Archivos principales
                'index.php',
                'validate_v8.php',
                'deploy_v8.php',
                '.htaccess',
                
                // Dependencias
                'composer.json',
                'composer.lock',
                'vendor/autoload.php',
                
                // Documentación
                'README.md',
                'README_V7.txt'
            ];
        }
        
        echo "✓ Identificados " . count($this->updateFiles) . " archivos de actualización\n";
    }
    
    /**
     * Verificar si un archivo debe ser excluido
     */
    private function shouldExclude($file) {
        foreach ($this->excludedPatterns as $pattern) {
            if (fnmatch($pattern, $file)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Verificar que los archivos existen
     */
    private function validateFiles() {
        echo "🔍 Validando archivos...\n";
        $missing = [];
        $valid = [];
        
        foreach ($this->updateFiles as $file) {
            $fullPath = $this->baseDir . '/' . $file;
            if (file_exists($fullPath)) {
                $valid[] = $file;
                echo "   ✓ $file\n";
            } else {
                $missing[] = $file;
                echo "   ❌ $file (no encontrado)\n";
            }
        }
        
        if (!empty($missing)) {
            echo "⚠️  Archivos faltantes: " . count($missing) . "\n";
            return false;
        }
        
        $this->updateFiles = $valid;
        return true;
    }
    
    /**
     * Crear directorio temporal para el paquete
     */
    private function createTempDir() {
        $tempDir = $this->baseDir . '/temp/update_' . $this->version . '_' . date('Y-m-d_H-i-s');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        return $tempDir;
    }
    
    /**
     * Copiar archivos al directorio temporal
     */
    private function copyFiles($tempDir) {
        echo "📁 Copiando archivos al paquete de actualización...\n";
        
        foreach ($this->updateFiles as $file) {
            $source = $this->baseDir . '/' . $file;
            $dest = $tempDir . '/' . $file;
            
            // Crear directorio destino si no existe
            $destDir = dirname($dest);
            if (!is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }
            
            if (copy($source, $dest)) {
                echo "   ✓ Copiado: $file\n";
            } else {
                echo "   ❌ Error copiando: $file\n";
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Crear archivo de instrucciones de actualización
     */
    private function createUpdateInstructions($tempDir) {
        $instructions = "# ACTUALIZACIÓN PROFIXCRM {$this->version}\n\n";
        $instructions .= "## INSTRUCCIONES DE ACTUALIZACIÓN\n\n";
        $instructions .= "### PASO 1: BACKUP\n";
        $instructions .= "```bash\n";
        $instructions .= "# Crear backup de base de datos\n";
        $instructions .= "mysqldump -u usuario -p profixcrm > backup_profixcrm_" . date('Y-m-d') . ".sql\n\n";
        $instructions .= "# Crear backup de archivos\n";
        $instructions .= "tar -czf backup_profixcrm_files_" . date('Y-m-d') . ".tar.gz public_html/\n";
        $instructions .= "```\n\n";
        
        $instructions .= "### PASO 2: SUBIR Y EXTRAER\n";
        $instructions .= "```bash\n";
        $instructions .= "# Subir archivo ZIP al servidor\n";
        $instructions .= "scp profixcrm_{$this->version}_update_" . date('Y-m-d_H-i-s') . ".zip usuario@servidor:/ruta/al/proyecto/\n\n";
        $instructions .= "# Extraer actualización\n";
        $instructions .= "unzip -o profixcrm_{$this->version}_update_" . date('Y-m-d_H-i-s') . ".zip\n";
        $instructions .= "```\n\n";
        
        $instructions .= "### PASO 3: ACTUALIZAR DEPENDENCIAS\n";
        $instructions .= "```bash\n";
        $instructions .= "composer install --no-dev --optimize-autoloader\n";
        $instructions .= "```\n\n";
        
        $instructions .= "### PASO 4: LIMPIAR CACHÉ\n";
        $instructions .= "```bash\n";
        $instructions .= "rm -rf cache/*\n";
        $instructions .= "rm -rf storage/cache/*\n";
        $instructions .= "rm -rf temp/*\n";
        $instructions .= "```\n\n";
        
        $instructions .= "### PASO 5: VALIDAR\n";
        $instructions .= "```bash\n";
        $instructions .= "php validate_v8.php\n";
        $instructions .= "```\n\n";
        
        $instructions .= "### ARCHIVOS INCLUIDOS\n";
        foreach ($this->updateFiles as $file) {
            $instructions .= "- $file\n";
        }
        
        file_put_contents($tempDir . '/ACTUALIZACION_README.md', $instructions);
        echo "✓ Creado archivo de instrucciones\n";
    }
    
    /**
     * Crear archivo ZIP del paquete
     */
    private function createZip($tempDir) {
        $zipName = 'profixcrm_' . $this->version . '_update_' . date('Y-m-d_H-i-s') . '.zip';
        $zipPath = $this->baseDir . '/' . $zipName;
        
        echo "📦 Creando archivo ZIP...\n";
        
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $this->addDirectoryToZip($zip, $tempDir, basename($tempDir));
            $zip->close();
            
            echo "✓ ZIP creado: $zipName\n";
            echo "📊 Tamaño: " . $this->formatBytes(filesize($zipPath)) . "\n";
            
            return $zipPath;
        } else {
            echo "❌ Error creando ZIP\n";
            return false;
        }
    }
    
    /**
     * Agregar directorio completo al ZIP
     */
    private function addDirectoryToZip($zip, $dir, $base) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = $base . '/' . substr($filePath, strlen($dir) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }
    }
    
    /**
     * Formatear tamaño de archivo
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
    
    /**
     * Limpiar directorio temporal
     */
    private function cleanup($tempDir) {
        if (is_dir($tempDir)) {
            $this->removeDirectory($tempDir);
            echo "🧹 Directorio temporal limpiado\n";
        }
    }
    
    /**
     * Eliminar directorio recursivamente
     */
    private function removeDirectory($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        $this->removeDirectory($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }
    
    /**
     * Ejecutar proceso completo
     */
    public function create() {
        echo "🚀 Creando paquete de actualización {$this->version}...\n\n";
        
        try {
            $this->identifyUpdateFiles();
            
            if (!$this->validateFiles()) {
                echo "\n❌ Validación fallida. No se puede crear el paquete.\n";
                return false;
            }
            
            $tempDir = $this->createTempDir();
            
            if (!$this->copyFiles($tempDir)) {
                echo "\n❌ Error copiando archivos.\n";
                $this->cleanup($tempDir);
                return false;
            }
            
            $this->createUpdateInstructions($tempDir);
            
            $zipPath = $this->createZip($tempDir);
            
            if ($zipPath) {
                echo "\n✅ Paquete de actualización creado exitosamente!\n";
                echo "📁 Archivo: " . basename($zipPath) . "\n";
                echo "📊 Tamaño: " . $this->formatBytes(filesize($zipPath)) . "\n";
            }
            
            $this->cleanup($tempDir);
            
            return $zipPath;
            
        } catch (Exception $e) {
            echo "\n❌ Error: " . $e->getMessage() . "\n";
            if (isset($tempDir)) {
                $this->cleanup($tempDir);
            }
            return false;
        }
    }
}

// Ejecutar script
if (php_sapi_name() === 'cli') {
    $creator = new UpdatePackageCreator('v8');
    $creator->create();
} else {
    echo "Este script debe ejecutarse desde la línea de comandos.\n";
}