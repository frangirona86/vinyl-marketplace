<?php

namespace App\AI\Tools;

/**
 * Standalone Vinyl Scorer - No AI dependency
 * Calculates investment scores based on marketplace metrics
 */
class VinylScorer
{
    /**
     * Calculate investment score for a vinyl record
     */
    public function calculate(
        int $have,
        int $want,
        float $rating,
        int $numForSale,
        float $lowestPrice = 0,
        float $suggestedPrice = 0
    ): array {
        // Calculate demand ratio (25 points max)
        $demandRatio = $have > 0 ? $want / $have : ($want > 0 ? 10 : 0);
        $demandScore = min(25, $demandRatio * 12.5); // 2.0 ratio = 25 points

        // Calculate rarity score (20 points max)
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
        if ($lowestPrice > 0 && $suggestedPrice > 0) {
            $priceRatio = $lowestPrice / $suggestedPrice;
            if ($priceRatio < 0.5) {
                $priceScore = 20; // Great deal
            } elseif ($priceRatio < 0.8) {
                $priceScore = 15; // Good deal
            } elseif ($priceRatio < 1.0) {
                $priceScore = 10; // Fair price
            } else {
                $priceScore = 5; // Overpriced
            }
        } elseif ($numForSale == 0) {
            $priceScore = 15; // Scarcity premium
        }

        // Calculate rating score (15 points max)
        $ratingScore = ($rating / 5) * 15;

        // Calculate availability score (10 points max)
        if ($numForSale == 0) {
            $availabilityScore = 10;
        } elseif ($numForSale < 5) {
            $availabilityScore = 8;
        } elseif ($numForSale < 20) {
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
                'num_for_sale' => $numForSale,
            ],
        ];
    }
}
