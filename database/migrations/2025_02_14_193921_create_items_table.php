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
        Schema::create('items', function (Blueprint $table) {
            $table->integer('id'); // Will be fillable to take typeID
            $table->string('name');
            $table->integer('group_id')->nullable();
            $table->integer('market_group_id')->nullable();
            $table->text('description')->nullable();
            $table->float('mass')->nullable();
            $table->float('volume')->nullable();
            $table->float('capacity')->nullable();
            $table->integer('portion_size')->nullable();
            $table->integer('race_id')->nullable();
            $table->float('base_price')->nullable();
            $table->boolean('published')->nullable();
            $table->integer('sound_id')->nullable();
            $table->integer('graphic_id')->nullable();
            $table->integer('icon_id')->nullable();
            $table->timestamps();

            $table->unique('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
