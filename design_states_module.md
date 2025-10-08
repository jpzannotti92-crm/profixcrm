# Módulo de Estados Dinámicos por Desk - Diseño Técnico

## 1. ANÁLISIS DE REQUERIMIENTOS

### Estados Actuales del Sistema
El sistema actualmente maneja estados fijos para leads:
- `new` - Nuevo
- `contacted` - Contactado  
- `interested` - Interesado
- `demo_account` - Cuenta Demo
- `no_answer` - Sin Respuesta
- `callback` - Callback
- `not_interested` - No Interesado
- `ftd` - First Time Deposit
- `client` - Cliente
- `lost` - Perdido

### Necesidades Identificadas
1. **Estados Personalizables por Desk**: Cada mesa debe poder crear sus propios estados
2. **Transiciones Inteligentes**: Definir qué estados pueden seguir a otros
3. **Historial Completo**: Rastrear todos los cambios de estado
4. **Reportes por Estado**: Métricas y análisis por estados personalizados
5. **Permisos Granulares**: Control de quién puede cambiar estados

## 2. DISEÑO DE BASE DE DATOS

### Tabla: `desk_states`
```sql
CREATE TABLE `desk_states` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `desk_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `display_name` VARCHAR(150) NOT NULL,
    `description` TEXT,
    `color` VARCHAR(7) DEFAULT '#6B7280', -- Color hex para UI
    `icon` VARCHAR(50) DEFAULT 'tag', -- Icono para UI
    `is_initial` BOOLEAN DEFAULT FALSE, -- Estado inicial para nuevos leads
    `is_final` BOOLEAN DEFAULT FALSE, -- Estado final (no permite más cambios)
    `is_active` BOOLEAN DEFAULT TRUE,
    `sort_order` INT DEFAULT 0,
    `created_by` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`desk_id`) REFERENCES `desks`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    
    UNIQUE KEY `unique_desk_state` (`desk_id`, `name`),
    INDEX `idx_desk_id` (`desk_id`),
    INDEX `idx_is_active` (`is_active`),
    INDEX `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB;
```

### Tabla: `state_transitions`
```sql
CREATE TABLE `state_transitions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `desk_id` INT NOT NULL,
    `from_state_id` INT, -- NULL significa "cualquier estado"
    `to_state_id` INT NOT NULL,
    `is_automatic` BOOLEAN DEFAULT FALSE, -- Transición automática
    `conditions` JSON, -- Condiciones para transición automática
    `required_permission` VARCHAR(100), -- Permiso requerido para esta transición
    `notification_template` TEXT, -- Template de notificación
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_by` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`desk_id`) REFERENCES `desks`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`from_state_id`) REFERENCES `desk_states`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`to_state_id`) REFERENCES `desk_states`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    
    UNIQUE KEY `unique_transition` (`desk_id`, `from_state_id`, `to_state_id`),
    INDEX `idx_desk_id` (`desk_id`),
    INDEX `idx_from_state` (`from_state_id`),
    INDEX `idx_to_state` (`to_state_id`)
) ENGINE=InnoDB;
```

### Modificación Tabla: `leads`
```sql
-- Agregar nueva columna para estado dinámico
ALTER TABLE `leads` 
ADD COLUMN `desk_state_id` INT NULL AFTER `status`,
ADD FOREIGN KEY (`desk_state_id`) REFERENCES `desk_states`(`id`) ON DELETE SET NULL,
ADD INDEX `idx_desk_state_id` (`desk_state_id`);

-- Mantener columna status para compatibilidad hacia atrás
-- Agregar trigger para sincronizar ambos campos
```

### Tabla: `lead_state_history` (Mejorada)
```sql
CREATE TABLE `lead_state_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `lead_id` INT NOT NULL,
    `old_status` VARCHAR(50), -- Estado legacy
    `new_status` VARCHAR(50), -- Estado legacy
    `old_desk_state_id` INT, -- Nuevo estado dinámico
    `new_desk_state_id` INT, -- Nuevo estado dinámico
    `transition_id` INT, -- Referencia a la transición usada
    `changed_by` INT,
    `reason` TEXT,
    `automatic` BOOLEAN DEFAULT FALSE, -- Si fue cambio automático
    `metadata` JSON, -- Datos adicionales del cambio
    `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`old_desk_state_id`) REFERENCES `desk_states`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`new_desk_state_id`) REFERENCES `desk_states`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`transition_id`) REFERENCES `state_transitions`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    
    INDEX `idx_lead_id` (`lead_id`),
    INDEX `idx_changed_by` (`changed_by`),
    INDEX `idx_changed_at` (`changed_at`),
    INDEX `idx_new_desk_state` (`new_desk_state_id`)
) ENGINE=InnoDB;
```

## 3. FUNCIONALIDADES PRINCIPALES

### 3.1 Gestión de Estados por Desk
- **Crear Estados**: Cada desk puede definir sus estados personalizados
- **Configurar Propiedades**: Color, icono, descripción, orden
- **Estados Especiales**: Marcar estados como inicial o final
- **Activar/Desactivar**: Control de estados activos

### 3.2 Sistema de Transiciones
- **Definir Flujos**: Establecer qué estados pueden seguir a otros
- **Transiciones Automáticas**: Cambios basados en condiciones
- **Permisos por Transición**: Control granular de acceso
- **Notificaciones**: Alertas automáticas en cambios de estado

### 3.3 Migración y Compatibilidad
- **Mapeo de Estados Legacy**: Convertir estados fijos a dinámicos
- **Compatibilidad Hacia Atrás**: Mantener funcionalidad existente
- **Migración Gradual**: Permitir transición por desk

## 4. API ENDPOINTS

### Estados por Desk
```
GET    /api/desk-states/{desk_id}           - Listar estados de un desk
POST   /api/desk-states                     - Crear nuevo estado
PUT    /api/desk-states/{id}                - Actualizar estado
DELETE /api/desk-states/{id}                - Eliminar estado
POST   /api/desk-states/{id}/activate       - Activar/desactivar estado
```

### Transiciones
```
GET    /api/state-transitions/{desk_id}     - Listar transiciones de un desk
POST   /api/state-transitions               - Crear transición
PUT    /api/state-transitions/{id}          - Actualizar transición
DELETE /api/state-transitions/{id}          - Eliminar transición
GET    /api/state-transitions/available/{from_state_id} - Estados disponibles desde un estado
```

### Cambios de Estado
```
POST   /api/leads/{id}/change-state         - Cambiar estado de lead
GET    /api/leads/{id}/state-history        - Historial de estados
GET    /api/leads/{id}/available-states     - Estados disponibles para el lead
```

### Reportes
```
GET    /api/reports/states-summary/{desk_id} - Resumen de leads por estado
GET    /api/reports/state-transitions/{desk_id} - Reporte de transiciones
GET    /api/reports/conversion-funnel/{desk_id} - Embudo de conversión
```

## 5. COMPONENTES FRONTEND

### 5.1 Gestión de Estados
- **StateManager**: Componente principal para gestionar estados
- **StateForm**: Formulario para crear/editar estados
- **StateList**: Lista de estados con drag & drop para ordenar
- **StatePreview**: Vista previa de cómo se ve el estado

### 5.2 Configuración de Transiciones
- **TransitionMatrix**: Matriz visual de transiciones permitidas
- **TransitionForm**: Formulario para configurar transiciones
- **FlowDiagram**: Diagrama de flujo de estados

### 5.3 Interfaz de Leads
- **StateSelector**: Selector mejorado de estados
- **StateHistory**: Historial visual de cambios
- **StateActions**: Botones de acción según transiciones disponibles

## 6. CARACTERÍSTICAS AVANZADAS

### 6.1 Estados Inteligentes
- **Condiciones Automáticas**: Cambio automático basado en:
  - Tiempo transcurrido
  - Actividades realizadas
  - Datos del lead
  - Interacciones externas

### 6.2 Plantillas de Estados
- **Estados Predefinidos**: Templates comunes para diferentes tipos de desk
- **Importar/Exportar**: Compartir configuraciones entre desks
- **Mejores Prácticas**: Sugerencias basadas en industria

### 6.3 Analíticas Avanzadas
- **Tiempo en Estado**: Análisis de duración promedio
- **Tasas de Conversión**: Por cada transición
- **Cuellos de Botella**: Identificar estados problemáticos
- **Predicciones**: ML para predecir próximos estados

## 7. PERMISOS Y SEGURIDAD

### Nuevos Permisos
```
desk_states.view         - Ver estados del desk
desk_states.create       - Crear estados
desk_states.edit         - Editar estados
desk_states.delete       - Eliminar estados
state_transitions.manage - Gestionar transiciones
lead_states.change       - Cambiar estado de leads
lead_states.history      - Ver historial de estados
```

### Reglas de Negocio
- Solo usuarios del desk pueden gestionar sus estados
- Admins pueden gestionar estados de cualquier desk
- Cambios de estado requieren permisos específicos
- Estados finales no permiten más cambios

## 8. PLAN DE IMPLEMENTACIÓN

### Fase 1: Base de Datos y Backend
1. Crear nuevas tablas
2. Implementar modelos PHP
3. Crear APIs básicas
4. Sistema de migración

### Fase 2: Frontend Básico
1. Componentes de gestión de estados
2. Interfaz de configuración
3. Selector de estados mejorado
4. Historial visual

### Fase 3: Transiciones Avanzadas
1. Sistema de transiciones
2. Condiciones automáticas
3. Notificaciones
4. Permisos granulares

### Fase 4: Analíticas y Reportes
1. Dashboards de estados
2. Reportes de conversión
3. Métricas avanzadas
4. Exportación de datos

### Fase 5: Características Avanzadas
1. Estados inteligentes
2. Plantillas predefinidas
3. Predicciones ML
4. Optimizaciones de rendimiento

## 9. CONSIDERACIONES TÉCNICAS

### Rendimiento
- Índices optimizados para consultas frecuentes
- Cache de estados y transiciones
- Paginación en historiales largos
- Consultas optimizadas para reportes

### Escalabilidad
- Soporte para miles de estados por desk
- Historial ilimitado con archivado
- APIs paginadas y filtradas
- Procesamiento asíncrono para cambios masivos

### Mantenimiento
- Logs detallados de cambios
- Backup automático de configuraciones
- Validaciones de integridad
- Herramientas de diagnóstico

Este diseño proporciona una base sólida para implementar un sistema de estados dinámicos robusto y escalable que mejorará significativamente la gestión de leads en el CRM.