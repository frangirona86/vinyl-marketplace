<?php

namespace App\Jobs;

use App\Models\DiscogsAnalysis;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnalyzeVinylJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60; // Retry after 60 seconds
    public int $timeout = 120;

    public function __construct(
        public int $vinylId
    ) {}

    public function handle(): void
    {
        $vinyl = DiscogsAnalysis::find($this->vinylId);
        
        if (!$vinyl) {
            Log::warning("AnalyzeVinylJob: Vinyl {$this->vinylId} not found");
            return;
        }

        // Skip if already analyzed
        if ($vinyl->ai_score && $vinyl->ai_analysis) {
            return;
        }

        $apiKey = config('services.openai.key');
        
        if (!$apiKey) {
            Log::warning('AnalyzeVinylJob: OpenAI API key not configured');
            return;
        }

        try {
            $prompt = $this->buildPrompt($vinyl);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('services.openai.model', 'gpt-4o-mini'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an expert vinyl record analyst. Analyze vinyls for investment potential. Be concise. Always provide SCORE (0-100), RECOMMENDATION (BUY/HOLD/AVOID), and PRICE RANGE.'
                    ],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'max_tokens' => 300,
                'temperature' => 0.7,
            ]);

            if (!$response->successful()) {
                throw new \Exception('OpenAI API error: ' . $response->status());
            }

            $analysis = $response->json()['choices'][0]['message']['content'] ?? null;

            if ($analysis) {
                $extracted = $this->extractFromAnalysis($analysis, $vinyl);
                
                $vinyl->update([
                    'ai_score' => $extracted['score'],
                    'ai_recommendation' => $extracted['recommendation'],
                    'ai_analysis' => $analysis,
                    'recommended_price_min' => $extracted['price_min'],
                    'recommended_price_max' => $extracted['price_max'],
                    'last_refreshed_at' => now(),
                ]);

                Log::info("AnalyzeVinylJob: Analyzed vinyl {$vinyl->discogs_id} - Score: {$extracted['score']}");
            }

        } catch (\Exception $e) {
            Log::error("AnalyzeVinylJob: Error analyzing vinyl {$this->vinylId}: " . $e->getMessage());
            throw $e; // Re-throw to trigger retry
        }
    }

    protected function buildPrompt(DiscogsAnalysis $vinyl): string
    {
        $styles = is_array($vinyl->styles) ? implode(', ', $vinyl->styles) : ($vinyl->styles ?? 'Unknown');
        $genres = is_array($vinyl->genres) ? implode(', ', $vinyl->genres) : ($vinyl->genres ?? 'Unknown');
        
        return "Analyze this vinyl for investment:

Artist: {$vinyl->artist_name}
Title: {$vinyl->title}
Year: {$vinyl->year}
Genre: {$genres}
Style: {$styles}
Have: {$vinyl->have} | Want: {$vinyl->want}
Demand ratio: {$vinyl->demand_ratio}
For sale: {$vinyl->num_for_sale}
Lowest price: " . ($vinyl->lowest_price ? "\${$vinyl->lowest_price}" : "N/A") . "

Provide: SCORE (0-100), RECOMMENDATION (BUY/HOLD/AVOID), PRICE RANGE (\$min - \$max), brief ANALYSIS.";
    }

    protected function extractFromAnalysis(string $analysis, DiscogsAnalysis $vinyl): array
    {
        $score = 50;
        $recommendation = 'HOLD';
        $priceMin = null;
        $priceMax = null;

        if (preg_match('/SCORE[:\s]*(\d+)/i', $analysis, $matches)) {
            $score = min(100, max(0, (int) $matches[1]));
        }

        if (preg_match('/RECOMMENDATION[:\s]*(BUY|HOLD|AVOID)/i', $analysis, $matches)) {
            $recommendation = strtoupper($matches[1]);
        }

        if (preg_match('/\$(\d+(?:\.\d{2})?)\s*[-–]\s*\$(\d+(?:\.\d{2})?)/i', $analysis, $matches)) {
            $priceMin = (float) $matches[1];
            $priceMax = (float) $matches[2];
        }

        return [
            'score' => $score,
            'recommendation' => $recommendation,
            'price_min' => $priceMin,
            'price_max' => $priceMax,
        ];
    }
}
