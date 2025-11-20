<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemPrice extends Model
{
    protected $table = 'items_prices';
    protected $primaryKey = 'item_id';
    public $incrementing = false;

    protected $fillable = [
        'item_id',
        'jita',
        'amarr',
        'dodixie',
        'hek',
        'rens',
    ];

    protected $casts = [
        'jita' => 'array',
        'amarr' => 'array',
        'dodixie' => 'array',
        'hek' => 'array',
        'rens' => 'array',
    ];
}
