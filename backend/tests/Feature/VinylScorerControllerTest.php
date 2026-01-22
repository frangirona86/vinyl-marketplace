<?php

namespace Tests\Feature;

use App\Models\DiscogsAnalysis;
use App\Services\DiscogsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class VinylScorerControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function mockDiscogsService(array $analysisData = null): void
    {
        $mock = Mockery::mock(DiscogsService::class);
        
        $defaultData = [
            "release" => [
                "discogs_id" => 123456,
                "title" => "Test Album",
                "artist_name" => "Test Artist",
                "year" => 1999,
                "country" => "UK",
                "label" => "Test Label",
                "genres" => ["Electronic"],
                "styles" => ["Techno"],
                "formats" => [["name" => "Vinyl"]],
                "images" => [
                    ["uri" => "https://example.com/cover.jpg", "uri150" => "https://example.com/thumb.jpg"]
                ],
            ],
            "community" => [
                "have" => 500,
                "want" => 1000,
                "rating_average" => 4.5,
                "rating_count" => 100,
            ],
            "marketplace" => [
                "stats" => [
                    "lowest_price" => ["value" => 25.00, "currency" => "USD"],
                ],
                "total_listings" => 10,
                "price_suggestions" => [],
            ],
            "analysis" => [
                "demand_ratio" => 2.0,
                "is_rare" => true,
                "is_in_demand" => true,
            ],
        ];

        $mock->shouldReceive("getCompleteAnalysis")
            ->andReturn($analysisData ?? $defaultData);

        $this->app->instance(DiscogsService::class, $mock);
    }

    public function test_quick_score_returns_algorithmic_score(): void
    {
        $this->mockDiscogsService();

        $response = $this->getJson("/api/vinyl-scorer/quick/123456");

        $response->assertStatus(200)
            ->assertJsonStructure([
                "source",
                "discogs_id",
                "release",
                "score",
                "recommendation",
                "breakdown",
            ])
            ->assertJson([
                "source" => "algorithmic",
                "discogs_id" => 123456,
            ]);
    }

    public function test_quick_score_returns_valid_score_range(): void
    {
        $this->mockDiscogsService();

        $response = $this->getJson("/api/vinyl-scorer/quick/123456");

        $response->assertStatus(200);
        
        $data = $response->json();
        $this->assertGreaterThanOrEqual(0, $data["score"]);
        $this->assertLessThanOrEqual(100, $data["score"]);
    }

    public function test_quick_score_returns_valid_recommendation(): void
    {
        $this->mockDiscogsService();

        $response = $this->getJson("/api/vinyl-scorer/quick/123456");

        $response->assertStatus(200);
        
        $data = $response->json();
        $this->assertContains($data["recommendation"], ["BUY", "HOLD", "AVOID"]);
    }

    public function test_quick_score_returns_404_for_not_found(): void
    {
        $mock = Mockery::mock(DiscogsService::class);
        $mock->shouldReceive("getCompleteAnalysis")->andReturn(null);
        $this->app->instance(DiscogsService::class, $mock);

        $response = $this->getJson("/api/vinyl-scorer/quick/999999");

        $response->assertStatus(404)
            ->assertJson(["error" => "Release not found"]);
    }

    public function test_batch_score_validates_input(): void
    {
        $response = $this->postJson("/api/vinyl-scorer/batch", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(["discogs_ids"]);
    }

    public function test_batch_score_returns_multiple_results(): void
    {
        $this->mockDiscogsService();

        $response = $this->postJson("/api/vinyl-scorer/batch", [
            "discogs_ids" => [123456, 123457],
        ]);

        $response->assertStatus(200);
        
        $data = $response->json();
        $this->assertCount(2, $data);
    }

    public function test_refresh_returns_404_for_nonexistent_record(): void
    {
        $response = $this->postJson("/api/vinyl-scorer/refresh/999999");

        $response->assertStatus(404)
            ->assertJsonStructure(["error", "discogs_id"]);
    }

    public function test_refresh_updates_existing_record(): void
    {
        $this->mockDiscogsService();

        DiscogsAnalysis::create([
            "discogs_id" => 123456,
            "title" => "Old Title",
            "have" => 100,
            "want" => 200,
            "lowest_price" => 20.00,
        ]);

        $response = $this->postJson("/api/vinyl-scorer/refresh/123456");

        $response->assertStatus(200)
            ->assertJsonStructure([
                "message",
                "discogs_id",
                "score",
                "recommendation",
                "changes",
                "is_trending",
            ]);
    }

    public function test_refresh_saves_previous_values(): void
    {
        $this->mockDiscogsService();

        DiscogsAnalysis::create([
            "discogs_id" => 123456,
            "title" => "Test Album",
            "have" => 100,
            "want" => 200,
            "lowest_price" => 20.00,
        ]);

        $this->postJson("/api/vinyl-scorer/refresh/123456");

        $record = DiscogsAnalysis::where("discogs_id", 123456)->first();
        
        $this->assertEquals(100, $record->previous_have);
        $this->assertEquals(200, $record->previous_want);
        $this->assertEquals(20.00, $record->previous_lowest_price);
    }

    public function test_refresh_detects_trending(): void
    {
        $this->mockDiscogsService();

        DiscogsAnalysis::create([
            "discogs_id" => 123456,
            "title" => "Test Album",
            "have" => 100,
            "want" => 100,
        ]);

        $response = $this->postJson("/api/vinyl-scorer/refresh/123456");

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertTrue($data["is_trending"]);
    }

    public function test_trending_endpoint_returns_trending_items(): void
    {
        DiscogsAnalysis::create([
            "discogs_id" => 1,
            "title" => "Trending Album",
            "want" => 220,
            "previous_want" => 100,
            "ai_score" => 75,
            "ai_recommendation" => "BUY",
        ]);

        DiscogsAnalysis::create([
            "discogs_id" => 2,
            "title" => "Stable Album",
            "want" => 105,
            "previous_want" => 100,
        ]);

        $response = $this->getJson("/api/vinyl-scorer/trending");

        $response->assertStatus(200)
            ->assertJsonStructure([
                "count",
                "items",
            ]);

        $data = $response->json();
        $this->assertEquals(1, $data["count"]);
    }

    public function test_analyze_validates_discogs_id(): void
    {
        $response = $this->postJson("/api/vinyl-scorer/analyze", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(["discogs_id"]);
    }

    public function test_analyze_falls_back_to_algorithmic_without_openai_key(): void
    {
        config(["services.openai.key" => null]);
        
        $this->mockDiscogsService();

        $response = $this->postJson("/api/vinyl-scorer/analyze", [
            "discogs_id" => 123456,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                "source" => "algorithmic",
            ]);
    }

    public function test_quick_score_includes_release_info(): void
    {
        $this->mockDiscogsService();

        $response = $this->getJson("/api/vinyl-scorer/quick/123456");

        $response->assertStatus(200);
        
        $data = $response->json();
        $this->assertArrayHasKey("release", $data);
        $this->assertEquals("Test Album", $data["release"]["title"]);
    }

    public function test_quick_score_includes_breakdown(): void
    {
        $this->mockDiscogsService();

        $response = $this->getJson("/api/vinyl-scorer/quick/123456");

        $response->assertStatus(200)
            ->assertJsonStructure([
                "breakdown" => [
                    "demand_score",
                    "rarity_score",
                    "price_score",
                    "rating_score",
                ],
            ]);
    }

    public function test_quick_score_includes_raw_metrics(): void
    {
        $this->mockDiscogsService();

        $response = $this->getJson("/api/vinyl-scorer/quick/123456");

        $response->assertStatus(200)
            ->assertJsonStructure([
                "raw_metrics" => [
                    "have",
                    "want",
                    "demand_ratio",
                ],
            ]);
        
        $data = $response->json();
        $this->assertEquals(500, $data["raw_metrics"]["have"]);
        $this->assertEquals(1000, $data["raw_metrics"]["want"]);
    }
}
