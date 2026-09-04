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
    Schema::create('sets', function (Blueprint $table) {
        $table->id();

        // Pokémon TCG API identifiers
        $table->string('external_id')->unique();

        $table->string('name');
        $table->string('series')->nullable();

        $table->unsignedInteger('printed_total')->nullable();
        $table->date('release_date')->nullable();

        $table->string('logo_url')->nullable();
        $table->string('symbol_url')->nullable();

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sets');
    }
};
