# Vinyl Marketplace - Backend API

Backend API for a vinyl records marketplace built with Laravel.

## 🚀 Stack

- **Framework:** Laravel 11
- **Database:** PostgreSQL
- **Cache:** Redis
- **Container:** Docker + Docker Compose

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
| `GET /api/discogs/search?q=query` | Search releases |
| `GET /api/discogs/search-market?q=query` | Search with market data (have/want/price) |
| `GET /api/discogs/artists-search?q=query` | Search artists |

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
| `GET /api/discogs/saved` | Get all saved analyses |
| `GET /api/discogs/saved/stats` | Get aggregated statistics |
| `DELETE /api/discogs/saved/{id}` | Remove from saved analyses |

**Save release example:**

```bash
curl -X POST "http://localhost:8080/api/discogs/releases/9269057/save" \
  -H "Content-Type: application/json" \
  -d '{"watchlist": true, "notes": "Track this release"}'
```

**Filter saved analyses:**

```bash
# Get watchlist items
GET /api/discogs/saved?watchlist=true

# Get rare items
GET /api/discogs/saved?rare=true

# Get high demand items
GET /api/discogs/saved?in_demand=true&min_demand=1.5

# Filter by artist
GET /api/discogs/saved?artist=Beatles

# Sort by demand ratio
GET /api/discogs/saved?sort=demand_ratio&dir=desc
```

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
```

## 🧪 Testing

```bash
# Run all tests
docker exec vinyl_app php artisan test

# Run specific test file
docker exec vinyl_app php artisan test --filter=RecordControllerTest
```

## 📁 Project Structure

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── RecordController.php
│   │   │   ├── ArtistController.php
│   │   │   ├── DiscogsController.php
│   │   │   └── ...
│   │   ├── Requests/        # Form Request validation
│   │   └── Resources/       # API Resources (JSON transformation)
│   ├── Models/
│   │   ├── Record.php
│   │   ├── Artist.php
│   │   ├── DiscogsAnalysis.php
│   │   └── ...
│   └── Services/
│       └── DiscogsService.php
├── database/
│   ├── factories/
│   └── migrations/
├── routes/
│   └── api.php
└── tests/
    └── Feature/
```

## 📊 Data Models

### DiscogsAnalysis

Stores analysis data from Discogs for tracking and comparison:

- **Release info:** title, artist, year, country, label, genres
- **Community stats:** have count, want count, ratings
- **Marketplace stats:** listings count, lowest price, price suggestions
- **Calculated metrics:** demand ratio, is_rare, is_in_demand
- **Tracking:** watchlist flag, notes, fetch timestamp

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
