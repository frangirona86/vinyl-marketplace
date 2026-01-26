<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YouTubeService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://www.googleapis.com/youtube/v3';

    public function __construct()
    {
        $this->apiKey = config('services.youtube.key');
    }

    /**
     * Search for tracks of a vinyl release
     */
    public function searchTracks(string $artist, string $title, ?int $year = null, int $maxResults = 5): array
    {
        if (!$this->apiKey) {
            Log::warning('YouTube API key not configured');
            return [];
        }

        // Build search query
        $query = "{$artist} {$title}";
        if ($year) {
            $query .= " {$year}";
        }

        try {
            $response = Http::get("{$this->baseUrl}/search", [
                'key' => $this->apiKey,
                'q' => $query,
                'part' => 'snippet',
                'type' => 'video',
                'maxResults' => $maxResults,
                'videoCategoryId' => '10', // Music category
                'order' => 'relevance',
            ]);

            if (!$response->successful()) {
                Log::warning('YouTube API error', [
                    'status' => $response->status(),
                    'query' => $query,
                ]);
                return [];
            }

            $data = $response->json();
            
            if (empty($data['items'])) {
                return [];
            }

            return $this->formatResults($data['items'], $artist, $title);

        } catch (\Exception $e) {
            Log::error('YouTube API exception', [
                'message' => $e->getMessage(),
                'query' => $query,
            ]);
            return [];
        }
    }

    /**
     * Search for a specific track by name
     */
    public function searchTrack(string $artist, string $trackName): ?array
    {
        if (!$this->apiKey) {
            return null;
        }

        $query = "{$artist} {$trackName}";

        try {
            $response = Http::get("{$this->baseUrl}/search", [
                'key' => $this->apiKey,
                'q' => $query,
                'part' => 'snippet',
                'type' => 'video',
                'maxResults' => 1,
                'videoCategoryId' => '10',
            ]);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();
            
            if (empty($data['items'])) {
                return null;
            }

            $item = $data['items'][0];
            return [
                'video_id' => $item['id']['videoId'],
                'title' => $item['snippet']['title'],
                'channel' => $item['snippet']['channelTitle'],
                'thumbnail' => $item['snippet']['thumbnails']['medium']['url'] ?? $item['snippet']['thumbnails']['default']['url'],
                'url' => "https://www.youtube.com/watch?v={$item['id']['videoId']}",
                'embed_url' => "https://www.youtube.com/embed/{$item['id']['videoId']}",
            ];

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get video details by ID
     */
    public function getVideoDetails(string $videoId): ?array
    {
        if (!$this->apiKey) {
            return null;
        }

        try {
            $response = Http::get("{$this->baseUrl}/videos", [
                'key' => $this->apiKey,
                'id' => $videoId,
                'part' => 'snippet,contentDetails,statistics',
            ]);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();
            
            if (empty($data['items'])) {
                return null;
            }

            $item = $data['items'][0];
            return [
                'video_id' => $item['id'],
                'title' => $item['snippet']['title'],
                'description' => $item['snippet']['description'],
                'channel' => $item['snippet']['channelTitle'],
                'duration' => $item['contentDetails']['duration'],
                'view_count' => $item['statistics']['viewCount'] ?? 0,
                'like_count' => $item['statistics']['likeCount'] ?? 0,
                'thumbnail' => $item['snippet']['thumbnails']['high']['url'] ?? $item['snippet']['thumbnails']['default']['url'],
                'url' => "https://www.youtube.com/watch?v={$item['id']}",
                'embed_url' => "https://www.youtube.com/embed/{$item['id']}",
            ];

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Format search results
     */
    protected function formatResults(array $items, string $artist, string $title): array
    {
        $tracks = [];
        $artistLower = strtolower($artist);
        $titleLower = strtolower($title);

        foreach ($items as $item) {
            $videoTitle = $item['snippet']['title'];
            $videoTitleLower = strtolower($videoTitle);
            
            // Calculate relevance score
            $relevance = 0;
            if (str_contains($videoTitleLower, $artistLower)) {
                $relevance += 50;
            }
            if (str_contains($videoTitleLower, $titleLower)) {
                $relevance += 30;
            }
            // Bonus for official channels
            $channelLower = strtolower($item['snippet']['channelTitle']);
            if (str_contains($channelLower, 'official') || str_contains($channelLower, $artistLower)) {
                $relevance += 20;
            }

            $tracks[] = [
                'video_id' => $item['id']['videoId'],
                'title' => $videoTitle,
                'channel' => $item['snippet']['channelTitle'],
                'thumbnail' => $item['snippet']['thumbnails']['medium']['url'] ?? $item['snippet']['thumbnails']['default']['url'],
                'url' => "https://www.youtube.com/watch?v={$item['id']['videoId']}",
                'embed_url' => "https://www.youtube.com/embed/{$item['id']['videoId']}",
                'relevance' => $relevance,
                'published_at' => $item['snippet']['publishedAt'],
            ];
        }

        // Sort by relevance
        usort($tracks, fn($a, $b) => $b['relevance'] <=> $a['relevance']);

        return $tracks;
    }

    /**
     * Check if API key is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }
}
