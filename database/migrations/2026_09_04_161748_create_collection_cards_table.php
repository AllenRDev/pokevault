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
    Schema::create('collection_cards', function (Blueprint $table) {
        $table->id();

        $table->foreignId('collection_id')
            ->constrained()
            ->cascadeOnDelete();

        $table->foreignId('card_id')
            ->constrained()
            ->cascadeOnDelete();

        $table->unsignedInteger('quantity')->default(1);

        $table->string('condition')->nullable();

        $table->string('language')->default('English');

        $table->decimal('purchase_price', 10, 2)->nullable();

        $table->date('purchase_date')->nullable();

        $table->text('notes')->nullable();

        $table->timestamps();

        $table->unique([
            'collection_id',
            'card_id',
            'condition',
            'language',
        ]);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection_cards');
    }
};
