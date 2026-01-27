<?php

namespace App\Jobs;

use App\Models\DiscogsAnalysis;
use App\Services\DiscogsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Log;

class ImportVinylFromDiscogsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 120; // 2 minutes (Discogs rate limit)
    public int $timeout = 180;

    public function __construct(
        public int $discogsId,
        public bool $analyzeAfter = false,
        public bool $fetchYouTube = false
    ) {}

    /**
     * Rate limit to respect Discogs API (60 requests/minute)
     */
    public function middleware(): array
    {
        return [new RateLimited('discogs-api')];
    }

    public function handle(DiscogsService $discogs): void
    {
        // Check if already exists
        if (DiscogsAnalysis::where('discogs_id', $this->discogsId)->exists()) {
            Log::info("ImportVinylFromDiscogsJob: Vinyl {$this->discogsId} already exists");
            return;
        }

        try {
            $analysis = $discogs->getCompleteAnalysis($this->discogsId);

            if (!$analysis) {
                Log::warning("ImportVinylFromDiscogsJob: Could not fetch vinyl {$this->discogsId}");
                return;
            }

            $release = $analysis['release'];
            $community = $analysis['community'];
            $marketplace = $analysis['marketplace'];

            $vinyl = DiscogsAnalysis::create([
                'discogs_id' => $this->discogsId,
                'title' => $release['title'],
                'artist_name' => $release['artist_name'],
                'year' => $release['year'],
                'country' => $release['country'],
                'label' => $release['label'],
                'catalog_number' => $release['catalog_number'],
                'genres' => $release['genres'],
                'styles' => $release['styles'] ?? [],
                'format' => $release['formats'][0]['name'] ?? null,
                'have' => $community['have'] ?? 0,
                'want' => $community['want'] ?? 0,
                'rating_average' => $community['rating_average'] ?? 0,
                'rating_count' => $community['rating_count'] ?? 0,
                'num_for_sale' => $marketplace['total_listings'] ?? 0,
                'lowest_price' => $marketplace['stats']['lowest_price']['value'] ?? null,
                'lowest_price_currency' => $marketplace['stats']['lowest_price']['currency'] ?? null,
                'price_suggestions' => $marketplace['price_suggestions'],
                'demand_ratio' => $analysis['analysis']['demand_ratio'],
                'is_rare' => $analysis['analysis']['is_rare'],
                'is_in_demand' => $analysis['analysis']['is_in_demand'],
                'raw_data' => $analysis,
                'cover_image' => $release['images'][0]['uri'] ?? null,
                'thumb' => $release['images'][0]['uri150'] ?? null,
                'fetched_at' => now(),
            ]);

            Log::info("ImportVinylFromDiscogsJob: Imported vinyl {$this->discogsId}");

            // Dispatch follow-up jobs if requested
            if ($this->analyzeAfter) {
                AnalyzeVinylJob::dispatch($vinyl->id)->onQueue('ai');
            }

            if ($this->fetchYouTube) {
                FetchYouTubeTracksJob::dispatch($vinyl->id)->onQueue('youtube');
            }

        } catch (\Exception $e) {
            Log::error("ImportVinylFromDiscogsJob: Error importing {$this->discogsId}: " . $e->getMessage());
            throw $e;
        }
    }
}
