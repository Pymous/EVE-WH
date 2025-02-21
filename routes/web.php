<?php

use App\Models\Item;
use App\Models\ItemPrice;
use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

Route::get('/', function () {
    return Inertia::render('Home');
});

Route::get('/solo', function () {
    // 19 is Spodumain
    // 34 is Tritanium
    $item = Item::find(34);
    dd($item->refinedFrom[50]);
    $marketGroups = [3638, 3639, 3640, 518, 519, 515, 516, 526, 523, 529, 528, 527, 525, 522, 521, 514, 512, 517, 2538, 2539, 2540, 530, 3487, 3488, 3489, 3490, 1855, 772, 792, 614, 2814, 2396, 2397, 2398, 2400, 2401, 20, 3636, 3637];
});

Route::get('/mfg', function () {
    // 687 is the Caracal Blueprint
    // $item = Item::find(687);
    // dd($item->manufactureItem());
    // 621 is a Caracal
    $search = "Caracal";
    $item = Item::where('name', $search)->first();
    dd($item->bp()->manufactureMaterials);


    $item = Item::find(621);
    dd($item->bp());
    dd($item->manufactureMaterials);
});
