# Vinyl Marketplace - Backend API

Backend API for a vinyl records marketplace built with Laravel.

## 🚀 Stack

- **Framework:** Laravel 11
- **Database:** PostgreSQL
- **Cache:** Redis
- **Container:** Docker + Docker Compose
- **AI:** Neuron AI + OpenAI

## 📦 Installation

### Prerequisites

- Docker & Docker Compose (or Colima for macOS)
- PHP 8.2+ (for local development)
- Composer

### Quick Start with Docker

```bash
# Navigate to infrastructure folder
cd infra

# Start all services
docker compose up --build

# Run migrations
docker exec vinyl_app php artisan migrate
```

The API will be available at `http://localhost:8080`

## 🔌 API Endpoints

### Core Resources

| Resource | Endpoint | Methods |
|----------|----------|---------|
| Records | `/api/records` | GET, POST, PUT, DELETE |
| Artists | `/api/artists` | GET, POST, PUT, DELETE |
| Variants | `/api/variants` | GET, POST, PUT, DELETE |
| Orders | `/api/orders` | GET, POST, PUT, DELETE |

### Discogs Integration

The API integrates with [Discogs](https://www.discogs.com/) for music database and marketplace data.

#### Search

| Endpoint | Description |
|----------|-------------|
| `GET /api/discogs/search?q=query` | Basic search releases |
| `GET /api/discogs/search-market?q=query` | Search with market data (have/want/price) |
| `GET /api/discogs/search-smart?q=query` | 🆕 **Smart search with AI insights & tags** |
| `GET /api/discogs/artists-search?q=query` | Search artists |

#### Smart Search Example

```bash
curl "http://localhost:8080/api/discogs/search-smart?q=aphex+twin&per_page=3"
```

**Response includes:**
```json
{
  "results": [
    {
      "title": "Aphex Twin - Selected Ambient Works",
      "year": 1992,
      "genre": "Electronic",
      "style": "Ambient",
      "have": 5000,
      "want": 8000,
      "thumb": "https://...",
      "cover_image": "https://...",
      "insights": {
        "tags": [
          {"type": "genre", "label": "🎛️ Electronic"},
          {"type": "style", "label": "🏷️ Ambient"},
          {"type": "demand", "label": "🔥 Hot Demand"},
          {"type": "rarity", "label": "✨ Rare"}
        ],
        "quick_score": 78,
        "demand_ratio": 1.6,
        "recommendation": "BUY",
        "insight": "Strong collector demand. 8000 people want this vs 5000 who own it."
      },
      "saved_analysis": null
    }
  ]
}
```

#### Release Details

| Endpoint | Description |
|----------|-------------|
| `GET /api/discogs/releases/{id}` | Get release details |
| `GET /api/discogs/releases/{id}/prices` | Get price suggestions by condition |
| `GET /api/discogs/releases/{id}/stats` | Get marketplace statistics |
| `GET /api/discogs/releases/{id}/listings` | Get active marketplace listings |
| `GET /api/discogs/releases/{id}/analysis` | Get complete analysis (all data combined) |

#### Analysis & Watchlist

| Endpoint | Description |
|----------|-------------|
| `POST /api/discogs/releases/{id}/save` | Save release to analysis database |
| `GET /api/discogs/saved` | Get all saved analyses (with filters) |
| `GET /api/discogs/saved/stats` | Get aggregated statistics |
| `GET /api/discogs/filters` | 🆕 **Get available filters (genres, styles, countries, years)** |
| `DELETE /api/discogs/saved/{id}` | Remove from saved analyses |

#### Filters Example

```bash
curl "http://localhost:8080/api/discogs/filters"
```

**Response:**
```json
{
  "genres": ["Electronic", "Rock", "Jazz"],
  "styles": ["Techno", "House", "Ambient", "IDM"],
  "countries": ["UK", "US", "Germany"],
  "years": {"min": 1970, "max": 2024},
  "labels": ["Warp Records", "Universal"],
  "formats": ["Vinyl", "CD"],
  "recommendations": ["BUY", "HOLD", "AVOID"]
}
```

### 🤖 AI Vinyl Scorer Agent

AI-powered investment analysis for vinyl records using [Neuron AI](https://www.neuron-ai.dev/).

| Endpoint | Description |
|----------|-------------|
| `GET /api/vinyl-scorer/quick/{id}` | Quick algorithmic score (no AI) |
| `POST /api/vinyl-scorer/analyze` | 🆕 Full AI analysis with **price recommendation** |
| `POST /api/vinyl-scorer/batch` | Score multiple releases at once |
| `POST /api/vinyl-scorer/refresh/{id}` | 🆕 **Refresh analysis & detect trends** |
| `POST /api/vinyl-scorer/refresh-all` | 🆕 **Refresh stale records** |
| `GET /api/vinyl-scorer/trending` | 🆕 **Get trending vinyls** |

#### AI Analysis with Price Recommendation

```bash
curl -X POST "http://localhost:8080/api/vinyl-scorer/analyze" \
  -H "Content-Type: application/json" \
  -d '{"discogs_id": 27604764}'
```

**Response:**
```json
{
  "source": "ai_agent",
  "discogs_id": 27604764,
  "score": 68,
  "recommendation": "BUY",
  "price_recommendation": {
    "min": 18.00,
    "max": 30.00
  },
  "analysis": "Analysis of this vinyl...",
  "saved_to_db": true,
  "db_id": 5,
  "is_trending": false
}
```

#### Refresh & Trend Detection

```bash
# Refresh a specific record
curl -X POST "http://localhost:8080/api/vinyl-scorer/refresh/27604764"

# Response includes trend detection
{
  "message": "Analysis refreshed successfully",
  "discogs_id": 27604764,
  "score": 72,
  "recommendation": "BUY",
  "changes": {
    "have": 50,
    "want": 200,
    "price": 5.00
  },
  "is_trending": true,
  "trending_alert": "🔥 This vinyl is trending! Want increased significantly."
}
```

#### Get Trending Items

```bash
curl "http://localhost:8080/api/vinyl-scorer/trending"
```

**Response:**
```json
{
  "count": 3,
  "items": [
    {
      "discogs_id": 123456,
      "title": "Album Name",
      "artist": "Artist",
      "score": 85,
      "recommendation": "BUY",
      "want_change": 150,
      "want_change_percent": 25.5,
      "price_recommendation": {
        "min": 20.00,
        "max": 35.00
      }
    }
  ]
}
```

#### Scoring Criteria (0-100)

| Factor | Weight | Description |
|--------|--------|-------------|
| Demand | 25% | want/have ratio |
| Rarity | 20% | How many people have it |
| Price | 20% | Price vs market suggestion |
| Rating | 15% | Community rating |
| Availability | 10% | Copies for sale |
| Market Interest | 10% | Total want count |

**Recommendations:**
- 75-100: STRONG BUY
- 60-74: BUY
- 45-59: HOLD
- 30-44: AVOID
- 0-29: STRONG AVOID

## ⚙️ Configuration

### Environment Variables

Copy `.env.example` to `.env` and configure:

```env
# Database
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=vinyl_db
DB_USERNAME=vinyl_user
DB_PASSWORD=your_password

# Discogs API (get keys at https://www.discogs.com/settings/developers)
DISCOGS_CONSUMER_KEY=your_consumer_key
DISCOGS_CONSUMER_SECRET=your_consumer_secret
DISCOGS_USER_AGENT="VinylMarketplace/1.0"

# OpenAI API (for AI Vinyl Scorer - get key at https://platform.openai.com/)
OPENAI_API_KEY=your_openai_key
OPENAI_MODEL=gpt-4o-mini
```

## 🧪 Testing

```bash
# Run all tests
docker exec vinyl_app php artisan test

# Run specific test suites
docker exec vinyl_app php artisan test --filter=DiscogsControllerTest
docker exec vinyl_app php artisan test --filter=VinylScorerControllerTest
docker exec vinyl_app php artisan test --filter=DiscogsAnalysisTest
```

**Test Coverage:**
- 52 tests, 159 assertions
- DiscogsAnalysis model scopes & attributes
- Search smart with insights
- Filters endpoint
- Vinyl scorer (quick, batch, analyze)
- Refresh & trending detection

## 📁 Project Structure

```
backend/
├── app/
│   ├── AI/
│   │   ├── Agents/
│   │   │   └── VinylScorerAgent.php    # AI Agent with tools
│   │   └── Tools/
│   │       └── VinylScorer.php          # Scoring algorithm
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── RecordController.php
│   │   │   ├── ArtistController.php
│   │   │   ├── DiscogsController.php    # Search, filters, saved
│   │   │   ├── VinylScorerController.php # AI scoring, refresh, trending
│   │   │   └── ...
│   │   ├── Requests/        # Form Request validation
│   │   └── Resources/       # API Resources (JSON transformation)
│   ├── Models/
│   │   ├── Record.php
│   │   ├── Artist.php
│   │   ├── DiscogsAnalysis.php   # With scopes & helpers
│   │   └── ...
│   └── Services/
│       └── DiscogsService.php
├── database/
│   ├── factories/
│   └── migrations/
├── routes/
│   └── api.php
└── tests/
    ├── Unit/
    │   └── DiscogsAnalysisTest.php
    └── Feature/
        ├── DiscogsControllerTest.php
        └── VinylScorerControllerTest.php
```

## 📊 Data Models

### DiscogsAnalysis

Stores analysis data from Discogs for tracking and comparison:

| Field | Type | Description |
|-------|------|-------------|
| `discogs_id` | integer | Unique Discogs release ID |
| `title` | string | Album title |
| `artist_name` | string | Artist name |
| `year` | integer | Release year |
| `country` | string | Country of release |
| `label` | string | Record label |
| `genres` | json | Array of genres |
| `styles` | json | Array of styles (subgenres) |
| `format` | string | Vinyl, CD, etc. |
| `have` | integer | Users who own it |
| `want` | integer | Users who want it |
| `demand_ratio` | decimal | want/have ratio |
| `lowest_price` | decimal | Current lowest price |
| `recommended_price_min` | decimal | AI recommended min price |
| `recommended_price_max` | decimal | AI recommended max price |
| `ai_score` | integer | AI investment score (0-100) |
| `ai_recommendation` | string | BUY/HOLD/AVOID |
| `previous_have` | integer | For trend tracking |
| `previous_want` | integer | For trend tracking |
| `thumb` | string | Thumbnail image URL |
| `cover_image` | string | Full cover image URL |
| `is_watchlist` | boolean | Watchlist flag |

### Model Scopes

```php
// Filter by genre/style
DiscogsAnalysis::byGenre('Electronic')->get();
DiscogsAnalysis::byStyle('Techno')->get();

// Filter by year
DiscogsAnalysis::byYear(1997)->get();
DiscogsAnalysis::byYearRange(1990, 1999)->get();

// Filter by country/label
DiscogsAnalysis::byCountry('UK')->get();
DiscogsAnalysis::byLabel('Warp')->get();

// AI filters
DiscogsAnalysis::highScoring(70)->get();
DiscogsAnalysis::recommendedBuy()->get();

// Trending
DiscogsAnalysis::trending()->get();
DiscogsAnalysis::needsRefresh(24)->get();
```

### Model Attributes

```php
$analysis->image;         // Thumbnail (for lists)
$analysis->image_large;   // Full cover (for detail)
$analysis->hasImage();    // Check if has image
$analysis->formatted_price; // "25.00 USD"
$analysis->demand_status;   // "Very High Demand"
$analysis->changes;         // Array of changes since last refresh
$analysis->isTrending();    // Boolean
```

## 🛠️ Development

```bash
# Enter container
docker exec -it vinyl_app bash

# Run artisan commands
php artisan migrate
php artisan tinker
php artisan route:list

# Clear caches
php artisan cache:clear
php artisan config:clear
```

## 📝 License

MIT
