<?php

namespace App\Services\Eve;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Config;

class EveEsi
{
    /**
     * Base URL for the EVE ESI API
     */
    protected $baseUrl;

    /**
     * Cache duration in minutes
     */
    protected $cacheDuration;

    /**
     * Maximum number of retries for API requests
     */
    protected $maxRetries;

    /**
     * Create a new EveEsi instance
     */
    public function __construct()
    {
        $this->baseUrl = 'https://esi.evetech.net/latest/';
        $this->cacheDuration = Config::get('eve.cache_duration', 60);
        $this->maxRetries = Config::get('eve.max_retries', 3);
    }

    /**
     * Get a configured HTTP client
     * 
     * @param int $timeout Custom timeout in seconds
     * @return \Illuminate\Http\Client\PendingRequest
     */
    protected function getHttpClient(int $timeout = 30): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->timeout($timeout)
            ->connectTimeout(10)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'User-Agent' => 'EVE-WH Application v1.0',
            ])
            ->retry($this->maxRetries, 1000)
            ->withoutVerifying(); // Disable SSL verification for development
    }

    /**
     * Test the connection to the EVE ESI API
     * 
     * @return bool
     */
    public function testConnection()
    {
        try {
            Log::info('Testing connection to EVE ESI API at: ' . $this->baseUrl . 'status/');

            $response = $this->getHttpClient(15)->get('status/');

            Log::info('EVE ESI API connection successful with status code: ' . $response->status());
            return $response->successful();
        } catch (\Exception $e) {
            Log::error('EVE ESI Connection Test Failed: ' . $e->getMessage());
            Log::error('Exception class: ' . get_class($e));
            return false;
        }
    }

    /**
     * Get market prices from the EVE ESI API
     * 
     * @return array|null
     */
    public function getMarketPrices()
    {
        // Check if we have cached data
        $cacheKey = 'eve_esi_market_prices';
        if (Cache::has($cacheKey)) {
            Log::info('Using cached market prices');
            return Cache::get($cacheKey);
        }

        try {
            $response = $this->getHttpClient(60)->get('markets/prices/');

            if ($response->successful()) {
                $data = $response->json();

                // Validate that we have actual data
                if (is_array($data) && count($data) > 0) {
                    // Cache the result
                    Cache::put($cacheKey, $data, $this->cacheDuration);
                    Log::info('Successfully fetched ' . count($data) . ' market prices from ESI API');
                    return $data;
                } else {
                    Log::warning('EVE ESI returned empty market prices data');
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to fetch market prices: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Get market orders for a specific region and type
     * 
     * @param int $regionId
     * @param int|null $page
     * @param int|null $typeId
     * @return array
     */
    public function getMarketOrders($regionId, $page = null, $typeId = null)
    {
        $cacheKey = "eve_esi_market_orders_{$regionId}_" . ($typeId ? "type_{$typeId}_" : "") . ($page ? "page_{$page}" : "all");

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $queryParams = ['order_type' => 'all'];

            if ($page !== null) {
                $queryParams['page'] = $page;
            }

            if ($typeId !== null) {
                $queryParams['type_id'] = $typeId;
            }

            $response = $this->getHttpClient()->get("markets/{$regionId}/orders/", [
                'query' => $queryParams
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Cache::put($cacheKey, $data, $this->cacheDuration / 2); // Cache for half the time of prices
                return $data;
            }
        } catch (\Exception $e) {
            Log::error("Error fetching market orders for region {$regionId}" . ($typeId ? ", type {$typeId}" : "") . ": " . $e->getMessage());
        }

        return [];
    }

    /**
     * Get universe type information
     * 
     * @param int $typeId
     * @return array|null
     */
    public function getUniverseType($typeId)
    {
        $cacheKey = "eve_esi_universe_type_{$typeId}";

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $response = $this->getHttpClient()->get("universe/types/{$typeId}/");

            if ($response->successful()) {
                $data = $response->json();
                Cache::put($cacheKey, $data, $this->cacheDuration * 24); // Cache for a day
                return $data;
            }
        } catch (\Exception $e) {
            Log::error("Error fetching universe type {$typeId}: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Get region information
     * 
     * @param int $regionId
     * @return array|null
     */
    public function getRegion($regionId)
    {
        $cacheKey = "eve_esi_region_{$regionId}";

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $response = $this->getHttpClient()->get("universe/regions/{$regionId}/");

            if ($response->successful()) {
                $data = $response->json();
                Cache::put($cacheKey, $data, $this->cacheDuration * 24 * 7); // Cache for a week
                return $data;
            }
        } catch (\Exception $e) {
            Log::error("Error fetching region {$regionId}: " . $e->getMessage());
        }

        return null;
    }
}
