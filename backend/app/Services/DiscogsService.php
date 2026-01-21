<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DiscogsService
{
    protected string $baseUrl;
    protected string $consumerKey;
    protected string $consumerSecret;
    protected string $userAgent;

    public function __construct()
    {
        $this->baseUrl = config('discogs.base_url');
        $this->consumerKey = config('discogs.consumer_key');
        $this->consumerSecret = config('discogs.consumer_secret');
        $this->userAgent = config('discogs.user_agent');
    }

    /**
     * Make an authenticated request to Discogs API
     */
    protected function request(string $endpoint, array $params = []): ?array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => $this->userAgent,
                'Authorization' => "Discogs key={$this->consumerKey}, secret={$this->consumerSecret}",
            ])->get("{$this->baseUrl}{$endpoint}", $params);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('Discogs API error', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Discogs API exception', [
                'endpoint' => $endpoint,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Search for releases (albums, singles, etc.)
     */
    public function searchReleases(string $query, int $perPage = 10, int $page = 1): ?array
    {
        $cacheKey = "discogs_search_" . md5($query . $perPage . $page);

        return Cache::remember($cacheKey, 3600, function () use ($query, $perPage, $page) {
            $response = $this->request('/database/search', [
                'q' => $query,
                'type' => 'release',
                'per_page' => $perPage,
                'page' => $page,
            ]);

            if (!$response || !isset($response['results'])) {
                return null;
            }

            return [
                'results' => collect($response['results'])->map(function ($item) {
                    return $this->formatSearchResult($item);
                })->toArray(),
                'pagination' => $response['pagination'] ?? null,
            ];
        });
    }

    /**
     * Search for artists
     */
    public function searchArtists(string $query, int $perPage = 10): ?array
    {
        $response = $this->request('/database/search', [
            'q' => $query,
            'type' => 'artist',
            'per_page' => $perPage,
        ]);

        if (!$response || !isset($response['results'])) {
            return null;
        }

        return collect($response['results'])->map(function ($item) {
            return [
                'id' => $item['id'] ?? null,
                'name' => $item['title'] ?? null,
                'thumb' => $item['thumb'] ?? null,
                'cover_image' => $item['cover_image'] ?? null,
            ];
        })->toArray();
    }

    /**
     * Get release details by Discogs ID
     */
    public function getRelease(int $releaseId): ?array
    {
        $cacheKey = "discogs_release_{$releaseId}";

        return Cache::remember($cacheKey, 86400, function () use ($releaseId) {
            $response = $this->request("/releases/{$releaseId}");

            if (!$response) {
                return null;
            }

            return $this->formatReleaseDetails($response);
        });
    }

    /**
     * Get artist details by Discogs ID
     */
    public function getArtist(int $artistId): ?array
    {
        $cacheKey = "discogs_artist_{$artistId}";

        return Cache::remember($cacheKey, 86400, function () use ($artistId) {
            $response = $this->request("/artists/{$artistId}");

            if (!$response) {
                return null;
            }

            return [
                'id' => $response['id'] ?? null,
                'name' => $response['name'] ?? null,
                'profile' => $response['profile'] ?? null,
                'images' => $response['images'] ?? [],
            ];
        });
    }

    /**
     * Get marketplace price suggestions for a release
     */
    public function getPriceSuggestions(int $releaseId): ?array
    {
        $response = $this->request("/marketplace/price_suggestions/{$releaseId}");

        if (!$response) {
            return null;
        }

        return $response;
    }

    /**
     * Get marketplace statistics for a release
     */
    public function getMarketplaceStats(int $releaseId): ?array
    {
        $response = $this->request("/marketplace/stats/{$releaseId}");

        return $response;
    }

    /**
     * Get community stats (have/want) for a release
     */
    public function getCommunityStats(int $releaseId): ?array
    {
        $response = $this->request("/releases/{$releaseId}");

        if (!$response) {
            return null;
        }

        return [
            'have' => $response['community']['have'] ?? 0,
            'want' => $response['community']['want'] ?? 0,
            'rating_average' => $response['community']['rating']['average'] ?? 0,
            'rating_count' => $response['community']['rating']['count'] ?? 0,
        ];
    }

    /**
     * Get marketplace listings for a release
     */
    public function getMarketplaceListings(int $releaseId, int $perPage = 25, int $page = 1): ?array
    {
        $response = $this->request("/marketplace/listings", [
            'release_id' => $releaseId,
            'per_page' => $perPage,
            'page' => $page,
        ]);

        if (!$response || !isset($response['listings'])) {
            return null;
        }

        return [
            'listings' => collect($response['listings'])->map(function ($listing) {
                return [
                    'id' => $listing['id'] ?? null,
                    'price' => [
                        'value' => $listing['price']['value'] ?? null,
                        'currency' => $listing['price']['currency'] ?? null,
                    ],
                    'condition' => $listing['condition'] ?? null,
                    'sleeve_condition' => $listing['sleeve_condition'] ?? null,
                    'ships_from' => $listing['ships_from'] ?? null,
                    'seller' => [
                        'username' => $listing['seller']['username'] ?? null,
                        'rating' => $listing['seller']['stats']['rating'] ?? null,
                        'total_sales' => $listing['seller']['stats']['total'] ?? null,
                    ],
                    'comments' => $listing['comments'] ?? null,
                    'posted' => $listing['posted'] ?? null,
                ];
            })->toArray(),
            'pagination' => $response['pagination'] ?? null,
        ];
    }

    /**
     * Get complete analysis data for a release
     * Combines: release info + community stats + marketplace stats + listings
     */
    public function getCompleteAnalysis(int $releaseId): ?array
    {
        // Get release details
        $release = $this->getRelease($releaseId);
        if (!$release) {
            return null;
        }

        // Get community stats
        $community = $this->getCommunityStats($releaseId);

        // Get marketplace stats
        $marketStats = $this->getMarketplaceStats($releaseId);

        // Get price suggestions by condition
        $priceSuggestions = $this->getPriceSuggestions($releaseId);

        // Get first page of listings
        $listings = $this->getMarketplaceListings($releaseId, 10);

        return [
            'release' => $release,
            'community' => $community,
            'marketplace' => [
                'stats' => $marketStats,
                'price_suggestions' => $priceSuggestions,
                'listings' => $listings['listings'] ?? [],
                'total_listings' => $listings['pagination']['items'] ?? 0,
            ],
            'analysis' => [
                'demand_ratio' => $community ? ($community['want'] / max($community['have'], 1)) : 0,
                'is_rare' => ($community['have'] ?? 0) < 100,
                'is_in_demand' => ($community['want'] ?? 0) > ($community['have'] ?? 0),
                'fetched_at' => now()->toISOString(),
            ],
        ];
    }

    /**
     * Search releases with marketplace data (for browsing)
     */
    public function searchWithMarketData(string $query, int $perPage = 10, int $page = 1): ?array
    {
        $searchResults = $this->searchReleases($query, $perPage, $page);

        if (!$searchResults || empty($searchResults['results'])) {
            return null;
        }

        // Enrich with community stats (limited to avoid rate limits)
        $enrichedResults = collect($searchResults['results'])->map(function ($result) {
            // Get community stats for each result
            $communityResponse = $this->request("/releases/{$result['discogs_id']}");

            $result['community'] = [
                'have' => $communityResponse['community']['have'] ?? 0,
                'want' => $communityResponse['community']['want'] ?? 0,
            ];

            $result['num_for_sale'] = $communityResponse['num_for_sale'] ?? 0;
            $result['lowest_price'] = $communityResponse['lowest_price'] ?? null;

            return $result;
        })->toArray();

        return [
            'results' => $enrichedResults,
            'pagination' => $searchResults['pagination'],
        ];
    }

    /**
     * Format a search result item
     */
    protected function formatSearchResult(array $item): array
    {
        return [
            'discogs_id' => $item['id'] ?? null,
            'title' => $item['title'] ?? null,
            'year' => $item['year'] ?? null,
            'country' => $item['country'] ?? null,
            'label' => $item['label'][0] ?? null,
            'genre' => $item['genre'][0] ?? null,
            'style' => $item['style'][0] ?? null,
            'format' => $item['format'][0] ?? null,
            'thumb' => $item['thumb'] ?? null,
            'cover_image' => $item['cover_image'] ?? null,
            'resource_url' => $item['resource_url'] ?? null,
        ];
    }

    /**
     * Format release details
     */
    protected function formatReleaseDetails(array $response): array
    {
        // Extract artist name (handle various formats)
        $artistName = null;
        if (isset($response['artists']) && !empty($response['artists'])) {
            $artistName = $response['artists'][0]['name'] ?? null;
        }

        // Extract tracklist
        $tracklist = collect($response['tracklist'] ?? [])->map(function ($track) {
            return [
                'position' => $track['position'] ?? null,
                'title' => $track['title'] ?? null,
                'duration' => $track['duration'] ?? null,
            ];
        })->toArray();

        return [
            'discogs_id' => $response['id'] ?? null,
            'title' => $response['title'] ?? null,
            'artist_name' => $artistName,
            'artists' => $response['artists'] ?? [],
            'year' => $response['year'] ?? null,
            'country' => $response['country'] ?? null,
            'label' => $response['labels'][0]['name'] ?? null,
            'catalog_number' => $response['labels'][0]['catno'] ?? null,
            'genres' => $response['genres'] ?? [],
            'styles' => $response['styles'] ?? [],
            'formats' => collect($response['formats'] ?? [])->map(function ($format) {
                return [
                    'name' => $format['name'] ?? null,
                    'qty' => $format['qty'] ?? null,
                    'descriptions' => $format['descriptions'] ?? [],
                ];
            })->toArray(),
            'tracklist' => $tracklist,
            'images' => collect($response['images'] ?? [])->map(function ($image) {
                return [
                    'type' => $image['type'] ?? null,
                    'uri' => $image['uri'] ?? null,
                    'uri150' => $image['uri150'] ?? null,
                ];
            })->toArray(),
            'notes' => $response['notes'] ?? null,
            'data_quality' => $response['data_quality'] ?? null,
        ];
    }
}
