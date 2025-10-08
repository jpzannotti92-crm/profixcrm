# Mejoras del Sistema de Logs en ProfixCRM v8

## Resumen de Mejoras

El sistema de logs en v8 ha sido completamente reestructurado para proporcionar mejor rendimiento, organización y análisis de eventos del sistema.

## 🆕 Nuevas Características

### 1. **Logging Estructurado**
Los logs ahora siguen el formato JSON estructurado para facilitar el análisis:

```json
{
  "timestamp": "2025-01-07T18:55:11.123Z",
  "level": "error",
  "channel": "system",
  "message": "Error de conexión a base de datos",
  "context": {
    "user_id": 123,
    "ip": "192.168.1.100",
    "file": "database.php",
    "line": 45,
    "error": "Connection refused"
  },
  "request_id": "abc123def456",
  "memory_usage": "24.5MB",
  "execution_time": "0.125s"
}
```

### 2. **Canales de Log Múltiples**
Organiza logs por categorías:

```php
// Canales disponibles
- 'system'     // Eventos del sistema
- 'database'   // Consultas y errores de BD
- 'auth'       // Intentos de autenticación
- 'api'        // Llamadas a la API
- 'security'   // Eventos de seguridad
- 'redirect'   // Redirecciones
- 'validation' // Validaciones
- 'performance' // Métricas de rendimiento
```

### 3. **Rotación Automática de Logs**
Configuración automática para prevenir archivos gigantes:

```php
'logging' => [
    'enabled' => true,
    'level' => 'info',
    'channels' => ['file', 'database'],
    'max_files' => 30,        // Mantener 30 días de logs
    'max_size' => 10485760,   // 10MB por archivo
    'rotate_daily' => true,   // Rotar diariamente
]
```

### 4. **Log en Base de Datos**
Opción de almacenar logs críticos en BD para análisis:

```sql
-- Estructura de tabla de logs
CREATE TABLE system_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    timestamp DATETIME NOT NULL,
    level VARCHAR(20) NOT NULL,
    channel VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    context JSON,
    request_id VARCHAR(64),
    user_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    memory_usage VARCHAR(20),
    execution_time DECIMAL(10,3),
    INDEX idx_timestamp (timestamp),
    INDEX idx_level (level),
    INDEX idx_channel (channel),
    INDEX idx_user_id (user_id),
    INDEX idx_request_id (request_id)
);
```

## 📁 Estructura de Archivos de Log

```
logs/
├── v8/
│   ├── system/
│   │   ├── system-2025-01-07.log
│   │   └── system-2025-01-06.log
│   ├── database/
│   │   ├── database-2025-01-07.log
│   │   └── database-2025-01-06.log
│   ├── auth/
│   │   ├── auth-2025-01-07.log
│   │   └── auth-2025-01-06.log
│   ├── api/
│   │   ├── api-2025-01-07.log
│   │   └── api-2025-01-06.log
│   ├── security/
│   │   ├── security-2025-01-07.log
│   │   └── security-2025-01-06.log
│   └── validation/
│       ├── validation-2025-01-07.log
│       └── validation-2025-01-06.log
└── archive/
    └── 2024-12/
        ├── system-2024-12-31.log.gz
        └── ...
```

## 🔧 Configuración del Sistema de Logs

### Configuración Básica
```php
// config/v8_config.php
'logging' => [
    'enabled' => true,
    'level' => 'info', // debug, info, warning, error, critical
    'channels' => ['file', 'database'],
    'max_files' => 30,
    'max_size' => 10485760,
    'rotate_daily' => true,
    'ip_filter' => ['127.0.0.1', '::1'], // IPs a excluir
    'user_filter' => [1], // Usuarios a excluir (ej. admin)
]
```

### Niveles de Log
```php
// Niveles disponibles (de menor a mayor severidad)
- 'debug'     // Información detallada para desarrollo
- 'info'      // Información general del sistema
- 'warning'   // Advertencias de posibles problemas
- 'error'     // Errores que no impiden funcionamiento
- 'critical'  // Errores críticos que requieren atención
```

## 🚀 Uso del Sistema de Logs

### Logging Básico
```php
use Src\Core\V8Logger;

// Obtener logger
$logger = V8Logger::getInstance();

// Log simple
$logger->info('Usuario inició sesión', ['user_id' => 123]);

// Log con contexto
$logger->error('Error de base de datos', [
    'query' => $sql,
    'error' => $error,
    'file' => __FILE__,
    'line' => __LINE__
]);
```

### Logging por Canal
```php
// Log específico por canal
$logger->channel('auth')->warning('Intento de login fallido', [
    'username' => $username,
    'ip' => $_SERVER['REMOTE_ADDR']
]);

$logger->channel('api')->info('API llamada', [
    'endpoint' => '/api/users',
    'method' => 'GET',
    'response_time' => '0.125s'
]);

$logger->channel('database')->debug('Query ejecutada', [
    'query' => $sql,
    'time' => '0.045s'
]);
```

### Logging de Rendimiento
```php
// Medir tiempo de ejecución
$logger->startTimer('proceso_ventas');

// ... código que se está midiendo ...

$logger->endTimer('proceso_ventas', 'Proceso de ventas completado');
```

## 📊 Análisis de Logs

### Herramientas de Análisis
```bash
# Ver logs recientes por canal
tail -f logs/v8/system/system-2025-01-07.log

# Buscar errores críticos
grep -i "critical" logs/v8/system/system-2025-01-07.log

# Ver estadísticas de errores por hora
grep -o '"timestamp":"[^"]*"' logs/v8/system/system-2025-01-07.log | \
cut -d'"' -f4 | cut -d'T' -f2 | cut -d':' -f1 | sort | uniq -c

# Análisis de rendimiento
grep "execution_time" logs/v8/performance/performance-*.log | \
awk -F'"' '{print $20}' | sort -n | tail -10
```

### Scripts de Análisis
```php
// scripts/analyze_logs.php
require_once 'src/Core/V8Logger.php';

$analyzer = new LogAnalyzer();

// Análisis de errores
$errorStats = $analyzer->getErrorStats('2025-01-07');
echo "Errores del día: " . $errorStats['total_errors'] . "\n";

// Top de errores
$topErrors = $analyzer->getTopErrors(10);
foreach ($topErrors as $error) {
    echo $error['count'] . " - " . $error['message'] . "\n";
}

// Métricas de rendimiento
$perfMetrics = $analyzer->getPerformanceMetrics();
echo "Tiempo promedio de respuesta: " . $perfMetrics['avg_response_time'] . "s\n";
```

## 🔔 Alertas y Notificaciones

### Configurar Alertas
```php
// Alertas por email para errores críticos
'notifications' => [
    'enabled' => true,
    'email' => 'admin@tudominio.com',
    'level' => 'critical', // Solo errores críticos
    'channels' => ['email', 'slack'], // Canales de notificación
]
```

### Ejemplo de Alerta
```php
// Cuando ocurre un error crítico
$logger->critical('Base de datos no disponible', [
    'error' => $error,
    'server' => gethostname(),
    'time' => date('Y-m-d H:i:s')
]);

// El sistema enviará automáticamente notificación
```

## 🛡️ Seguridad en Logs

### Sanitización de Datos
```php
// Datos sensibles se sanitizan automáticamente
$logger->info('Pago procesado', [
    'user_id' => 123,
    'amount' => 99.99,
    'card_number' => '****-****-****-1234', // Sanitizado automáticamente
    'cvv' => '***' // Sanitizado automáticamente
]);
```

### Exclusión de IPs y Usuarios
```php
// Configurar filtros
'ip_filter' => ['127.0.0.1', '::1', '192.168.1.100'],
'user_filter' => [1, 2], // IDs de usuarios a excluir
```

## 📈 Métricas y Dashboard

### Métricas Disponibles
```php
// Métricas automáticas del sistema
- Total de logs por nivel
- Errores por hora/día/semana
- Tiempo promedio de respuesta
- Uso de memoria
- Consultas de BD lentas
- Intentos de autenticación fallidos
- Llamadas a API por endpoint
- Redirecciones procesadas
```

### Dashboard de Monitoreo
```php
// scripts/dashboard.php
$dashboard = new LogDashboard();

// Obtener métricas del día actual
$metrics = $dashboard->getDailyMetrics();

// Generar gráficos
$charts = $dashboard->generateCharts();

// Mostrar alertas activas
$alerts = $dashboard->getActiveAlerts();
```

## 🔧 Mantenimiento de Logs

### Rotación Automática
```bash
# Script de rotación diaria (ejecutado por cron)
0 0 * * * php scripts/rotate_logs.php

# Limpieza de logs antiguos
0 2 * * 0 php scripts/cleanup_logs.php --days=30
```

### Compresión de Logs Antiguos
```bash
# Comprimir logs mayores a 7 días
find logs/v8/ -name "*.log" -mtime +7 -exec gzip {} \;

# Mover a archivo comprimido
find logs/v8/ -name "*.log.gz" -mtime +7 -exec mv {} logs/archive/ \;
```

## 🚨 Solución de Problemas

### Logs No se Generan
```bash
# Verificar permisos
ls -la logs/v8/
chmod 755 logs/v8/
chown -R www-data:www-data logs/

# Verificar espacio en disco
df -h

# Verificar configuración
php -r "print_r(parse_ini_file('config/v8_config.php', true));"
```

### Archivos de Log Muy Grandes
```bash
# Verificar tamaño de archivos
du -sh logs/v8/

# Forzar rotación
php scripts/rotate_logs.php --force

# Comprimir manualmente
gzip logs/v8/system/system-2025-01-07.log
```

### Error: "Cannot write to log file"
```bash
# Verificar y corregir permisos
sudo chown -R www-data:www-data logs/
sudo chmod -R 755 logs/

# Verificar SELinux (si aplica)
getenforce
setenforce 0 # Temporal
```

## 📚 Referencia Rápida

### Comandos Útiles
```bash
# Ver logs en tiempo real
tail -f logs/v8/system/system-*.log

# Buscar errores de hoy
grep -i error logs/v8/system/system-$(date +%Y-%m-%d).log

# Estadísticas de logs
find logs/v8/ -name "*.log" -exec wc -l {} \;

# Logs más recientes
ls -lat logs/v8/system/

# Buscar por usuario
grep "user_id.*123" logs/v8/auth/auth-*.log

# Buscar por IP
grep "192.168.1.100" logs/v8/security/security-*.log
```

### Funciones de Logger
```php
// Funciones disponibles
$logger->debug($message, $context);
$logger->info($message, $context);
$logger->warning($message, $context);
$logger->error($message, $context);
$logger->critical($message, $context);

// Funciones especiales
$logger->startTimer($name);
$logger->endTimer($name, $message);
$logger->channel($name)->log($level, $message, $context);
```

## 🎯 Mejores Prácticas

1. **Usar niveles apropiados**: No uses 'debug' en producción
2. **Contexto relevante**: Incluir datos útiles para debugging
3. **Evitar información sensible**: No loguear contraseñas o datos personales
4. **Rotación regular**: Configurar rotación para evitar archivos grandes
5. **Monitoreo activo**: Revisar logs críticos regularmente
6. **Alertas configuradas**: Configurar notificaciones para errores críticos
7. **Backup de logs**: Incluir logs en estrategia de respaldo
8. **Análisis periódico**: Revisar patrones y tendencias

---

**Nota**: El sistema de logs v8 está diseñado para ser escalable y eficiente. Ajusta la configuración según las necesidades de tu servidor y volumen de tráfico.