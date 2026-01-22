<?php

namespace Tests\Unit;

use App\Models\DiscogsAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscogsAnalysisTest extends TestCase
{
    use RefreshDatabase;

    // ==================== Scope Tests ====================

    public function test_scope_by_genre(): void
    {
        DiscogsAnalysis::create([
            "discogs_id" => 1,
            "title" => "Electronic Album",
            "genres" => ["Electronic", "Ambient"],
        ]);

        DiscogsAnalysis::create([
            "discogs_id" => 2,
            "title" => "Rock Album",
            "genres" => ["Rock"],
        ]);

        $results = DiscogsAnalysis::byGenre("Electronic")->get();
        
        $this->assertCount(1, $results);
        $this->assertEquals("Electronic Album", $results->first()->title);
    }

    public function test_scope_by_style(): void
    {
        DiscogsAnalysis::create([
            "discogs_id" => 1,
            "title" => "Techno Album",
            "styles" => ["Techno", "Minimal"],
        ]);

        DiscogsAnalysis::create([
            "discogs_id" => 2,
            "title" => "House Album",
            "styles" => ["House"],
        ]);

        $results = DiscogsAnalysis::byStyle("Techno")->get();
        
        $this->assertCount(1, $results);
        $this->assertEquals("Techno Album", $results->first()->title);
    }

    public function test_scope_by_year(): void
    {
        DiscogsAnalysis::create(["discogs_id" => 1, "title" => "1995 Album", "year" => 1995]);
        DiscogsAnalysis::create(["discogs_id" => 2, "title" => "2000 Album", "year" => 2000]);

        $results = DiscogsAnalysis::byYear(1995)->get();
        
        $this->assertCount(1, $results);
        $this->assertEquals("1995 Album", $results->first()->title);
    }

    public function test_scope_by_year_range(): void
    {
        DiscogsAnalysis::create(["discogs_id" => 1, "title" => "1990 Album", "year" => 1990]);
        DiscogsAnalysis::create(["discogs_id" => 2, "title" => "1995 Album", "year" => 1995]);
        DiscogsAnalysis::create(["discogs_id" => 3, "title" => "2005 Album", "year" => 2005]);

        $results = DiscogsAnalysis::byYearRange(1990, 2000)->get();
        
        $this->assertCount(2, $results);
    }

    public function test_scope_high_scoring(): void
    {
        DiscogsAnalysis::create(["discogs_id" => 1, "title" => "High Score", "ai_score" => 85]);
        DiscogsAnalysis::create(["discogs_id" => 2, "title" => "Low Score", "ai_score" => 40]);

        $results = DiscogsAnalysis::highScoring(70)->get();
        
        $this->assertCount(1, $results);
        $this->assertEquals("High Score", $results->first()->title);
    }

    public function test_scope_recommended_buy(): void
    {
        DiscogsAnalysis::create(["discogs_id" => 1, "title" => "Buy This", "ai_recommendation" => "BUY"]);
        DiscogsAnalysis::create(["discogs_id" => 2, "title" => "Hold This", "ai_recommendation" => "HOLD"]);

        $results = DiscogsAnalysis::recommendedBuy()->get();
        
        $this->assertCount(1, $results);
        $this->assertEquals("Buy This", $results->first()->title);
    }

    public function test_scope_trending(): void
    {
        // Trending: want increased >10%
        DiscogsAnalysis::create([
            "discogs_id" => 1,
            "title" => "Trending",
            "want" => 150, "have" => 100,
            "previous_want" => 100, "previous_have" => 80,
        ]);

        // Not trending: want increased <10%
        DiscogsAnalysis::create([
            "discogs_id" => 2,
            "title" => "Stable",
            "want" => 105,
            "previous_want" => 100, "previous_have" => 80,
        ]);

        $results = DiscogsAnalysis::trending()->get();
        
        $this->assertCount(1, $results);
        $this->assertEquals("Trending", $results->first()->title);
    }

    // ==================== Method Tests ====================

    public function test_is_trending_returns_true_when_want_increased(): void
    {
        $analysis = new DiscogsAnalysis([
            "want" => 150, "have" => 100,
            "previous_want" => 100, "previous_have" => 80,
        ]);

        $this->assertTrue($analysis->isTrending());
    }

    public function test_is_trending_returns_false_when_no_previous_data(): void
    {
        $analysis = new DiscogsAnalysis([
            "want" => 150, "have" => 100,
            "previous_want" => null,
        ]);

        $this->assertFalse($analysis->isTrending());
    }

    public function test_calculate_demand_ratio(): void
    {
        $analysis = new DiscogsAnalysis([
            "have" => 100,
            "want" => 200,
        ]);

        $this->assertEquals(2.0, $analysis->calculateDemandRatio());
    }

    public function test_calculate_demand_ratio_handles_zero_have(): void
    {
        $analysis = new DiscogsAnalysis([
            "have" => 0,
            "want" => 100,
        ]);

        $this->assertEquals(999.99, $analysis->calculateDemandRatio());
    }

    public function test_needs_refresh_returns_true_for_old_data(): void
    {
        $analysis = new DiscogsAnalysis([
            "fetched_at" => now()->subHours(25),
        ]);

        $this->assertTrue($analysis->needsRefresh());
    }

    public function test_needs_refresh_returns_false_for_recent_data(): void
    {
        $analysis = new DiscogsAnalysis([
            "fetched_at" => now()->subHours(12),
        ]);

        $this->assertFalse($analysis->needsRefresh());
    }

    // ==================== Attribute Tests ====================

    public function test_image_attribute_returns_thumb_first(): void
    {
        $analysis = new DiscogsAnalysis([
            "thumb" => "thumb.jpg",
            "cover_image" => "cover.jpg",
        ]);

        $this->assertEquals("thumb.jpg", $analysis->image);
    }

    public function test_image_large_attribute_returns_cover_first(): void
    {
        $analysis = new DiscogsAnalysis([
            "thumb" => "thumb.jpg",
            "cover_image" => "cover.jpg",
        ]);

        $this->assertEquals("cover.jpg", $analysis->image_large);
    }

    public function test_has_image_returns_true_when_has_thumb(): void
    {
        $analysis = new DiscogsAnalysis([
            "thumb" => "thumb.jpg",
        ]);

        $this->assertTrue($analysis->hasImage());
    }

    public function test_has_image_returns_false_when_no_images(): void
    {
        $analysis = new DiscogsAnalysis([]);

        $this->assertFalse($analysis->hasImage());
    }

    public function test_formatted_price_attribute(): void
    {
        $analysis = new DiscogsAnalysis([
            "lowest_price" => 25.50,
            "lowest_price_currency" => "EUR",
        ]);

        $this->assertEquals("25.50 EUR", $analysis->formatted_price);
    }

    public function test_demand_status_attribute(): void
    {
        $veryHigh = new DiscogsAnalysis(["demand_ratio" => 2.5]);
        $high = new DiscogsAnalysis(["demand_ratio" => 1.5]);
        $moderate = new DiscogsAnalysis(["demand_ratio" => 0.7]);
        $low = new DiscogsAnalysis(["demand_ratio" => 0.2]);

        $this->assertEquals("Very High Demand", $veryHigh->demand_status);
        $this->assertEquals("High Demand", $high->demand_status);
        $this->assertEquals("Moderate Demand", $moderate->demand_status);
        $this->assertEquals("Low Demand", $low->demand_status);
    }

    public function test_changes_attribute(): void
    {
        $analysis = new DiscogsAnalysis([
            "have" => 150,
            "want" => 300,
            "lowest_price" => 30.00,
            "previous_have" => 100,
            "previous_want" => 200,
            "previous_lowest_price" => 25.00,
        ]);

        $changes = $analysis->changes;

        $this->assertEquals(50, $changes["have_change"]);
        $this->assertEquals(100, $changes["want_change"]);
        $this->assertEquals(5.00, $changes["price_change"]);
    }

    // ==================== JSON Cast Tests ====================

    public function test_genres_are_cast_to_array(): void
    {
        $analysis = DiscogsAnalysis::create([
            "discogs_id" => 1,
            "title" => "Test",
            "genres" => ["Electronic", "Ambient"],
        ]);

        $this->assertIsArray($analysis->genres);
        $this->assertContains("Electronic", $analysis->genres);
    }

    public function test_styles_are_cast_to_array(): void
    {
        $analysis = DiscogsAnalysis::create([
            "discogs_id" => 1,
            "title" => "Test",
            "styles" => ["Techno", "House"],
        ]);

        $this->assertIsArray($analysis->styles);
        $this->assertContains("Techno", $analysis->styles);
    }
}
