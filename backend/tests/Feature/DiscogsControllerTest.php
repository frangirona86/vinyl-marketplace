<?php

namespace Tests\Feature;

use App\Models\DiscogsAnalysis;
use App\Services\DiscogsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DiscogsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function mockDiscogsService(): void
    {
        $mock = Mockery::mock(DiscogsService::class);
        
        $mock->shouldReceive("searchWithMarketData")
            ->andReturn([
                "results" => [
                    [
                        "id" => 123456,
                        "discogs_id" => 123456,
                        "title" => "Test Album - Artist",
                        "year" => 1999,
                        "genre" => "Electronic",
                        "style" => "Techno",
                        "format" => "Vinyl",
                        "have" => 500,
                        "want" => 1000,
                        "for_sale" => 10,
                        "lowest_price" => 25.00,
                        "thumb" => "https://example.com/thumb.jpg",
                        "cover_image" => "https://example.com/cover.jpg",
                    ],
                ],
                "pagination" => [
                    "page" => 1,
                    "pages" => 1,
                    "items" => 1,
                ],
            ]);

        $this->app->instance(DiscogsService::class, $mock);
    }

    // ==================== Search Smart Tests ====================

    public function test_search_smart_requires_query(): void
    {
        $response = $this->getJson("/api/discogs/search-smart");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(["q"]);
    }

    public function test_search_smart_requires_min_length(): void
    {
        $response = $this->getJson("/api/discogs/search-smart?q=a");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(["q"]);
    }

    public function test_search_smart_returns_results_with_insights(): void
    {
        $this->mockDiscogsService();

        $response = $this->getJson("/api/discogs/search-smart?q=test+album");

        $response->assertStatus(200)
            ->assertJsonStructure([
                "pagination",
                "results" => [
                    "*" => [
                        "id",
                        "title",
                        "have",
                        "want",
                        "insights" => [
                            "tags",
                            "quick_score",
                            "demand_ratio",
                            "insight",
                            "recommendation",
                        ],
                    ],
                ],
            ]);
    }

    public function test_search_smart_includes_genre_tags(): void
    {
        $this->mockDiscogsService();

        $response = $this->getJson("/api/discogs/search-smart?q=test");

        $response->assertStatus(200);
        
        $data = $response->json();
        $tags = $data["results"][0]["insights"]["tags"];
        
        $genreTags = array_filter($tags, fn($t) => $t["type"] === "genre");
        $this->assertNotEmpty($genreTags);
    }

    public function test_search_smart_calculates_demand_ratio(): void
    {
        $this->mockDiscogsService();

        $response = $this->getJson("/api/discogs/search-smart?q=test");

        $response->assertStatus(200);
        
        $data = $response->json();
        $demandRatio = $data["results"][0]["insights"]["demand_ratio"];
        
        $this->assertEquals(2.0, $demandRatio);
    }

    public function test_search_smart_includes_images(): void
    {
        $this->mockDiscogsService();

        $response = $this->getJson("/api/discogs/search-smart?q=test");

        $response->assertStatus(200);

        $data = $response->json();
        $result = $data["results"][0];
        
        $this->assertArrayHasKey("thumb", $result);
        $this->assertArrayHasKey("cover_image", $result);
    }

    public function test_search_smart_includes_saved_analysis_if_exists(): void
    {
        DiscogsAnalysis::create([
            "discogs_id" => 123456,
            "title" => "Test Album",
            "ai_score" => 85,
            "ai_recommendation" => "BUY",
            "recommended_price_min" => 20.00,
            "recommended_price_max" => 35.00,
        ]);

        $this->mockDiscogsService();

        $response = $this->getJson("/api/discogs/search-smart?q=test");

        $response->assertStatus(200);
        
        $data = $response->json();
        $savedAnalysis = $data["results"][0]["saved_analysis"];
        
        $this->assertNotNull($savedAnalysis);
        $this->assertEquals(85, $savedAnalysis["ai_score"]);
    }

    // ==================== Filters Tests ====================

    public function test_filters_returns_genres(): void
    {
        DiscogsAnalysis::create([
            "discogs_id" => 1,
            "title" => "Album 1",
            "genres" => ["Electronic", "Ambient"],
        ]);

        $response = $this->getJson("/api/discogs/filters");

        $response->assertStatus(200)
            ->assertJsonStructure(["genres"]);
        
        $data = $response->json();
        $this->assertContains("Electronic", $data["genres"]);
    }

    public function test_filters_returns_styles(): void
    {
        DiscogsAnalysis::create([
            "discogs_id" => 3,
            "title" => "Album 3",
            "styles" => ["Techno", "House"],
        ]);

        $response = $this->getJson("/api/discogs/filters");

        $response->assertStatus(200)
            ->assertJsonStructure(["styles"]);
        
        $data = $response->json();
        $this->assertContains("Techno", $data["styles"]);
    }

    public function test_filters_returns_year_range(): void
    {
        DiscogsAnalysis::create([
            "discogs_id" => 6,
            "title" => "Old Album",
            "year" => 1985,
        ]);

        DiscogsAnalysis::create([
            "discogs_id" => 7,
            "title" => "New Album",
            "year" => 2023,
        ]);

        $response = $this->getJson("/api/discogs/filters");

        $response->assertStatus(200)
            ->assertJsonStructure([
                "years" => ["min", "max"],
            ]);
        
        $data = $response->json();
        $this->assertEquals(1985, $data["years"]["min"]);
        $this->assertEquals(2023, $data["years"]["max"]);
    }

    public function test_filters_returns_formats(): void
    {
        DiscogsAnalysis::create([
            "discogs_id" => 8,
            "title" => "Vinyl Album",
            "format" => "Vinyl",
        ]);

        $response = $this->getJson("/api/discogs/filters");

        $response->assertStatus(200)
            ->assertJsonStructure(["formats"]);
        
        $data = $response->json();
        $this->assertContains("Vinyl", $data["formats"]);
    }

    // ==================== Saved Analyses Tests ====================

    public function test_get_saved_returns_paginated_results(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            DiscogsAnalysis::create([
                "discogs_id" => $i,
                "title" => "Album {$i}",
            ]);
        }

        $response = $this->getJson("/api/discogs/saved");

        $response->assertStatus(200)
            ->assertJsonStructure([
                "data",
                "current_page",
                "per_page",
                "total",
                "last_page",
            ]);
        
        $this->assertCount(20, $response->json('data')); // Default per_page is 20
        $this->assertEquals(25, $response->json('total'));
    }

    public function test_remove_saved_deletes_analysis(): void
    {
        $analysis = DiscogsAnalysis::create([
            "discogs_id" => 200,
            "title" => "To Delete",
        ]);

        $response = $this->deleteJson("/api/discogs/saved/{$analysis->discogs_id}");

        $response->assertStatus(200)
            ->assertJson(["message" => "Removed from analysis database"]);

        $this->assertDatabaseMissing("discogs_analyses", [
            "discogs_id" => $analysis->discogs_id,
        ]);
    }

    public function test_saved_stats_returns_correct_counts(): void
    {
        DiscogsAnalysis::create([
            "discogs_id" => 300,
            "title" => "Rare Album",
            "is_rare" => true,
            "is_in_demand" => true,
            "is_watchlist" => true,
        ]);

        DiscogsAnalysis::create([
            "discogs_id" => 301,
            "title" => "Common Album",
            "is_rare" => false,
            "is_in_demand" => false,
            "is_watchlist" => false,
        ]);

        $response = $this->getJson("/api/discogs/saved/stats");

        $response->assertStatus(200)
            ->assertJsonStructure([
                "data" => [
                    "total",
                    "watchlist",
                    "rare",
                    "in_demand",
                ],
            ]);

        $data = $response->json();
        $this->assertEquals(2, $data["data"]["total"]);
        $this->assertEquals(1, $data["data"]["rare"]);
    }
}
