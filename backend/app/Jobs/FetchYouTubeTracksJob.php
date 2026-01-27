<?php

namespace App\Jobs;

use App\Models\DiscogsAnalysis;
use App\Services\YouTubeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchYouTubeTracksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 30;
    public int $timeout = 60;

    public function __construct(
        public int $vinylId,
        public int $maxTracks = 3
    ) {}

    public function handle(YouTubeService $youtube): void
    {
        $vinyl = DiscogsAnalysis::find($this->vinylId);
        
        if (!$vinyl) {
            Log::warning("FetchYouTubeTracksJob: Vinyl {$this->vinylId} not found");
            return;
        }

        // Skip if already fetched recently (within 7 days)
        if ($vinyl->youtube_fetched_at && $vinyl->youtube_fetched_at->diffInDays(now()) < 7) {
            return;
        }

        if (!$youtube->isConfigured()) {
            Log::warning('FetchYouTubeTracksJob: YouTube API key not configured');
            return;
        }

        $artist = $vinyl->artist_name ?? 'Unknown';
        $title = $vinyl->title ?? 'Unknown';

        if ($artist === 'Unknown' && $title === 'Unknown') {
            $vinyl->update([
                'youtube_tracks' => [],
                'has_youtube' => false,
                'youtube_fetched_at' => now(),
            ]);
            return;
        }

        try {
            $tracks = $youtube->searchTracks($artist, $title, $vinyl->year, $this->maxTracks);
            
            // Filter by relevance
            $relevantTracks = array_filter($tracks, fn($t) => $t['relevance'] >= 30);
            $relevantTracks = array_slice($relevantTracks, 0, $this->maxTracks);
            
            $hasTracks = !empty($relevantTracks);

            $vinyl->update([
                'youtube_tracks' => array_values($relevantTracks),
                'has_youtube' => $hasTracks,
                'youtube_fetched_at' => now(),
            ]);

            Log::info("FetchYouTubeTracksJob: Found " . count($relevantTracks) . " tracks for vinyl {$vinyl->discogs_id}");

        } catch (\Exception $e) {
            Log::error("FetchYouTubeTracksJob: Error for vinyl {$this->vinylId}: " . $e->getMessage());
            throw $e;
        }
    }
}
