<?php

namespace App\Http\Controllers;

use App\AI\Agents\VinylScorerAgent;
use App\AI\Tools\VinylScorer;
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
            // Use the AI Agent
            $agent = VinylScorerAgent::make();
            
            $response = $agent->chat(
                new UserMessage(
                    "Analyze the vinyl record with Discogs ID: {$discogsId}. " .
                    "Use the analyze_discogs tool to get the data, then " .
                    "use calculate_score to compute the investment score. " .
                    "Provide your analysis and final recommendation."
                )
            );

            return response()->json([
                'source' => 'ai_agent',
                'discogs_id' => $discogsId,
                'analysis' => $response->getContent(),
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
