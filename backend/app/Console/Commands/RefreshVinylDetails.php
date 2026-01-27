<?php

namespace App\Console\Commands;

use App\Models\DiscogsAnalysis;
use App\Services\DiscogsService;
use Illuminate\Console\Command;

class RefreshVinylDetails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vinyls:refresh-details 
                            {--limit=50 : Number of vinyls to refresh}
                            {--force : Force refresh even if already has tracklist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh vinyl details (tracklist, prices) from Discogs API';

    protected DiscogsService $discogs;

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

        // Get vinyls that need tracklist/price refresh
        $query = DiscogsAnalysis::query()
            ->whereNotNull('discogs_id');
        
        if (!$force) {
            $query->where(function ($q) {
                $q->whereNull('tracklist')
                  ->orWhere('tracklist', '[]')
                  ->orWhereNull('price_suggestions');
            });
        }

        $vinyls = $query->limit($limit)->get();

        if ($vinyls->isEmpty()) {
            $this->info('No vinyls need refreshing.');
            return Command::SUCCESS;
        }

        $this->info("Refreshing details for {$vinyls->count()} vinyls...");
        $this->newLine();

        $progressBar = $this->output->createProgressBar($vinyls->count());
        $progressBar->start();

        $updated = 0;
        $errors = 0;

        foreach ($vinyls as $vinyl) {
            try {
                $analysis = $this->discogs->getCompleteAnalysis($vinyl->discogs_id);

                if (!$analysis) {
                    $errors++;
                    $progressBar->advance();
                    continue;
                }

                $release = $analysis['release'];
                $marketplace = $analysis['marketplace'];
                $community = $analysis['community'];

                // Update vinyl with full details
                $vinyl->update([
                    'tracklist' => $release['tracklist'] ?? [],
                    'styles' => $release['styles'] ?? $vinyl->styles,
                    'price_suggestions' => $marketplace['price_suggestions'] ?? null,
                    'lowest_price' => $marketplace['stats']['lowest_price']['value'] ?? $vinyl->lowest_price,
                    'lowest_price_currency' => $marketplace['stats']['lowest_price']['currency'] ?? $vinyl->lowest_price_currency,
                    'num_for_sale' => $marketplace['total_listings'] ?? $vinyl->num_for_sale,
                    'have' => $community['have'] ?? $vinyl->have,
                    'want' => $community['want'] ?? $vinyl->want,
                    'rating_average' => $community['rating_average'] ?? $vinyl->rating_average,
                    'rating_count' => $community['rating_count'] ?? $vinyl->rating_count,
                    'last_refreshed_at' => now(),
                ]);

                $updated++;
                $progressBar->advance();

                // Respect rate limits (60 requests per minute)
                usleep(1000000); // 1 second delay

            } catch (\Exception $e) {
                $errors++;
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("═══════════════════════════════════════");
        $this->info("Refresh Complete!");
        $this->info("Updated: {$updated}");
        $this->info("Errors: {$errors}");
        $this->info("═══════════════════════════════════════");

        return Command::SUCCESS;
    }
}
