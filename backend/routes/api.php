<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RecordController;
use App\Http\Controllers\ArtistController;
use App\Http\Controllers\VariantController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\DiscogsController;
use App\Http\Controllers\VinylScorerController;
use App\Http\Controllers\SearchController;

// Records API
Route::apiResource("records", RecordController::class);

// Artists API
Route::apiResource("artists", ArtistController::class);

// Variants API
Route::apiResource("variants", VariantController::class);

// Orders API
Route::apiResource("orders", OrderController::class);

// Discogs API Integration
Route::prefix("discogs")->group(function () {
    // Search
    Route::get("/search", [DiscogsController::class, "searchReleases"]);
    Route::get("/search-market", [DiscogsController::class, "searchWithMarket"]);
    Route::get("/search-smart", [DiscogsController::class, "searchSmart"]); // AI-enriched search
    Route::get("/artists-search", [DiscogsController::class, "searchArtists"]);
    
    // Release details
    Route::get("/releases/{id}", [DiscogsController::class, "getRelease"]);
    Route::get("/releases/{id}/prices", [DiscogsController::class, "getPrices"]);
    Route::get("/releases/{id}/stats", [DiscogsController::class, "getStats"]);
    Route::get("/releases/{id}/listings", [DiscogsController::class, "getListings"]);
    Route::get("/releases/{id}/analysis", [DiscogsController::class, "getAnalysis"]);
    
    // Save to analysis DB
    Route::post("/releases/{id}/save", [DiscogsController::class, "saveToAnalysis"]);
    
    // Artist details
    Route::get("/artists/{id}", [DiscogsController::class, "getArtist"]);
    
    // Saved analyses (specific routes must come before {id} routes)
    Route::get("/saved", [DiscogsController::class, "getSaved"]);
    Route::get("/saved/stats", [DiscogsController::class, "getSavedStats"]);
    Route::get("/saved/{id}", [DiscogsController::class, "getSavedById"])->whereNumber('id');
    Route::delete("/saved/{id}", [DiscogsController::class, "removeSaved"])->whereNumber('id');
    
    // Filters for frontend (genres, styles, countries, etc.)
    Route::get("/filters", [DiscogsController::class, "getFilters"]);
});

// Queue Management
Route::prefix("queue")->group(function () {
    Route::post("/analyze/{id}", [\App\Http\Controllers\QueueController::class, "analyzeVinyl"]);
    Route::post("/analyze-batch", [\App\Http\Controllers\QueueController::class, "analyzeBatch"]);
    Route::post("/youtube/{id}", [\App\Http\Controllers\QueueController::class, "fetchYouTube"]);
    Route::post("/import", [\App\Http\Controllers\QueueController::class, "importFromDiscogs"]);
    Route::get("/stats", [\App\Http\Controllers\QueueController::class, "stats"]);
});

// Vinyl Scorer AI Agent
Route::prefix("vinyl-scorer")->group(function () {
    // AI-powered analysis (requires OpenAI key)
    Route::post("/analyze", [VinylScorerController::class, "analyze"]);
    
    // Quick algorithmic score (no AI needed)
    Route::get("/quick/{discogs_id}", [VinylScorerController::class, "quickScore"]);
    
    // Batch scoring for multiple releases
    Route::post("/batch", [VinylScorerController::class, "batchScore"]);
    
    // Refresh/update analysis for trend detection
    Route::post("/refresh/{discogs_id}", [VinylScorerController::class, "refresh"]);
    Route::post("/refresh-all", [VinylScorerController::class, "refreshAll"]);
    
    // Get trending vinyls (increasing in demand)
    Route::get("/trending", [VinylScorerController::class, "trending"]);
});

// Smart Search API (with history and autocomplete)
Route::prefix("search")->group(function () {
    // Autocomplete suggestions
    Route::get("/suggest", [SearchController::class, "suggest"]);
    
    // Full search with filters
    Route::get("/", [SearchController::class, "search"]);
    
    // Search history
    Route::get("/history", [SearchController::class, "history"]);
    Route::delete("/history", [SearchController::class, "clearHistory"]);
    
    // Record selection (for improving suggestions)
    Route::post("/select", [SearchController::class, "recordSelection"]);
    
    // Search stats
    Route::get("/stats", [SearchController::class, "stats"]);
    
    // Cache management (for scaling)
    Route::post("/warm-cache", [SearchController::class, "warmCache"]);
    Route::delete("/cache", [SearchController::class, "invalidateCache"]);
});
