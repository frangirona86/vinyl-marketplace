<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Discogs API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for connecting to the Discogs API
    | Get your credentials at: https://www.discogs.com/settings/developers
    |
    */

    'consumer_key' => env('DISCOGS_CONSUMER_KEY'),
    'consumer_secret' => env('DISCOGS_CONSUMER_SECRET'),
    'user_agent' => env('DISCOGS_USER_AGENT', 'VinylMarketplace/1.0'),
    'base_url' => 'https://api.discogs.com',
];
