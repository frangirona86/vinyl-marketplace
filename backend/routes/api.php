<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RecordController;
use App\Http\Controllers\ArtistController;
use App\Http\Controllers\VariantController;
use App\Http\Controllers\OrderController;

// Records API
Route::apiResource('records', RecordController::class);

// Artists API
Route::apiResource('artists', ArtistController::class);

// Variants API
Route::apiResource('variants', VariantController::class);

// Orders API
Route::apiResource('orders', OrderController::class);

