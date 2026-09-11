<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Card extends Model
{
    protected $fillable = [
        'external_id',
        'set_id',
        'name',
        'number',
        'rarity',
        'image_small',
        'image_large',
    ];

    public function set(): BelongsTo
    {
        return $this->belongsTo(Set::class);
    }

    public function userCards(): HasMany
{
    return $this->hasMany(UserCard::class);
}
}