<?php

namespace App\Http\Controllers;

use App\AI\Agents\VinylScorerAgent;
use App\AI\Tools\VinylScorer;
use App\Models\DiscogsAnalysis;
use App\Services\DiscogsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use NeuronAI\Chat\Messages\UserMessage;

class VinylScorerController extends Controller
{
    protected DiscogsService $discogs;

    public function __construct(DiscogsService $discogs)
    {
        $this->discogs = $discogs;
    }

    /**
     * Get AI-powered analysis and score for a vinyl release
     * POST /api/vinyl-scorer/analyze
     */
    public function analyze(Request $request): JsonResponse
    {
        $request->validate([
            'discogs_id' => 'required|integer',
        ]);

        $discogsId = $request->input('discogs_id');

        // Check if OpenAI key is configured
        if (!config('services.openai.key')) {
            // Fall back to algorithmic scoring without AI
            return $this->algorithmicScore($discogsId);
        }

        try {
            // Get Discogs data first
            $discogsData = $this->discogs->getCompleteAnalysis($discogsId);
            
            if (!$discogsData) {
                return response()->json(['error' => 'Release not found'], 404);
            }

            // Use the AI Agent
            $agent = VinylScorerAgent::make();
            
            // Get price context
            $lowestPrice = $discogsData['marketplace']['stats']['lowest_price']['value'] ?? null;
            $priceSuggestions = $discogsData['marketplace']['price_suggestions'] ?? [];
            $priceContext = $lowestPrice ? "Current lowest price: \${$lowestPrice}. " : "No listings available. ";
            
            $response = $agent->chat(
                new UserMessage(
                    "Analyze the vinyl record with Discogs ID: {$discogsId}. " .
                    "Use the analyze_discogs tool to get the data, then " .
                    "use calculate_score to compute the investment score. " .
                    $priceContext .
                    "At the end, you MUST include this exact format on its own line: " .
                    "PRICE RECOMMENDATION: Min: \$XX.XX - Max: \$XX.XX " .
                    "(Replace XX.XX with actual dollar amounts based on rarity, demand, and market value)"
                )
            );

            $aiAnalysis = $response->getContent();

            // Calculate score for storage
            $scorer = new VinylScorer();
            $score = $scorer->calculate(
                have: $discogsData['community']['have'] ?? 0,
                want: $discogsData['community']['want'] ?? 0,
                rating: $discogsData['community']['rating_average'] ?? 0,
                numForSale: $discogsData['marketplace']['total_listings'] ?? 0
            );

            // Extract price recommendation from AI response
            $priceRec = $this->extractPriceRecommendation($aiAnalysis);

            // Check if record exists to save previous values
            $existing = DiscogsAnalysis::where('discogs_id', $discogsId)->first();

            // Save to database automatically
            $saved = DiscogsAnalysis::updateOrCreate(
                ['discogs_id' => $discogsId],
                [
                    'title' => $discogsData['release']['title'] ?? 'Unknown',
                    'artist_name' => $discogsData['release']['artist_name'] ?? 'Unknown',
                    'year' => $discogsData['release']['year'] ?? null,
                    'country' => $discogsData['release']['country'] ?? null,
                    'label' => $discogsData['release']['label'] ?? null,
                    'genres' => $discogsData['release']['genres'] ?? [],
                    'styles' => $discogsData['release']['styles'] ?? [],
                    'format' => $discogsData['release']['formats'][0]['name'] ?? null,
                    'have' => $discogsData['community']['have'] ?? 0,
                    'want' => $discogsData['community']['want'] ?? 0,
                    'rating_average' => $discogsData['community']['rating_average'] ?? 0,
                    'rating_count' => $discogsData['community']['rating_count'] ?? 0,
                    'num_for_sale' => $discogsData['marketplace']['total_listings'] ?? 0,
                    'lowest_price' => $discogsData['marketplace']['stats']['lowest_price']['value'] ?? null,
                    'lowest_price_currency' => $discogsData['marketplace']['stats']['lowest_price']['currency'] ?? 'USD',
                    'recommended_price_min' => $priceRec['min'],
                    'recommended_price_max' => $priceRec['max'],
                    'demand_ratio' => $discogsData['analysis']['demand_ratio'] ?? 0,
                    'ai_score' => $score['total_score'],
                    'ai_recommendation' => $score['recommendation'],
                    'ai_analysis' => $aiAnalysis,
                    'is_rare' => $discogsData['analysis']['is_rare'] ?? false,
                    'is_in_demand' => $discogsData['analysis']['is_in_demand'] ?? false,
                    'cover_image' => $discogsData['release']['images'][0]['uri'] ?? null,
                    'thumb' => $discogsData['release']['images'][0]['uri150'] ?? null,
                    'notes' => "AI Score: {$score['total_score']}/100 - {$score['recommendation']}",
                    // Save previous values for trend tracking
                    'previous_have' => $existing?->have,
                    'previous_want' => $existing?->want,
                    'previous_lowest_price' => $existing?->lowest_price,
                    'fetched_at' => now(),
                    'last_refreshed_at' => now(),
                ]
            );

            return response()->json([
                'source' => 'ai_agent',
                'discogs_id' => $discogsId,
                'analysis' => $aiAnalysis,
                'score' => $score['total_score'],
                'recommendation' => $score['recommendation'],
                'price_recommendation' => [
                    'min' => $priceRec['min'],
                    'max' => $priceRec['max'],
                ],
                'saved_to_db' => true,
                'db_id' => $saved->id,
                'is_trending' => $saved->isTrending(),
            ]);

        } catch (\Exception $e) {
            // Fall back to algorithmic scoring on error
            return $this->algorithmicScore($discogsId, $e->getMessage());
        }
    }

    /**
     * Get quick algorithmic score (no AI)
     * GET /api/vinyl-scorer/quick/{discogs_id}
     */
    public function quickScore(int $discogsId): JsonResponse
    {
        return $this->algorithmicScore($discogsId);
    }

    /**
     * Batch score multiple releases
     * POST /api/vinyl-scorer/batch
     */
    public function batchScore(Request $request): JsonResponse
    {
        $request->validate([
            'discogs_ids' => 'required|array|min:1|max:10',
            'discogs_ids.*' => 'integer',
        ]);

        $results = [];

        foreach ($request->input('discogs_ids') as $discogsId) {
            $analysis = $this->discogs->getCompleteAnalysis($discogsId);

            if (!$analysis) {
                $results[] = [
                    'discogs_id' => $discogsId,
                    'error' => 'Release not found',
                ];
                continue;
            }

            $scorer = new VinylScorer();
            $score = $scorer->calculate(
                have: $analysis['community']['have'] ?? 0,
                want: $analysis['community']['want'] ?? 0,
                rating: $analysis['community']['rating_average'] ?? 0,
                numForSale: $analysis['marketplace']['total_listings'] ?? 0,
                lowestPrice: $analysis['marketplace']['stats']['lowest_price']['value'] ?? 0,
                suggestedPrice: $this->getSuggestedPrice($analysis['marketplace']['price_suggestions'] ?? [])
            );

            $results[] = [
                'discogs_id' => $discogsId,
                'title' => $analysis['release']['title'] ?? 'Unknown',
                'artist' => $analysis['release']['artist_name'] ?? 'Unknown',
                'score' => $score['total_score'],
                'recommendation' => $score['recommendation'],
            ];
        }

        // Sort by score descending
        usort($results, fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

        return response()->json([
            'results' => $results,
            'count' => count($results),
        ]);
    }

    /**
     * Calculate algorithmic score without AI
     */
    protected function algorithmicScore(int $discogsId, ?string $fallbackReason = null): JsonResponse
    {
        $analysis = $this->discogs->getCompleteAnalysis($discogsId);

        if (!$analysis) {
            return response()->json([
                'error' => 'Release not found',
                'discogs_id' => $discogsId,
            ], 404);
        }

        $scorer = new VinylScorer();
        $score = $scorer->calculate(
            have: $analysis['community']['have'] ?? 0,
            want: $analysis['community']['want'] ?? 0,
            rating: $analysis['community']['rating_average'] ?? 0,
            numForSale: $analysis['marketplace']['total_listings'] ?? 0,
            lowestPrice: $analysis['marketplace']['stats']['lowest_price']['value'] ?? 0,
            suggestedPrice: $this->getSuggestedPrice($analysis['marketplace']['price_suggestions'] ?? [])
        );

        $response = [
            'source' => 'algorithmic',
            'discogs_id' => $discogsId,
            'release' => [
                'title' => $analysis['release']['title'] ?? 'Unknown',
                'artist' => $analysis['release']['artist_name'] ?? 'Unknown',
                'year' => $analysis['release']['year'] ?? null,
                'label' => $analysis['release']['label'] ?? null,
            ],
            'score' => $score['total_score'],
            'recommendation' => $score['recommendation'],
            'breakdown' => $score['breakdown'],
            'raw_metrics' => [
                'have' => $analysis['community']['have'] ?? 0,
                'want' => $analysis['community']['want'] ?? 0,
                'demand_ratio' => $analysis['analysis']['demand_ratio'] ?? 0,
                'rating' => $analysis['community']['rating_average'] ?? 0,
                'num_for_sale' => $analysis['marketplace']['total_listings'] ?? 0,
            ],
        ];

        if ($fallbackReason) {
            $response['fallback_reason'] = $fallbackReason;
        }

        return response()->json($response);
    }

    /**
     * Refresh analysis for an existing record
     * POST /api/vinyl-scorer/refresh/{discogs_id}
     */
    public function refresh(int $discogsId): JsonResponse
    {
        // Check if record exists
        $existing = DiscogsAnalysis::where('discogs_id', $discogsId)->first();
        
        if (!$existing) {
            return response()->json([
                'error' => 'Record not found in database. Use /analyze first.',
                'discogs_id' => $discogsId,
            ], 404);
        }

        // Get fresh data from Discogs
        $discogsData = $this->discogs->getCompleteAnalysis($discogsId);
        
        if (!$discogsData) {
            return response()->json(['error' => 'Release not found on Discogs'], 404);
        }

        // Calculate new score
        $scorer = new VinylScorer();
        $score = $scorer->calculate(
            have: $discogsData['community']['have'] ?? 0,
            want: $discogsData['community']['want'] ?? 0,
            rating: $discogsData['community']['rating_average'] ?? 0,
            numForSale: $discogsData['marketplace']['total_listings'] ?? 0
        );

        // Calculate changes
        $changes = [
            'have' => ($discogsData['community']['have'] ?? 0) - $existing->have,
            'want' => ($discogsData['community']['want'] ?? 0) - $existing->want,
            'price' => $existing->lowest_price 
                ? ($discogsData['marketplace']['stats']['lowest_price']['value'] ?? 0) - $existing->lowest_price 
                : null,
        ];

        // Update record with previous values saved
        $existing->update([
            'previous_have' => $existing->have,
            'previous_want' => $existing->want,
            'previous_lowest_price' => $existing->lowest_price,
            'have' => $discogsData['community']['have'] ?? 0,
            'want' => $discogsData['community']['want'] ?? 0,
            'rating_average' => $discogsData['community']['rating_average'] ?? 0,
            'num_for_sale' => $discogsData['marketplace']['total_listings'] ?? 0,
            'lowest_price' => $discogsData['marketplace']['stats']['lowest_price']['value'] ?? null,
            'demand_ratio' => $discogsData['analysis']['demand_ratio'] ?? 0,
            'ai_score' => $score['total_score'],
            'ai_recommendation' => $score['recommendation'],
            'is_rare' => $discogsData['analysis']['is_rare'] ?? false,
            'is_in_demand' => $discogsData['analysis']['is_in_demand'] ?? false,
            'last_refreshed_at' => now(),
        ]);

        $isTrending = $existing->isTrending();

        return response()->json([
            'message' => 'Analysis refreshed successfully',
            'discogs_id' => $discogsId,
            'title' => $existing->title,
            'artist' => $existing->artist_name,
            'score' => $score['total_score'],
            'recommendation' => $score['recommendation'],
            'changes' => $changes,
            'is_trending' => $isTrending,
            'trending_alert' => $isTrending ? '🔥 This vinyl is trending! Want increased significantly.' : null,
        ]);
    }

    /**
     * Refresh all items that need updating
     * POST /api/vinyl-scorer/refresh-all
     */
    public function refreshAll(Request $request): JsonResponse
    {
        $hours = $request->input('hours', 24);
        $limit = $request->input('limit', 10);

        $items = DiscogsAnalysis::needsRefresh($hours)
            ->limit($limit)
            ->get();

        $results = [];
        foreach ($items as $item) {
            $discogsData = $this->discogs->getCompleteAnalysis($item->discogs_id);
            
            if (!$discogsData) {
                $results[] = ['discogs_id' => $item->discogs_id, 'status' => 'not_found'];
                continue;
            }

            $scorer = new VinylScorer();
            $score = $scorer->calculate(
                have: $discogsData['community']['have'] ?? 0,
                want: $discogsData['community']['want'] ?? 0,
                rating: $discogsData['community']['rating_average'] ?? 0,
                numForSale: $discogsData['marketplace']['total_listings'] ?? 0
            );

            $item->update([
                'previous_have' => $item->have,
                'previous_want' => $item->want,
                'previous_lowest_price' => $item->lowest_price,
                'have' => $discogsData['community']['have'] ?? 0,
                'want' => $discogsData['community']['want'] ?? 0,
                'lowest_price' => $discogsData['marketplace']['stats']['lowest_price']['value'] ?? null,
                'demand_ratio' => $discogsData['analysis']['demand_ratio'] ?? 0,
                'ai_score' => $score['total_score'],
                'ai_recommendation' => $score['recommendation'],
                'last_refreshed_at' => now(),
            ]);

            $results[] = [
                'discogs_id' => $item->discogs_id,
                'title' => $item->title,
                'status' => 'refreshed',
                'score' => $score['total_score'],
                'is_trending' => $item->isTrending(),
            ];
        }

        return response()->json([
            'refreshed' => count($results),
            'results' => $results,
        ]);
    }

    /**
     * Get trending items
     * GET /api/vinyl-scorer/trending
     */
    public function trending(): JsonResponse
    {
        $trending = DiscogsAnalysis::trending()
            ->orderByRaw('(want - previous_want) DESC')
            ->limit(20)
            ->get();

        return response()->json([
            'count' => $trending->count(),
            'items' => $trending->map(fn($item) => [
                'discogs_id' => $item->discogs_id,
                'title' => $item->title,
                'artist' => $item->artist_name,
                'score' => $item->ai_score,
                'recommendation' => $item->ai_recommendation,
                'want_change' => $item->want - $item->previous_want,
                'want_change_percent' => $item->previous_want > 0 
                    ? round((($item->want - $item->previous_want) / $item->previous_want) * 100, 1) 
                    : null,
                'price_recommendation' => [
                    'min' => $item->recommended_price_min,
                    'max' => $item->recommended_price_max,
                ],
            ]),
        ]);
    }

    /**
     * Extract price recommendation from AI analysis text
     */
    protected function extractPriceRecommendation(string $analysis): array
    {
        $min = null;
        $max = null;

        // Try to find "Min: $X" pattern
        if (preg_match('/min[:\s]*\$?(\d+(?:\.\d{2})?)/i', $analysis, $matches)) {
            $min = (float) $matches[1];
        }

        // Try to find "Max: $X" pattern
        if (preg_match('/max[:\s]*\$?(\d+(?:\.\d{2})?)/i', $analysis, $matches)) {
            $max = (float) $matches[1];
        }

        // Alternative: Try to find "$X - $Y" pattern
        if (!$min && !$max && preg_match('/\$(\d+(?:\.\d{2})?)\s*[-–]\s*\$(\d+(?:\.\d{2})?)/i', $analysis, $matches)) {
            $min = (float) $matches[1];
            $max = (float) $matches[2];
        }

        return ['min' => $min, 'max' => $max];
    }

    /**
     * Get average suggested price from price suggestions
     */
    protected function getSuggestedPrice(array $priceSuggestions): float
    {
        if (empty($priceSuggestions)) {
            return 0;
        }

        // Try to get VG+ or VG price as reference
        $conditions = ['Very Good Plus (VG+)', 'Very Good (VG)', 'Near Mint (NM or M-)'];
        
        foreach ($conditions as $condition) {
            if (isset($priceSuggestions[$condition]['value'])) {
                return (float) $priceSuggestions[$condition]['value'];
            }
        }

        // Return first available price
        $firstPrice = reset($priceSuggestions);
        return (float) ($firstPrice['value'] ?? 0);
    }
}
