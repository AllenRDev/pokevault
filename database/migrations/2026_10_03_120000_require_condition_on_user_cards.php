<?php

use App\Models\UserCard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `condition` is part of the unique index over `user_id`, `card_id`,
     * `condition`, and `language`. Databases treat NULLs as distinct when
     * enforcing a unique index, so while the column was nullable two rows
     * could hold the same card with no grade recorded. The rows are
     * backfilled before the constraint is tightened.
     */
    public function up(): void
    {
        DB::table('user_cards')
            ->whereNull('condition')
            ->update(['condition' => UserCard::DEFAULT_CONDITION]);

        Schema::table('user_cards', function (Blueprint $table): void {
            $table->string('condition')
                ->default(UserCard::DEFAULT_CONDITION)
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_cards', function (Blueprint $table): void {
            $table->string('condition')
                ->nullable()
                ->default(null)
                ->change();
        });
    }
};
