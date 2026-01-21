<?php

namespace App\AI\Tools;

use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use App\Services\DiscogsService;

class AnalyzeDiscogsTool extends Tool
{
    /**
     * Tool name for the AI to reference
     */
    public function getName(): string
    {
        return 'analyze_discogs_release';
    }

    /**
     * Description of what this tool does
     */
    public function getDescription(): string
    {
        return 'Retrieves complete analysis data from Discogs for a vinyl release including community stats (have/want), marketplace data (prices, listings), and rating information.';
    }

    /**
     * Define the parameters this tool accepts
     */
    public function getProperties(): array
    {
        return [
            new ToolProperty(
                name: 'discogs_id',
                type: 'integer',
                description: 'The Discogs release ID to analyze',
                required: true
            ),
        ];
    }

    /**
     * Execute the tool
     */
    public function __invoke(int $discogs_id): array
    {
        $discogs = app(DiscogsService::class);
        $analysis = $discogs->getCompleteAnalysis($discogs_id);

        if (!$analysis) {
            return [
                'error' => true,
                'message' => "Could not find release with ID: {$discogs_id}",
            ];
        }

        // Return structured data for the AI to analyze
        return [
            'discogs_id' => $discogs_id,
            'title' => $analysis['release']['title'] ?? 'Unknown',
            'artist' => $analysis['release']['artist_name'] ?? 'Unknown',
            'year' => $analysis['release']['year'] ?? null,
            'country' => $analysis['release']['country'] ?? null,
            'label' => $analysis['release']['label'] ?? null,
            'genres' => $analysis['release']['genres'] ?? [],
            'format' => $analysis['release']['formats'][0]['name'] ?? 'Vinyl',
            
            // Community stats
            'community' => [
                'have' => $analysis['community']['have'] ?? 0,
                'want' => $analysis['community']['want'] ?? 0,
                'rating' => $analysis['community']['rating_average'] ?? 0,
                'rating_count' => $analysis['community']['rating_count'] ?? 0,
            ],
            
            // Marketplace stats
            'marketplace' => [
                'num_for_sale' => $analysis['marketplace']['total_listings'] ?? 0,
                'lowest_price' => $analysis['marketplace']['stats']['lowest_price'] ?? null,
                'price_suggestions' => $analysis['marketplace']['price_suggestions'] ?? [],
            ],
            
            // Pre-calculated metrics
            'metrics' => [
                'demand_ratio' => $analysis['analysis']['demand_ratio'] ?? 0,
                'is_rare' => $analysis['analysis']['is_rare'] ?? false,
                'is_in_demand' => $analysis['analysis']['is_in_demand'] ?? false,
            ],
        ];
    }
}
