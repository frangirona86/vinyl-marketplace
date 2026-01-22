# Vinyl Marketplace - Frontend

Frontend de la aplicación Vinyl Marketplace, construido con Vue.js 3 y Tailwind CSS v4.

## Tecnologías

- **Vue.js 3** - Framework progresivo de JavaScript
- **Vite** - Build tool de nueva generación
- **Tailwind CSS v4** - Framework de utilidades CSS
- **Vue Router 4** - Enrutamiento oficial para Vue.js
- **Axios** - Cliente HTTP
- **Vitest** - Framework de testing

## Requisitos

- Node.js 18+
- npm 9+

## Instalación

```bash
# Instalar dependencias
npm install

# Ejecutar en desarrollo
npm run dev

# Build para producción
npm run build

# Preview de producción
npm run preview
```

## Scripts disponibles

| Script | Descripción |
|--------|-------------|
| `npm run dev` | Servidor de desarrollo en http://localhost:5173 |
| `npm run build` | Build de producción |
| `npm run preview` | Preview del build de producción |
| `npm test` | Ejecutar tests en modo watch |
| `npm run test:run` | Ejecutar tests una vez |
| `npm run test:coverage` | Tests con reporte de cobertura |

## Estructura del proyecto

```
frontend/
├── public/
│   └── placeholder-vinyl.svg    # Placeholder para imágenes
├── src/
│   ├── api/
│   │   └── discogs.js           # Servicio API para backend
│   ├── components/
│   │   ├── layout/
│   │   │   └── AppHeader.vue    # Header de la aplicación
│   │   └── ui/
│   │       ├── EmptyState.vue       # Estado vacío
│   │       ├── FiltersPanel.vue     # Panel de filtros
│   │       ├── LoadingSpinner.vue   # Spinner de carga
│   │       ├── Pagination.vue       # Paginación
│   │       ├── SearchBar.vue        # Barra de búsqueda
│   │       ├── ThemeToggle.vue      # Toggle dark/light mode
│   │       ├── VinylCard.vue        # Tarjeta de vinyl (grid)
│   │       ├── VinylCardSkeleton.vue # Skeleton loading
│   │       └── VinylListItem.vue    # Item de vinyl (lista)
│   ├── composables/
│   │   ├── useTheme.js          # Gestión de tema (dark/light)
│   │   └── useVinyls.js         # Gestión de vinyls y filtros
│   ├── router/
│   │   └── index.js             # Configuración de rutas
│   ├── views/
│   │   ├── SearchResults.vue    # Resultados de búsqueda
│   │   ├── VinylDetail.vue      # Detalle de vinyl
│   │   └── VinylListing.vue     # Listado principal
│   ├── App.vue                  # Componente raíz
│   ├── main.js                  # Punto de entrada
│   └── style.css                # Estilos globales y tema
├── tests/
│   ├── components/              # Tests de componentes
│   ├── composables/             # Tests de composables
│   └── setup.js                 # Configuración de tests
├── index.html
├── package.json
├── vite.config.js
└── vitest.config.js
```

## Características

### Vista de Listado (estilo PriceRunner)
- Grid y lista de vinyls
- Filtros laterales (género, estilo, país, precio, demanda)
- Ordenación por múltiples campos
- Paginación completa
- Estados de carga con skeletons

### Tarjetas de Vinyl
- Imagen del disco con fallback
- Información del artista y título
- Géneros y año
- Estadísticas have/want
- Ratio de demanda con colores
- Precio más bajo
- Score de AI y recomendación
- Badge de rareza

### Sistema de Temas
- Dark mode (por defecto)
- Light mode
- Persistencia en localStorage
- Detección automática de preferencia del sistema
- Transiciones suaves

### Diseño Responsive
- Mobile-first
- Breakpoints: sm, md, lg, xl
- Menú de filtros colapsable en móvil

## Paleta de colores

### Dark Mode
| Color | Hex | Uso |
|-------|-----|-----|
| Primary | `#0F0F0F` | Fondo principal |
| Secondary | `#1E1E1E` | Fondo de tarjetas |
| Surface | `#252525` | Inputs, badges |
| Text | `#F5F5F5` | Texto principal |
| Text Muted | `#A0A0A0` | Texto secundario |
| Coral | `#FF4655` | Acciones, alertas |
| Lilac | `#9A77FF` | Acentos, hovers |

### Light Mode
| Color | Hex | Uso |
|-------|-----|-----|
| Primary | `#FFFFFF` | Fondo principal |
| Secondary | `#F8F9FA` | Fondo de tarjetas |
| Surface | `#F1F3F4` | Inputs, badges |
| Text | `#1A1A1A` | Texto principal |
| Text Muted | `#5F6368` | Texto secundario |
| Coral | `#E53935` | Acciones, alertas |
| Lilac | `#7C4DFF` | Acentos, hovers |

## Tipografías

- **Titulares**: Unica One (geometric sans-serif)
- **Texto general**: Inter (alta legibilidad)

## API

El frontend se conecta al backend Laravel a través de un proxy en desarrollo:

```javascript
// vite.config.js
server: {
  proxy: {
    '/api': {
      target: 'http://localhost:8000',
      changeOrigin: true,
    }
  }
}
```

### Endpoints utilizados

| Endpoint | Descripción |
|----------|-------------|
| `GET /api/discogs/saved` | Listar vinyls guardados |
| `GET /api/discogs/filters` | Obtener filtros disponibles |
| `GET /api/discogs/search-smart` | Búsqueda con insights |
| `GET /api/discogs/releases/:id/analysis` | Análisis de un vinyl |
| `POST /api/discogs/releases/:id/save` | Guardar vinyl |
| `DELETE /api/discogs/saved/:id` | Eliminar vinyl guardado |

## Testing

```bash
# Tests en modo watch
npm test

# Tests una vez
npm run test:run

# Con coverage
npm run test:coverage
```

### Tests incluidos (63 tests)

- **VinylCard** (15 tests): Renderizado, badges, colores, eventos
- **Pagination** (14 tests): Navegación, estados disabled
- **EmptyState** (9 tests): Props, slots, eventos
- **ThemeToggle** (7 tests): Iconos, toggle
- **useTheme** (8 tests): Persistencia, toggle
- **useVinyls** (10 tests): Filtros, fetch, errores

## Desarrollo

### Requisitos
1. Backend Laravel corriendo en `http://localhost:8000`
2. Base de datos configurada

### Iniciar desarrollo
```bash
# Terminal 1 - Backend
cd ../backend
php artisan serve

# Terminal 2 - Frontend
npm run dev
```

## Licencia

MIT
