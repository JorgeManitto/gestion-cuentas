<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WooProduct extends Model
{
    // El id viene de WooCommerce, no es auto-increment
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'game_id',
        'name',
        'platform',
        'image_url',
        'category_raw',
        'last_synced_at',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
