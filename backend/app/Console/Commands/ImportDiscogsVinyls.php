<?php

namespace App\Console\Commands;

use App\Models\DiscogsAnalysis;
use App\Services\DiscogsService;
use Illuminate\Console\Command;

class ImportDiscogsVinyls extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'discogs:import 
                            {--styles=* : Styles to search for (e.g., electro, techno)}
                            {--genre=Electronic : Genre to filter}
                            {--total=100 : Total vinyls to import}
                            {--per-style= : Vinyls per style (auto-calculated if not set)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import vinyls from Discogs by style/genre and save to analysis database';

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
        $styles = $this->option('styles');
        $genre = $this->option('genre');
        $total = (int) $this->option('total');
        $perStyle = $this->option('per-style');

        if (empty($styles)) {
            $this->error('Please provide at least one style with --styles');
            return Command::FAILURE;
        }

        // Calculate vinyls per style
        $perStyleCount = $perStyle ? (int) $perStyle : (int) ceil($total / count($styles));
        
        $this->info("Importing {$total} vinyls from Discogs");
        $this->info("Genre: {$genre}");
        $this->info("Styles: " . implode(', ', $styles));
        $this->info("Per style: ~{$perStyleCount}");
        $this->newLine();

        $totalImported = 0;
        $totalSkipped = 0;
        $totalErrors = 0;

        foreach ($styles as $style) {
            $this->info("🔍 Searching for: {$style}");
            
            $imported = $this->importByStyle($style, $genre, $perStyleCount);
            
            $totalImported += $imported['imported'];
            $totalSkipped += $imported['skipped'];
            $totalErrors += $imported['errors'];
            
            $this->info("   ✓ Imported: {$imported['imported']}, Skipped: {$imported['skipped']}, Errors: {$imported['errors']}");
            $this->newLine();

            // Check if we've reached the total
            if ($totalImported >= $total) {
                $this->info("Reached target of {$total} vinyls");
                break;
            }
        }

        $this->newLine();
        $this->info("═══════════════════════════════════════");
        $this->info("Import Complete!");
        $this->info("Total Imported: {$totalImported}");
        $this->info("Total Skipped (duplicates): {$totalSkipped}");
        $this->info("Total Errors: {$totalErrors}");
        $this->info("═══════════════════════════════════════");

        return Command::SUCCESS;
    }

    /**
     * Import vinyls by style
     */
    protected function importByStyle(string $style, string $genre, int $limit): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = 0;
        $page = 1;
        $perPage = 50;

        $progressBar = $this->output->createProgressBar($limit);
        $progressBar->start();

        while ($imported < $limit) {
            // Search Discogs with style-specific query (returns have/want in results)
            $results = $this->searchDiscogsWithStyle($style, $genre, $perPage, $page);

            if (!$results || empty($results['results'])) {
                $this->warn("   No more results for {$style} (page {$page})");
                break;
            }

            foreach ($results['results'] as $result) {
                if ($imported >= $limit) {
                    break;
                }

                $releaseId = $result['id'] ?? null;
                
                if (!$releaseId) {
                    $errors++;
                    continue;
                }

                // Check if already exists
                if (DiscogsAnalysis::where('discogs_id', $releaseId)->exists()) {
                    $skipped++;
                    continue;
                }

                // Save directly from search results (includes have/want)
                try {
                    $saved = $this->saveReleaseQuick($result);
                    
                    if ($saved) {
                        $imported++;
                        $progressBar->advance();
                    } else {
                        $errors++;
                    }
                    
                } catch (\Exception $e) {
                    $errors++;
                }
            }

            $page++;
            
            // Respect rate limit between pages
            usleep(500000); // 500ms delay between pages
            
            // Safety limit - go up to 10 pages per style
            if ($page > 10) {
                break;
            }
        }

        $progressBar->finish();
        $this->newLine();

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Search Discogs API with style filter
     */
    protected function searchDiscogsWithStyle(string $style, string $genre, int $perPage, int $page): ?array
    {
        try {
            // Use direct API call with style parameter
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'User-Agent' => config('discogs.user_agent'),
                'Authorization' => 'Discogs key=' . config('discogs.consumer_key') . ', secret=' . config('discogs.consumer_secret'),
            ])->get(config('discogs.base_url') . '/database/search', [
                'style' => $style,
                'genre' => $genre,
                'format' => 'Vinyl',
                'type' => 'release',
                'per_page' => $perPage,
                'page' => $page,
            ]);

            if (!$response->successful()) {
                $this->error("API error: " . $response->status());
                return null;
            }

            $data = $response->json();
            
            if (!isset($data['results'])) {
                return null;
            }

            // Return raw results (include community have/want data)
            return [
                'results' => $data['results'],
                'pagination' => $data['pagination'] ?? null,
            ];
        } catch (\Exception $e) {
            $this->error("Search error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Save a release to the analysis database (quick mode - from search results)
     */
    protected function saveReleaseQuick(array $item): bool
    {
        try {
            $have = $item['community']['have'] ?? 0;
            $want = $item['community']['want'] ?? 0;
            $demandRatio = $have > 0 ? round($want / $have, 2) : 0;

            // Parse title to extract artist - title
            $titleParts = explode(' - ', $item['title'] ?? '', 2);
            $artistName = count($titleParts) > 1 ? trim($titleParts[0]) : null;
            $title = count($titleParts) > 1 ? trim($titleParts[1]) : ($item['title'] ?? null);

            DiscogsAnalysis::updateOrCreate(
                ['discogs_id' => $item['id']],
                [
                    'title' => $title,
                    'artist_name' => $artistName,
                    'year' => $item['year'] ?? null,
                    'country' => $item['country'] ?? null,
                    'label' => $item['label'][0] ?? null,
                    'catalog_number' => $item['catno'] ?? null,
                    'genres' => $item['genre'] ?? [],
                    'styles' => $item['style'] ?? [],
                    'format' => $item['format'][0] ?? null,
                    'have' => $have,
                    'want' => $want,
                    'demand_ratio' => $demandRatio,
                    'is_rare' => $have < 100,
                    'is_in_demand' => $want > $have,
                    'cover_image' => $item['cover_image'] ?? null,
                    'thumb' => $item['thumb'] ?? null,
                    'fetched_at' => now(),
                ]
            );

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Save a release to the analysis database (full mode - with API calls)
     */
    protected function saveRelease(int $releaseId): bool
    {
        $analysis = $this->discogs->getCompleteAnalysis($releaseId);

        if (!$analysis) {
            return false;
        }

        $release = $analysis['release'];
        $community = $analysis['community'];
        $marketplace = $analysis['marketplace'];

        try {
            DiscogsAnalysis::updateOrCreate(
                ['discogs_id' => $releaseId],
                [
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
                ]
            );

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
