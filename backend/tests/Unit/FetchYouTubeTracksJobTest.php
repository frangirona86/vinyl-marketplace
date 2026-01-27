<?php

namespace Tests\Unit;

use App\Jobs\FetchYouTubeTracksJob;
use App\Models\DiscogsAnalysis;
use App\Services\YouTubeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class FetchYouTubeTracksJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_skips_nonexistent_vinyl(): void
    {
        $youtube = Mockery::mock(YouTubeService::class);
        $youtube->shouldNotReceive('searchTracks');

        $job = new FetchYouTubeTracksJob(99999);
        $job->handle($youtube);
    }

    public function test_job_skips_recently_fetched_vinyl(): void
    {
        $vinyl = DiscogsAnalysis::factory()->create([
            'youtube_fetched_at' => now()->subDays(3), // Less than 7 days ago
        ]);

        $youtube = Mockery::mock(YouTubeService::class);
        $youtube->shouldNotReceive('searchTracks');

        $job = new FetchYouTubeTracksJob($vinyl->id);
        $job->handle($youtube);
    }

    public function test_job_sets_has_youtube_false_for_unknown_vinyl(): void
    {
        $vinyl = DiscogsAnalysis::factory()->create([
            'artist_name' => 'Unknown',
            'title' => 'Unknown',
            'youtube_fetched_at' => null,
        ]);

        $youtube = Mockery::mock(YouTubeService::class);
        $youtube->shouldReceive('isConfigured')->andReturn(true);

        $job = new FetchYouTubeTracksJob($vinyl->id);
        $job->handle($youtube);

        $vinyl->refresh();
        
        $this->assertFalse($vinyl->has_youtube);
        $this->assertEmpty($vinyl->youtube_tracks);
        $this->assertNotNull($vinyl->youtube_fetched_at);
    }

    public function test_job_saves_found_tracks(): void
    {
        $vinyl = DiscogsAnalysis::factory()->create([
            'artist_name' => 'Test Artist',
            'title' => 'Test Album',
            'youtube_fetched_at' => null,
        ]);

        $mockTracks = [
            [
                'video_id' => 'abc123',
                'title' => 'Test Video',
                'relevance' => 80,
            ],
        ];

        $youtube = Mockery::mock(YouTubeService::class);
        $youtube->shouldReceive('isConfigured')->andReturn(true);
        $youtube->shouldReceive('searchTracks')
            ->with('Test Artist', 'Test Album', $vinyl->year, 3)
            ->andReturn($mockTracks);

        $job = new FetchYouTubeTracksJob($vinyl->id);
        $job->handle($youtube);

        $vinyl->refresh();
        
        $this->assertTrue($vinyl->has_youtube);
        $this->assertCount(1, $vinyl->youtube_tracks);
        $this->assertNotNull($vinyl->youtube_fetched_at);
    }

    public function test_job_filters_low_relevance_tracks(): void
    {
        $vinyl = DiscogsAnalysis::factory()->create([
            'artist_name' => 'Test Artist',
            'title' => 'Test Album',
            'youtube_fetched_at' => null,
        ]);

        $mockTracks = [
            ['video_id' => 'abc', 'relevance' => 80],
            ['video_id' => 'def', 'relevance' => 20], // Below threshold
            ['video_id' => 'ghi', 'relevance' => 50],
        ];

        $youtube = Mockery::mock(YouTubeService::class);
        $youtube->shouldReceive('isConfigured')->andReturn(true);
        $youtube->shouldReceive('searchTracks')->andReturn($mockTracks);

        $job = new FetchYouTubeTracksJob($vinyl->id);
        $job->handle($youtube);

        $vinyl->refresh();
        
        // Only tracks with relevance >= 30 should be saved
        $this->assertCount(2, $vinyl->youtube_tracks);
    }

    public function test_job_skips_if_youtube_not_configured(): void
    {
        $vinyl = DiscogsAnalysis::factory()->create([
            'youtube_fetched_at' => null,
        ]);

        $youtube = Mockery::mock(YouTubeService::class);
        $youtube->shouldReceive('isConfigured')->andReturn(false);
        $youtube->shouldNotReceive('searchTracks');

        $job = new FetchYouTubeTracksJob($vinyl->id);
        $job->handle($youtube);

        $vinyl->refresh();
        $this->assertNull($vinyl->youtube_fetched_at);
    }

    public function test_job_has_correct_retry_settings(): void
    {
        $job = new FetchYouTubeTracksJob(1);

        $this->assertEquals(2, $job->tries);
        $this->assertEquals(30, $job->backoff);
        $this->assertEquals(60, $job->timeout);
    }
}
