<?php

namespace App\Console\Commands;

use App\Models\DiscogsAnalysis;
use App\Services\YouTubeService;
use Illuminate\Console\Command;

class FetchYouTubeTracks extends Command
{
    protected $signature = 'vinyls:youtube 
                            {--limit=100 : Maximum vinyls to process}
                            {--force : Re-fetch even if already has YouTube data}
                            {--max-tracks=3 : Maximum tracks per vinyl}';

    protected $description = 'Fetch YouTube tracks for vinyl releases';

    protected YouTubeService $youtube;

    public function __construct(YouTubeService $youtube)
    {
        parent::__construct();
        $this->youtube = $youtube;
    }

    public function handle(): int
    {
        if (!$this->youtube->isConfigured()) {
            $this->error('YouTube API key not configured. Add YOUTUBE_API_KEY to .env');
            return Command::FAILURE;
        }

        $limit = (int) $this->option('limit');
        $force = $this->option('force');
        $maxTracks = (int) $this->option('max-tracks');

        // Get vinyls to process
        $query = DiscogsAnalysis::query();
        
        if (!$force) {
            $query->where(function ($q) {
                $q->whereNull('youtube_fetched_at')
                  ->orWhere('has_youtube', false);
            });
        }

        $vinyls = $query->limit($limit)->get();

        if ($vinyls->isEmpty()) {
            $this->info('All vinyls already have YouTube data. Use --force to re-fetch.');
            return Command::SUCCESS;
        }

        $this->info("Fetching YouTube tracks for {$vinyls->count()} vinyls...");
        $this->newLine();

        $progressBar = $this->output->createProgressBar($vinyls->count());
        $progressBar->start();

        $found = 0;
        $notFound = 0;
        $errors = 0;

        foreach ($vinyls as $vinyl) {
            try {
                $result = $this->fetchTracksForVinyl($vinyl, $maxTracks);
                
                if ($result) {
                    $found++;
                } else {
                    $notFound++;
                }

                // Rate limit - YouTube allows 10,000 units/day
                // Search costs 100 units, so ~100 searches/day max
                usleep(200000); // 200ms between requests

            } catch (\Exception $e) {
                $errors++;
                $this->newLine();
                $this->error("Error for {$vinyl->discogs_id}: " . $e->getMessage());
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("═══════════════════════════════════════");
        $this->info("YouTube Track Fetch Complete!");
        $this->info("Found tracks: {$found}");
        $this->info("No tracks found: {$notFound}");
        $this->info("Errors: {$errors}");
        $this->info("═══════════════════════════════════════");

        // Show summary
        $totalWithYoutube = DiscogsAnalysis::where('has_youtube', true)->count();
        $this->info("Total vinyls with YouTube: {$totalWithYoutube}");

        return Command::SUCCESS;
    }

    protected function fetchTracksForVinyl(DiscogsAnalysis $vinyl, int $maxTracks): bool
    {
        $artist = $vinyl->artist_name ?? 'Unknown';
        $title = $vinyl->title ?? 'Unknown';

        // Skip if no meaningful data
        if ($artist === 'Unknown' && $title === 'Unknown') {
            $vinyl->update([
                'youtube_tracks' => [],
                'has_youtube' => false,
                'youtube_fetched_at' => now(),
            ]);
            return false;
        }

        // Search for tracks
        $tracks = $this->youtube->searchTracks($artist, $title, $vinyl->year, $maxTracks);

        // Filter tracks with minimum relevance
        $relevantTracks = array_filter($tracks, fn($t) => $t['relevance'] >= 30);

        // Take only the best tracks
        $relevantTracks = array_slice($relevantTracks, 0, $maxTracks);

        $hasTracks = !empty($relevantTracks);

        $vinyl->update([
            'youtube_tracks' => array_values($relevantTracks),
            'has_youtube' => $hasTracks,
            'youtube_fetched_at' => now(),
        ]);

        return $hasTracks;
    }
}
