<?php

namespace App\Http\Controllers;

use App\Models\DiscogsAnalysis;
use App\Services\DiscogsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscogsController extends Controller
{
    protected DiscogsService $discogs;

    public function __construct(DiscogsService $discogs)
    {
        $this->discogs = $discogs;
    }

    /**
     * Search for releases
     * GET /api/discogs/search?q=Abbey+Road
     */
    public function searchReleases(Request $request): JsonResponse
    {
        $request->validate([
            "q" => "required|string|min:2",
            "per_page" => "nullable|integer|min:1|max:50",
            "page" => "nullable|integer|min:1",
        ]);

        $results = $this->discogs->searchReleases(
            $request->input("q"),
            $request->input("per_page", 10),
            $request->input("page", 1)
        );

        if (!$results) {
            return response()->json(["message" => "No results found", "results" => []], 200);
        }

        return response()->json($results);
    }

    /**
     * Search releases with market data (have/want/price)
     * GET /api/discogs/search-market?q=Abbey+Road
     */
    public function searchWithMarket(Request $request): JsonResponse
    {
        $request->validate([
            "q" => "required|string|min:2",
            "per_page" => "nullable|integer|min:1|max:10",
            "page" => "nullable|integer|min:1",
        ]);

        $results = $this->discogs->searchWithMarketData(
            $request->input("q"),
            $request->input("per_page", 5),
            $request->input("page", 1)
        );

        if (!$results) {
            return response()->json(["message" => "No results found", "results" => []], 200);
        }

        return response()->json($results);
    }

    /**
     * Smart search with AI-generated quick insights for each result
     * GET /api/discogs/search-smart?q=Abbey+Road
     */
    public function searchSmart(Request $request): JsonResponse
    {
        $request->validate([
            "q" => "required|string|min:2",
            "per_page" => "nullable|integer|min:1|max:10",
            "page" => "nullable|integer|min:1",
        ]);

        $results = $this->discogs->searchWithMarketData(
            $request->input("q"),
            $request->input("per_page", 5),
            $request->input("page", 1)
        );

        if (!$results || empty($results['results'])) {
            return response()->json(["message" => "No results found", "results" => []], 200);
        }

        // Enrich each result with quick insights
        $enrichedResults = array_map(function ($item) {
            return $this->enrichWithInsights($item);
        }, $results['results']);

        return response()->json([
            'pagination' => $results['pagination'] ?? null,
            'results' => $enrichedResults,
        ]);
    }

    /**
     * Enrich a search result with quick AI insights
     */
    protected function enrichWithInsights(array $item): array
    {
        $have = $item['have'] ?? 0;
        $want = $item['want'] ?? 0;
        $forSale = $item['for_sale'] ?? 0;
        $lowestPrice = $item['lowest_price'] ?? null;
        
        // Calculate metrics
        $demandRatio = $have > 0 ? round($want / $have, 2) : ($want > 0 ? 999 : 0);
        
        // Generate tags
        $tags = [];
        
        // Rarity tags
        if ($have < 100) {
            $tags[] = ['type' => 'rarity', 'label' => '💎 Ultra Rare', 'value' => 'ultra_rare'];
        } elseif ($have < 500) {
            $tags[] = ['type' => 'rarity', 'label' => '✨ Rare', 'value' => 'rare'];
        } elseif ($have > 5000) {
            $tags[] = ['type' => 'rarity', 'label' => '📦 Common', 'value' => 'common'];
        }
        
        // Demand tags
        if ($demandRatio >= 2) {
            $tags[] = ['type' => 'demand', 'label' => '🔥 Hot Demand', 'value' => 'hot'];
        } elseif ($demandRatio >= 1) {
            $tags[] = ['type' => 'demand', 'label' => '📈 High Demand', 'value' => 'high'];
        } elseif ($demandRatio < 0.3) {
            $tags[] = ['type' => 'demand', 'label' => '😴 Low Interest', 'value' => 'low'];
        }
        
        // Availability tags
        if ($forSale == 0) {
            $tags[] = ['type' => 'availability', 'label' => '🚫 Unavailable', 'value' => 'unavailable'];
        } elseif ($forSale <= 3) {
            $tags[] = ['type' => 'availability', 'label' => '⚡ Limited Stock', 'value' => 'limited'];
        } elseif ($forSale > 50) {
            $tags[] = ['type' => 'availability', 'label' => '✅ Many Available', 'value' => 'many'];
        }
        
        // Price opportunity tags
        if ($lowestPrice !== null) {
            if ($lowestPrice < 15) {
                $tags[] = ['type' => 'price', 'label' => '💰 Budget Friendly', 'value' => 'budget'];
            } elseif ($lowestPrice > 100) {
                $tags[] = ['type' => 'price', 'label' => '💵 Premium', 'value' => 'premium'];
            }
        }
        
        // Genre tags (use emoji mapping for common genres)
        $genre = $item['genre'] ?? null;
        $style = $item['style'] ?? null;
        $genreEmojis = [
            'Electronic' => '🎛️',
            'Rock' => '🎸',
            'Hip Hop' => '🎤',
            'Jazz' => '🎷',
            'Classical' => '🎻',
            'Funk / Soul' => '🕺',
            'Reggae' => '🌴',
            'Latin' => '💃',
            'Pop' => '🎵',
            'Blues' => '🎹',
            'Folk, World, & Country' => '🪕',
            'Stage & Screen' => '🎬',
        ];
        
        if ($genre) {
            $emoji = $genreEmojis[$genre] ?? '🎶';
            $tags[] = ['type' => 'genre', 'label' => "{$emoji} {$genre}", 'value' => strtolower(str_replace(' ', '_', $genre))];
        }
        
        if ($style && $style !== $genre) {
            $tags[] = ['type' => 'style', 'label' => "🏷️ {$style}", 'value' => strtolower(str_replace(' ', '_', $style))];
        }
        
        // Generate quick score (simplified)
        $quickScore = $this->calculateQuickScore($have, $want, $forSale, $lowestPrice);
        
        // Generate quick insight text
        $insight = $this->generateQuickInsight($have, $want, $forSale, $lowestPrice, $demandRatio, $quickScore);
        
        // Check if we have this in our DB already
        $savedAnalysis = DiscogsAnalysis::where('discogs_id', $item['id'] ?? null)->first();
        
        return array_merge($item, [
            'insights' => [
                'tags' => $tags,
                'quick_score' => $quickScore,
                'demand_ratio' => $demandRatio,
                'insight' => $insight,
                'recommendation' => $quickScore >= 65 ? 'BUY' : ($quickScore >= 40 ? 'HOLD' : 'AVOID'),
            ],
            'saved_analysis' => $savedAnalysis ? [
                'id' => $savedAnalysis->id,
                'ai_score' => $savedAnalysis->ai_score,
                'ai_recommendation' => $savedAnalysis->ai_recommendation,
                'recommended_price_min' => $savedAnalysis->recommended_price_min,
                'recommended_price_max' => $savedAnalysis->recommended_price_max,
                'is_watchlist' => $savedAnalysis->is_watchlist,
            ] : null,
        ]);
    }

    /**
     * Calculate quick score for search results
     */
    protected function calculateQuickScore(int $have, int $want, int $forSale, ?float $price): int
    {
        $score = 50; // Base score
        
        // Demand factor (+/- 20)
        $demandRatio = $have > 0 ? $want / $have : 0;
        $score += min(20, $demandRatio * 10);
        
        // Rarity factor (+/- 15)
        if ($have < 200) $score += 15;
        elseif ($have < 1000) $score += 8;
        elseif ($have > 5000) $score -= 5;
        
        // Availability factor (+/- 10)
        if ($forSale == 0) $score += 10; // Scarcity premium
        elseif ($forSale > 50) $score -= 5;
        
        // Price factor (+/- 5)
        if ($price !== null && $price < 20) $score += 5;
        
        return max(0, min(100, (int) $score));
    }

    /**
     * Generate quick insight text
     */
    protected function generateQuickInsight(
        int $have, 
        int $want, 
        int $forSale, 
        ?float $price, 
        float $demandRatio,
        int $score
    ): string {
        $insights = [];
        
        // Rarity insight
        if ($have < 100) {
            $insights[] = "Ultra rare ({$have} owners)";
        } elseif ($have < 500) {
            $insights[] = "Rare pressing ({$have} collectors)";
        }
        
        // Demand insight
        if ($demandRatio >= 2) {
            $insights[] = "{$want} people want this vs {$have} who own it";
        } elseif ($demandRatio >= 1) {
            $insights[] = "Strong collector demand";
        }
        
        // Availability insight
        if ($forSale == 0) {
            $insights[] = "Not currently for sale - watch for listings";
        } elseif ($forSale <= 3) {
            $insights[] = "Only {$forSale} copies available";
        }
        
        // Price insight
        if ($price !== null) {
            if ($price < 15) {
                $insights[] = "Entry-level price point";
            } elseif ($price > 50 && $forSale <= 5) {
                $insights[] = "Premium pricing reflects scarcity";
            }
        }
        
        // Final recommendation based on score
        if ($score >= 70) {
            $insights[] = "⭐ Worth investigating further";
        } elseif ($score >= 55) {
            $insights[] = "Solid option for collectors";
        }
        
        return implode('. ', $insights) ?: "Standard release, typical market conditions";
    }

    /**
     * Search for artists
     * GET /api/discogs/artists-search?q=Beatles
     */
    public function searchArtists(Request $request): JsonResponse
    {
        $request->validate([
            "q" => "required|string|min:2",
        ]);

        $results = $this->discogs->searchArtists($request->input("q"));

        return response()->json(["results" => $results ?? []]);
    }

    /**
     * Get release details
     * GET /api/discogs/releases/{id}
     */
    public function getRelease(int $id): JsonResponse
    {
        $release = $this->discogs->getRelease($id);

        if (!$release) {
            return response()->json(["message" => "Release not found"], 404);
        }

        return response()->json(["data" => $release]);
    }

    /**
     * Get artist details
     * GET /api/discogs/artists/{id}
     */
    public function getArtist(int $id): JsonResponse
    {
        $artist = $this->discogs->getArtist($id);

        if (!$artist) {
            return response()->json(["message" => "Artist not found"], 404);
        }

        return response()->json(["data" => $artist]);
    }

    /**
     * Get price suggestions
     * GET /api/discogs/releases/{id}/prices
     */
    public function getPrices(int $id): JsonResponse
    {
        $prices = $this->discogs->getPriceSuggestions($id);

        if (!$prices) {
            return response()->json(["message" => "Price data not available"], 404);
        }

        return response()->json(["data" => $prices]);
    }

    /**
     * Get marketplace stats
     * GET /api/discogs/releases/{id}/stats
     */
    public function getStats(int $id): JsonResponse
    {
        $stats = $this->discogs->getMarketplaceStats($id);

        if (!$stats) {
            return response()->json(["message" => "Stats not available"], 404);
        }

        return response()->json(["data" => $stats]);
    }

    /**
     * Get marketplace listings
     * GET /api/discogs/releases/{id}/listings
     */
    public function getListings(Request $request, int $id): JsonResponse
    {
        $listings = $this->discogs->getMarketplaceListings(
            $id,
            $request->input("per_page", 25),
            $request->input("page", 1)
        );

        if (!$listings) {
            return response()->json(["message" => "Listings not available", "listings" => []], 200);
        }

        return response()->json($listings);
    }

    /**
     * Get COMPLETE analysis (all data combined)
     * GET /api/discogs/releases/{id}/analysis
     */
    public function getAnalysis(int $id): JsonResponse
    {
        $analysis = $this->discogs->getCompleteAnalysis($id);

        if (!$analysis) {
            return response()->json(["message" => "Analysis not available"], 404);
        }

        return response()->json(["data" => $analysis]);
    }

    /**
     * Save release to watchlist/analysis DB
     * POST /api/discogs/releases/{id}/save
     */
    public function saveToAnalysis(Request $request, int $id): JsonResponse
    {
        $analysis = $this->discogs->getCompleteAnalysis($id);

        if (!$analysis) {
            return response()->json(["message" => "Could not fetch release data"], 404);
        }

        $release = $analysis["release"];
        $community = $analysis["community"];
        $marketplace = $analysis["marketplace"];

        // Save or update in database
        $saved = DiscogsAnalysis::updateOrCreate(
            ["discogs_id" => $id],
            [
                "title" => $release["title"],
                "artist_name" => $release["artist_name"],
                "artist_id" => $release["artist_id"] ?? null,
                "artist_thumbnail" => $release["artist_thumbnail"] ?? null,
                "year" => $release["year"],
                "country" => $release["country"],
                "label" => $release["label"],
                "catalog_number" => $release["catalog_number"],
                "genres" => $release["genres"],
                "styles" => $release["styles"] ?? [],
                "tracklist" => $release["tracklist"] ?? [],
                "format" => $release["formats"][0]["name"] ?? null,
                "format_descriptions" => $release["format_descriptions"] ?? [],
                "have" => $community["have"] ?? 0,
                "want" => $community["want"] ?? 0,
                "rating_average" => $community["rating_average"] ?? 0,
                "rating_count" => $community["rating_count"] ?? 0,
                "num_for_sale" => $marketplace["total_listings"] ?? 0,
                "lowest_price" => $marketplace["stats"]["lowest_price"]["value"] ?? null,
                "lowest_price_currency" => $marketplace["stats"]["lowest_price"]["currency"] ?? null,
                "price_suggestions" => $marketplace["price_suggestions"],
                "demand_ratio" => $analysis["analysis"]["demand_ratio"],
                "is_rare" => $analysis["analysis"]["is_rare"],
                "is_in_demand" => $analysis["analysis"]["is_in_demand"],
                "raw_data" => $analysis,
                "cover_image" => $release["images"][0]["uri"] ?? null,
                "thumb" => $release["images"][0]["uri150"] ?? null,
                "is_watchlist" => $request->input("watchlist", false),
                "notes" => $release["notes"] ?? $request->input("notes"),
                "data_quality" => $release["data_quality"] ?? null,
                "fetched_at" => now(),
            ]
        );

        return response()->json([
            "message" => "Release saved to analysis database",
            "data" => $saved,
        ], 201);
    }

    /**
     * Get all saved analyses
     * GET /api/discogs/saved
     */
    public function getSaved(Request $request): JsonResponse
    {
        $query = DiscogsAnalysis::query();

        // Filters
        if ($request->has("watchlist")) {
            $query->where("is_watchlist", $request->boolean("watchlist"));
        }

        if ($request->has("rare")) {
            $query->where("is_rare", $request->boolean("rare"));
        }

        if ($request->has("in_demand")) {
            $query->where("is_in_demand", $request->boolean("in_demand"));
        }

        if ($request->has("artist")) {
            $query->byArtist($request->input("artist"));
        }

        if ($request->has("genre")) {
            $query->byGenre($request->input("genre"));
        }

        if ($request->has("style")) {
            $query->byStyle($request->input("style"));
        }

        if ($request->has("min_demand")) {
            $query->withHighDemand($request->input("min_demand"));
        }

        if ($request->has("max_price")) {
            $query->underPrice($request->input("max_price"));
        }

        // Sorting
        $sortBy = $request->input("sort", "demand_ratio");
        $sortDir = $request->input("dir", "desc");
        $query->orderBy($sortBy, $sortDir);

        return response()->json([
            "data" => $query->paginate($request->input("per_page", 20)),
        ]);
    }

    /**
     * Get a single saved analysis by discogs_id
     * GET /api/discogs/saved/{id}
     */
    public function getSavedById(int $id): JsonResponse
    {
        $vinyl = DiscogsAnalysis::where('discogs_id', $id)->first();

        if (!$vinyl) {
            return response()->json(['message' => 'Vinyl not found'], 404);
        }

        return response()->json(['data' => $vinyl]);
    }

    /**
     * Get available filters (genres, styles, countries, years)
     * GET /api/discogs/filters
     */
    public function getFilters(): JsonResponse
    {
        // Get all unique genres from JSON array column
        $allGenres = DiscogsAnalysis::whereNotNull('genres')
            ->pluck('genres')
            ->flatten()
            ->filter()
            ->unique()
            ->sort()
            ->values();

        // Get all unique styles
        $allStyles = DiscogsAnalysis::whereNotNull('styles')
            ->pluck('styles')
            ->flatten()
            ->filter()
            ->unique()
            ->sort()
            ->values();

        // Get unique countries
        $countries = DiscogsAnalysis::whereNotNull('country')
            ->distinct()
            ->pluck('country')
            ->sort()
            ->values();

        // Get year range
        $yearStats = DiscogsAnalysis::whereNotNull('year')
            ->selectRaw('MIN(year) as min_year, MAX(year) as max_year')
            ->first();

        // Get unique labels (top 50)
        $labels = DiscogsAnalysis::whereNotNull('label')
            ->select('label')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('label')
            ->orderByDesc('count')
            ->limit(50)
            ->pluck('label');

        // Get unique formats
        $formats = DiscogsAnalysis::whereNotNull('format')
            ->distinct()
            ->pluck('format')
            ->sort()
            ->values();

        return response()->json([
            'genres' => $allGenres,
            'styles' => $allStyles,
            'countries' => $countries,
            'years' => [
                'min' => $yearStats->min_year ?? 1950,
                'max' => $yearStats->max_year ?? date('Y'),
            ],
            'labels' => $labels,
            'formats' => $formats,
            'recommendations' => ['BUY', 'HOLD', 'AVOID'],
        ]);
    }

    /**
     * Get analysis stats/summary
     * GET /api/discogs/saved/stats
     */
    public function getSavedStats(): JsonResponse
    {
        $stats = [
            "total" => DiscogsAnalysis::count(),
            "watchlist" => DiscogsAnalysis::watchlist()->count(),
            "rare" => DiscogsAnalysis::rare()->count(),
            "in_demand" => DiscogsAnalysis::inDemand()->count(),
            "avg_demand_ratio" => DiscogsAnalysis::avg("demand_ratio"),
            "avg_price" => DiscogsAnalysis::whereNotNull("lowest_price")->avg("lowest_price"),
            "by_genre" => $this->getGenreStats(),
            "top_demand" => DiscogsAnalysis::orderByDesc("demand_ratio")
                ->limit(5)
                ->get(["discogs_id", "title", "artist_name", "demand_ratio", "have", "want"]),
        ];

        return response()->json(["data" => $stats]);
    }

    /**
     * Remove from analysis DB
     * DELETE /api/discogs/saved/{id}
     */
    public function removeSaved(int $id): JsonResponse
    {
        $analysis = DiscogsAnalysis::where("discogs_id", $id)->first();

        if (!$analysis) {
            return response()->json(["message" => "Not found in saved analyses"], 404);
        }

        $analysis->delete();

        return response()->json(["message" => "Removed from analysis database"]);
    }

    /**
     * Get genre statistics (PostgreSQL and SQLite compatible)
     */
    protected function getGenreStats(): array
    {
        $vinyls = DiscogsAnalysis::whereNotNull('genres')
            ->get(['genres']);
        
        $genreCounts = [];
        foreach ($vinyls as $vinyl) {
            $genres = is_array($vinyl->genres) ? $vinyl->genres : [];
            foreach ($genres as $genre) {
                if (!empty($genre)) {
                    $genreCounts[$genre] = ($genreCounts[$genre] ?? 0) + 1;
                }
            }
        }
        
        arsort($genreCounts);
        
        $result = [];
        $count = 0;
        foreach ($genreCounts as $genre => $cnt) {
            if ($count >= 10) break;
            $result[] = ['genre' => $genre, 'count' => $cnt];
            $count++;
        }
        
        return $result;
    }
}
