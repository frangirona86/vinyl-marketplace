#!/bin/bash

# Vinyl Marketplace - Docker Start Script

set -e

echo "🎵 Starting Vinyl Marketplace with Docker..."

# Navigate to infra directory
cd "$(dirname "$0")"

# Build and start containers
echo "📦 Building containers..."
docker-compose build

echo "🚀 Starting services..."
docker-compose up -d

# Wait for postgres to be ready
echo "⏳ Waiting for PostgreSQL..."
sleep 5

# Run migrations
echo "🗃️  Running migrations..."
docker-compose exec -T app php artisan migrate --force

# Clear caches
echo "🧹 Clearing caches..."
docker-compose exec -T app php artisan config:clear
docker-compose exec -T app php artisan cache:clear

echo ""
echo "✅ Vinyl Marketplace is running!"
echo ""
echo "   Backend API:  http://localhost:8080"
echo "   Frontend:     http://localhost:5173"
echo "   PostgreSQL:   localhost:5432"
echo "   Redis:        localhost:6379"
echo ""
echo "📋 Useful commands:"
echo "   docker-compose logs -f        # View logs"
echo "   docker-compose exec app bash  # Shell into app"
echo "   docker-compose down           # Stop all"
echo ""
