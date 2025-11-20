<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\Request;

class ApiController
{

    public function searchItem(Request $request)
    {
        // Check if we have a input search and an input type
        $data = $request->validate([
            'search' => ['required', 'string'],
            'type' => ['sometimes', 'string'],
        ]);


        if (@$data['type'] === 'id') {
            $item = Item::where('id', $data['search'])->firstOrFail();
        } else {
            $item = Item::whereRaw('LOWER(name) = ?', [strtolower($data['search'])])->firstOrFail();
        }

        $bp = $item->bp()->load('manufactureMaterials');

        return response()->json([
            'item' => $item,
            'bp' => $bp,
        ]);
    }

    public function searchItems(Request $request)
    {
        $search = $request->input('search');
        $items = Item::whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%'])->get();

        return response()->json([
            'items' => $items,
        ]);
    }

    public function solverOres()
    {
        $marketGroups = [3638, 3639, 3640, 518, 519, 515, 516, 526, 523, 529, 528, 527, 525, 522, 521, 514, 512, 517, 2538, 2539, 2540, 530, 3487, 3488, 3489, 3490, 1855, 792, 614, 2814, 2396, 2397, 2398, 2400, 2401, 20, 3636, 3637];

        // Get all the items that are in the market groups and the name start with Compressed%
        $items = Item::whereIn('market_group_id', $marketGroups)->where('name', 'like', 'Compressed%')->with(['prices', 'refinedMaterials'])->get();

        $ores = [];
        foreach ($items as $item) {
            // If prices don't exist or jita sell percentile is null/0, skip it as it's not available
            if (!$item->prices || !isset($item->prices->jita['sell']['percentile']) || !$item->prices->jita['sell']['percentile']) {
                continue;
            }
            $ores[$item->name] = [
                'id' => $item->id,
                'cost' => $item->prices->jita['sell']['percentile'],
                'm3' => $item->volume,
            ];

            foreach ($item->refinedMaterials as $material) {
                $ores[$item->name][$material->name] = $material->pivot->quantity;
            }
        }

        return response()->json($ores);
    }

    public function getOresList()
    {
        $marketGroups = [3638, 3639, 3640, 518, 519, 515, 516, 526, 523, 529, 528, 527, 525, 522, 521, 514, 512, 517, 2538, 2539, 2540, 530, 3487, 3488, 3489, 3490, 1855, 792, 614, 2814, 2396, 2397, 2398, 2400, 2401, 20, 3636, 3637];

        // Get all the items that are in the market groups and the name start with Compressed%
        $items = Item::whereIn('market_group_id', $marketGroups)->where('name', 'like', 'Compressed%')->with(['prices', 'refinedMaterials'])->get();

        // Make a list of just the names and return that
        $ores = [];
        foreach ($items as $item) {
            $ores[] = $item->name;
        }

        return $ores;
    }
}
