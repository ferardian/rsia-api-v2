<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\GoogleReview;
use Carbon\Carbon;

class GoogleReviewService
{
    protected $apiKey;
    protected $placeId;
    protected $baseUrl = 'https://maps.googleapis.com/maps/api/place/details/json';

    public function __construct()
    {
        $this->apiKey = config('services.google.places.api_key');
        $this->placeId = config('services.google.places.place_id');
    }

    /**
     * Get Google Reviews
     * 
     * Fetches reviews from database or Google Places API.
     * Cache duration: 6 hours (21600 seconds).
     * 
     * @return array
     */
    public function getReviews()
    {
        // Cache key for API fetch prevention
        $cacheKey = 'google_reviews_fetched_at';
        $fetchedAt = Cache::get($cacheKey);

        // Check if we need to fetch new data (Cache expired or no data in DB)
        $shouldFetch = false;
        if (!$fetchedAt) {
            $shouldFetch = true;
        } else {
            // Check if 6 hours passed
            $lastFetch = Carbon::parse($fetchedAt);
            if ($lastFetch->diffInHours(Carbon::now()) >= 6) {
                $shouldFetch = true;
            }
        }

        // If DB is empty, force fetch
        if (GoogleReview::count() == 0) {
            $shouldFetch = true;
        }

        if ($shouldFetch) {
            $this->fetchAndSaveReviews();
            Cache::put($cacheKey, Carbon::now()->toDateTimeString(), 21600); // 6 hours
        }

        // Return data with overall rating
        return [
            'reviews' => GoogleReview::orderBy('time', 'desc')->take(5)->get(),
            'rating' => Cache::get('google_place_rating', 0),
            'user_ratings_total' => Cache::get('google_place_ratings_total', 0),
        ];
    }

    private function fetchAndSaveReviews()
    {
        if (!$this->apiKey || !$this->placeId) {
            return;
        }

        try {
            // Use Legacy API to support reviews_sort=newest
            $response = Http::get($this->baseUrl, [
                'place_id' => $this->placeId,
                'fields' => 'reviews,rating,user_ratings_total',
                'reviews_sort' => 'newest',
                'key' => $this->apiKey,
                'language' => 'id'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['result'])) {
                    // Cache overall place rating
                    if (isset($data['result']['rating'])) {
                        Cache::put('google_place_rating', $data['result']['rating'], 21600);
                    }
                    if (isset($data['result']['user_ratings_total'])) {
                        Cache::put('google_place_ratings_total', $data['result']['user_ratings_total'], 21600);
                    }

                    if (isset($data['result']['reviews'])) {
                        // Truncate existing reviews
                        GoogleReview::truncate();

                        foreach ($data['result']['reviews'] as $item) {
                            GoogleReview::create([
                                'author_name' => $item['author_name'] ?? 'Pengguna Google',
                                'author_url' => $item['author_url'] ?? '#',
                                'profile_photo_url' => $item['profile_photo_url'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($item['author_name'] ?? 'User') . '&background=random',
                                'rating' => $item['rating'] ?? 0,
                                'relative_time_description' => $item['relative_time_description'] ?? '',
                                'text' => $item['text'] ?? '',
                                'time' => $item['time'] ?? time()
                            ]);
                        }
                    }
                } else if (isset($data['error_message'])) {
                    Log::error('Google Places Legacy API Error: ' . $data['error_message']);
                }
            } else {
                Log::error('Google Places API Request Failed: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('Google Places Service Exception: ' . $e->getMessage());
        }
    }
}
