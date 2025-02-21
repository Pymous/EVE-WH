<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('items_refined', function (Blueprint $table) {
            // Here, we'll store the list of items an item can refine into, it's based on SDE invTypeMaterials
            // typeID is the item that can be refined
            // materialTypeID is the item that it refines into
            // quantity is the amount of materialTypeID that is produced from refining typeID
            $table->integer('item_id');
            $table->integer('material_id');
            $table->integer('quantity')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items_refined');
    }
};
