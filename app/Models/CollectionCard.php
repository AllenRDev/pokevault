<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionCard extends Model
{
    protected $fillable = [
        'collection_id',
        'card_id',
        'quantity',
        'condition',
        'language',
        'purchase_price',
        'purchase_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'purchase_date' => 'date',
        ];
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }
}