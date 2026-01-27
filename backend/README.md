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

### 🔍 Smart Search API (New!)

Advanced search system with autocomplete, history tracking, and intelligent caching.

| Endpoint | Description |
|----------|-------------|
| `GET /api/search?q=query` | Full-text search with filters |
| `GET /api/search/suggest?q=query` | Autocomplete suggestions |
| `GET /api/search/history` | User search history |
| `DELETE /api/search/history` | Clear search history |
| `POST /api/search/select` | Record user selection (improves suggestions) |
| `GET /api/search/stats` | Search statistics |
| `POST /api/search/warm-cache` | Pre-warm cache for common queries |
| `DELETE /api/search/cache` | Invalidate search cache |

#### Smart Search Example

```bash
curl "http://localhost:8080/api/search?q=aphex+twin&sort=demand&genre=Electronic"
```

**Response:**
```json
{
  "query": "aphex twin",
  "type": "all",
  "results": {
    "local": {
      "data": [
        {
          "id": 1234,
          "title": "Selected Ambient Works",
          "artist": "Aphex Twin",
          "year": 1992,
          "genres": ["Electronic"],
          "have": 200,
          "want": 800,
          "demand_ratio": 4.0,
          "lowest_price": 120.00,
          "insights": {
            "tags": [
              {"type": "rarity", "label": "💎 Ultra Rare", "value": "ultra_rare"},
              {"type": "demand", "label": "🔥 Hot", "value": "hot"}
            ],
            "quick_score": 85,
            "recommendation": "BUY"
          },
          "source": "local"
        }
      ],
      "total": 5,
      "per_page": 20,
      "current_page": 1
    },
    "discogs": []
  },
  "total": 5,
  "filters_applied": {"sort": "demand", "genre": "Electronic"}
}
```

#### Autocomplete Suggestions

```bash
curl "http://localhost:8080/api/search/suggest?q=beat"
```

**Response:**
```json
{
  "query": "beat",
  "suggestions": {
    "artists": [
      {"id": 1, "name": "The Beatles", "vinyl_count": 15, "avg_demand": 1.8}
    ],
    "genres": [
      {"name": "Beat", "count": 42, "type": "genre"}
    ],
    "labels": [
      {"name": "Beat Records", "count": 8, "type": "label"}
    ],
    "vinyls": [
      {"id": 123, "title": "Abbey Road", "artist": "The Beatles", "price": 30.00}
    ]
  },
  "recent": [],
  "popular": [
    {"query": "electronic", "count": 25}
  ]
}
```

#### Search Filters

| Filter | Type | Description |
|--------|------|-------------|
| `q` | string | Search query (required, min 2 chars) |
| `type` | string | all, vinyl, artist, genre, label |
| `genre` | string | Filter by genre |
| `year_from` | integer | Minimum year |
| `year_to` | integer | Maximum year |
| `price_min` | numeric | Minimum price |
| `price_max` | numeric | Maximum price |
| `sort` | string | relevance, demand, price_asc, price_desc, year, rare |
| `per_page` | integer | Results per page (max 50) |

### 🔄 Queue System (Redis)

Asynchronous job processing for heavy tasks. Uses Redis as queue driver with rate limiting.

| Endpoint | Description |
|----------|-------------|
| `POST /api/queue/analyze/{id}` | Queue AI analysis for a vinyl |
| `POST /api/queue/analyze-batch` | Queue batch analysis (limit, include_youtube) |
| `POST /api/queue/youtube/{id}` | Queue YouTube track fetch |
| `POST /api/queue/import` | Queue Discogs import (discogs_id, analyze, youtube) |
| `GET /api/queue/stats` | Get queue statistics |

#### Queue Configuration

```env
QUEUE_CONNECTION=redis
REDIS_HOST=redis
REDIS_PORT=6379
```

#### Running Queue Workers

```bash
# Single worker (all queues)
php artisan queue:work --queue=ai,youtube,discogs,default

# Or use the provided script
./scripts/run-workers.sh
```

#### Rate Limits

| API | Limit |
|-----|-------|
| Discogs | 60 requests/minute |
| OpenAI | 30 requests/minute |
| YouTube | 100 requests/day |

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

# YouTube API (for track previews - get key at https://console.cloud.google.com/)
YOUTUBE_API_KEY=your_youtube_key
```

## 🧪 Testing

```bash
# Run all tests
docker exec vinyl_app php artisan test

# Run specific test suites
docker exec vinyl_app php artisan test --filter=SearchControllerTest
docker exec vinyl_app php artisan test --filter=DiscogsControllerTest
docker exec vinyl_app php artisan test --filter=VinylScorerControllerTest
docker exec vinyl_app php artisan test --filter=DiscogsAnalysisTest
```

**Test Coverage:**
- 128 tests, 389 assertions

| Test Suite | Tests | Description |
|------------|-------|-------------|
| SearchControllerTest | 24 | Smart search, suggestions, history, caching |
| DiscogsControllerTest | 14 | Search smart, filters, saved analyses |
| VinylScorerControllerTest | 16 | Quick score, analyze, refresh, trending |
| RecordControllerTest | 14 | CRUD operations, variants |
| QueueControllerTest | 8 | Analyze, batch, YouTube, import jobs |
| SearchHistoryTest | 12 | History recording, scopes, popular searches |
| DiscogsAnalysisTest | 22 | Model scopes, attributes, casting |
| FetchYouTubeTracksJobTest | 7 | YouTube track fetching job |
| AnalyzeVinylJobTest | 5 | AI analysis job |
| ArtistControllerTest | 3 | Artist CRUD |
| ExampleTests | 3 | Basic sanity checks |

## 📁 Project Structure

```
backend/
├── app/
│   ├── AI/
│   │   ├── Agents/
│   │   │   └── VinylScorerAgent.php    # AI Agent with tools
│   │   └── Tools/
│   │       └── VinylScorer.php          # Scoring algorithm
│   ├── Console/
│   │   └── Commands/
│   │       ├── ImportDiscogsVinyls.php   # Import vinyls from Discogs
│   │       └── RefreshVinylDetails.php   # Refresh tracklist & prices
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── RecordController.php
│   │   │   ├── ArtistController.php
│   │   │   ├── DiscogsController.php     # Search, filters, saved
│   │   │   ├── SearchController.php      # 🆕 Smart search & autocomplete
│   │   │   ├── VinylScorerController.php # AI scoring, refresh, trending
│   │   │   ├── QueueController.php       # Queue management API
│   │   │   └── ...
│   │   ├── Requests/        # Form Request validation
│   │   └── Resources/       # API Resources (JSON transformation)
│   ├── Jobs/
│   │   ├── AnalyzeVinylJob.php           # AI analysis job
│   │   ├── FetchYouTubeTracksJob.php     # YouTube fetch job
│   │   ├── ImportVinylFromDiscogsJob.php # Discogs import job
│   │   └── BatchAnalyzeVinylsJob.php     # Batch processing job
│   ├── Models/
│   │   ├── Record.php
│   │   ├── Artist.php
│   │   ├── DiscogsAnalysis.php   # With scopes & helpers
│   │   ├── SearchHistory.php     # 🆕 Search history tracking
│   │   └── ...
│   └── Services/
│       ├── DiscogsService.php
│       └── YouTubeService.php    # YouTube API integration
├── database/
│   ├── factories/
│   │   └── DiscogsAnalysisFactory.php
│   └── migrations/
├── routes/
│   └── api.php
├── scripts/
│   └── run-workers.sh            # Queue workers script
└── tests/
    ├── Unit/
    │   ├── DiscogsAnalysisTest.php
    │   ├── SearchHistoryTest.php         # 🆕 Search history model tests
    │   ├── AnalyzeVinylJobTest.php
    │   └── FetchYouTubeTracksJobTest.php
    └── Feature/
        ├── SearchControllerTest.php      # 🆕 Smart search tests
        ├── DiscogsControllerTest.php
        ├── VinylScorerControllerTest.php
        └── QueueControllerTest.php
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

### SearchHistory

Tracks user search history for autocomplete and analytics:

| Field | Type | Description |
|-------|------|-------------|
| `session_id` | string | Session identifier |
| `user_id` | integer | Optional user ID |
| `query` | string | Search query |
| `type` | string | Search type (general, genre, artist) |
| `results_count` | integer | Number of results returned |
| `filters` | json | Applied filters |
| `selected_result` | json | User's clicked result |

#### Model Scopes

```php
// Filter by session/user
SearchHistory::bySession($sessionId)->get();
SearchHistory::byUser($userId)->get();

// Get unique queries
SearchHistory::uniqueQueries()->limit(10)->get();

// Get recent searches
SearchHistory::recent(5)->get();

// Get popular searches
SearchHistory::getPopularSearches(10, 7); // top 10 in last 7 days
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
