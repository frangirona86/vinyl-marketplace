<?php

namespace App\Console\Commands;

use App\Models\DiscogsAnalysis;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class AnalyzeVinylsAI extends Command
{
    protected $signature = 'vinyls:analyze-ai 
                            {--limit=100 : Maximum vinyls to analyze}
                            {--batch-size=5 : Vinyls per API call}';

    protected $description = 'Analyze vinyls using AI (OpenAI) for investment scoring';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $batchSize = (int) $this->option('batch-size');

        // Get vinyls without AI analysis
        $vinyls = DiscogsAnalysis::whereNull('ai_score')
            ->orWhereNull('ai_analysis')
            ->limit($limit)
            ->get();

        if ($vinyls->isEmpty()) {
            $this->info('All vinyls already have AI analysis.');
            return Command::SUCCESS;
        }

        $this->info("Analyzing {$vinyls->count()} vinyls with AI...");
        $this->newLine();

        $progressBar = $this->output->createProgressBar($vinyls->count());
        $progressBar->start();

        $analyzed = 0;
        $errors = 0;

        // Process in batches
        foreach ($vinyls->chunk($batchSize) as $batch) {
            $result = $this->analyzeBatch($batch);
            $analyzed += $result['success'];
            $errors += $result['errors'];
            
            foreach ($batch as $vinyl) {
                $progressBar->advance();
            }

            // Rate limit - wait between batches
            usleep(500000); // 500ms
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("═══════════════════════════════════════");
        $this->info("AI Analysis Complete!");
        $this->info("Analyzed: {$analyzed}");
        $this->info("Errors: {$errors}");
        $this->info("═══════════════════════════════════════");

        return Command::SUCCESS;
    }

    protected function analyzeBatch($vinyls): array
    {
        $success = 0;
        $errors = 0;

        foreach ($vinyls as $vinyl) {
            try {
                $result = $this->analyzeVinyl($vinyl);
                if ($result) {
                    $success++;
                } else {
                    $errors++;
                }
            } catch (\Exception $e) {
                $errors++;
                $this->error("Error analyzing {$vinyl->discogs_id}: " . $e->getMessage());
            }
        }

        return ['success' => $success, 'errors' => $errors];
    }

    protected function analyzeVinyl(DiscogsAnalysis $vinyl): bool
    {
        $apiKey = config('services.openai.key');
        
        if (!$apiKey) {
            $this->error('OpenAI API key not configured');
            return false;
        }

        // Build prompt with vinyl data
        $prompt = $this->buildPrompt($vinyl);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('services.openai.model', 'gpt-4o-mini'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an expert vinyl record analyst and collector. Analyze vinyls for investment potential based on rarity, demand, and market conditions. Be concise but insightful. Always provide a score (0-100), recommendation (BUY/HOLD/AVOID), and price range.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => 300,
                'temperature' => 0.7,
            ]);

            if (!$response->successful()) {
                return false;
            }

            $data = $response->json();
            $analysis = $data['choices'][0]['message']['content'] ?? null;

            if (!$analysis) {
                return false;
            }

            // Extract score, recommendation, and prices from analysis
            $extracted = $this->extractFromAnalysis($analysis, $vinyl);

            // Update vinyl with AI analysis
            $vinyl->update([
                'ai_score' => $extracted['score'],
                'ai_recommendation' => $extracted['recommendation'],
                'ai_analysis' => $analysis,
                'recommended_price_min' => $extracted['price_min'],
                'recommended_price_max' => $extracted['price_max'],
                'last_refreshed_at' => now(),
            ]);

            return true;

        } catch (\Exception $e) {
            return false;
        }
    }

    protected function buildPrompt(DiscogsAnalysis $vinyl): string
    {
        $styles = is_array($vinyl->styles) ? implode(', ', $vinyl->styles) : ($vinyl->styles ?? 'Unknown');
        $genres = is_array($vinyl->genres) ? implode(', ', $vinyl->genres) : ($vinyl->genres ?? 'Unknown');
        
        return "Analyze this vinyl record for investment potential:

Artist: {$vinyl->artist_name}
Title: {$vinyl->title}
Year: {$vinyl->year}
Label: {$vinyl->label}
Country: {$vinyl->country}
Genre: {$genres}
Style: {$styles}
Format: {$vinyl->format}

Market Data:
- Collectors who have it: {$vinyl->have}
- Collectors who want it: {$vinyl->want}
- Demand ratio: {$vinyl->demand_ratio}
- Currently for sale: {$vinyl->num_for_sale}
- Lowest price: " . ($vinyl->lowest_price ? "\${$vinyl->lowest_price}" : "N/A") . "

Provide:
1. Investment SCORE (0-100)
2. RECOMMENDATION: BUY, HOLD, or AVOID
3. PRICE RANGE: Min \$XX - Max \$XX (fair market value)
4. Brief analysis (2-3 sentences)

Format your response as:
SCORE: [number]
RECOMMENDATION: [BUY/HOLD/AVOID]
PRICE RANGE: \$[min] - \$[max]
ANALYSIS: [your analysis]";
    }

    protected function extractFromAnalysis(string $analysis, DiscogsAnalysis $vinyl): array
    {
        $score = 50; // Default
        $recommendation = 'HOLD';
        $priceMin = null;
        $priceMax = null;

        // Extract score
        if (preg_match('/SCORE[:\s]*(\d+)/i', $analysis, $matches)) {
            $score = min(100, max(0, (int) $matches[1]));
        }

        // Extract recommendation
        if (preg_match('/RECOMMENDATION[:\s]*(BUY|HOLD|AVOID)/i', $analysis, $matches)) {
            $recommendation = strtoupper($matches[1]);
        }

        // Extract price range
        if (preg_match('/\$(\d+(?:\.\d{2})?)\s*[-–]\s*\$(\d+(?:\.\d{2})?)/i', $analysis, $matches)) {
            $priceMin = (float) $matches[1];
            $priceMax = (float) $matches[2];
        }

        // Fallback: calculate score based on demand if not extracted
        if ($score === 50 && $vinyl->demand_ratio > 0) {
            $score = $this->calculateAlgorithmicScore($vinyl);
        }

        // Determine recommendation based on score if not extracted
        if ($recommendation === 'HOLD' && $score !== 50) {
            if ($score >= 70) {
                $recommendation = 'BUY';
            } elseif ($score < 40) {
                $recommendation = 'AVOID';
            }
        }

        return [
            'score' => $score,
            'recommendation' => $recommendation,
            'price_min' => $priceMin,
            'price_max' => $priceMax,
        ];
    }

    protected function calculateAlgorithmicScore(DiscogsAnalysis $vinyl): int
    {
        $score = 50;

        // Demand factor (+/- 25)
        if ($vinyl->demand_ratio >= 2) {
            $score += 25;
        } elseif ($vinyl->demand_ratio >= 1) {
            $score += 15;
        } elseif ($vinyl->demand_ratio >= 0.5) {
            $score += 5;
        } elseif ($vinyl->demand_ratio < 0.2) {
            $score -= 10;
        }

        // Rarity factor (+/- 15)
        if ($vinyl->have < 50) {
            $score += 15;
        } elseif ($vinyl->have < 200) {
            $score += 10;
        } elseif ($vinyl->have > 2000) {
            $score -= 5;
        }

        // Availability factor (+/- 10)
        if ($vinyl->num_for_sale == 0) {
            $score += 10;
        } elseif ($vinyl->num_for_sale <= 3) {
            $score += 5;
        }

        return max(0, min(100, $score));
    }
}
