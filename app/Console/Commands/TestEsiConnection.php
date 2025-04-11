<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\Eve\EveEsi;

class TestEsiConnection extends Command
{
    protected $signature = 'eve:test-connection {--debug : Enable detailed debug output}';
    protected $description = 'Test connection to EVE ESI API';

    /**
     * The EVE ESI service
     * 
     * @var \App\Services\Eve\EveEsi
     */
    protected $esiService;

    /**
     * Create a new command instance
     * 
     * @param \App\Services\Eve\EveEsi $esiService
     * @return void
     */
    public function __construct(EveEsi $esiService)
    {
        parent::__construct();
        $this->esiService = $esiService;
    }

    public function handle()
    {
        $this->info('Testing connection to EVE ESI API...');
        $debug = $this->option('debug');

        if ($debug) {
            $this->info('Debug mode enabled - showing detailed information');
        }

        try {
            $baseUrl = 'https://esi.evetech.net/latest/';
            $endpoints = [
                'status' => 'status/',
                'market_prices' => 'markets/prices/',
                'forge_orders' => 'markets/10000002/orders/?page=1&type_id=34', // Test Tritanium in The Forge
            ];

            foreach ($endpoints as $name => $endpoint) {
                $url = $baseUrl . $endpoint;
                $this->info("Testing $name endpoint: $url");

                if ($debug) {
                    $this->info("Making request to: $url");
                }

                try {
                    // Use Laravel's HTTP facade for clean request handling with SSL verification disabled
                    $response = Http::withHeaders([
                        'User-Agent' => 'EVE-WH Testing',
                        'Accept' => 'application/json'
                    ])
                        ->timeout(30)
                        ->withoutVerifying() // Disable SSL verification for development
                        ->get($url);

                    $statusCode = $response->status();

                    $this->info("Response Status Code for $name: {$statusCode}");

                    if ($debug) {
                        $this->info("Headers: " . json_encode($response->headers()));
                        $this->info("Body sample: " . substr($response->body(), 0, 100) .
                            (strlen($response->body()) > 100 ? '...' : ''));
                    }

                    if ($response->successful()) {
                        $this->info("✓ Connection to $name endpoint successful!");
                        Log::info("EVE ESI $name connection test successful");
                    } else {
                        $this->error("✗ Connection to $name endpoint failed with status code: {$statusCode}");
                        Log::error("EVE ESI $name connection test failed: $statusCode");
                    }
                } catch (\Exception $e) {
                    $this->error("✗ Connection to $name endpoint failed with exception: " . $e->getMessage());
                    Log::error("EVE ESI $name connection test exception: " . $e->getMessage());

                    if ($debug) {
                        $this->error("Exception trace: " . $e->getTraceAsString());
                    }
                }

                // Pause briefly between requests to avoid rate limiting
                usleep(200000); // 200ms
            }

            // Also test using the EveEsi service
            $this->info("Testing connection using EveEsi service...");
            $serviceResult = $this->esiService->testConnection();

            if ($serviceResult) {
                $this->info("✓ EveEsi service connection test successful!");
            } else {
                $this->error("✗ EveEsi service connection test failed");
            }

            return 0;
        } catch (\Exception $e) {
            $this->error('General connection error: ' . $e->getMessage());
            Log::error('EVE ESI general connection error: ' . $e->getMessage());
            return 1;
        }
    }
}
