<?php

namespace Tests\Unit;

use App\Jobs\AnalyzeVinylJob;
use App\Models\DiscogsAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnalyzeVinylJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_skips_already_analyzed_vinyl(): void
    {
        $vinyl = DiscogsAnalysis::factory()->create([
            'ai_score' => 75,
            'ai_analysis' => 'Already analyzed',
        ]);

        Http::fake();

        $job = new AnalyzeVinylJob($vinyl->id);
        $job->handle();

        // Should not make any HTTP requests
        Http::assertNothingSent();
    }

    public function test_job_skips_nonexistent_vinyl(): void
    {
        Http::fake();

        $job = new AnalyzeVinylJob(99999);
        $job->handle();

        Http::assertNothingSent();
    }

    public function test_job_extracts_score_from_analysis(): void
    {
        $vinyl = DiscogsAnalysis::factory()->create([
            'ai_score' => null,
            'ai_analysis' => null,
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => "SCORE: 85\nRECOMMENDATION: BUY\nPRICE RANGE: \$50 - \$100\nANALYSIS: Great vinyl."
                        ]
                    ]
                ]
            ], 200),
        ]);

        config(['services.openai.key' => 'test-key']);

        $job = new AnalyzeVinylJob($vinyl->id);
        $job->handle();

        $vinyl->refresh();
        
        $this->assertEquals(85, $vinyl->ai_score);
        $this->assertEquals('BUY', $vinyl->ai_recommendation);
        $this->assertEquals(50, $vinyl->recommended_price_min);
        $this->assertEquals(100, $vinyl->recommended_price_max);
    }

    public function test_job_handles_missing_openai_key(): void
    {
        $vinyl = DiscogsAnalysis::factory()->create([
            'ai_score' => null,
        ]);

        config(['services.openai.key' => null]);

        Http::fake();

        $job = new AnalyzeVinylJob($vinyl->id);
        $job->handle();

        Http::assertNothingSent();
        
        $vinyl->refresh();
        $this->assertNull($vinyl->ai_score);
    }

    public function test_job_has_correct_retry_settings(): void
    {
        $job = new AnalyzeVinylJob(1);

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(60, $job->backoff);
        $this->assertEquals(120, $job->timeout);
    }
}
