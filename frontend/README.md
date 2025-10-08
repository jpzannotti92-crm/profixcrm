# iaTrade CRM - Frontend Moderno

## 🚀 Tecnologías Utilizadas

- **React 18** - Framework principal
- **TypeScript** - Tipado estático
- **Vite** - Build tool moderno y rápido
- **Tailwind CSS** - Framework CSS utility-first
- **React Query** - Gestión de estado del servidor
- **React Router** - Enrutamiento
- **React Hook Form** - Gestión de formularios
- **Zustand** - Gestión de estado global
- **Chart.js** - Gráficos interactivos
- **Framer Motion** - Animaciones
- **Heroicons** - Iconos SVG

## 🎨 Características de Diseño

### Sistema de Temas
- **Tema Claro/Oscuro** automático
- **Paleta profesional** azul oscuro y blanco
- **Variables CSS** dinámicas
- **Persistencia** en localStorage

### Componentes Modernos
- **Cards** con sombras suaves y efectos hover
- **Botones** con animaciones y estados
- **Formularios** con validación en tiempo real
- **Tablas** responsivas con paginación
- **Modales** con backdrop blur
- **Notificaciones** toast profesionales

### Responsive Design
- **Mobile-first** approach
- **Breakpoints** optimizados
- **Sidebar** colapsable
- **Navegación** adaptativa

## 📁 Estructura del Proyecto

```
frontend/
├── src/
│   ├── components/          # Componentes reutilizables
│   │   ├── ui/             # Componentes base (Button, Input, etc.)
│   │   └── dashboard/      # Componentes específicos del dashboard
│   ├── contexts/           # Contextos de React (Auth, Theme)
│   ├── layouts/            # Layouts de páginas
│   ├── pages/              # Páginas de la aplicación
│   │   ├── auth/          # Páginas de autenticación
│   │   ├── dashboard/     # Dashboard principal
│   │   ├── leads/         # Gestión de leads
│   │   ├── users/         # Gestión de usuarios
│   │   ├── roles/         # Gestión de roles
│   │   ├── desks/         # Gestión de mesas
│   │   └── trading/       # Módulos de trading
│   ├── services/          # APIs y servicios
│   ├── types/             # Definiciones TypeScript
│   ├── utils/             # Utilidades
│   └── hooks/             # Custom hooks
├── public/                # Archivos estáticos
└── dist/                  # Build de producción
```

## 🛠 Instalación y Desarrollo

### Prerrequisitos
- Node.js 18+ 
- npm o yarn

### Instalación
```bash
cd frontend
npm install
```

### Desarrollo
```bash
npm run dev
```
La aplicación estará disponible en `http://localhost:3000`

### Build de Producción
```bash
npm run build
```

### Preview de Producción
```bash
npm run preview
```

## 🔧 Configuración

### Variables de Entorno
Crear archivo `.env` en la raíz del frontend:

```env
VITE_API_URL=http://localhost:8000/api
VITE_APP_NAME=iaTrade CRM
VITE_APP_VERSION=2.0.0
```

### Proxy de Desarrollo
El archivo `vite.config.ts` está configurado para hacer proxy de las APIs al backend PHP:

```typescript
server: {
  proxy: {
    '/api': {
      target: 'http://localhost:8000',
      changeOrigin: true,
    },
  },
}
```

## 📊 Funcionalidades Implementadas

### ✅ Sistema de Autenticación
- Login con validación
- Gestión de tokens JWT
- Rutas protegidas
- Contexto de usuario

### ✅ Dashboard Profesional
- Estadísticas en tiempo real
- Gráficos interactivos
- Métricas de conversión
- Actividad reciente

### ✅ Gestión de Leads
- Lista con filtros avanzados
- Paginación eficiente
- Estados visuales
- Acciones CRUD

### 🔄 En Desarrollo
- Detalle de leads
- Importación de CSV
- Gestión de usuarios
- Módulos de trading
- Reportes avanzados

## 🎯 Próximas Mejoras

### Funcionalidades
- [ ] Sistema de notificaciones en tiempo real
- [ ] Exportación de datos
- [ ] Filtros avanzados con fechas
- [ ] Búsqueda global
- [ ] Configuración de usuario

### Técnicas
- [ ] Tests unitarios con Vitest
- [ ] Tests E2E con Playwright
- [ ] Storybook para componentes
- [ ] PWA capabilities
- [ ] Optimización de bundle

## 🚀 Ventajas sobre HTML Tradicional

### Performance
- **Virtual DOM** para actualizaciones eficientes
- **Code splitting** automático
- **Lazy loading** de componentes
- **Optimización** de assets

### Desarrollo
- **TypeScript** para mejor DX
- **Hot reload** instantáneo
- **Componentes reutilizables**
- **Estado centralizado**

### Mantenimiento
- **Arquitectura modular**
- **Separación de responsabilidades**
- **Testing** integrado
- **Documentación** automática

### UX/UI
- **Animaciones** fluidas
- **Transiciones** profesionales
- **Estados de carga** elegantes
- **Feedback** inmediato

## 🔗 Integración con Backend

La aplicación está diseñada para trabajar con el backend PHP existente:

- **APIs REST** compatibles
- **Autenticación JWT** 
- **CORS** configurado
- **Manejo de errores** centralizado

## 📱 Responsive Design

- **Mobile**: < 768px
- **Tablet**: 768px - 1024px  
- **Desktop**: > 1024px
- **4K**: > 1920px

## 🎨 Paleta de Colores

### Tema Claro
- **Primary**: #1e3a8a (Azul oscuro)
- **Success**: #059669 (Verde)
- **Warning**: #d97706 (Naranja)
- **Danger**: #dc2626 (Rojo)

### Tema Oscuro
- **Primary**: #3b82f6 (Azul brillante)
- **Background**: #0f172a (Azul muy oscuro)
- **Surface**: #1e293b (Azul oscuro)

## 📄 Licencia

Proyecto privado - iaTrade CRM © 2024