# Vinyl Marketplace - Frontend

Frontend application for Vinyl Marketplace, built with Vue.js 3 and Tailwind CSS v4.

## Technologies

- **Vue.js 3** - Progressive JavaScript Framework
- **Vite** - Next Generation Build Tool
- **Tailwind CSS v4** - Utility-First CSS Framework
- **Vue Router 4** - Official Router for Vue.js
- **Axios** - HTTP Client
- **Vitest** - Testing Framework

## Requirements

- Node.js 18+
- npm 9+

## Installation

```bash
# Install dependencies
npm install

# Run development server
npm run dev

# Build for production
npm run build

# Preview production build
npm run preview
```

## Available Scripts

| Script | Description |
|--------|-------------|
| `npm run dev` | Development server at http://localhost:5173 |
| `npm run build` | Production build |
| `npm run preview` | Preview production build |
| `npm test` | Run tests in watch mode |
| `npm run test:run` | Run tests once |
| `npm run test:coverage` | Run tests with coverage report |

## Project Structure

```
frontend/
├── public/
│   └── placeholder-vinyl.svg    # Image placeholder
├── src/
│   ├── api/
│   │   ├── discogs.js           # Discogs API service
│   │   └── search.js            # 🆕 Smart Search API service
│   ├── components/
│   │   ├── layout/
│   │   │   └── AppHeader.vue    # Application header
│   │   └── ui/
│   │       ├── EmptyState.vue       # Empty state component
│   │       ├── FiltersPanel.vue     # Filters panel
│   │       ├── LoadingSpinner.vue   # Loading spinner
│   │       ├── Pagination.vue       # Pagination component
│   │       ├── SearchBar.vue        # Basic search bar
│   │       ├── SmartSearchBar.vue   # 🆕 Smart search with autocomplete
│   │       ├── ThemeToggle.vue      # Dark/light mode toggle
│   │       ├── VinylCard.vue        # Vinyl card (grid view)
│   │       ├── VinylCardSkeleton.vue # Skeleton loading
│   │       └── VinylListItem.vue    # Vinyl item (list view)
│   ├── composables/
│   │   ├── useTheme.js          # Theme management (dark/light)
│   │   └── useVinyls.js         # Vinyls and filters management
│   ├── router/
│   │   └── index.js             # Routes configuration
│   ├── views/
│   │   ├── SearchResults.vue    # Search results page
│   │   ├── VinylDetail.vue      # Vinyl detail page with YouTube player
│   │   └── VinylListing.vue     # Main listing page
│   ├── App.vue                  # Root component
│   ├── main.js                  # Entry point
│   └── style.css                # Global styles and theme
├── tests/
│   ├── components/              # Component tests
│   ├── composables/             # Composable tests
│   └── setup.js                 # Test configuration
├── index.html
├── package.json
├── vite.config.js
└── vitest.config.js
```

## Features

### Smart Search (New!)
- Real-time autocomplete suggestions
- Categorized suggestions (artists, genres, labels, vinyls)
- Search history tracking
- Popular searches display
- Advanced filters (genre, year range, price range)
- Sorting options (relevance, demand, price, year, rarity)
- Results with AI insights and recommendations
- Records user selections to improve suggestions

### Listing View (PriceRunner style)
- Grid and list view modes
- Sidebar filters (genre, style, country, price, demand)
- Multi-field sorting
- Full pagination
- Skeleton loading states
- YouTube preview indicator on cards

### Vinyl Cards
- Album cover with fallback
- Artist and title information
- Genres and year
- Have/want statistics
- Color-coded demand ratio
- Lowest price
- AI score and recommendation
- Rarity badge
- YouTube play indicator (red button)

### Vinyl Detail Page
- Full album artwork
- AI analysis with score and recommendation
- Market statistics (have/want/demand ratio)
- Market price range (from/to based on condition)
- Complete tracklist with positions and durations
- Tracklist-YouTube integration (play button on matching tracks)
- Genres and styles
- YouTube tracks section with embedded player
- Modal video player
- Direct links to Discogs and YouTube

### YouTube Integration
- Automatic track search for each vinyl
- Embedded video player in detail view
- Preview indicator in listing cards
- Relevance-based track matching

### Theme System
- Dark mode (default)
- Light mode
- localStorage persistence
- Automatic system preference detection
- Smooth transitions

### Responsive Design
- Mobile-first approach
- Breakpoints: sm, md, lg, xl
- Collapsible filter menu on mobile

## Color Palette

### Dark Mode
| Color | Hex | Usage |
|-------|-----|-------|
| Primary | `#0F0F0F` | Main background |
| Secondary | `#1E1E1E` | Card backgrounds |
| Surface | `#252525` | Inputs, badges |
| Text | `#F5F5F5` | Primary text |
| Text Muted | `#A0A0A0` | Secondary text |
| Coral | `#FF4655` | Actions, alerts |
| Lilac | `#9A77FF` | Accents, hovers |

### Light Mode
| Color | Hex | Usage |
|-------|-----|-------|
| Primary | `#FFFFFF` | Main background |
| Secondary | `#F8F9FA` | Card backgrounds |
| Surface | `#F1F3F4` | Inputs, badges |
| Text | `#1A1A1A` | Primary text |
| Text Muted | `#5F6368` | Secondary text |
| Coral | `#E53935` | Actions, alerts |
| Lilac | `#7C4DFF` | Accents, hovers |

## Typography

- **Headings**: Unica One (geometric sans-serif)
- **Body text**: Inter (high readability)

## API

The frontend connects to the Laravel backend through a development proxy:

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

### API Endpoints

| Endpoint | Description |
|----------|-------------|
| `GET /api/discogs/saved` | List saved vinyls (includes YouTube data) |
| `GET /api/discogs/saved/:id` | Get single vinyl with YouTube tracks |
| `GET /api/discogs/filters` | Get available filters |
| `GET /api/discogs/search-smart` | Search with AI insights |
| `GET /api/discogs/releases/:id/analysis` | Get vinyl analysis |
| `POST /api/discogs/releases/:id/save` | Save vinyl |
| `DELETE /api/discogs/saved/:id` | Delete saved vinyl |

### Smart Search API (New!)

| Endpoint | Description |
|----------|-------------|
| `GET /api/search?q=query` | Full-text search with filters |
| `GET /api/search/suggest?q=query` | Autocomplete suggestions |
| `GET /api/search/history` | User search history |
| `DELETE /api/search/history` | Clear search history |
| `POST /api/search/select` | Record user selection |
| `GET /api/search/stats` | Search statistics |

### Queue API (Background Processing)

| Endpoint | Description |
|----------|-------------|
| `POST /api/queue/analyze/:id` | Queue AI analysis |
| `POST /api/queue/analyze-batch` | Queue batch analysis |
| `POST /api/queue/youtube/:id` | Queue YouTube fetch |
| `POST /api/queue/import` | Queue Discogs import |
| `GET /api/queue/stats` | Get queue statistics |

## Testing

```bash
# Run tests in watch mode
npm test

# Run tests once
npm run test:run

# Run with coverage
npm run test:coverage
```

### Test Coverage (91 tests)

- **VinylCard** (18 tests): Rendering, badges, colors, events, YouTube indicator
- **Pagination** (14 tests): Navigation, disabled states
- **EmptyState** (9 tests): Props, slots, events
- **ThemeToggle** (7 tests): Icons, toggle functionality
- **useTheme** (8 tests): Persistence, toggle
- **useVinyls** (10 tests): Filters, fetch, error handling
- **useSearch** (25 tests): Suggestions, history, keyboard navigation, selection

## Development

### Prerequisites
1. Laravel backend running at `http://localhost:8000`
2. Database configured
3. YouTube API key configured (for track previews)

### Start Development
```bash
# Terminal 1 - Backend
cd ../backend
php artisan serve

# Terminal 2 - Frontend
npm run dev
```

### Backend Commands

```bash
# Import vinyls from Discogs
php artisan discogs:import --styles=Techno --styles=Electro --total=100

# Run AI analysis on vinyls
php artisan vinyls:analyze-ai --limit=100

# Fetch YouTube tracks
php artisan vinyls:youtube --limit=100
```

## License

MIT
