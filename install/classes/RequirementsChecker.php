<?php

/**
 * Verificador de requisitos del sistema
 * Comprueba todos los requisitos necesarios para la instalación
 */
class RequirementsChecker
{
    private $requirements = [];
    private $errors = [];
    private $warnings = [];

    public function __construct()
    {
        $this->defineRequirements();
    }

    /**
     * Define todos los requisitos del sistema
     */
    private function defineRequirements()
    {
        $this->requirements = [
            'php_version' => [
                'name' => 'Versión de PHP',
                'required' => '7.4.0',
                'recommended' => '8.1.0',
                'critical' => true
            ],
            'php_extensions' => [
                'name' => 'Extensiones de PHP',
                'required' => [
                    'pdo' => 'PDO (PHP Data Objects)',
                    'pdo_mysql' => 'PDO MySQL Driver',
                    'mysqli' => 'MySQLi Extension',
                    'json' => 'JSON Extension',
                    'curl' => 'cURL Extension',
                    'mbstring' => 'Multibyte String Extension',
                    'openssl' => 'OpenSSL Extension',
                    'zip' => 'ZIP Extension',
                    'gd' => 'GD Extension (para imágenes)',
                    'fileinfo' => 'Fileinfo Extension'
                ],
                'optional' => [
                    'imagick' => 'ImageMagick Extension (recomendado para imágenes)',
                    'redis' => 'Redis Extension (para cache)',
                    'memcached' => 'Memcached Extension (para cache)'
                ],
                'critical' => true
            ],
            'php_settings' => [
                'name' => 'Configuración de PHP',
                'settings' => [
                    'memory_limit' => ['min' => '256M', 'recommended' => '512M'],
                    'max_execution_time' => ['min' => 60, 'recommended' => 300],
                    'upload_max_filesize' => ['min' => '10M', 'recommended' => '50M'],
                    'post_max_size' => ['min' => '10M', 'recommended' => '50M'],
                    'max_input_vars' => ['min' => 3000, 'recommended' => 5000]
                ],
                'critical' => false
            ],
            'directories' => [
                'name' => 'Permisos de Directorios',
                'paths' => [
                    '../uploads' => ['permission' => 0755, 'writable' => true],
                    '../logs' => ['permission' => 0755, 'writable' => true],
                    '../cache' => ['permission' => 0755, 'writable' => true],
                    '../config' => ['permission' => 0755, 'writable' => true],
                    '../public/assets' => ['permission' => 0755, 'writable' => true]
                ],
                'critical' => true
            ],
            'database' => [
                'name' => 'Base de Datos',
                'engines' => ['MySQL 5.7+', 'MariaDB 10.3+'],
                'critical' => true
            ],
            'web_server' => [
                'name' => 'Servidor Web',
                'servers' => ['Apache 2.4+', 'Nginx 1.18+'],
                'features' => ['mod_rewrite (Apache)', 'URL Rewriting'],
                'critical' => false
            ]
        ];
    }

    /**
     * Ejecuta todas las verificaciones
     */
    public function checkAll()
    {
        $this->checkPHPVersion();
        $this->checkPHPExtensions();
        $this->checkPHPSettings();
        $this->checkDirectoryPermissions();
        $this->checkDatabaseConnection();
        $this->checkWebServer();

        return [
            'passed' => empty($this->errors),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'requirements' => $this->requirements
        ];
    }

    /**
     * Verifica la versión de PHP
     */
    private function checkPHPVersion()
    {
        $currentVersion = PHP_VERSION;
        $requiredVersion = $this->requirements['php_version']['required'];
        $recommendedVersion = $this->requirements['php_version']['recommended'];

        if (version_compare($currentVersion, $requiredVersion, '<')) {
            $this->errors[] = [
                'type' => 'php_version',
                'message' => "PHP {$requiredVersion} o superior es requerido. Versión actual: {$currentVersion}",
                'solution' => "Actualiza PHP a la versión {$requiredVersion} o superior",
                'commands' => $this->getPHPUpdateCommands()
            ];
        } elseif (version_compare($currentVersion, $recommendedVersion, '<')) {
            $this->warnings[] = [
                'type' => 'php_version',
                'message' => "Se recomienda PHP {$recommendedVersion} o superior. Versión actual: {$currentVersion}",
                'solution' => "Considera actualizar a PHP {$recommendedVersion} para mejor rendimiento"
            ];
        }
    }

    /**
     * Verifica las extensiones de PHP
     */
    private function checkPHPExtensions()
    {
        $required = $this->requirements['php_extensions']['required'];
        $optional = $this->requirements['php_extensions']['optional'];

        foreach ($required as $extension => $description) {
            if (!extension_loaded($extension)) {
                $this->errors[] = [
                    'type' => 'php_extension',
                    'message' => "Extensión requerida '{$extension}' no está instalada: {$description}",
                    'solution' => "Instala la extensión {$extension}",
                    'commands' => $this->getExtensionInstallCommands($extension)
                ];
            }
        }

        foreach ($optional as $extension => $description) {
            if (!extension_loaded($extension)) {
                $this->warnings[] = [
                    'type' => 'php_extension',
                    'message' => "Extensión opcional '{$extension}' no está instalada: {$description}",
                    'solution' => "Considera instalar la extensión {$extension} para funcionalidad adicional"
                ];
            }
        }
    }

    /**
     * Verifica la configuración de PHP
     */
    private function checkPHPSettings()
    {
        $settings = $this->requirements['php_settings']['settings'];

        foreach ($settings as $setting => $values) {
            $currentValue = ini_get($setting);
            $minValue = $values['min'];
            $recommendedValue = $values['recommended'];

            if ($this->compareValues($currentValue, $minValue, $setting) < 0) {
                $this->errors[] = [
                    'type' => 'php_setting',
                    'message' => "Configuración '{$setting}' es muy baja. Actual: {$currentValue}, Mínimo: {$minValue}",
                    'solution' => "Aumenta el valor de {$setting} a al menos {$minValue}",
                    'commands' => ["Edita php.ini: {$setting} = {$recommendedValue}"]
                ];
            } elseif ($this->compareValues($currentValue, $recommendedValue, $setting) < 0) {
                $this->warnings[] = [
                    'type' => 'php_setting',
                    'message' => "Se recomienda aumentar '{$setting}'. Actual: {$currentValue}, Recomendado: {$recommendedValue}",
                    'solution' => "Considera aumentar {$setting} a {$recommendedValue}"
                ];
            }
        }
    }

    /**
     * Verifica permisos de directorios
     */
    private function checkDirectoryPermissions()
    {
        $paths = $this->requirements['directories']['paths'];

        foreach ($paths as $path => $requirements) {
            $fullPath = __DIR__ . '/' . $path;
            
            // Crear directorio si no existe
            if (!is_dir($fullPath)) {
                if (!mkdir($fullPath, $requirements['permission'], true)) {
                    $this->errors[] = [
                        'type' => 'directory',
                        'message' => "No se pudo crear el directorio: {$path}",
                        'solution' => "Crea manualmente el directorio con permisos {$requirements['permission']}",
                        'commands' => [
                            "mkdir -p " . dirname($fullPath),
                            "chmod " . decoct($requirements['permission']) . " " . $fullPath
                        ]
                    ];
                    continue;
                }
            }

            // Verificar permisos de escritura
            if ($requirements['writable'] && !is_writable($fullPath)) {
                $this->errors[] = [
                    'type' => 'directory',
                    'message' => "El directorio no tiene permisos de escritura: {$path}",
                    'solution' => "Otorga permisos de escritura al directorio",
                    'commands' => [
                        "chmod " . decoct($requirements['permission']) . " " . $fullPath,
                        "chown www-data:www-data " . $fullPath . " (Linux/Apache)"
                    ]
                ];
            }
        }
    }

    /**
     * Verifica la conexión a la base de datos (básica)
     */
    private function checkDatabaseConnection()
    {
        // Esta verificación se hará más detallada en el paso de base de datos
        if (!extension_loaded('pdo_mysql') && !extension_loaded('mysqli')) {
            $this->errors[] = [
                'type' => 'database',
                'message' => "No hay extensiones de MySQL disponibles (pdo_mysql o mysqli)",
                'solution' => "Instala al menos una extensión de MySQL para PHP",
                'commands' => $this->getExtensionInstallCommands('pdo_mysql')
            ];
        }
    }

    /**
     * Verifica el servidor web
     */
    private function checkWebServer()
    {
        $serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
        
        if (stripos($serverSoftware, 'apache') !== false) {
            // Verificar mod_rewrite para Apache
            if (!function_exists('apache_get_modules') || !in_array('mod_rewrite', apache_get_modules())) {
                $this->warnings[] = [
                    'type' => 'web_server',
                    'message' => "mod_rewrite no está habilitado en Apache",
                    'solution' => "Habilita mod_rewrite para URLs amigables"
                ];
            }
        }
    }

    /**
     * Compara valores teniendo en cuenta diferentes tipos
     */
    private function compareValues($current, $required, $setting)
    {
        // Convertir valores de memoria a bytes
        if (in_array($setting, ['memory_limit', 'upload_max_filesize', 'post_max_size'])) {
            return $this->convertToBytes($current) - $this->convertToBytes($required);
        }

        // Comparación numérica para otros valores
        return (int)$current - (int)$required;
    }

    /**
     * Convierte valores de memoria a bytes
     */
    private function convertToBytes($value)
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value)-1]);
        $value = (int)$value;

        switch($last) {
            case 'g': $value *= 1024;
            case 'm': $value *= 1024;
            case 'k': $value *= 1024;
        }

        return $value;
    }

    /**
     * Obtiene comandos para actualizar PHP
     */
    private function getPHPUpdateCommands()
    {
        return [
            "# Ubuntu/Debian:",
            "sudo apt update",
            "sudo apt install php8.1 php8.1-cli php8.1-common",
            "",
            "# CentOS/RHEL:",
            "sudo yum update",
            "sudo yum install php81 php81-cli",
            "",
            "# Windows (XAMPP):",
            "Descarga la última versión de XAMPP desde https://www.apachefriends.org/"
        ];
    }

    /**
     * Obtiene comandos para instalar extensiones
     */
    private function getExtensionInstallCommands($extension)
    {
        return [
            "# Ubuntu/Debian:",
            "sudo apt install php-{$extension}",
            "",
            "# CentOS/RHEL:",
            "sudo yum install php-{$extension}",
            "",
            "# Windows (XAMPP):",
            "Descomenta ;extension={$extension} en php.ini",
            "",
            "# Reinicia el servidor web después de la instalación"
        ];
    }

    /**
     * Obtiene el estado general de los requisitos
     */
    public function getOverallStatus()
    {
        $criticalErrors = array_filter($this->errors, function($error) {
            return in_array($error['type'], ['php_version', 'php_extension', 'directory', 'database']);
        });

        return [
            'can_proceed' => empty($criticalErrors),
            'critical_errors' => count($criticalErrors),
            'total_errors' => count($this->errors),
            'warnings' => count($this->warnings)
        ];
    }

    /**
     * Genera reporte detallado
     */
    public function generateReport()
    {
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'requirements_check' => $this->checkAll(),
            'system_info' => [
                'os' => PHP_OS,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'loaded_extensions' => get_loaded_extensions()
            ]
        ];

        return $report;
    }
}