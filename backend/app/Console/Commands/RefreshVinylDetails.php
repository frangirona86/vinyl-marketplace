<?php

namespace App\Console\Commands;

use App\Models\DiscogsAnalysis;
use App\Services\DiscogsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RefreshVinylDetails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vinyls:refresh-details 
                            {--limit=50 : Number of vinyls to refresh}
                            {--force : Force refresh even if already has tracklist}
                            {--delay=1500 : Delay between requests in ms (default 1500ms for rate limit)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh vinyl details (tracklist, prices) from Discogs API';

    protected DiscogsService $discogs;
    protected array $errorLog = [];

    public function __construct(DiscogsService $discogs)
    {
        parent::__construct();
        $this->discogs = $discogs;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $force = $this->option('force');
        $delay = (int) $this->option('delay');

        // Get vinyls that need tracklist/price refresh
        $query = DiscogsAnalysis::query()
            ->whereNotNull('discogs_id');
        
        if (!$force) {
            $query->where(function ($q) {
                $q->whereNull('tracklist')
                  ->orWhereRaw("tracklist::text = '[]'")
                  ->orWhereRaw("tracklist::text = 'null'")
                  ->orWhereNull('price_suggestions');
            });
        }

        $vinyls = $query->limit($limit)->get();

        if ($vinyls->isEmpty()) {
            $this->info('No vinyls need refreshing.');
            return Command::SUCCESS;
        }

        $this->info("Refreshing details for {$vinyls->count()} vinyls...");
        $this->info("Delay between requests: {$delay}ms");
        $this->newLine();

        Log::channel('daily')->info('=== VINYL REFRESH STARTED ===', [
            'total_vinyls' => $vinyls->count(),
            'force' => $force,
            'delay_ms' => $delay,
        ]);

        $progressBar = $this->output->createProgressBar($vinyls->count());
        $progressBar->start();

        $updated = 0;
        $errors = 0;
        $apiErrors = 0;
        $dbErrors = 0;

        foreach ($vinyls as $vinyl) {
            try {
                // Step 1: Call Discogs API
                $analysis = $this->discogs->getCompleteAnalysis($vinyl->discogs_id);

                if (!$analysis) {
                    $apiErrors++;
                    $errors++;
                    $this->logError('API_NULL_RESPONSE', $vinyl, 'Discogs API returned null - possible rate limit or not found');
                    $progressBar->advance();
                    usleep($delay * 1000);
                    continue;
                }

                $release = $analysis['release'] ?? null;
                $marketplace = $analysis['marketplace'] ?? null;
                $community = $analysis['community'] ?? null;

                if (!$release) {
                    $apiErrors++;
                    $errors++;
                    $this->logError('API_NO_RELEASE', $vinyl, 'API response missing release data');
                    $progressBar->advance();
                    usleep($delay * 1000);
                    continue;
                }

                // Step 2: Update database
                try {
                    $vinyl->update([
                        'tracklist' => $release['tracklist'] ?? [],
                        'styles' => $release['styles'] ?? $vinyl->styles,
                        'format_descriptions' => $release['format_descriptions'] ?? [],
                        'artist_id' => $release['artist_id'] ?? $vinyl->artist_id,
                        'artist_thumbnail' => $release['artist_thumbnail'] ?? $vinyl->artist_thumbnail,
                        'notes' => $release['notes'] ?? $vinyl->notes,
                        'data_quality' => $release['data_quality'] ?? $vinyl->data_quality,
                        'price_suggestions' => $marketplace['price_suggestions'] ?? null,
                        'lowest_price' => $marketplace['stats']['lowest_price']['value'] ?? $vinyl->lowest_price,
                        'lowest_price_currency' => $marketplace['stats']['lowest_price']['currency'] ?? $vinyl->lowest_price_currency,
                        'num_for_sale' => $marketplace['total_listings'] ?? $vinyl->num_for_sale,
                        'have' => $community['have'] ?? $vinyl->have,
                        'want' => $community['want'] ?? $vinyl->want,
                        'rating_average' => $community['rating_average'] ?? $vinyl->rating_average,
                        'rating_count' => $community['rating_count'] ?? $vinyl->rating_count,
                        'cover_image' => $release['images'][0]['uri'] ?? $vinyl->cover_image,
                        'thumb' => $release['images'][0]['uri150'] ?? $vinyl->thumb,
                        'last_refreshed_at' => now(),
                    ]);

                    $updated++;
                    
                    Log::channel('daily')->debug('Vinyl updated successfully', [
                        'discogs_id' => $vinyl->discogs_id,
                        'title' => $vinyl->title,
                        'tracks_count' => count($release['tracklist'] ?? []),
                    ]);

                } catch (\Exception $dbException) {
                    $dbErrors++;
                    $errors++;
                    $this->logError('DATABASE_ERROR', $vinyl, $dbException->getMessage());
                }

                $progressBar->advance();

                // Respect rate limits
                usleep($delay * 1000);

            } catch (\Exception $e) {
                $errors++;
                $this->logError('GENERAL_ERROR', $vinyl, $e->getMessage());
                $progressBar->advance();
                usleep($delay * 1000);
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->info("═══════════════════════════════════════");
        $this->info("Refresh Complete!");
        $this->info("Updated: {$updated}");
        $this->info("Total Errors: {$errors}");
        $this->info("  - API Errors: {$apiErrors}");
        $this->info("  - DB Errors: {$dbErrors}");
        $this->info("═══════════════════════════════════════");

        // Log final summary
        Log::channel('daily')->info('=== VINYL REFRESH COMPLETED ===', [
            'updated' => $updated,
            'total_errors' => $errors,
            'api_errors' => $apiErrors,
            'db_errors' => $dbErrors,
            'error_details' => $this->errorLog,
        ]);

        // Show error summary if any
        if (!empty($this->errorLog)) {
            $this->newLine();
            $this->warn("Error log saved to: storage/logs/laravel-" . date('Y-m-d') . ".log");
            
            // Show first 5 errors in console
            $this->newLine();
            $this->info("Last errors (see log for full details):");
            foreach (array_slice($this->errorLog, -5) as $error) {
                $this->line("  [{$error['type']}] ID:{$error['discogs_id']} - {$error['title']}");
                $this->line("    → {$error['message']}");
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Log an error with context
     */
    protected function logError(string $type, DiscogsAnalysis $vinyl, string $message): void
    {
        $errorEntry = [
            'type' => $type,
            'discogs_id' => $vinyl->discogs_id,
            'title' => $vinyl->title,
            'artist' => $vinyl->artist_name,
            'message' => $message,
            'timestamp' => now()->toISOString(),
        ];

        $this->errorLog[] = $errorEntry;

        Log::channel('daily')->warning("Vinyl refresh error: {$type}", $errorEntry);
    }
}
