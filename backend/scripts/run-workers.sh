#!/bin/bash

# Vinyl Marketplace - Queue Workers
# Run this script to process queued jobs

echo "🎵 Starting Vinyl Marketplace Queue Workers..."
echo ""

# Check if Redis is available
if ! redis-cli ping > /dev/null 2>&1; then
    echo "❌ Redis is not running. Start Redis first:"
    echo "   brew services start redis  (macOS)"
    echo "   sudo systemctl start redis (Linux)"
    echo "   Or use Docker: cd infra && docker-compose up -d redis"
    exit 1
fi

echo "✅ Redis is running"
echo ""

# Function to start a worker in background
start_worker() {
    local queue=$1
    local name=$2
    echo "🚀 Starting $name worker (queue: $queue)..."
    php artisan queue:work redis --queue=$queue --sleep=3 --tries=3 --max-jobs=100 &
}

# Start workers for each queue
start_worker "ai" "AI Analysis"
start_worker "youtube" "YouTube"
start_worker "discogs" "Discogs Import"
start_worker "default" "Default"

echo ""
echo "✅ All workers started!"
echo ""
echo "📋 Queue endpoints:"
echo "   POST /api/queue/analyze/{id}     - Analyze single vinyl"
echo "   POST /api/queue/analyze-batch    - Batch analyze vinyls"
echo "   POST /api/queue/youtube/{id}     - Fetch YouTube tracks"
echo "   POST /api/queue/import           - Import from Discogs"
echo "   GET  /api/queue/stats            - Queue statistics"
echo ""
echo "Press Ctrl+C to stop all workers"

# Wait for all background jobs
wait
