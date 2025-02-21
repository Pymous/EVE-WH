<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $fillable = [
        'id',
        'name',
        'group_id',
        'market_group_id',
        'description',
        'mass',
        'volume',
        'capacity',
        'portion_size',
        'race_id',
        'base_price',
        'published',
        'sound_id',
        'graphic_id',
        'icon_id',
    ];

    /*
    ACTIVITY LIST : 
    1 : Manufacturing
    3 : Researching Time Efficiency
    4 : Researching Material Efficiency
    5 : Copying
    7 : Duplicating
    8 : Reverse Engineering / Invention
    */


    // an Item can have many refined materials that are also Item through the items_refined table, so this item.id is the item_id, and material_id is the id of the refined material
    public function refinedMaterials()
    {
        return $this->belongsToMany(Item::class, 'items_refined', 'item_id', 'material_id')->withPivot('quantity');
    }

    // Now we want the inverse of refinedMaterials, which go from a material to the items that can be refined from it
    public function refinedFrom()
    {
        return $this->belongsToMany(Item::class, 'items_refined', 'material_id', 'item_id')->withPivot('quantity');
    }

    // Return the needed material to use that Item (usually Item is a blueprint)
    public function manufactureMaterials()
    {
        return $this->belongsToMany(Item::class, 'items_activity_materials', 'item_id', 'material_id')->where('activity', '1')->withPivot(['activity', 'quantity']);
    }

    // return the Item that is produced by the current Item, through the items_activity_products
    public function manufacture()
    {
        return $this->belongsToMany(Item::class, 'items_activity_products', 'item_id', 'product_id')->withPivot(['activity', 'quantity']);
    }

    // manufactureItem return the first Item that manufacture() return that has an activity of 1, which is manufacturing
    public function manufactureItem()
    {
        return $this->manufacture()->wherePivot('activity', 1)->first();
    }

    // manufacturedBy is the inverse of manufacture, it returns the Item that is used to produce the current Item, which will usually be a blueprint
    public function manufacturedBy()
    {
        return $this->belongsToMany(Item::class, 'items_activity_products', 'product_id', 'item_id')->withPivot(['activity', 'quantity']);
    }

    // bp is the first Item that manufacturedBy() return that has an activity of 1, which is manufacturing
    public function bp()
    {
        return $this->manufacturedBy()->wherePivot('activity', 1)->first();
    }

    public function prices()
    {
        return $this->hasOne(ItemPrice::class, 'item_id', 'id');
    }
}
