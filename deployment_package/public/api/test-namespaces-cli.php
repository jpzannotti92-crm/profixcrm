<?php
// Script de prueba para verificar la configuración de namespaces
require_once __DIR__ . '/../../vendor/autoload.php';

echo "=== PRUEBA DE NAMESPACES ===\n";
echo "Directorio actual: " . __DIR__ . "\n";
echo "Autoload incluido: " . (file_exists(__DIR__ . '/../../vendor/autoload.php') ? 'SI' : 'NO') . "\n";

// Verificar si la clase Lead existe
echo "\n=== CLASE LEAD ===\n";
if (class_exists('IaTradeCRM\Models\Lead')) {
    echo "✓ Clase Lead encontrada en IaTradeCRM\\Models\n";
    
    // Intentar usar el método findByEmail
    try {
        $lead = \IaTradeCRM\Models\Lead::findByEmail('test@example.com');
        echo "✓ Método findByEmail funciona\n";
        echo "Resultado: " . ($lead ? 'Lead encontrado' : 'Lead no encontrado') . "\n";
    } catch (Exception $e) {
        echo "✗ Error al usar findByEmail: " . $e->getMessage() . "\n";
    }
} else {
    echo "✗ Clase Lead NO encontrada en IaTradeCRM\\Models\n";
    echo "Clases disponibles: " . implode(', ', array_filter(get_declared_classes(), function($class) {
        return strpos($class, 'Lead') !== false;
    })) . "\n";
}

// Verificar RBACMiddleware
echo "\n=== RBAC MIDDLEWARE ===\n";
if (class_exists('IaTradeCRM\Middleware\RBACMiddleware')) {
    echo "✓ RBACMiddleware encontrado en IaTradeCRM\\Middleware\n";
} else {
    echo "✗ RBACMiddleware NO encontrado en IaTradeCRM\\Middleware\n";
    echo "Middlewares disponibles: " . implode(', ', array_filter(get_declared_classes(), function($class) {
        return strpos($class, 'Middleware') !== false;
    })) . "\n";
}

echo "\n=== FIN DE LA PRUEBA ===\n";