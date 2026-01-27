<?php

namespace Tests\Feature;

use App\Models\DiscogsAnalysis;
use App\Models\SearchHistory;
use App\Services\DiscogsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class SearchControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function mockDiscogsService(): void
    {
        $mock = Mockery::mock(DiscogsService::class);
        
        $mock->shouldReceive('searchWithMarketData')
            ->andReturn([
                'results' => [
                    [
                        'id' => 123456,
                        'title' => 'Test Album',
                        'artist' => 'Test Artist',
                        'year' => 1999,
                        'genre' => 'Electronic',
                        'have' => 500,
                        'want' => 1000,
                        'for_sale' => 10,
                        'lowest_price' => 25.00,
                        'thumb' => 'https://example.com/thumb.jpg',
                    ],
                ],
            ]);

        $this->app->instance(DiscogsService::class, $mock);
    }

    protected function createTestVinyls(): void
    {
        DiscogsAnalysis::create([
            'discogs_id' => 1,
            'title' => 'Abbey Road',
            'artist_name' => 'The Beatles',
            'year' => 1969,
            'genres' => ['Rock'],
            'label' => 'Apple Records',
            'have' => 5000,
            'want' => 8000,
            'demand_ratio' => 1.6,
            'lowest_price' => 30.00,
            'is_rare' => false,
        ]);

        DiscogsAnalysis::create([
            'discogs_id' => 2,
            'title' => 'Kind of Blue',
            'artist_name' => 'Miles Davis',
            'year' => 1959,
            'genres' => ['Jazz'],
            'label' => 'Columbia',
            'have' => 3000,
            'want' => 6000,
            'demand_ratio' => 2.0,
            'lowest_price' => 45.00,
            'is_rare' => true,
        ]);

        DiscogsAnalysis::create([
            'discogs_id' => 3,
            'title' => 'Selected Ambient Works',
            'artist_name' => 'Aphex Twin',
            'year' => 1992,
            'genres' => ['Electronic'],
            'label' => 'Apollo',
            'have' => 200,
            'want' => 800,
            'demand_ratio' => 4.0,
            'lowest_price' => 120.00,
            'is_rare' => true,
        ]);
    }

    // ==================== Suggest Endpoint Tests ====================

    public function test_suggest_requires_query(): void
    {
        $response = $this->getJson('/api/search/suggest');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    public function test_suggest_returns_categorized_suggestions(): void
    {
        $this->createTestVinyls();

        $response = $this->getJson('/api/search/suggest?q=beatles');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'query',
                'suggestions' => [
                    'artists',
                    'genres',
                    'labels',
                    'vinyls',
                ],
                'recent',
            ]);
    }

    public function test_suggest_finds_artists(): void
    {
        $this->createTestVinyls();

        $response = $this->getJson('/api/search/suggest?q=beatles');

        $response->assertStatus(200);
        
        $data = $response->json();
        $artists = $data['suggestions']['artists'];
        
        $this->assertNotEmpty($artists);
        $this->assertEquals('The Beatles', $artists[0]['name']);
    }

    public function test_suggest_finds_vinyls_by_title(): void
    {
        $this->createTestVinyls();

        $response = $this->getJson('/api/search/suggest?q=abbey');

        $response->assertStatus(200);
        
        $data = $response->json();
        $vinyls = $data['suggestions']['vinyls'];
        
        $this->assertNotEmpty($vinyls);
        $this->assertEquals('Abbey Road', $vinyls[0]['title']);
    }

    public function test_suggest_is_cached(): void
    {
        $this->createTestVinyls();

        // First request
        $response1 = $this->getJson('/api/search/suggest?q=beatles');
        $response1->assertStatus(200);

        // Second request should be cached
        $response2 = $this->getJson('/api/search/suggest?q=beatles');
        $response2->assertStatus(200);

        // Results should be identical
        $this->assertEquals($response1->json(), $response2->json());
    }

    public function test_suggest_includes_unique_recent_searches(): void
    {
        $this->createTestVinyls();

        // Create search history with duplicates
        $first = SearchHistory::create([
            'session_id' => 'test-session',
            'query' => 'beatles',
            'type' => 'general',
            'results_count' => 10,
        ]);
        \DB::table('search_histories')
            ->where('id', $first->id)
            ->update(['created_at' => now()->subMinutes(5)]);

        $second = SearchHistory::create([
            'session_id' => 'test-session',
            'query' => 'beatles', // Duplicate
            'type' => 'general',
            'results_count' => 15,
        ]);
        \DB::table('search_histories')
            ->where('id', $second->id)
            ->update(['created_at' => now()->subMinutes(1)]);

        SearchHistory::create([
            'session_id' => 'test-session',
            'query' => 'rock',
            'type' => 'genre',
            'results_count' => 8,
        ]);

        $response = $this->getJson('/api/search/suggest?q=test', [
            'X-Session-ID' => 'test-session',
        ]);

        $response->assertStatus(200);
        
        $data = $response->json();
        
        // Recent searches should be unique
        $this->assertArrayHasKey('recent', $data);
        $recentQueries = array_column($data['recent'], 'query');
        $this->assertEquals(count($recentQueries), count(array_unique($recentQueries)));
    }

    // ==================== Search Endpoint Tests ====================

    public function test_search_requires_query(): void
    {
        $response = $this->getJson('/api/search');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    public function test_search_requires_min_length(): void
    {
        $response = $this->getJson('/api/search?q=a');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    public function test_search_returns_local_results(): void
    {
        $this->createTestVinyls();
        $this->mockDiscogsService();

        $response = $this->getJson('/api/search?q=beatles');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'query',
                'type',
                'results' => [
                    'local' => [
                        'data',
                        'total',
                        'per_page',
                        'current_page',
                    ],
                    'discogs',
                ],
                'total',
                'filters_applied',
            ]);
    }

    public function test_search_enriches_results_with_insights(): void
    {
        $this->createTestVinyls();
        $this->mockDiscogsService();

        $response = $this->getJson('/api/search?q=beatles');

        $response->assertStatus(200);
        
        $data = $response->json();
        $localResults = $data['results']['local']['data'];
        
        $this->assertNotEmpty($localResults);
        $this->assertArrayHasKey('insights', $localResults[0]);
        $this->assertArrayHasKey('tags', $localResults[0]['insights']);
        $this->assertArrayHasKey('quick_score', $localResults[0]['insights']);
    }

    public function test_search_filters_by_genre(): void
    {
        $this->createTestVinyls();
        $this->mockDiscogsService();

        $response = $this->getJson('/api/search?q=album&genre=Jazz');

        $response->assertStatus(200);
        
        $data = $response->json();
        $localResults = $data['results']['local']['data'];
        
        // Only Jazz albums should be returned
        foreach ($localResults as $result) {
            $this->assertContains('Jazz', $result['genres']);
        }
    }

    public function test_search_filters_by_year_range(): void
    {
        $this->createTestVinyls();
        $this->mockDiscogsService();

        $response = $this->getJson('/api/search?q=album&year_from=1960&year_to=1970');

        $response->assertStatus(200);
        
        $data = $response->json();
        $localResults = $data['results']['local']['data'];
        
        foreach ($localResults as $result) {
            $this->assertGreaterThanOrEqual(1960, $result['year']);
            $this->assertLessThanOrEqual(1970, $result['year']);
        }
    }

    public function test_search_filters_by_price(): void
    {
        $this->createTestVinyls();
        $this->mockDiscogsService();

        $response = $this->getJson('/api/search?q=album&price_max=50');

        $response->assertStatus(200);
        
        $data = $response->json();
        $localResults = $data['results']['local']['data'];
        
        foreach ($localResults as $result) {
            $this->assertLessThanOrEqual(50, $result['lowest_price']);
        }
    }

    public function test_search_sorts_by_demand(): void
    {
        $this->createTestVinyls();
        $this->mockDiscogsService();

        $response = $this->getJson('/api/search?q=album&sort=demand');

        $response->assertStatus(200);
        
        $data = $response->json();
        $localResults = $data['results']['local']['data'];
        
        // Results should be sorted by demand_ratio descending
        $lastRatio = PHP_INT_MAX;
        foreach ($localResults as $result) {
            $this->assertLessThanOrEqual($lastRatio, $result['demand_ratio']);
            $lastRatio = $result['demand_ratio'];
        }
    }

    public function test_search_records_history(): void
    {
        $this->createTestVinyls();
        $this->mockDiscogsService();

        $response = $this->getJson('/api/search?q=beatles', [
            'X-Session-ID' => 'test-session-123',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('search_histories', [
            'session_id' => 'test-session-123',
            'query' => 'beatles',
            'type' => 'all',
        ]);
    }

    // ==================== History Endpoint Tests ====================

    public function test_history_returns_session_searches(): void
    {
        SearchHistory::create([
            'session_id' => 'test-session',
            'query' => 'beatles',
            'type' => 'general',
            'results_count' => 10,
        ]);

        SearchHistory::create([
            'session_id' => 'test-session',
            'query' => 'jazz',
            'type' => 'genre',
            'results_count' => 5,
        ]);

        $response = $this->getJson('/api/search/history', [
            'X-Session-ID' => 'test-session',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'recent',
                'user_history',
                'popular',
                'trending',
            ]);
    }

    public function test_history_returns_unique_recent_searches(): void
    {
        // Create duplicate searches (same query, different times)
        $first = SearchHistory::create([
            'session_id' => 'test-session',
            'query' => 'beatles',
            'type' => 'general',
            'results_count' => 10,
        ]);
        \DB::table('search_histories')
            ->where('id', $first->id)
            ->update(['created_at' => now()->subMinutes(5)]);

        $second = SearchHistory::create([
            'session_id' => 'test-session',
            'query' => 'beatles', // Duplicate
            'type' => 'general',
            'results_count' => 15,
        ]);
        \DB::table('search_histories')
            ->where('id', $second->id)
            ->update(['created_at' => now()->subMinutes(1)]); // More recent

        SearchHistory::create([
            'session_id' => 'test-session',
            'query' => 'jazz',
            'type' => 'genre',
            'results_count' => 5,
        ]);

        $response = $this->getJson('/api/search/history', [
            'X-Session-ID' => 'test-session',
        ]);

        $response->assertStatus(200);
        
        $data = $response->json();
        $recentQueries = array_column($data['recent'], 'query');
        
        // Should have unique queries only
        $this->assertCount(2, $recentQueries);
        $this->assertContains('beatles', $recentQueries);
        $this->assertContains('jazz', $recentQueries);
        
        // No duplicates
        $this->assertEquals(count($recentQueries), count(array_unique($recentQueries)));
    }

    public function test_history_recent_searches_ordered_by_most_recent(): void
    {
        // Create records and then update timestamps directly in DB
        $first = SearchHistory::create([
            'session_id' => 'test-session',
            'query' => 'first search',
            'type' => 'general',
            'results_count' => 5,
        ]);
        \DB::table('search_histories')
            ->where('id', $first->id)
            ->update(['created_at' => now()->subMinutes(10)]);

        $second = SearchHistory::create([
            'session_id' => 'test-session',
            'query' => 'second search',
            'type' => 'general',
            'results_count' => 8,
        ]);
        \DB::table('search_histories')
            ->where('id', $second->id)
            ->update(['created_at' => now()->subMinutes(5)]);

        $third = SearchHistory::create([
            'session_id' => 'test-session',
            'query' => 'third search',
            'type' => 'general',
            'results_count' => 12,
        ]);
        \DB::table('search_histories')
            ->where('id', $third->id)
            ->update(['created_at' => now()->subMinutes(1)]);

        $response = $this->getJson('/api/search/history', [
            'X-Session-ID' => 'test-session',
        ]);

        $response->assertStatus(200);
        
        $data = $response->json();
        $recentQueries = array_column($data['recent'], 'query');
        
        // Most recent first
        $this->assertEquals('third search', $recentQueries[0]);
        $this->assertEquals('second search', $recentQueries[1]);
        $this->assertEquals('first search', $recentQueries[2]);
    }

    public function test_clear_history_deletes_session_searches(): void
    {
        SearchHistory::create([
            'session_id' => 'test-session',
            'query' => 'beatles',
            'type' => 'general',
            'results_count' => 10,
        ]);

        $response = $this->deleteJson('/api/search/history', [], [
            'X-Session-ID' => 'test-session',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Search history cleared']);

        $this->assertDatabaseMissing('search_histories', [
            'session_id' => 'test-session',
        ]);
    }

    // ==================== Selection Recording Tests ====================

    public function test_record_selection_requires_fields(): void
    {
        $response = $this->postJson('/api/search/select', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['query', 'selected_id', 'selected_type']);
    }

    public function test_record_selection_updates_history(): void
    {
        $search = SearchHistory::create([
            'session_id' => 'test-session',
            'query' => 'beatles',
            'type' => 'general',
            'results_count' => 10,
        ]);

        $response = $this->postJson('/api/search/select', [
            'query' => 'beatles',
            'selected_id' => 123,
            'selected_type' => 'vinyl',
            'selected_title' => 'Abbey Road',
        ], [
            'X-Session-ID' => 'test-session',
        ]);

        $response->assertStatus(200);

        $search->refresh();
        $this->assertNotNull($search->selected_result);
        $this->assertEquals(123, $search->selected_result['id']);
        $this->assertEquals('vinyl', $search->selected_result['type']);
    }

    // ==================== Stats Endpoint Tests ====================

    public function test_stats_returns_search_statistics(): void
    {
        $this->createTestVinyls();

        $response = $this->getJson('/api/search/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_vinyls',
                'total_artists',
                'genres',
                'countries',
                'price_range' => ['min', 'max', 'avg'],
                'year_range' => ['min', 'max'],
                'trending',
            ]);
    }

    public function test_stats_are_cached(): void
    {
        $this->createTestVinyls();

        // First request
        $response1 = $this->getJson('/api/search/stats');
        $response1->assertStatus(200);

        // Add more data
        DiscogsAnalysis::create([
            'discogs_id' => 999,
            'title' => 'New Album',
            'artist_name' => 'New Artist',
        ]);

        // Second request should return cached (same count)
        $response2 = $this->getJson('/api/search/stats');
        $response2->assertStatus(200);

        // Stats should be cached (same values)
        $this->assertEquals(
            $response1->json('total_vinyls'),
            $response2->json('total_vinyls')
        );
    }

    // ==================== Cache Management Tests ====================

    public function test_warm_cache_endpoint(): void
    {
        $this->createTestVinyls();

        $response = $this->postJson('/api/search/warm-cache');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'queries_cached',
            ]);
    }

    public function test_invalidate_cache_endpoint(): void
    {
        $response = $this->deleteJson('/api/search/cache');

        $response->assertStatus(200)
            ->assertJsonStructure(['message']);
    }

    // ==================== Score Calculation Tests ====================

    public function test_quick_score_calculation_rare_high_demand(): void
    {
        DiscogsAnalysis::create([
            'discogs_id' => 100,
            'title' => 'Ultra Rare Album',
            'artist_name' => 'Test Artist',
            'have' => 50,
            'want' => 500,
            'demand_ratio' => 10.0,
            'num_for_sale' => 0,
            'lowest_price' => 15.00,
        ]);

        $this->mockDiscogsService();

        $response = $this->getJson('/api/search?q=ultra+rare');

        $response->assertStatus(200);
        
        $data = $response->json();
        $localResults = $data['results']['local']['data'];
        
        if (!empty($localResults)) {
            $quickScore = $localResults[0]['insights']['quick_score'];
            // Rare (have < 200) + high demand + no for sale + low price = high score
            $this->assertGreaterThanOrEqual(70, $quickScore);
        }
    }

    public function test_recommendation_based_on_score(): void
    {
        $this->createTestVinyls();
        $this->mockDiscogsService();

        $response = $this->getJson('/api/search?q=album');

        $response->assertStatus(200);
        
        $data = $response->json();
        $localResults = $data['results']['local']['data'];
        
        foreach ($localResults as $result) {
            $score = $result['insights']['quick_score'];
            $recommendation = $result['insights']['recommendation'];
            
            if ($score >= 65) {
                $this->assertEquals('BUY', $recommendation);
            } elseif ($score >= 40) {
                $this->assertEquals('HOLD', $recommendation);
            } else {
                $this->assertEquals('PASS', $recommendation);
            }
        }
    }
}
