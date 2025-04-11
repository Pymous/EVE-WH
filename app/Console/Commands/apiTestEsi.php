<?php

namespace App\Console\Commands;

use App\Services\Eve\EveEsi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class apiTestEsi extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:test:esi {--extended : Run extended tests}';
    protected $prefix = 'ESI Test : ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test EVE ESI API connection and fetch market prices directly';

    /**
     * The EVE ESI service
     *
     * @var \App\Services\Eve\EveEsi
     */
    protected $esiService;

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
        $this->info($this->prefix . 'Starting EVE ESI API Test');
        $startTime = microtime(true);

        try {
            $this->testBasicConnection();
            $this->testMarketPrices();

            // Run extended tests if requested
            if ($this->option('extended')) {
                $this->testRegionMarketOrders();
                $this->testUniverseType();
            }

            $executionTime = round(microtime(true) - $startTime, 2);
            $this->info($this->prefix . "All tests completed successfully! (Total time: {$executionTime}s)");
            return 0;
        } catch (\Exception $e) {
            $this->error($this->prefix . 'Test failed: ' . $e->getMessage());
            Log::error('EVE ESI API Test failed: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Test basic connection to EVE ESI API
     */
    protected function testBasicConnection()
    {
        $this->info($this->prefix . 'Testing basic connection to EVE ESI API...');

        if (!$this->esiService->testConnection()) {
            throw new \Exception('Failed to connect to EVE ESI API');
        }

        $this->info($this->prefix . '✓ Successfully connected to EVE ESI API');
    }

    /**
     * Test fetching market prices
     */
    protected function testMarketPrices()
    {
        $this->info($this->prefix . 'Testing getMarketPrices method...');
        $startTime = microtime(true);

        $prices = $this->esiService->getMarketPrices();
        $executionTime = round(microtime(true) - $startTime, 2);

        if (!$prices) {
            throw new \Exception('Failed to fetch market prices (took ' . $executionTime . ' seconds)');
        }

        $this->info($this->prefix . '✓ Successfully fetched ' . count($prices) . ' market prices (took ' . $executionTime . ' seconds)');

        // Display sample of the prices
        $this->info($this->prefix . 'Sample of market prices:');
        $sample = collect($prices)->take(5)->map(function ($item) {
            return [
                'type_id' => $item['type_id'],
                'adjusted_price' => $item['adjusted_price'] ?? 'N/A',
                'average_price' => $item['average_price'] ?? 'N/A',
            ];
        })->toArray();

        $this->table(
            ['Type ID', 'Adjusted Price', 'Average Price'],
            $sample
        );
    }

    /**
     * Test fetching market orders for a region
     */
    protected function testRegionMarketOrders()
    {
        $regionId = 10000002; // The Forge (Jita)
        $typeId = 34; // Tritanium

        $this->info($this->prefix . "Testing market orders for Tritanium in The Forge...");
        $startTime = microtime(true);

        $orders = $this->esiService->getMarketOrders($regionId, null, $typeId);
        $executionTime = round(microtime(true) - $startTime, 2);

        if (empty($orders)) {
            $this->warn($this->prefix . "No market orders found for Tritanium in The Forge (took {$executionTime}s)");
            return;
        }

        $ordersCollection = collect($orders);
        $buyCount = $ordersCollection->where('is_buy_order', true)->count();
        $sellCount = $ordersCollection->where('is_buy_order', false)->count();

        $this->info($this->prefix . "✓ Successfully fetched {$buyCount} buy and {$sellCount} sell orders for Tritanium (took {$executionTime}s)");

        // Display sample orders
        if ($ordersCollection->isNotEmpty()) {
            $this->info($this->prefix . 'Sample of market orders:');
            $sample = $ordersCollection->take(3)->map(function ($order) {
                return [
                    'order_id' => $order['order_id'],
                    'type' => $order['is_buy_order'] ? 'Buy' : 'Sell',
                    'price' => number_format($order['price'], 2),
                    'volume' => $order['volume_remain'] . '/' . $order['volume_total'],
                    'location' => $order['location_id'],
                ];
            })->toArray();

            $this->table(
                ['Order ID', 'Type', 'Price', 'Volume (Remain/Total)', 'Location'],
                $sample
            );
        }
    }

    /**
     * Test fetching universe type information
     */
    protected function testUniverseType()
    {
        $typeId = 34; // Tritanium

        $this->info($this->prefix . "Testing universe type information for Tritanium (ID: {$typeId})...");
        $startTime = microtime(true);

        $typeInfo = $this->esiService->getUniverseType($typeId);
        $executionTime = round(microtime(true) - $startTime, 2);

        if (!$typeInfo) {
            $this->warn($this->prefix . "Failed to fetch type information for Tritanium (took {$executionTime}s)");
            return;
        }

        $this->info($this->prefix . "✓ Successfully fetched type information for {$typeInfo['name']} (took {$executionTime}s)");

        // Display type information
        $this->info($this->prefix . 'Type information:');
        $info = [
            'name' => $typeInfo['name'],
            'group_id' => $typeInfo['group_id'],
            'volume' => $typeInfo['volume'],
            'portion_size' => $typeInfo['portion_size'],
            'description' => substr($typeInfo['description'], 0, 50) . '...'
        ];

        $this->table(
            ['Property', 'Value'],
            collect($info)->map(function ($value, $key) {
                return ['property' => $key, 'value' => $value];
            })->toArray()
        );
    }
}
