<?php

namespace App\Console\Commands;

use App\Models\Item;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class sdeImportItems extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sde:import:items';
    protected $prefix = 'Items (invTypes) : ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import SDE invTypes data into the base';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startTime = microtime(true);
        $this->info($this->prefix . 'Importing');

        $fileInvTypes = storage_path('sde/invTypes.csv');
        $file = fopen($fileInvTypes, 'r');
        $header = fgetcsv($file); // The first line are the headers, so we just snatch them and use them as keys

        DB::table('items')->truncate();

        // We'll be inserting a lot of data, so we'll chunk it to avoid memory issues and speed up the process
        $itemsToInsert = [];
        while ($data = fgetcsv($file)) {
            $data = array_combine($header, $data);

            // Check that marketGroupId is not empty/None, we selling stuff over here !
            // if (empty($data['marketGroupID']) || $data['marketGroupID'] === 'None') {
            //     continue;
            // }

            $itemsToInsert[] = [
                'id' => $data['typeID'],
                'name' => $data['typeName'],
                'group_id' => $data['groupID'],
                'market_group_id' => $data['marketGroupID'],
                'description' => $data['description'],
                'mass' => $data['mass'],
                'volume' => $data['volume'],
                'capacity' => $data['capacity'],
                'portion_size' => $data['portionSize'],
                'race_id' => $data['raceID'],
                'base_price' => $data['basePrice'],
                'published' => $data['published'],
                'sound_id' => $data['soundID'],
                'graphic_id' => $data['graphicID'],
                'icon_id' => $data['iconID'],
            ];

            // Chunk the data and insert into the database, or the final insert is gonna take 2 days.
            if (count($itemsToInsert) >= 1000) {
                DB::table('items')->insert($itemsToInsert);
                $itemsToInsert = [];
            }
        }

        // Insert any remaining items
        if (!empty($itemsToInsert)) {
            DB::table('items')->insert($itemsToInsert);
        }

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        $this->info($this->prefix . 'Finished (' . round($executionTime, 2) . ' seconds)');
    }
}
