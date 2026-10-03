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
        Schema::create('cards', function (Blueprint $table) {
            $table->id();

            // Pokémon TCG API identifier, e.g. "sv8pt5-2"
            $table->string('external_id')->unique();

            $table->foreignId('set_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('number');
            $table->string('rarity')->nullable();

            $table->string('image_small')->nullable();
            $table->string('image_large')->nullable();

            $table->timestamps();

            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
