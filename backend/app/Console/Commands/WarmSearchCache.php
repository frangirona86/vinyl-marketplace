<?php

namespace App\Console\Commands;

use App\Models\DiscogsAnalysis;
use App\Models\SearchHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WarmSearchCache extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'search:warm-cache 
                            {--popular : Include popular search terms}
                            {--genres : Include all genres}
                            {--artists : Include top artists}';

    /**
     * The console command description.
     */
    protected $description = 'Warm up the search cache for better performance';

    /**
     * Cache TTL constants
     */
    private const CACHE_SUGGESTIONS_TTL = 600;  // 10 minutes for warmed cache
    private const CACHE_FILTERS_TTL = 1800;     // 30 minutes

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting search cache warming...');
        $startTime = microtime(true);
        $cachedCount = 0;

        // Always warm common queries
        $commonQueries = [
            'rock', 'jazz', 'electronic', 'hip hop', 'classical',
            'beatles', 'pink floyd', 'led zeppelin', 'miles davis',
            'vinyl', 'rare', 'limited', 'first press', 'original',
            'soul', 'funk', 'disco', 'techno', 'house',
        ];

        $this->info('Warming common search queries...');
        $bar = $this->output->createProgressBar(count($commonQueries));

        foreach ($commonQueries as $query) {
            $this->warmSuggestions($query);
            $cachedCount++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        // Warm popular searches if flag is set
        if ($this->option('popular')) {
            $this->info('Warming popular search queries...');
            $popularQueries = SearchHistory::select('query')
                ->selectRaw('COUNT(*) as count')
                ->where('created_at', '>=', now()->subDays(7))
                ->groupBy('query')
                ->orderByDesc('count')
                ->limit(50)
                ->pluck('query');

            $bar = $this->output->createProgressBar($popularQueries->count());

            foreach ($popularQueries as $query) {
                $this->warmSuggestions($query);
                $cachedCount++;
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        }

        // Warm genres if flag is set
        if ($this->option('genres')) {
            $this->info('Warming genres cache...');
            $this->warmGenres();
            $cachedCount++;
        }

        // Warm top artists if flag is set
        if ($this->option('artists')) {
            $this->info('Warming top artists...');
            $topArtists = DiscogsAnalysis::select('artist_name')
                ->selectRaw('COUNT(*) as count')
                ->groupBy('artist_name')
                ->orderByDesc('count')
                ->limit(100)
                ->pluck('artist_name');

            $bar = $this->output->createProgressBar($topArtists->count());

            foreach ($topArtists as $artist) {
                $this->warmSuggestions($artist);
                $cachedCount++;
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        }

        // Always warm stats
        $this->info('Warming search stats...');
        $this->warmStats();
        $cachedCount++;

        $elapsed = round(microtime(true) - $startTime, 2);
        $this->info("Cache warming complete! Cached {$cachedCount} items in {$elapsed}s");

        return Command::SUCCESS;
    }

    /**
     * Warm suggestions cache for a query
     */
    private function warmSuggestions(string $query): void
    {
        $normalizedQuery = md5(strtolower(trim($query)));
        $cacheKey = "search:suggest:{$normalizedQuery}";

        try {
            Cache::tags(['search', 'suggestions'])->remember(
                $cacheKey,
                self::CACHE_SUGGESTIONS_TTL,
                fn() => $this->buildSuggestions($query)
            );
        } catch (\Exception $e) {
            // Fallback without tags
            Cache::remember($cacheKey, self::CACHE_SUGGESTIONS_TTL, fn() => $this->buildSuggestions($query));
        }
    }

    /**
     * Build suggestions (simplified version)
     */
    private function buildSuggestions(string $query): array
    {
        $suggestions = [
            'artists' => [],
            'genres' => [],
            'labels' => [],
            'vinyls' => [],
        ];

        $searchTerm = '%' . strtolower($query) . '%';

        // Search artists
        $artists = DiscogsAnalysis::select('artist_name', 'artist_id', 'artist_thumbnail')
            ->selectRaw('COUNT(*) as vinyl_count')
            ->whereRaw('LOWER(artist_name) LIKE ?', [$searchTerm])
            ->groupBy('artist_name', 'artist_id', 'artist_thumbnail')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(5)
            ->get();

        foreach ($artists as $artist) {
            $suggestions['artists'][] = [
                'id' => $artist->artist_id,
                'name' => $artist->artist_name,
                'thumbnail' => $artist->artist_thumbnail,
                'vinyl_count' => $artist->vinyl_count,
                'type' => 'artist',
            ];
        }

        // Search vinyls
        $vinyls = DiscogsAnalysis::select([
                'discogs_id', 'title', 'artist_name', 'year',
                'thumb', 'lowest_price', 'demand_ratio', 'is_rare'
            ])
            ->where(function ($q) use ($searchTerm) {
                $q->whereRaw('LOWER(title) LIKE ?', [$searchTerm])
                  ->orWhereRaw('LOWER(artist_name) LIKE ?', [$searchTerm]);
            })
            ->orderByDesc('demand_ratio')
            ->limit(6)
            ->get();

        foreach ($vinyls as $vinyl) {
            $suggestions['vinyls'][] = [
                'id' => $vinyl->discogs_id,
                'title' => $vinyl->title,
                'artist' => $vinyl->artist_name,
                'year' => $vinyl->year,
                'thumb' => $vinyl->thumb,
                'price' => $vinyl->lowest_price,
                'demand_ratio' => $vinyl->demand_ratio,
                'is_rare' => $vinyl->is_rare,
                'type' => 'vinyl',
            ];
        }

        return $suggestions;
    }

    /**
     * Warm genres cache
     */
    private function warmGenres(): void
    {
        $genres = DiscogsAnalysis::select('genres')
            ->whereNotNull('genres')
            ->get()
            ->pluck('genres')
            ->flatten()
            ->filter()
            ->countBy()
            ->sortDesc()
            ->map(fn($count, $name) => ['name' => $name, 'count' => $count, 'type' => 'genre'])
            ->values()
            ->toArray();

        try {
            Cache::tags(['search', 'filters'])->put('search:genres:all', $genres, self::CACHE_FILTERS_TTL);
        } catch (\Exception $e) {
            Cache::put('search:genres:all', $genres, self::CACHE_FILTERS_TTL);
        }
    }

    /**
     * Warm stats cache
     */
    private function warmStats(): void
    {
        $stats = [
            'total_vinyls' => DiscogsAnalysis::count(),
            'total_artists' => DiscogsAnalysis::distinct('artist_name')->count(),
            'countries' => DiscogsAnalysis::distinct('country')->count(),
            'price_range' => [
                'min' => DiscogsAnalysis::min('lowest_price'),
                'max' => DiscogsAnalysis::max('lowest_price'),
                'avg' => round(DiscogsAnalysis::avg('lowest_price') ?? 0, 2),
            ],
            'year_range' => [
                'min' => DiscogsAnalysis::min('year'),
                'max' => DiscogsAnalysis::max('year'),
            ],
        ];

        try {
            Cache::tags(['search', 'stats'])->put('search:stats:global', $stats, 3600);
        } catch (\Exception $e) {
            Cache::put('search:stats:global', $stats, 3600);
        }
    }
}
