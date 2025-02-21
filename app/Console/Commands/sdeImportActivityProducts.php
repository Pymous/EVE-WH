<?php

namespace App\Console\Commands;

use App\Models\Item;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class sdeImportActivityProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sde:import:activity_products';
    protected $prefix = 'ActivityProducts (industryActivityProducts) : ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import SDE industryActivityProducts data into the base';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startTime = microtime(true);
        $this->info($this->prefix . 'Importing');

        $fileInvTypes = storage_path('sde/industryActivityProducts.csv');
        $file = fopen($fileInvTypes, 'r');

        // Skip the first line
        fgetcsv($file);

        // Drop the content of items_refined
        DB::table('items_activity_products')->truncate();

        // Loop over the file and insert the data into items_refined accordingly
        $productsToInsert = [];
        while ($data = fgetcsv($file)) {

            $productsToInsert[] = [
                'item_id' => $data[0],
                'activity' => $data[1],
                'product_id' => $data[2],
                'quantity' => $data[3],
            ];

            // Chunk the data and insert into the database, or the final insert is gonna take 2 days.
            if (count($productsToInsert) >= 1000) {
                DB::table('items_activity_products')->insert($productsToInsert);
                $productsToInsert = [];
            }
        }


        if (!empty($productsToInsert)) {
            DB::table('items_activity_products')->insert($productsToInsert);
            $productsToInsert = [];
        }


        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        $this->info($this->prefix . 'Finished (' . round($executionTime, 2) . ' seconds)');
    }
}
