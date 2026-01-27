<?php

namespace App\Http\Controllers;

use App\Models\DiscogsAnalysis;
use App\Models\SearchHistory;
use App\Services\DiscogsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class SearchController extends Controller
{
    protected DiscogsService $discogs;

    // Cache TTL constants (in seconds)
    private const CACHE_SUGGESTIONS_TTL = 300;      // 5 minutes
    private const CACHE_SEARCH_RESULTS_TTL = 180;   // 3 minutes
    private const CACHE_POPULAR_TTL = 600;          // 10 minutes
    private const CACHE_STATS_TTL = 3600;           // 1 hour
    private const CACHE_FILTERS_TTL = 1800;         // 30 minutes
    private const CACHE_DISCOGS_TTL = 900;          // 15 minutes

    public function __construct(DiscogsService $discogs)
    {
        $this->discogs = $discogs;
    }

    /**
     * Safe cache helper that falls back when tags aren't supported
     */
    private function cacheRemember(string $key, int $ttl, callable $callback, array $tags = [])
    {
        try {
            if (!empty($tags) && $this->supportsTags()) {
                return Cache::tags($tags)->remember($key, $ttl, $callback);
            }
        } catch (\Exception $e) {
            // Tags not supported, fall through to simple cache
        }
        
        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Safe cache put helper
     */
    private function cachePut(string $key, $value, int $ttl, array $tags = []): void
    {
        try {
            if (!empty($tags) && $this->supportsTags()) {
                Cache::tags($tags)->put($key, $value, $ttl);
                return;
            }
        } catch (\Exception $e) {
            // Tags not supported, fall through
        }
        
        Cache::put($key, $value, $ttl);
    }

    /**
     * Safe cache get helper
     */
    private function cacheGet(string $key, array $tags = [])
    {
        try {
            if (!empty($tags) && $this->supportsTags()) {
                return Cache::tags($tags)->get($key);
            }
        } catch (\Exception $e) {
            // Tags not supported
        }
        
        return Cache::get($key);
    }

    /**
     * Check if cache store supports tagging
     */
    private function supportsTags(): bool
    {
        try {
            $store = Cache::getStore();
            return method_exists($store, 'tags');
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Smart search with autocomplete suggestions
     * GET /api/search/suggest?q=beatles
     */
    public function suggest(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:1|max:100',
        ]);

        $query = trim($request->input('q'));
        $sessionId = $request->header('X-Session-ID', session()->getId());
        $normalizedQuery = $this->normalizeQuery($query);

        // Try to get from cache first
        $cacheKey = "search:suggest:{$normalizedQuery}";
        
        $suggestions = $this->cacheRemember(
            $cacheKey, 
            self::CACHE_SUGGESTIONS_TTL, 
            fn() => $this->buildSuggestions($query),
            ['search', 'suggestions']
        );

        // Get recent searches (cached per session)
        $recentCacheKey = "search:recent:{$sessionId}";
        $recentSearches = Cache::remember($recentCacheKey, 60, fn() => $this->getRecentSearches($sessionId));

        // Get popular searches
        $popular = $this->getPopularSearchesCached();

        // Track this query for trending (async via Redis)
        $this->trackSearchQuery($query);

        return response()->json([
            'query' => $query,
            'suggestions' => $suggestions,
            'recent' => $recentSearches,
            'popular' => $popular,
        ]);
    }

    /**
     * Normalize query for consistent cache keys
     */
    private function normalizeQuery(string $query): string
    {
        return md5(strtolower(trim(preg_replace('/\s+/', ' ', $query))));
    }

    /**
     * Track search query for trending analysis (using Redis sorted set)
     */
    private function trackSearchQuery(string $query): void
    {
        try {
            $normalizedQuery = strtolower(trim($query));
            $hourKey = "search:trending:" . date('Y-m-d-H');
            $dayKey = "search:trending:" . date('Y-m-d');
            
            // Increment in both hourly and daily sorted sets
            Redis::zincrby($hourKey, 1, $normalizedQuery);
            Redis::zincrby($dayKey, 1, $normalizedQuery);
            
            // Set expiration (hourly: 2 hours, daily: 48 hours)
            Redis::expire($hourKey, 7200);
            Redis::expire($dayKey, 172800);
        } catch (\Exception $e) {
            // Silently fail - tracking is non-critical
        }
    }

    /**
     * Get trending searches from Redis
     */
    private function getTrendingSearches(int $limit = 5): array
    {
        try {
            $dayKey = "search:trending:" . date('Y-m-d');
            $trending = Redis::zrevrange($dayKey, 0, $limit - 1, 'WITHSCORES');
            
            $results = [];
            foreach ($trending as $query => $score) {
                $results[] = [
                    'query' => $query,
                    'count' => (int) $score,
                    'type' => 'trending',
                ];
            }
            return $results;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get popular searches with caching
     */
    private function getPopularSearchesCached(): array
    {
        return $this->cacheRemember(
            'search:popular:global',
            self::CACHE_POPULAR_TTL,
            fn() => SearchHistory::getPopularSearches(5, 7),
            ['search', 'popular']
        );
    }

    /**
     * Build categorized suggestions with optimized queries
     */
    protected function buildSuggestions(string $query): array
    {
        $suggestions = [
            'artists' => [],
            'genres' => [],
            'labels' => [],
            'vinyls' => [],
        ];

        $searchTerm = '%' . strtolower($query) . '%';
        $isPostgres = DB::connection()->getDriverName() === 'pgsql';

        // Parallel query execution for better performance
        // Using database-level optimization

        // Search artists with aggregation
        $artistsQuery = DiscogsAnalysis::select('artist_name', 'artist_id', 'artist_thumbnail')
            ->selectRaw('COUNT(*) as vinyl_count');
        
        if ($isPostgres) {
            $artistsQuery->selectRaw('ROUND(AVG(demand_ratio)::numeric, 2) as avg_demand');
        } else {
            $artistsQuery->selectRaw('ROUND(AVG(demand_ratio), 2) as avg_demand');
        }
        
        $artists = $artistsQuery
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
                'avg_demand' => $artist->avg_demand,
                'type' => 'artist',
            ];
        }

        // Search genres - use cached genres list for faster filtering
        $allGenres = $this->getCachedGenres();
        $matchingGenres = collect($allGenres)
            ->filter(fn($g) => stripos($g['name'], $query) !== false)
            ->take(5)
            ->values();
        
        $suggestions['genres'] = $matchingGenres->toArray();

        // Search labels with aggregation
        $labels = DiscogsAnalysis::select('label')
            ->selectRaw('COUNT(*) as count')
            ->whereRaw('LOWER(label) LIKE ?', [$searchTerm])
            ->whereNotNull('label')
            ->groupBy('label')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(5)
            ->get();

        foreach ($labels as $label) {
            $suggestions['labels'][] = [
                'name' => $label->label,
                'count' => $label->count,
                'type' => 'label',
            ];
        }

        // Search vinyls - optimized with index hints
        $vinyls = DiscogsAnalysis::select([
                'discogs_id', 'title', 'artist_name', 'year', 
                'thumb', 'lowest_price', 'have', 'want', 
                'demand_ratio', 'is_rare', 'genres'
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
                'have' => $vinyl->have,
                'want' => $vinyl->want,
                'demand_ratio' => $vinyl->demand_ratio,
                'is_rare' => $vinyl->is_rare,
                'genre' => is_array($vinyl->genres) ? ($vinyl->genres[0] ?? null) : null,
                'type' => 'vinyl',
            ];
        }

        return $suggestions;
    }

    /**
     * Get cached genres list
     */
    private function getCachedGenres(): array
    {
        return $this->cacheRemember(
            'search:genres:all',
            self::CACHE_FILTERS_TTL,
            function () {
                return DiscogsAnalysis::select('genres')
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
            },
            ['search', 'filters']
        );
    }

    /**
     * Full-text intelligent search
     * GET /api/search?q=beatles&type=all&page=1
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:200',
            'type' => 'nullable|string|in:all,vinyl,artist,genre,label',
            'genre' => 'nullable|string',
            'year_from' => 'nullable|integer|min:1900|max:2030',
            'year_to' => 'nullable|integer|min:1900|max:2030',
            'price_min' => 'nullable|numeric|min:0',
            'price_max' => 'nullable|numeric|min:0',
            'sort' => 'nullable|string|in:relevance,demand,price_asc,price_desc,year,rare',
            'per_page' => 'nullable|integer|min:1|max:50',
            'page' => 'nullable|integer|min:1',
            'include_discogs' => 'nullable|boolean',
        ]);

        $query = trim($request->input('q'));
        $type = $request->input('type', 'all');
        $sessionId = $request->header('X-Session-ID', session()->getId());
        $perPage = $request->input('per_page', 20);
        $page = $request->input('page', 1);
        $includeDiscogs = $request->boolean('include_discogs', true);

        // Build cache key from all search parameters
        $cacheKey = $this->buildSearchCacheKey($request);

        // Try to get cached results
        $cachedResults = $this->cacheGet($cacheKey, ['search', 'results']);
        
        if ($cachedResults) {
            // Record the search asynchronously
            $this->recordSearchAsync($sessionId, $query, $type, $cachedResults['total'], $request);
            return response()->json($cachedResults);
        }

        // Build local search
        $localResults = $this->searchLocal($request);

        // If local results are few and discogs search is enabled, search Discogs too
        $discogsResults = [];
        if ($includeDiscogs && count($localResults['data']) < 5) {
            $discogsResults = $this->searchDiscogsCached($query, $perPage);
        }

        // Calculate total results
        $totalLocal = $localResults['total'] ?? 0;
        $totalDiscogs = count($discogsResults);

        $response = [
            'query' => $query,
            'type' => $type,
            'results' => [
                'local' => $localResults,
                'discogs' => $discogsResults,
            ],
            'total' => $totalLocal + $totalDiscogs,
            'filters_applied' => $request->only(['genre', 'year_from', 'year_to', 'price_min', 'price_max', 'sort']),
            'cached' => false,
        ];

        // Cache the results
        $this->cachePut($cacheKey, $response, self::CACHE_SEARCH_RESULTS_TTL, ['search', 'results']);

        // Record the search
        $this->recordSearchAsync($sessionId, $query, $type, $totalLocal + $totalDiscogs, $request);

        return response()->json($response);
    }

    /**
     * Build a unique cache key for search parameters
     */
    private function buildSearchCacheKey(Request $request): string
    {
        $params = [
            'q' => strtolower(trim($request->input('q'))),
            'type' => $request->input('type', 'all'),
            'genre' => $request->input('genre'),
            'year_from' => $request->input('year_from'),
            'year_to' => $request->input('year_to'),
            'price_min' => $request->input('price_min'),
            'price_max' => $request->input('price_max'),
            'sort' => $request->input('sort', 'relevance'),
            'per_page' => $request->input('per_page', 20),
            'page' => $request->input('page', 1),
        ];
        
        return 'search:results:' . md5(json_encode($params));
    }

    /**
     * Record search asynchronously (non-blocking)
     */
    private function recordSearchAsync(string $sessionId, string $query, string $type, int $resultsCount, Request $request): void
    {
        // Use dispatch for truly async, but for now just record quickly
        try {
            SearchHistory::record(
                sessionId: $sessionId,
                query: $query,
                type: $type,
                resultsCount: $resultsCount,
                userId: auth()->id(),
                filters: $request->only(['genre', 'year_from', 'year_to', 'price_min', 'price_max', 'sort'])
            );
            
            // Invalidate recent searches cache for this session
            Cache::forget("search:recent:{$sessionId}");
            
            // Track for trending
            $this->trackSearchQuery($query);
        } catch (\Exception $e) {
            // Non-critical, continue silently
        }
    }

    /**
     * Search local database with full-text search
     */
    protected function searchLocal(Request $request): array
    {
        $query = trim($request->input('q'));
        $perPage = $request->input('per_page', 20);

        $dbQuery = DiscogsAnalysis::query();
        $isPostgres = DB::connection()->getDriverName() === 'pgsql';

        if ($isPostgres) {
            // Full-text search using PostgreSQL
            $searchTerms = preg_split('/\s+/', $query);
            $tsQuery = implode(' & ', array_map(fn($t) => $t . ':*', $searchTerms));

            $dbQuery->whereRaw(
                "to_tsvector('english', COALESCE(title, '') || ' ' || COALESCE(artist_name, '') || ' ' || COALESCE(label, '')) @@ to_tsquery('english', ?)",
                [$tsQuery]
            );

            // Add ranking
            $dbQuery->selectRaw("*, ts_rank(
                to_tsvector('english', COALESCE(title, '') || ' ' || COALESCE(artist_name, '') || ' ' || COALESCE(label, '')),
                to_tsquery('english', ?)
            ) as search_rank", [$tsQuery]);
        } else {
            // Fallback to LIKE for SQLite and other databases
            $searchTerm = '%' . strtolower($query) . '%';
            
            $dbQuery->where(function ($q) use ($searchTerm) {
                $q->whereRaw('LOWER(title) LIKE ?', [$searchTerm])
                  ->orWhereRaw('LOWER(artist_name) LIKE ?', [$searchTerm])
                  ->orWhereRaw('LOWER(COALESCE(label, "")) LIKE ?', [$searchTerm]);
            });

            // Add a pseudo search_rank based on demand for consistency
            $dbQuery->selectRaw('*, demand_ratio as search_rank');
        }

        // Apply filters
        if ($request->filled('genre')) {
            if ($isPostgres) {
                $dbQuery->whereJsonContains('genres', $request->input('genre'));
            } else {
                // SQLite JSON support
                $dbQuery->whereRaw("genres LIKE ?", ['%"' . $request->input('genre') . '"%']);
            }
        }

        if ($request->filled('year_from')) {
            $dbQuery->where('year', '>=', $request->input('year_from'));
        }

        if ($request->filled('year_to')) {
            $dbQuery->where('year', '<=', $request->input('year_to'));
        }

        if ($request->filled('price_min')) {
            $dbQuery->where('lowest_price', '>=', $request->input('price_min'));
        }

        if ($request->filled('price_max')) {
            $dbQuery->where('lowest_price', '<=', $request->input('price_max'));
        }

        // Apply sorting
        $sort = $request->input('sort', 'relevance');
        switch ($sort) {
            case 'demand':
                $dbQuery->orderByDesc('demand_ratio');
                break;
            case 'price_asc':
                $dbQuery->orderBy('lowest_price');
                break;
            case 'price_desc':
                $dbQuery->orderByDesc('lowest_price');
                break;
            case 'year':
                $dbQuery->orderByDesc('year');
                break;
            case 'rare':
                $dbQuery->orderByDesc('is_rare')->orderBy('have');
                break;
            default:
                $dbQuery->orderByDesc('search_rank')->orderByDesc('demand_ratio');
        }

        $results = $dbQuery->paginate($perPage);

        // Enrich results with insights
        $enrichedData = $results->getCollection()->map(function ($item) {
            return $this->enrichWithInsights($item);
        });

        return [
            'data' => $enrichedData,
            'total' => $results->total(),
            'per_page' => $results->perPage(),
            'current_page' => $results->currentPage(),
            'last_page' => $results->lastPage(),
        ];
    }

    /**
     * Search Discogs API with caching
     */
    protected function searchDiscogsCached(string $query, int $perPage = 10): array
    {
        $cacheKey = 'search:discogs:' . $this->normalizeQuery($query) . ':' . $perPage;
        
        return $this->cacheRemember(
            $cacheKey,
            self::CACHE_DISCOGS_TTL,
            fn() => $this->searchDiscogs($query, $perPage),
            ['search', 'discogs']
        );
    }

    /**
     * Search Discogs API
     */
    protected function searchDiscogs(string $query, int $perPage = 10): array
    {
        try {
            $results = $this->discogs->searchWithMarketData($query, min($perPage, 10), 1);
            
            if (!$results || empty($results['results'])) {
                return [];
            }

            return array_map(function ($item) {
                return $this->enrichDiscogsResult($item);
            }, $results['results']);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Enrich local result with insights
     */
    protected function enrichWithInsights($item): array
    {
        $have = $item->have ?? 0;
        $want = $item->want ?? 0;
        $demandRatio = $item->demand_ratio ?? 0;
        $price = $item->lowest_price;

        $tags = [];

        // Rarity tags
        if ($have < 100) {
            $tags[] = ['type' => 'rarity', 'label' => '💎 Ultra Rare', 'value' => 'ultra_rare', 'color' => 'purple'];
        } elseif ($have < 500) {
            $tags[] = ['type' => 'rarity', 'label' => '✨ Rare', 'value' => 'rare', 'color' => 'lilac'];
        }

        // Demand tags
        if ($demandRatio >= 2) {
            $tags[] = ['type' => 'demand', 'label' => '🔥 Hot', 'value' => 'hot', 'color' => 'coral'];
        } elseif ($demandRatio >= 1) {
            $tags[] = ['type' => 'demand', 'label' => '📈 Trending', 'value' => 'trending', 'color' => 'green'];
        }

        // Price tags
        if ($price !== null && $price < 15) {
            $tags[] = ['type' => 'price', 'label' => '💰 Bargain', 'value' => 'bargain', 'color' => 'green'];
        }

        $quickScore = $this->calculateQuickScore($have, $want, $item->num_for_sale ?? 0, $price);

        return [
            'id' => $item->discogs_id,
            'title' => $item->title,
            'artist' => $item->artist_name,
            'artist_id' => $item->artist_id,
            'year' => $item->year,
            'country' => $item->country,
            'label' => $item->label,
            'genres' => $item->genres ?? [],
            'styles' => $item->styles ?? [],
            'format' => $item->format,
            'thumb' => $item->thumb,
            'cover' => $item->cover_image,
            'have' => $have,
            'want' => $want,
            'demand_ratio' => $demandRatio,
            'lowest_price' => $price,
            'for_sale' => $item->num_for_sale ?? 0,
            'is_rare' => $item->is_rare,
            'is_in_demand' => $item->is_in_demand,
            'ai_score' => $item->ai_score,
            'ai_recommendation' => $item->ai_recommendation,
            'search_rank' => $item->search_rank ?? null,
            'insights' => [
                'tags' => $tags,
                'quick_score' => $quickScore,
                'recommendation' => $quickScore >= 65 ? 'BUY' : ($quickScore >= 40 ? 'HOLD' : 'PASS'),
            ],
            'source' => 'local',
        ];
    }

    /**
     * Enrich Discogs result
     */
    protected function enrichDiscogsResult(array $item): array
    {
        $have = $item['have'] ?? 0;
        $want = $item['want'] ?? 0;
        $forSale = $item['for_sale'] ?? 0;
        $price = $item['lowest_price'] ?? null;
        $demandRatio = $have > 0 ? round($want / $have, 2) : 0;

        $tags = [];

        if ($have < 100) {
            $tags[] = ['type' => 'rarity', 'label' => '💎 Ultra Rare', 'value' => 'ultra_rare', 'color' => 'purple'];
        } elseif ($have < 500) {
            $tags[] = ['type' => 'rarity', 'label' => '✨ Rare', 'value' => 'rare', 'color' => 'lilac'];
        }

        if ($demandRatio >= 2) {
            $tags[] = ['type' => 'demand', 'label' => '🔥 Hot', 'value' => 'hot', 'color' => 'coral'];
        }

        $quickScore = $this->calculateQuickScore($have, $want, $forSale, $price);

        return [
            'id' => $item['id'] ?? null,
            'title' => $item['title'] ?? 'Unknown',
            'artist' => $item['artist'] ?? 'Unknown Artist',
            'year' => $item['year'] ?? null,
            'country' => $item['country'] ?? null,
            'label' => $item['label'] ?? null,
            'genres' => isset($item['genre']) ? [$item['genre']] : [],
            'styles' => isset($item['style']) ? [$item['style']] : [],
            'format' => $item['format'] ?? null,
            'thumb' => $item['thumb'] ?? null,
            'cover' => $item['cover_image'] ?? null,
            'have' => $have,
            'want' => $want,
            'demand_ratio' => $demandRatio,
            'lowest_price' => $price,
            'for_sale' => $forSale,
            'insights' => [
                'tags' => $tags,
                'quick_score' => $quickScore,
                'recommendation' => $quickScore >= 65 ? 'BUY' : ($quickScore >= 40 ? 'HOLD' : 'PASS'),
            ],
            'source' => 'discogs',
        ];
    }

    /**
     * Calculate quick score
     */
    protected function calculateQuickScore(int $have, int $want, int $forSale, ?float $price): int
    {
        $score = 50;
        
        $demandRatio = $have > 0 ? $want / $have : 0;
        $score += min(20, $demandRatio * 10);
        
        if ($have < 200) $score += 15;
        elseif ($have < 1000) $score += 8;
        elseif ($have > 5000) $score -= 5;
        
        if ($forSale == 0) $score += 10;
        elseif ($forSale > 50) $score -= 5;
        
        if ($price !== null && $price < 20) $score += 5;
        
        return max(0, min(100, (int) $score));
    }

    /**
     * Get recent searches for session
     * GET /api/search/history
     */
    public function history(Request $request): JsonResponse
    {
        $sessionId = $request->header('X-Session-ID', session()->getId());
        $userId = auth()->id();

        // Cache session history
        $sessionHistory = Cache::remember(
            "search:history:session:{$sessionId}",
            60,
            fn() => SearchHistory::bySession($sessionId)
                ->uniqueQueries()
                ->limit(10)
                ->get()
        );

        // Get user-based history if authenticated
        $userHistory = [];
        if ($userId) {
            $userHistory = Cache::remember(
                "search:history:user:{$userId}",
                60,
                fn() => SearchHistory::byUser($userId)
                    ->uniqueQueries()
                    ->limit(10)
                    ->get()
            );
        }

        // Get popular and trending
        $popular = $this->getPopularSearchesCached();
        $trending = $this->getTrendingSearches(5);

        return response()->json([
            'recent' => $sessionHistory,
            'user_history' => $userHistory,
            'popular' => $popular,
            'trending' => $trending,
        ]);
    }

    /**
     * Get recent searches helper
     */
    protected function getRecentSearches(string $sessionId, int $limit = 5): array
    {
        // Use groupBy instead of distinct for PostgreSQL compatibility
        return SearchHistory::bySession($sessionId)
            ->select('query', 'type', DB::raw('MAX(created_at) as created_at'))
            ->groupBy('query', 'type')
            ->orderByDesc(DB::raw('MAX(created_at)'))
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Clear search history
     * DELETE /api/search/history
     */
    public function clearHistory(Request $request): JsonResponse
    {
        $sessionId = $request->header('X-Session-ID', session()->getId());
        
        SearchHistory::bySession($sessionId)->delete();
        
        // Clear cache
        Cache::forget("search:recent:{$sessionId}");
        Cache::forget("search:history:session:{$sessionId}");

        if (auth()->check()) {
            $userId = auth()->id();
            SearchHistory::byUser($userId)->delete();
            Cache::forget("search:history:user:{$userId}");
        }

        return response()->json(['message' => 'Search history cleared']);
    }

    /**
     * Record a selection (when user clicks a result)
     * POST /api/search/select
     */
    public function recordSelection(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string',
            'selected_id' => 'required',
            'selected_type' => 'required|string|in:vinyl,artist,genre,label',
            'selected_title' => 'nullable|string',
        ]);

        $sessionId = $request->header('X-Session-ID', session()->getId());

        // Update the most recent search with this query
        $search = SearchHistory::bySession($sessionId)
            ->where('query', $request->input('query'))
            ->orderByDesc('created_at')
            ->first();

        if ($search) {
            $search->update([
                'selected_result' => [
                    'id' => $request->input('selected_id'),
                    'type' => $request->input('selected_type'),
                    'title' => $request->input('selected_title'),
                ],
            ]);
        }

        return response()->json(['message' => 'Selection recorded']);
    }

    /**
     * Get quick stats for search
     * GET /api/search/stats
     */
    public function stats(): JsonResponse
    {
        $stats = $this->cacheRemember(
            'search:stats:global',
            self::CACHE_STATS_TTL,
            function () {
                return [
                    'total_vinyls' => DiscogsAnalysis::count(),
                    'total_artists' => DiscogsAnalysis::distinct('artist_name')->count(),
                    'genres' => count($this->getCachedGenres()),
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
            },
            ['search', 'stats']
        );

        // Add real-time trending data
        $stats['trending'] = $this->getTrendingSearches(5);

        return response()->json($stats);
    }

    /**
     * Warm up the cache (call this from a scheduled job)
     * POST /api/search/warm-cache
     */
    public function warmCache(): JsonResponse
    {
        // Pre-cache common queries
        $commonQueries = ['rock', 'jazz', 'electronic', 'beatles', 'pink floyd', 'vinyl', 'rare'];
        
        foreach ($commonQueries as $query) {
            $cacheKey = "search:suggest:" . $this->normalizeQuery($query);
            // Always cache (the helper handles tags gracefully)
            $this->cachePut(
                $cacheKey,
                $this->buildSuggestions($query),
                self::CACHE_SUGGESTIONS_TTL * 2,
                ['search', 'suggestions']
            );
        }

        // Pre-cache genres and stats
        $this->getCachedGenres();
        $this->getPopularSearchesCached();

        return response()->json([
            'message' => 'Cache warmed successfully',
            'queries_cached' => count($commonQueries),
        ]);
    }

    /**
     * Invalidate search cache (admin only)
     * DELETE /api/search/cache
     */
    public function invalidateCache(): JsonResponse
    {
        try {
            if ($this->supportsTags()) {
                Cache::tags(['search'])->flush();
                return response()->json(['message' => 'Search cache invalidated']);
            }
        } catch (\Exception $e) {
            // Fall through to partial clear
        }
        
        // If tags not supported, clear individual keys
        $keys = ['search:stats:global', 'search:popular:global', 'search:genres:all'];
        foreach ($keys as $key) {
            Cache::forget($key);
        }
        return response()->json(['message' => 'Cache cleared (partial without Redis tags support)']);
    }
}
