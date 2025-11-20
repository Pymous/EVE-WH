<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Yaml\Yaml;

class sdeImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sde:import {--type=* : Specific types to import (items, type_materials, activity_materials, activity_products)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate YAML files from the SDE to the internal DB';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Remove the memory limit to allow processing large files
        ini_set('memory_limit', '-1');
        // Set the maximum execution time to unlimited
        set_time_limit(0);

        $types = $this->option('type');

        // If specific types are provided, only import those
        if (!empty($types)) {
            foreach ($types as $type) {
                $this->importType($type);
            }
            return Command::SUCCESS;
        }

        // Otherwise, import all types
        $this->importType('items');
        $this->importType('type_materials');
        $this->importType('activity_materials');
        $this->importType('activity_products');

        $this->info("All imports completed!");

        return Command::SUCCESS;
    }

    /**
     * Import a specific type of data
     */
    protected function importType($type)
    {
        switch ($type) {
            case 'items':
                $this->importItems();
                break;
            case 'type_materials':
                $this->importTypeMaterials();
                break;
            case 'activity_materials':
                $this->importActivityMaterials();
                break;
            case 'activity_products':
                $this->importActivityProducts();
                break;
            default:
                $this->error("Unknown import type: $type");
        }
    }

    /**
     * Import items from types.yaml
     */
    protected function importItems()
    {
        $startTime = microtime(true);
        $this->info('Items (types.yaml) : Importing');

        $yamlFile = storage_path('sde/yaml/types.yaml');

        if (!File::exists($yamlFile)) {
            $this->error("File not found: $yamlFile");
            return;
        }

        $content = File::get($yamlFile);
        $data = Yaml::parse($content);
        unset($content);

        DB::table('items')->truncate();

        $itemsToInsert = [];
        $bar = $this->output->createProgressBar(count($data));
        $bar->start();

        foreach ($data as $typeID => $typeData) {
            $itemsToInsert[] = [
                'id' => $typeID,
                'name' => $this->extractEnglishText($typeData['name'] ?? ''),
                'group_id' => $typeData['groupID'] ?? null,
                'market_group_id' => $typeData['marketGroupID'] ?? null,
                'description' => $this->extractEnglishText($typeData['description'] ?? ''),
                'mass' => $typeData['mass'] ?? 0,
                'volume' => $typeData['volume'] ?? 0,
                'capacity' => $typeData['capacity'] ?? 0,
                'portion_size' => $typeData['portionSize'] ?? 1,
                'race_id' => $typeData['raceID'] ?? null,
                'base_price' => $typeData['basePrice'] ?? 0,
                'published' => $typeData['published'] ?? false,
                'sound_id' => $typeData['soundID'] ?? null,
                'graphic_id' => $typeData['graphicID'] ?? null,
                'icon_id' => $typeData['iconID'] ?? null,
            ];

            if (count($itemsToInsert) >= 1000) {
                DB::table('items')->insert($itemsToInsert);
                $itemsToInsert = [];
            }

            $bar->advance();
        }

        if (!empty($itemsToInsert)) {
            DB::table('items')->insert($itemsToInsert);
        }

        $bar->finish();
        $this->newLine();

        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 2);
        $this->info('Items (types.yaml) : Finished (' . $executionTime . ' seconds)');
    }

    /**
     * Import type materials from typeMaterials.yaml
     */
    protected function importTypeMaterials()
    {
        $startTime = microtime(true);
        $this->info('Refined (typeMaterials.yaml) : Importing');

        $yamlFile = storage_path('sde/yaml/typeMaterials.yaml');

        if (!File::exists($yamlFile)) {
            $this->error("File not found: $yamlFile");
            return;
        }

        $content = File::get($yamlFile);
        $data = Yaml::parse($content);
        unset($content);

        DB::table('items_refined')->truncate();

        $refinedToInsert = [];
        $bar = $this->output->createProgressBar(count($data));
        $bar->start();

        foreach ($data as $typeID => $typeData) {
            if (isset($typeData['materials']) && is_array($typeData['materials'])) {
                foreach ($typeData['materials'] as $material) {
                    $refinedToInsert[] = [
                        'item_id' => $typeID,
                        'material_id' => $material['materialTypeID'],
                        'quantity' => $material['quantity'],
                    ];

                    if (count($refinedToInsert) >= 1000) {
                        DB::table('items_refined')->insert($refinedToInsert);
                        $refinedToInsert = [];
                    }
                }
            }
            $bar->advance();
        }

        if (!empty($refinedToInsert)) {
            DB::table('items_refined')->insert($refinedToInsert);
        }

        $bar->finish();
        $this->newLine();

        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 2);
        $this->info('Refined (typeMaterials.yaml) : Finished (' . $executionTime . ' seconds)');
    }

    /**
     * Import activity materials from blueprints.yaml
     */
    protected function importActivityMaterials()
    {
        $startTime = microtime(true);
        $this->info('ActivityMaterials (blueprints.yaml) : Importing');

        $yamlFile = storage_path('sde/yaml/blueprints.yaml');

        if (!File::exists($yamlFile)) {
            $this->error("File not found: $yamlFile");
            return;
        }

        $content = File::get($yamlFile);
        $data = Yaml::parse($content);
        unset($content);

        DB::table('items_activity_materials')->truncate();

        $materialsToInsert = [];
        $bar = $this->output->createProgressBar(count($data));
        $bar->start();

        foreach ($data as $blueprintID => $blueprintData) {
            if (isset($blueprintData['activities']) && is_array($blueprintData['activities'])) {
                foreach ($blueprintData['activities'] as $activityName => $activityData) {
                    $activityID = $this->getActivityID($activityName);

                    if (isset($activityData['materials']) && is_array($activityData['materials'])) {
                        foreach ($activityData['materials'] as $material) {
                            $materialsToInsert[] = [
                                'item_id' => $blueprintID,
                                'activity' => $activityID,
                                'material_id' => $material['typeID'],
                                'quantity' => $material['quantity'],
                            ];

                            if (count($materialsToInsert) >= 1000) {
                                DB::table('items_activity_materials')->insert($materialsToInsert);
                                $materialsToInsert = [];
                            }
                        }
                    }
                }
            }
            $bar->advance();
        }

        if (!empty($materialsToInsert)) {
            DB::table('items_activity_materials')->insert($materialsToInsert);
        }

        $bar->finish();
        $this->newLine();

        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 2);
        $this->info('ActivityMaterials (blueprints.yaml) : Finished (' . $executionTime . ' seconds)');
    }

    /**
     * Import activity products from blueprints.yaml
     */
    protected function importActivityProducts()
    {
        $startTime = microtime(true);
        $this->info('ActivityProducts (blueprints.yaml) : Importing');

        $yamlFile = storage_path('sde/yaml/blueprints.yaml');

        if (!File::exists($yamlFile)) {
            $this->error("File not found: $yamlFile");
            return;
        }

        $content = File::get($yamlFile);
        $data = Yaml::parse($content);
        unset($content);

        DB::table('items_activity_products')->truncate();

        $productsToInsert = [];
        $bar = $this->output->createProgressBar(count($data));
        $bar->start();

        foreach ($data as $blueprintID => $blueprintData) {
            if (isset($blueprintData['activities']) && is_array($blueprintData['activities'])) {
                foreach ($blueprintData['activities'] as $activityName => $activityData) {
                    $activityID = $this->getActivityID($activityName);

                    if (isset($activityData['products']) && is_array($activityData['products'])) {
                        foreach ($activityData['products'] as $product) {
                            $productsToInsert[] = [
                                'item_id' => $blueprintID,
                                'activity' => $activityID,
                                'product_id' => $product['typeID'],
                                'quantity' => $product['quantity'],
                            ];

                            if (count($productsToInsert) >= 1000) {
                                DB::table('items_activity_products')->insert($productsToInsert);
                                $productsToInsert = [];
                            }
                        }
                    }
                }
            }
            $bar->advance();
        }

        if (!empty($productsToInsert)) {
            DB::table('items_activity_products')->insert($productsToInsert);
        }

        $bar->finish();
        $this->newLine();

        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 2);
        $this->info('ActivityProducts (blueprints.yaml) : Finished (' . $executionTime . ' seconds)');
    }

    /**
     * Convert activity name to activity ID
     */
    protected function getActivityID($activityName)
    {
        $activityMap = [
            'manufacturing' => 1,
            'researching_technology' => 2,
            'research_time' => 3,
            'research_material' => 4,
            'copying' => 5,
            'duplicating' => 6,
            'reverse_engineering' => 7,
            'invention' => 8,
            'reactions' => 9,
        ];

        return $activityMap[$activityName] ?? 0;
    }

    /**
     * Extract English text from multilanguage field or return as-is
     */
    protected function extractEnglishText($field)
    {
        if (is_array($field)) {
            return $field['en'] ?? (reset($field) ?: '');
        }
        return $field;
    }
}
