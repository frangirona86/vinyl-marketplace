<?php

namespace App\Jobs;

use App\Models\DiscogsAnalysis;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BatchAnalyzeVinylsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(
        public int $limit = 50,
        public bool $includeYouTube = true
    ) {}

    public function handle(): void
    {
        // Get vinyls without AI analysis
        $vinyls = DiscogsAnalysis::whereNull('ai_score')
            ->orWhereNull('ai_analysis')
            ->limit($this->limit)
            ->get();

        Log::info("BatchAnalyzeVinylsJob: Queuing {$vinyls->count()} vinyls for analysis");

        foreach ($vinyls as $vinyl) {
            // Dispatch AI analysis with delay to avoid rate limits
            AnalyzeVinylJob::dispatch($vinyl->id)
                ->onQueue('ai')
                ->delay(now()->addSeconds($vinyls->search($vinyl) * 2)); // 2 second delay between each

            // Optionally queue YouTube fetch
            if ($this->includeYouTube && !$vinyl->has_youtube) {
                FetchYouTubeTracksJob::dispatch($vinyl->id)
                    ->onQueue('youtube')
                    ->delay(now()->addSeconds($vinyls->search($vinyl) * 3)); // 3 second delay
            }
        }

        Log::info("BatchAnalyzeVinylsJob: Queued all jobs successfully");
    }
}
