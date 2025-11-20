<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\ItemPrice;
use App\Services\Eve\EveEsi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

class apiEsiPrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:esi:prices {--fallback-only : Only use the fallback price method}';
    protected $prefix = 'ESI (MarketPrices) : ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pull prices from EVE Online ESI API and store them in the database';

    /**
     * The EVE ESI service
     *
     * @var \App\Services\Eve\EveEsi
     */
    protected $esiService;

    /**
     * Fallback market prices from the ESI API
     */
    protected $marketPrices = [];

    /**
     * Create a new command instance.
     *
     * @param \App\Services\Eve\EveEsi $esiService
     * @return void
     */
    public function __construct(EveEsi $esiService)
    {
        parent::__construct();
        $this->esiService = $esiService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startTime = microtime(true);
        $this->info($this->prefix . 'Starting');

        // Test connection to EVE ESI API
        if (!$this->esiService->testConnection()) {
            $this->error($this->prefix . 'Failed to connect to EVE ESI API');
            return 1;
        }

        $this->info($this->prefix . 'Successfully connected to EVE ESI API');

        // First, fetch all market prices as a fallback
        $this->fetchMarketPrices();

        // Get all the items that are in the market groups and with a name starting with Compressed%
        $marketGroups = Config::get('eve.market_groups', [
            3638,
            3639,
            3640,
            518,
            519,
            515,
            516,
            526,
            523,
            529,
            528,
            527,
            525,
            522,
            521,
            514,
            512,
            517,
            2538,
            2539,
            2540,
            530,
            3487,
            3488,
            3489,
            3490,
            1855,
            792,
            614,
            2814,
            2396,
            2397,
            2398,
            2400,
            2401,
            20,
            3636,
            3637
        ]);

        $items = Item::whereIn('market_group_id', $marketGroups)
            ->where('name', 'like', 'Compressed%')
            ->get();

        $itemsIds = $items->pluck('id')->toArray();

        if (empty($itemsIds)) {
            $this->warn($this->prefix . 'No items found matching the criteria');
            return 0;
        }

        $this->info($this->prefix . 'Found ' . count($itemsIds) . ' items to process');

        // Only process fallback prices if the --fallback-only flag is used
        if ($this->option('fallback-only')) {
            $this->info($this->prefix . 'Processing with fallback prices only');
            $this->processFallbackPrices($itemsIds);

            $this->logExecutionTime($startTime);
            return 0;
        }

        // Using EVE region IDs from config
        $regions = Config::get('eve.regions', [
            'jita' => 10000002,     // The Forge (Jita)
            'amarr' => 10000043,    // Domain (Amarr)
            'dodixie' => 10000032,  // Sinq Laison (Dodixie)
            'rens' => 10000030,     // Heimatar (Rens)
            'hek' => 10000042,      // Metropolis (Hek)
        ]);

        // Process each region
        foreach ($regions as $marketName => $regionId) {
            $this->info($this->prefix . 'Getting prices for ' . ucfirst($marketName) . ' (Region ID: ' . $regionId . ')');
            $this->processRegionMarket($marketName, $regionId, $itemsIds);
        }

        $this->logExecutionTime($startTime);
        return 0;
    }

    /**
     * Log execution time
     * 
     * @param float $startTime
     * @return void
     */
    protected function logExecutionTime($startTime)
    {
        $executionTime = round(microtime(true) - $startTime, 2);
        $this->info($this->prefix . 'Finished (' . $executionTime . ' seconds)');
    }

    /**
     * Fetch all market prices from the ESI API
     */
    protected function fetchMarketPrices()
    {
        $this->info($this->prefix . 'Fetching market prices for fallback data');
        $prices = $this->esiService->getMarketPrices();

        if (!$prices) {
            $this->warn($this->prefix . 'Failed to fetch market prices for fallback');
            return;
        }

        // Convert to a lookup array indexed by type_id
        $this->marketPrices = collect($prices)->keyBy('type_id')->toArray();

        $this->info($this->prefix . 'Fetched ' . count($this->marketPrices) . ' market prices for fallback');
    }

    /**
     * Process items using only fallback prices
     */
    protected function processFallbackPrices($itemIds)
    {
        $count = 0;
        $prices = collect($this->marketPrices);

        foreach ($itemIds as $itemId) {
            if (isset($this->marketPrices[$itemId])) {
                $price = $this->marketPrices[$itemId];

                // Use average_price or adjusted_price, whichever is available
                $priceValue = $price['average_price'] ?? $price['adjusted_price'] ?? 0;

                if ($priceValue > 0) {
                    // Create a uniform price structure for all trade hubs
                    $priceData = $this->createUniformPriceData($priceValue);

                    // Update all market hubs with the same value
                    $data = [
                        'jita' => $priceData,
                        'amarr' => $priceData,
                        'dodixie' => $priceData,
                        'rens' => $priceData,
                        'hek' => $priceData,
                    ];

                    // Store in database
                    ItemPrice::updateOrCreate(
                        ['item_id' => $itemId],
                        $data
                    );

                    $count++;
                } else {
                    $this->warn($this->prefix . 'No valid price found for item ID ' . $itemId);
                }
            } else {
                $this->warn($this->prefix . 'No fallback price found for item ID ' . $itemId);
            }
        }

        $this->info($this->prefix . 'Updated ' . $count . ' items with fallback prices');
    }

    /**
     * Create uniform price data structure
     * 
     * @param float $priceValue
     * @return array
     */
    protected function createUniformPriceData($priceValue)
    {
        return [
            'buy' => [
                'volume' => 0,
                'max' => $priceValue,
                'min' => $priceValue,
                'percentile' => $priceValue,
                'median' => $priceValue,
                'average' => $priceValue,
                'stddev' => 0,
            ],
            'sell' => [
                'volume' => 0,
                'max' => $priceValue,
                'min' => $priceValue,
                'percentile' => $priceValue,
                'median' => $priceValue,
                'average' => $priceValue,
                'stddev' => 0,
            ],
        ];
    }

    /**
     * Process market data for a specific region
     *
     * @param string $marketName
     * @param int $regionId
     * @param array $itemIds
     * @return void
     */
    protected function processRegionMarket($marketName, $regionId, $itemIds)
    {
        // Process items in chunks to avoid overloading the API
        $chunks = array_chunk($itemIds, 50);
        $processedCount = 0;

        foreach ($chunks as $chunk) {
            $this->info($this->prefix . 'Processing batch of ' . count($chunk) . ' items...');

            // Process each item in the chunk
            foreach ($chunk as $itemId) {
                // Get both buy and sell orders for this item in this region
                $orders = $this->esiService->getMarketOrders($regionId, null, $itemId);

                if (empty($orders)) {
                    $this->warn($this->prefix . 'No orders found for item ID ' . $itemId . ' in ' . ucfirst($marketName));

                    // If no market orders were found, try to use the fallback price
                    $this->useFallbackPrice($marketName, $itemId);
                    continue;
                }

                // Separate buy and sell orders using Laravel collection
                $orderCollection = collect($orders);
                $buyOrders = $orderCollection->filter(fn($order) => $order['is_buy_order'] === true);
                $sellOrders = $orderCollection->filter(fn($order) => $order['is_buy_order'] === false);

                // Calculate statistics
                $orderStats = $this->calculateOrderStats($buyOrders, $sellOrders);

                // Store in database - using updateOrCreate to ensure we're not overwriting other market data
                ItemPrice::updateOrCreate(
                    ['item_id' => $itemId],
                    [
                        $marketName => $orderStats,
                    ]
                );

                $processedCount++;

                // Sleep briefly to avoid hitting rate limits
                usleep(50000); // 50ms
            }
        }

        $this->info($this->prefix . 'Processed ' . $processedCount . ' items for ' . ucfirst($marketName));
    }

    /**
     * Use fallback price when market orders are not available
     * 
     * @param string $marketName
     * @param int $itemId
     * @return void
     */
    protected function useFallbackPrice($marketName, $itemId)
    {
        if (isset($this->marketPrices[$itemId])) {
            $price = $this->marketPrices[$itemId];
            $priceValue = $price['average_price'] ?? $price['adjusted_price'] ?? 0;

            if ($priceValue > 0) {
                $priceData = $this->createUniformPriceData($priceValue);

                // Store in database for this specific market only
                ItemPrice::updateOrCreate(
                    ['item_id' => $itemId],
                    [
                        $marketName => $priceData
                    ]
                );

                $this->info($this->prefix . 'Used fallback price for item ID ' . $itemId . ' in ' . ucfirst($marketName) . ': ' . $priceValue);
            } else {
                $this->warn($this->prefix . 'No valid fallback price found for item ID ' . $itemId);
            }
        } else {
            $this->warn($this->prefix . 'No fallback price data available for item ID ' . $itemId);
        }
    }

    /**
     * Calculate statistics from market orders
     *
     * @param \Illuminate\Support\Collection $buyOrders
     * @param \Illuminate\Support\Collection $sellOrders
     * @return array
     */
    protected function calculateOrderStats(Collection $buyOrders, Collection $sellOrders)
    {
        $stats = [
            'buy' => [
                'volume' => 0,
                'max' => 0,
                'min' => PHP_FLOAT_MAX,
                'percentile' => 0,
                'median' => 0,
                'average' => 0,
                'stddev' => 0,
            ],
            'sell' => [
                'volume' => 0,
                'max' => 0,
                'min' => PHP_FLOAT_MAX,
                'percentile' => 0,
                'median' => 0,
                'average' => 0,
                'stddev' => 0,
            ],
        ];

        // Process buy orders
        if ($buyOrders->isNotEmpty()) {
            $prices = $buyOrders->pluck('price')->toArray();
            $volumes = $buyOrders->pluck('volume_remain')->toArray();

            $stats['buy']['volume'] = array_sum($volumes);
            $stats['buy']['max'] = max($prices);
            $stats['buy']['min'] = min($prices);
            $stats['buy']['average'] = array_sum($prices) / count($prices);
            $stats['buy']['median'] = $this->calculateMedian($prices);
            $stats['buy']['stddev'] = $this->calculateStdDev($prices);
            $stats['buy']['percentile'] = $this->calculatePercentile($prices, 95); // 95th percentile for buy
        }

        // Process sell orders
        if ($sellOrders->isNotEmpty()) {
            $prices = $sellOrders->pluck('price')->toArray();
            $volumes = $sellOrders->pluck('volume_remain')->toArray();

            $stats['sell']['volume'] = array_sum($volumes);
            $stats['sell']['max'] = max($prices);
            $stats['sell']['min'] = min($prices);
            $stats['sell']['average'] = array_sum($prices) / count($prices);
            $stats['sell']['median'] = $this->calculateMedian($prices);
            $stats['sell']['stddev'] = $this->calculateStdDev($prices);
            $stats['sell']['percentile'] = $this->calculatePercentile($prices, 5); // 5th percentile for sell
        }

        return $stats;
    }

    /**
     * Calculate the median value of an array
     *
     * @param array $array
     * @return float
     */
    protected function calculateMedian($array)
    {
        sort($array);
        $count = count($array);
        $middle = floor($count / 2);

        if ($count % 2 == 0) {
            return ($array[$middle - 1] + $array[$middle]) / 2;
        } else {
            return $array[$middle];
        }
    }

    /**
     * Calculate standard deviation
     *
     * @param array $array
     * @return float
     */
    protected function calculateStdDev($array)
    {
        $count = count($array);
        if ($count < 2) {
            return 0;
        }

        $mean = array_sum($array) / $count;
        $variance = 0.0;

        foreach ($array as $value) {
            $variance += pow($value - $mean, 2);
        }

        return sqrt($variance / ($count - 1));
    }

    /**
     * Calculate percentile
     *
     * @param array $array
     * @param int $percentile
     * @return float
     */
    protected function calculatePercentile($array, $percentile)
    {
        sort($array);
        $count = count($array);

        if ($count == 0) {
            return 0;
        }

        if ($count == 1) {
            return $array[0];
        }

        $rank = ($percentile / 100) * ($count - 1);
        $low = floor($rank);
        $high = ceil($rank);

        if ($low == $high) {
            return $array[$low];
        }

        return $array[$low] + ($array[$high] - $array[$low]) * ($rank - $low);
    }
}
