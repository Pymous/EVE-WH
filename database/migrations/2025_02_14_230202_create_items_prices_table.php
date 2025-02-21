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
        Schema::create('items_prices', function (Blueprint $table) {
            $table->integer('item_id')->unique();
            $table->json('jita')->nullable();
            $table->json('amarr')->nullable();
            $table->json('dodixie')->nullable();
            $table->json('hek')->nullable();
            $table->json('rens')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items_prices');
    }
};
