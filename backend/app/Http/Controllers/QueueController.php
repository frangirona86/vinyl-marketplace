<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzeVinylJob;
use App\Jobs\BatchAnalyzeVinylsJob;
use App\Jobs\FetchYouTubeTracksJob;
use App\Jobs\ImportVinylFromDiscogsJob;
use App\Models\DiscogsAnalysis;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;

class QueueController extends Controller
{
    /**
     * Queue AI analysis for a single vinyl
     * POST /api/queue/analyze/{id}
     */
    public function analyzeVinyl(int $id): JsonResponse
    {
        $vinyl = DiscogsAnalysis::find($id);
        
        if (!$vinyl) {
            return response()->json(['error' => 'Vinyl not found'], 404);
        }

        AnalyzeVinylJob::dispatch($vinyl->id)->onQueue('ai');

        return response()->json([
            'message' => 'Analysis queued successfully',
            'vinyl_id' => $vinyl->id,
            'discogs_id' => $vinyl->discogs_id,
        ]);
    }

    /**
     * Queue batch AI analysis for multiple vinyls
     * POST /api/queue/analyze-batch
     */
    public function analyzeBatch(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:100',
            'include_youtube' => 'nullable|boolean',
        ]);

        $limit = $request->input('limit', 50);
        $includeYouTube = $request->boolean('include_youtube', true);

        BatchAnalyzeVinylsJob::dispatch($limit, $includeYouTube)->onQueue('default');

        return response()->json([
            'message' => 'Batch analysis queued',
            'limit' => $limit,
            'include_youtube' => $includeYouTube,
        ]);
    }

    /**
     * Queue YouTube tracks fetch for a single vinyl
     * POST /api/queue/youtube/{id}
     */
    public function fetchYouTube(int $id): JsonResponse
    {
        $vinyl = DiscogsAnalysis::find($id);
        
        if (!$vinyl) {
            return response()->json(['error' => 'Vinyl not found'], 404);
        }

        FetchYouTubeTracksJob::dispatch($vinyl->id)->onQueue('youtube');

        return response()->json([
            'message' => 'YouTube fetch queued',
            'vinyl_id' => $vinyl->id,
        ]);
    }

    /**
     * Queue import from Discogs
     * POST /api/queue/import
     */
    public function importFromDiscogs(Request $request): JsonResponse
    {
        $request->validate([
            'discogs_id' => 'required|integer',
            'analyze' => 'nullable|boolean',
            'youtube' => 'nullable|boolean',
        ]);

        $discogsId = $request->input('discogs_id');
        $analyze = $request->boolean('analyze', true);
        $youtube = $request->boolean('youtube', true);

        ImportVinylFromDiscogsJob::dispatch($discogsId, $analyze, $youtube)
            ->onQueue('discogs');

        return response()->json([
            'message' => 'Import queued',
            'discogs_id' => $discogsId,
            'will_analyze' => $analyze,
            'will_fetch_youtube' => $youtube,
        ]);
    }

    /**
     * Get queue statistics
     * GET /api/queue/stats
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'pending_analysis' => DiscogsAnalysis::whereNull('ai_score')->count(),
            'pending_youtube' => DiscogsAnalysis::where('has_youtube', false)
                ->orWhereNull('has_youtube')
                ->count(),
            'total_vinyls' => DiscogsAnalysis::count(),
            'analyzed' => DiscogsAnalysis::whereNotNull('ai_score')->count(),
            'with_youtube' => DiscogsAnalysis::where('has_youtube', true)->count(),
        ];

        // Queue sizes (if using Redis)
        if (config('queue.default') === 'redis') {
            $stats['queues'] = [
                'default' => Queue::size('default'),
                'ai' => Queue::size('ai'),
                'youtube' => Queue::size('youtube'),
                'discogs' => Queue::size('discogs'),
            ];
        }

        return response()->json(['data' => $stats]);
    }
}
