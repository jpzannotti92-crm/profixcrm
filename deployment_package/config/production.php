<?php
/**
 * Configuración de Producción - iaTrade CRM
 * ==========================================
 * 
 * Este archivo contiene todas las configuraciones optimizadas para el entorno de producción
 * Base de datos: spin2pay_profixcrm
 * Usuario: spin2pay_profixadmin
 * Host: localhost:3306
 */

return [
    // Configuración de la aplicación
    'app' => [
        'name' => 'iaTrade CRM',
        'version' => '1.0.0',
        'environment' => 'production',
        'debug' => false,
        'url' => 'https://tudominio.com',
        'timezone' => 'UTC',
        'locale' => 'es',
        'fallback_locale' => 'en',
    ],

    // Configuración de base de datos
    'database' => [
        'default' => 'mysql',
        'connections' => [
            'mysql' => [
                'driver' => 'mysql',
                'host' => 'localhost',
                'port' => 3306,
                'database' => 'spin2pay_profixcrm',
                'username' => 'spin2pay_profixadmin',
                'password' => 'Jeanpi9941991@',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
                'engine' => 'InnoDB',
                'options' => [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
                ],
            ],
        ],
        'migrations' => 'migrations',
    ],

    // Configuración de cache
    'cache' => [
        'default' => 'file',
        'stores' => [
            'file' => [
                'driver' => 'file',
                'path' => __DIR__ . '/../storage/cache',
            ],
            'redis' => [
                'driver' => 'redis',
                'connection' => 'cache',
            ],
        ],
        'prefix' => 'iatrade_crm_cache',
        'ttl' => 3600, // 1 hora
    ],

    // Configuración de sesiones
    'session' => [
        'driver' => 'file',
        'lifetime' => 120, // 2 horas
        'expire_on_close' => false,
        'encrypt' => true,
        'files' => __DIR__ . '/../storage/sessions',
        'connection' => null,
        'table' => 'sessions',
        'store' => null,
        'lottery' => [2, 100],
        'cookie' => 'iatrade_crm_session',
        'path' => '/',
        'domain' => null,
        'secure' => true, // Solo HTTPS en producción
        'http_only' => true,
        'same_site' => 'lax',
    ],

    // Configuración de seguridad
    'security' => [
        'key' => '629cbfb533b8ee8a43a9cb37b3ffd50c',
        'cipher' => 'AES-256-CBC',
        'jwt' => [
            'secret' => '8de21b037207fc54e63217b00be2926c11de212eceb3b1fd2420738d7f1cb86b',
            'ttl' => 3600, // 1 hora
            'refresh_ttl' => 20160, // 2 semanas
            'algo' => 'HS256',
        ],
        'csrf' => [
            'enabled' => true,
            'token_lifetime' => 3600,
        ],
        'rate_limiting' => [
            'enabled' => true,
            'max_attempts' => 100,
            'decay_minutes' => 1,
        ],
        'password' => [
            'min_length' => 8,
            'require_uppercase' => true,
            'require_lowercase' => true,
            'require_numbers' => true,
            'require_symbols' => true,
        ],
    ],

    // Configuración de logs
    'logging' => [
        'default' => 'stack',
        'channels' => [
            'stack' => [
                'driver' => 'stack',
                'channels' => ['single', 'daily'],
                'ignore_exceptions' => false,
            ],
            'single' => [
                'driver' => 'single',
                'path' => __DIR__ . '/../storage/logs/iatrade.log',
                'level' => 'error',
            ],
            'daily' => [
                'driver' => 'daily',
                'path' => __DIR__ . '/../storage/logs/iatrade.log',
                'level' => 'error',
                'days' => 30,
            ],
            'syslog' => [
                'driver' => 'syslog',
                'level' => 'error',
            ],
        ],
    ],

    // Configuración de correo
    'mail' => [
        'default' => 'smtp',
        'mailers' => [
            'smtp' => [
                'transport' => 'smtp',
                'host' => 'smtp.gmail.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => null, // Configurar según necesidades
                'password' => null, // Configurar según necesidades
                'timeout' => null,
            ],
        ],
        'from' => [
            'address' => 'noreply@tudominio.com',
            'name' => 'iaTrade CRM',
        ],
    ],

    // Configuración de archivos
    'filesystems' => [
        'default' => 'local',
        'disks' => [
            'local' => [
                'driver' => 'local',
                'root' => __DIR__ . '/../storage/app',
            ],
            'public' => [
                'driver' => 'local',
                'root' => __DIR__ . '/../storage/app/public',
                'url' => '/storage',
                'visibility' => 'public',
            ],
            'uploads' => [
                'driver' => 'local',
                'root' => __DIR__ . '/../public/uploads',
                'url' => '/uploads',
                'visibility' => 'public',
            ],
        ],
    ],

    // Configuración de rendimiento
    'performance' => [
        'opcache' => [
            'enabled' => true,
            'memory_consumption' => 256,
            'max_accelerated_files' => 20000,
            'revalidate_freq' => 0,
        ],
        'compression' => [
            'enabled' => true,
            'level' => 6,
            'threshold' => 1024,
        ],
        'minification' => [
            'css' => true,
            'js' => true,
            'html' => true,
        ],
    ],

    // Límites del sistema
    'limits' => [
        'max_upload_size' => '10M',
        'max_execution_time' => 300,
        'memory_limit' => '256M',
        'max_input_vars' => 3000,
        'max_requests_per_minute' => 100,
        'max_concurrent_users' => 1000,
    ],

    // Configuración de APIs externas
    'apis' => [
        'trading' => [
            'enabled' => true,
            'timeout' => 30,
            'retry_attempts' => 3,
        ],
        'notifications' => [
            'enabled' => true,
            'providers' => ['email', 'sms'],
        ],
    ],

    // Configuración de monitoreo
    'monitoring' => [
        'enabled' => true,
        'health_check' => [
            'enabled' => true,
            'interval' => 300, // 5 minutos
        ],
        'performance' => [
            'log_slow_queries' => true,
            'slow_query_time' => 2000, // 2 segundos
        ],
        'alerts' => [
            'email' => 'admin@tudominio.com',
            'thresholds' => [
                'cpu' => 80,
                'memory' => 85,
                'disk' => 90,
            ],
        ],
    ],

    // Configuración de backup
    'backup' => [
        'enabled' => true,
        'schedule' => 'daily',
        'retention_days' => 30,
        'destinations' => [
            'local' => __DIR__ . '/../storage/backups',
        ],
        'databases' => ['mysql'],
        'files' => [
            'include' => [
                __DIR__ . '/../public/uploads',
                __DIR__ . '/../storage/app',
            ],
            'exclude' => [
                __DIR__ . '/../storage/logs',
                __DIR__ . '/../storage/cache',
            ],
        ],
    ],

    // Configuración de features
    'features' => [
        'trading' => true,
        'reports' => true,
        'notifications' => true,
        'analytics' => true,
        'api_access' => true,
        'mobile_app' => true,
        'integrations' => true,
        'advanced_permissions' => true,
    ],
];