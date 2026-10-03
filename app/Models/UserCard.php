<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCard extends Model
{
    /**
     * The physical grades a card can be stored in.
     *
     * @var list<string>
     */
    public const CONDITIONS = [
        'Near Mint',
        'Lightly Played',
        'Moderately Played',
        'Heavily Played',
        'Damaged',
    ];

    /**
     * The card languages that can be stored.
     *
     * @var list<string>
     */
    public const LANGUAGES = [
        'English',
        'Japanese',
        'Korean',
        'Chinese',
        'French',
        'German',
        'Italian',
        'Spanish',
        'Portuguese',
    ];

    /**
     * The most copies of a single card a user may own.
     */
    public const MAX_QUANTITY = 999;

    /**
     * The grade assumed when a card is stored without one.
     *
     * This is also the column default. `condition` is part of the unique
     * index over `user_id`, `card_id`, `condition`, and `language`, and
     * databases treat NULLs as distinct in a unique index, so a nullable
     * condition would let duplicate rows for the same card through.
     */
    public const DEFAULT_CONDITION = 'Near Mint';

    /**
     * The default grade applied to new models.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'condition' => self::DEFAULT_CONDITION,
    ];

    protected $fillable = [
        'user_id',
        'card_id',
        'quantity',
        'condition',
        'language',
        'purchase_price',
        'purchase_date',
        'market_price',
        'market_price_updated_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'purchase_date' => 'date',
            'market_price' => 'decimal:2',
            'market_price_updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Card, $this>
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }
}
