<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchHistory extends Model
{
    protected $fillable = [
        'session_id',
        'user_id',
        'query',
        'type',
        'results_count',
        'filters',
        'selected_result',
    ];

    protected $casts = [
        'filters' => 'array',
        'selected_result' => 'array',
        'results_count' => 'integer',
    ];

    /**
     * Get the user that made the search
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get searches by session
     */
    public function scopeBySession($query, string $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    /**
     * Scope to get searches by user
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get recent searches
     */
    public function scopeRecent($query, int $limit = 10)
    {
        return $query->orderByDesc('created_at')->limit($limit);
    }

    /**
     * Scope to get unique queries only
     */
    public function scopeUniqueQueries($query)
    {
        return $query->select('query', 'type')
            ->selectRaw('MAX(created_at) as last_searched')
            ->selectRaw('COUNT(*) as search_count')
            ->groupBy('query', 'type')
            ->orderByDesc('last_searched');
    }

    /**
     * Get popular searches globally
     */
    public static function getPopularSearches(int $limit = 10, int $days = 7): array
    {
        return static::where('created_at', '>=', now()->subDays($days))
            ->select('query', 'type')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('query', 'type')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Record a search
     */
    public static function record(
        string $sessionId,
        string $query,
        string $type = 'general',
        int $resultsCount = 0,
        ?int $userId = null,
        ?array $filters = null
    ): static {
        return static::create([
            'session_id' => $sessionId,
            'user_id' => $userId,
            'query' => $query,
            'type' => $type,
            'results_count' => $resultsCount,
            'filters' => $filters,
        ]);
    }
}
