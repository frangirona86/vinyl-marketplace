<?php

namespace Tests\Unit;

use App\Models\SearchHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchHistoryTest extends TestCase
{
    use RefreshDatabase;

    // ==================== Record Method Tests ====================

    public function test_record_creates_search_history(): void
    {
        $search = SearchHistory::record(
            sessionId: 'test-session-123',
            query: 'beatles',
            type: 'general',
            resultsCount: 10
        );

        $this->assertInstanceOf(SearchHistory::class, $search);
        $this->assertEquals('test-session-123', $search->session_id);
        $this->assertEquals('beatles', $search->query);
        $this->assertEquals('general', $search->type);
        $this->assertEquals(10, $search->results_count);
    }

    public function test_record_with_user(): void
    {
        $user = User::factory()->create();

        $search = SearchHistory::record(
            sessionId: 'test-session',
            query: 'jazz',
            type: 'genre',
            resultsCount: 5,
            userId: $user->id
        );

        $this->assertEquals($user->id, $search->user_id);
    }

    public function test_record_with_filters(): void
    {
        $filters = ['genre' => 'Rock', 'year_from' => 1970];

        $search = SearchHistory::record(
            sessionId: 'test-session',
            query: 'rock music',
            type: 'general',
            resultsCount: 20,
            filters: $filters
        );

        $this->assertEquals($filters, $search->filters);
    }

    // ==================== Scope Tests ====================

    public function test_by_session_scope(): void
    {
        SearchHistory::create([
            'session_id' => 'session-1',
            'query' => 'beatles',
            'type' => 'general',
        ]);

        SearchHistory::create([
            'session_id' => 'session-2',
            'query' => 'jazz',
            'type' => 'general',
        ]);

        SearchHistory::create([
            'session_id' => 'session-1',
            'query' => 'rock',
            'type' => 'general',
        ]);

        $session1Searches = SearchHistory::bySession('session-1')->get();

        $this->assertCount(2, $session1Searches);
    }

    public function test_by_user_scope(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        SearchHistory::create([
            'session_id' => 'session-1',
            'user_id' => $user1->id,
            'query' => 'beatles',
            'type' => 'general',
        ]);

        SearchHistory::create([
            'session_id' => 'session-2',
            'user_id' => $user2->id,
            'query' => 'jazz',
            'type' => 'general',
        ]);

        SearchHistory::create([
            'session_id' => 'session-3',
            'user_id' => $user1->id,
            'query' => 'rock',
            'type' => 'general',
        ]);

        $user1Searches = SearchHistory::byUser($user1->id)->get();

        $this->assertCount(2, $user1Searches);
    }

    public function test_recent_scope(): void
    {
        for ($i = 0; $i < 15; $i++) {
            SearchHistory::create([
                'session_id' => 'session-1',
                'query' => "search-{$i}",
                'type' => 'general',
            ]);
        }

        $recent = SearchHistory::recent(5)->get();

        $this->assertCount(5, $recent);
    }

    public function test_unique_queries_scope(): void
    {
        SearchHistory::create([
            'session_id' => 'session-1',
            'query' => 'beatles',
            'type' => 'general',
        ]);

        // Duplicate query
        SearchHistory::create([
            'session_id' => 'session-1',
            'query' => 'beatles',
            'type' => 'general',
        ]);

        SearchHistory::create([
            'session_id' => 'session-1',
            'query' => 'jazz',
            'type' => 'genre',
        ]);

        $unique = SearchHistory::uniqueQueries()->get();

        $this->assertCount(2, $unique);
    }

    // ==================== Popular Searches Tests ====================

    public function test_get_popular_searches(): void
    {
        // Create multiple searches for same queries
        for ($i = 0; $i < 5; $i++) {
            SearchHistory::create([
                'session_id' => "session-{$i}",
                'query' => 'beatles',
                'type' => 'general',
            ]);
        }

        for ($i = 0; $i < 3; $i++) {
            SearchHistory::create([
                'session_id' => "session-{$i}",
                'query' => 'jazz',
                'type' => 'genre',
            ]);
        }

        SearchHistory::create([
            'session_id' => 'session-x',
            'query' => 'rock',
            'type' => 'general',
        ]);

        $popular = SearchHistory::getPopularSearches(3, 7);

        $this->assertCount(3, $popular);
        $this->assertEquals('beatles', $popular[0]['query']);
        $this->assertEquals(5, $popular[0]['count']);
    }

    public function test_get_popular_searches_respects_days_limit(): void
    {
        // Old search (should not be included) - use factory to properly set timestamp
        $oldSearch = SearchHistory::create([
            'session_id' => 'session-old',
            'query' => 'old search',
            'type' => 'general',
        ]);
        // Update timestamp directly in DB to avoid model events
        \DB::table('search_histories')
            ->where('id', $oldSearch->id)
            ->update(['created_at' => now()->subDays(10)]);

        // Recent search
        SearchHistory::create([
            'session_id' => 'session-new',
            'query' => 'new search',
            'type' => 'general',
        ]);

        $popular = SearchHistory::getPopularSearches(10, 7);

        $queries = array_column($popular, 'query');
        $this->assertNotContains('old search', $queries);
        $this->assertContains('new search', $queries);
    }

    // ==================== Relationship Tests ====================

    public function test_belongs_to_user(): void
    {
        $user = User::factory()->create();

        $search = SearchHistory::create([
            'session_id' => 'session-1',
            'user_id' => $user->id,
            'query' => 'beatles',
            'type' => 'general',
        ]);

        $this->assertInstanceOf(User::class, $search->user);
        $this->assertEquals($user->id, $search->user->id);
    }

    // ==================== Cast Tests ====================

    public function test_filters_are_cast_to_array(): void
    {
        $search = SearchHistory::create([
            'session_id' => 'session-1',
            'query' => 'rock',
            'type' => 'general',
            'filters' => ['genre' => 'Rock', 'year' => 1970],
        ]);

        $search->refresh();

        $this->assertIsArray($search->filters);
        $this->assertEquals('Rock', $search->filters['genre']);
    }

    public function test_selected_result_is_cast_to_array(): void
    {
        $search = SearchHistory::create([
            'session_id' => 'session-1',
            'query' => 'beatles',
            'type' => 'general',
            'selected_result' => [
                'id' => 123,
                'type' => 'vinyl',
                'title' => 'Abbey Road',
            ],
        ]);

        $search->refresh();

        $this->assertIsArray($search->selected_result);
        $this->assertEquals(123, $search->selected_result['id']);
    }
}
