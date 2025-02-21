<?php

namespace App\Console\Commands;

use App\Models\Item;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class sdeImportTypeMaterials extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sde:import:type_materials';
    protected $prefix = 'Refined (invTypeMaterials) : ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import SDE invTypeMaterials data into the base';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startTime = microtime(true);
        $this->info($this->prefix . 'Importing');

        $fileInvTypes = storage_path('sde/invTypeMaterials.csv');
        $file = fopen($fileInvTypes, 'r');

        // Skip the first line
        fgetcsv($file);

        // Drop the content of items_refined
        DB::table('items_refined')->truncate();

        // Loop over the file and insert the data into items_refined accordingly
        $refinedToInsert = [];
        while ($data = fgetcsv($file)) {

            $refinedToInsert[] = [
                'item_id' => $data[0],
                'material_id' => $data[1],
                'quantity' => $data[2] / 100, // The quantity in the file is for a BATCH of 100, we rectify that here
            ];

            // Chunk the data and insert into the database, or the final insert is gonna take 2 days.
            if (count($refinedToInsert) >= 1000) {
                DB::table('items_refined')->insert($refinedToInsert);
                $refinedToInsert = [];
            }
        }


        if (!empty($refinedToInsert)) {
            DB::table('items_refined')->insert($refinedToInsert);
            $refinedToInsert = [];
        }


        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        $this->info($this->prefix . 'Finished (' . round($executionTime, 2) . ' seconds)');
    }
}
