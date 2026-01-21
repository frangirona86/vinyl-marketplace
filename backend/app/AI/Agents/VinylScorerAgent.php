<?php

namespace App\AI\Agents;

use NeuronAI\Agent;
use NeuronAI\SystemPrompt;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\OpenAI\OpenAI;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use NeuronAI\Tools\PropertyType;
use App\Services\DiscogsService;
use App\AI\Tools\VinylScorer;

class VinylScorerAgent extends Agent
{
    /**
     * Configure the AI provider (OpenAI, Anthropic, etc.)
     */
    protected function provider(): AIProviderInterface
    {
        return new OpenAI(
            key: config('services.openai.key'),
            model: config('services.openai.model', 'gpt-4o-mini')
        );
    }

    /**
     * Define the agent's system instructions
     */
    public function instructions(): string
    {
        return new SystemPrompt(
            background: [
                'You are an expert vinyl record investment analyst.',
                'You analyze marketplace data from Discogs to evaluate vinyl records.',
                'Your goal is to help collectors find good investment opportunities.',
            ],
            steps: [
                'First, retrieve the complete analysis data for the vinyl record using the analyze_discogs tool.',
                'Then calculate the investment score using the calculate_score tool.',
                'Finally, provide your expert analysis and recommendation.',
            ],
            output: [
                'Always provide a score from 0-100.',
                'Explain the key factors that influenced your score.',
                'Give a clear BUY / HOLD / AVOID recommendation.',
                'Be concise but informative.',
            ]
        );
    }

    /**
     * Register the tools the agent can use
     */
    public function tools(): array
    {
        return [
            $this->createAnalyzeDiscogsTool(),
            $this->createCalculateScoreTool(),
        ];
    }

    /**
     * Create the Discogs analysis tool
     */
    protected function createAnalyzeDiscogsTool(): Tool
    {
        return Tool::make(
            name: 'analyze_discogs',
            description: 'Retrieves complete analysis data from Discogs for a vinyl release including community stats (have/want), marketplace data (prices, listings), and rating information.',
            properties: [
                new ToolProperty(
                    name: 'discogs_id',
                    type: PropertyType::INTEGER,
                    description: 'The Discogs release ID to analyze',
                    required: true
                ),
            ]
        )->setCallable(function (int $discogs_id): string {
            $discogs = app(DiscogsService::class);
            $analysis = $discogs->getCompleteAnalysis($discogs_id);

            if (!$analysis) {
                return json_encode(['error' => "Could not find release with ID: {$discogs_id}"]);
            }

            return json_encode([
                'discogs_id' => $discogs_id,
                'title' => $analysis['release']['title'] ?? 'Unknown',
                'artist' => $analysis['release']['artist_name'] ?? 'Unknown',
                'year' => $analysis['release']['year'] ?? null,
                'label' => $analysis['release']['label'] ?? null,
                'genres' => $analysis['release']['genres'] ?? [],
                'community' => [
                    'have' => $analysis['community']['have'] ?? 0,
                    'want' => $analysis['community']['want'] ?? 0,
                    'rating' => $analysis['community']['rating_average'] ?? 0,
                    'rating_count' => $analysis['community']['rating_count'] ?? 0,
                ],
                'marketplace' => [
                    'num_for_sale' => $analysis['marketplace']['total_listings'] ?? 0,
                    'lowest_price' => $analysis['marketplace']['stats']['lowest_price'] ?? null,
                ],
                'metrics' => [
                    'demand_ratio' => $analysis['analysis']['demand_ratio'] ?? 0,
                    'is_rare' => $analysis['analysis']['is_rare'] ?? false,
                    'is_in_demand' => $analysis['analysis']['is_in_demand'] ?? false,
                ],
            ]);
        });
    }

    /**
     * Create the score calculation tool
     */
    protected function createCalculateScoreTool(): Tool
    {
        return Tool::make(
            name: 'calculate_score',
            description: 'Calculates a numerical investment score (0-100) for a vinyl record based on the metrics.',
            properties: [
                new ToolProperty(
                    name: 'have',
                    type: PropertyType::INTEGER,
                    description: 'Number of people who have this record',
                    required: true
                ),
                new ToolProperty(
                    name: 'want',
                    type: PropertyType::INTEGER,
                    description: 'Number of people who want this record',
                    required: true
                ),
                new ToolProperty(
                    name: 'rating',
                    type: PropertyType::NUMBER,
                    description: 'Average rating (0-5)',
                    required: true
                ),
                new ToolProperty(
                    name: 'num_for_sale',
                    type: PropertyType::INTEGER,
                    description: 'Number of copies currently for sale',
                    required: true
                ),
            ]
        )->setCallable(function (int $have, int $want, float $rating, int $num_for_sale): string {
            $scorer = new VinylScorer();
            $result = $scorer->calculate(
                have: $have,
                want: $want,
                rating: $rating,
                numForSale: $num_for_sale
            );

            return json_encode($result);
        });
    }
}
