<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\ItemPrice;
use Illuminate\Console\Command;

class apiFuzzPrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:fuzz:prices';
    protected $prefix = 'Fuzz (MarketPrices) : ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pull prices from Fuzzwork Market API and store them in the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startTime = microtime(true);
        $this->info($this->prefix . 'Starting');

        // Get all the items that are in the market groups and with a name starting with Compressed%
        $marketGroups = [3638, 3639, 3640, 518, 519, 515, 516, 526, 523, 529, 528, 527, 525, 522, 521, 514, 512, 517, 2538, 2539, 2540, 530, 3487, 3488, 3489, 3490, 1855, 792, 614, 2814, 2396, 2397, 2398, 2400, 2401, 20, 3636, 3637];
        $items = Item::whereIn('market_group_id', $marketGroups)->where('name', 'like', 'Compressed%')->get();
        $itemsIds = $items->pluck('id')->toArray();

        // 60003760 is Jita IV - Moon 4 - Caldari Navy Assembly Plant
        // 60008494 is Amarr VIII (Oris) - Emperor Family Academy
        // 60011866 is Dodixie IX - Moon 20 - Federation Navy Assembly Plant
        // 60005686 is Rens VI - Moon 8 - Brutor Tribe Treasury
        // 60003760 is Hek VIII - Moon 12 - Boundless Creation Factory
        $stations = [
            "jita" => 60003760,
            "amarr" => 60008494,
            "dodixie" => 60011866,
            "rens" => 60005686,
            "hek" => 60003760,
        ];

        // Loop over each $stations
        foreach ($stations as $station => $stationId) {
            $this->info($this->prefix . 'Getting prices for ' . ucfirst($station));

            $url = 'https://market.fuzzwork.co.uk/aggregates/?station=' . $stationId . '&types=' . implode(',', $itemsIds);
            $response = file_get_contents($url);
            $response = json_decode($response);


            foreach ($response as $k => $item) {
                $itemPrice = ItemPrice::updateOrCreate(
                    ['item_id' => $k],
                    [
                        $station => $item,
                    ]
                );
            }
        }

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        $this->info($this->prefix . 'Finished (' . round($executionTime, 2) . ' seconds)');
    }
}
