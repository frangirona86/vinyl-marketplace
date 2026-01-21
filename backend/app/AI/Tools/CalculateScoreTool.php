<?php

namespace App\AI\Tools;

use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

class CalculateScoreTool extends Tool
{
    /**
     * Tool name for the AI to reference
     */
    public function getName(): string
    {
        return 'calculate_vinyl_score';
    }

    /**
     * Description of what this tool does
     */
    public function getDescription(): string
    {
        return 'Calculates a numerical investment score (0-100) for a vinyl record based on demand, rarity, price, and rating metrics.';
    }

    /**
     * Define the parameters this tool accepts
     */
    public function getProperties(): array
    {
        return [
            new ToolProperty(
                name: 'have',
                type: 'integer',
                description: 'Number of people who have this record',
                required: true
            ),
            new ToolProperty(
                name: 'want',
                type: 'integer',
                description: 'Number of people who want this record',
                required: true
            ),
            new ToolProperty(
                name: 'rating',
                type: 'number',
                description: 'Average rating (0-5)',
                required: true
            ),
            new ToolProperty(
                name: 'num_for_sale',
                type: 'integer',
                description: 'Number of copies currently for sale',
                required: true
            ),
            new ToolProperty(
                name: 'lowest_price',
                type: 'number',
                description: 'Lowest price available (0 if none)',
                required: false
            ),
            new ToolProperty(
                name: 'suggested_price',
                type: 'number',
                description: 'Suggested market price (0 if unknown)',
                required: false
            ),
        ];
    }

    /**
     * Execute the scoring calculation
     */
    public function __invoke(
        int $have,
        int $want,
        float $rating,
        int $num_for_sale,
        float $lowest_price = 0,
        float $suggested_price = 0
    ): array {
        // Calculate demand ratio (25 points max)
        $demandRatio = $have > 0 ? $want / $have : ($want > 0 ? 10 : 0);
        $demandScore = min(25, $demandRatio * 12.5); // 2.0 ratio = 25 points

        // Calculate rarity score (20 points max)
        // Fewer copies = higher score
        if ($have < 50) {
            $rarityScore = 20;
        } elseif ($have < 200) {
            $rarityScore = 15;
        } elseif ($have < 1000) {
            $rarityScore = 10;
        } elseif ($have < 5000) {
            $rarityScore = 5;
        } else {
            $rarityScore = 2;
        }

        // Calculate price opportunity score (20 points max)
        $priceScore = 0;
        if ($lowest_price > 0 && $suggested_price > 0) {
            $priceRatio = $lowest_price / $suggested_price;
            if ($priceRatio < 0.5) {
                $priceScore = 20; // Great deal
            } elseif ($priceRatio < 0.8) {
                $priceScore = 15; // Good deal
            } elseif ($priceRatio < 1.0) {
                $priceScore = 10; // Fair price
            } else {
                $priceScore = 5; // Overpriced
            }
        } elseif ($num_for_sale == 0) {
            $priceScore = 15; // Scarcity premium
        }

        // Calculate rating score (15 points max)
        $ratingScore = ($rating / 5) * 15;

        // Calculate availability score (10 points max)
        // Fewer listings = higher score
        if ($num_for_sale == 0) {
            $availabilityScore = 10;
        } elseif ($num_for_sale < 5) {
            $availabilityScore = 8;
        } elseif ($num_for_sale < 20) {
            $availabilityScore = 5;
        } else {
            $availabilityScore = 2;
        }

        // Calculate market interest score (10 points max)
        $marketInterestScore = min(10, ($want / 100) * 2);

        // Total score
        $totalScore = round(
            $demandScore +
            $rarityScore +
            $priceScore +
            $ratingScore +
            $availabilityScore +
            $marketInterestScore
        );

        // Ensure score is within bounds
        $totalScore = max(0, min(100, $totalScore));

        // Determine recommendation
        if ($totalScore >= 75) {
            $recommendation = 'STRONG BUY';
        } elseif ($totalScore >= 60) {
            $recommendation = 'BUY';
        } elseif ($totalScore >= 45) {
            $recommendation = 'HOLD';
        } elseif ($totalScore >= 30) {
            $recommendation = 'AVOID';
        } else {
            $recommendation = 'STRONG AVOID';
        }

        return [
            'total_score' => $totalScore,
            'recommendation' => $recommendation,
            'breakdown' => [
                'demand_score' => round($demandScore, 1),
                'rarity_score' => round($rarityScore, 1),
                'price_score' => round($priceScore, 1),
                'rating_score' => round($ratingScore, 1),
                'availability_score' => round($availabilityScore, 1),
                'market_interest_score' => round($marketInterestScore, 1),
            ],
            'metrics_used' => [
                'demand_ratio' => round($demandRatio, 4),
                'have' => $have,
                'want' => $want,
                'rating' => $rating,
                'num_for_sale' => $num_for_sale,
            ],
        ];
    }
}
