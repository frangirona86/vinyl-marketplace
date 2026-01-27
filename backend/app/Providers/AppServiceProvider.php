<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Rate limiter for Discogs API (60 requests per minute)
        RateLimiter::for('discogs-api', function ($job) {
            return Limit::perMinute(60);
        });

        // Rate limiter for OpenAI API (reasonable limit)
        RateLimiter::for('openai-api', function ($job) {
            return Limit::perMinute(30);
        });

        // Rate limiter for YouTube API (100 requests per day for search)
        RateLimiter::for('youtube-api', function ($job) {
            return Limit::perDay(100);
        });
    }
}
