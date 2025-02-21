<?php

namespace App\Console\Commands;

use App\Models\Item;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class sdeImportActivityMaterials extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sde:import:activity_materials';
    protected $prefix = 'ActivityMaterials (industryActivityMaterials) : ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import SDE industryActivityMaterials data into the base';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startTime = microtime(true);
        $this->info($this->prefix . 'Importing');

        $fileInvTypes = storage_path('sde/industryActivityMaterials.csv');
        $file = fopen($fileInvTypes, 'r');

        // Skip the first line
        fgetcsv($file);

        // Drop the content of items_refined
        DB::table('items_activity_materials')->truncate();

        // Loop over the file and insert the data into items_refined accordingly
        $materialsToInsert = [];
        while ($data = fgetcsv($file)) {

            $materialsToInsert[] = [
                'item_id' => $data[0],
                'activity' => $data[1],
                'material_id' => $data[2],
                'quantity' => $data[3],
            ];

            // Chunk the data and insert into the database, or the final insert is gonna take 2 days.
            if (count($materialsToInsert) >= 1000) {
                DB::table('items_activity_materials')->insert($materialsToInsert);
                $materialsToInsert = [];
            }
        }


        if (!empty($materialsToInsert)) {
            DB::table('items_activity_materials')->insert($materialsToInsert);
            $materialsToInsert = [];
        }


        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        $this->info($this->prefix . 'Finished (' . round($executionTime, 2) . ' seconds)');
    }
}
