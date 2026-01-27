<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeVinylJob;
use App\Jobs\BatchAnalyzeVinylsJob;
use App\Jobs\FetchYouTubeTracksJob;
use App\Jobs\ImportVinylFromDiscogsJob;
use App\Models\DiscogsAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueueControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_analyze_vinyl_queues_job(): void
    {
        $vinyl = DiscogsAnalysis::factory()->create();

        $response = $this->postJson("/api/queue/analyze/{$vinyl->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Analysis queued successfully',
                'vinyl_id' => $vinyl->id,
            ]);

        Queue::assertPushed(AnalyzeVinylJob::class, function ($job) use ($vinyl) {
            return $job->vinylId === $vinyl->id;
        });
    }

    public function test_analyze_vinyl_returns_404_for_nonexistent(): void
    {
        $response = $this->postJson("/api/queue/analyze/99999");

        $response->assertStatus(404);
    }

    public function test_analyze_batch_queues_job(): void
    {
        $response = $this->postJson("/api/queue/analyze-batch", [
            'limit' => 25,
            'include_youtube' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Batch analysis queued',
                'limit' => 25,
                'include_youtube' => true,
            ]);

        Queue::assertPushed(BatchAnalyzeVinylsJob::class);
    }

    public function test_analyze_batch_uses_defaults(): void
    {
        $response = $this->postJson("/api/queue/analyze-batch");

        $response->assertStatus(200)
            ->assertJson([
                'limit' => 50,
                'include_youtube' => true,
            ]);
    }

    public function test_fetch_youtube_queues_job(): void
    {
        $vinyl = DiscogsAnalysis::factory()->create();

        $response = $this->postJson("/api/queue/youtube/{$vinyl->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'YouTube fetch queued',
                'vinyl_id' => $vinyl->id,
            ]);

        Queue::assertPushed(FetchYouTubeTracksJob::class, function ($job) use ($vinyl) {
            return $job->vinylId === $vinyl->id;
        });
    }

    public function test_fetch_youtube_returns_404_for_nonexistent(): void
    {
        $response = $this->postJson("/api/queue/youtube/99999");

        $response->assertStatus(404);
    }

    public function test_import_from_discogs_queues_job(): void
    {
        $response = $this->postJson("/api/queue/import", [
            'discogs_id' => 12345,
            'analyze' => true,
            'youtube' => false,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Import queued',
                'discogs_id' => 12345,
                'will_analyze' => true,
                'will_fetch_youtube' => false,
            ]);

        Queue::assertPushed(ImportVinylFromDiscogsJob::class, function ($job) {
            return $job->discogsId === 12345 
                && $job->analyzeAfter === true 
                && $job->fetchYouTube === false;
        });
    }

    public function test_import_validates_discogs_id(): void
    {
        $response = $this->postJson("/api/queue/import", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['discogs_id']);
    }

    public function test_stats_returns_counts(): void
    {
        DiscogsAnalysis::factory()->count(3)->create(['ai_score' => null]);
        DiscogsAnalysis::factory()->count(2)->create(['ai_score' => 75]);
        DiscogsAnalysis::factory()->count(1)->create(['has_youtube' => true]);

        $response = $this->getJson("/api/queue/stats");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'pending_analysis',
                    'pending_youtube',
                    'total_vinyls',
                    'analyzed',
                    'with_youtube',
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals(6, $data['total_vinyls']);
        $this->assertEquals(2, $data['analyzed']);
    }
}
