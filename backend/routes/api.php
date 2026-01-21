<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RecordController;
use App\Http\Controllers\ArtistController;
use App\Http\Controllers\VariantController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\DiscogsController;

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
    
    // Saved analyses
    Route::get("/saved", [DiscogsController::class, "getSaved"]);
    Route::get("/saved/stats", [DiscogsController::class, "getSavedStats"]);
    Route::delete("/saved/{id}", [DiscogsController::class, "removeSaved"]);
});
