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
    // app/Models/WooProduct.php

    public static function normalizePlatform(?string $raw): ?string
    {
        if (! $raw) return null;
        $p = mb_strtoupper(trim($raw));

        return match (true) {
            str_contains($p, 'SWITCH 2'), str_contains($p, 'SWITCH2')  => 'SWITCH_2',
            str_contains($p, 'SWITCH')                                 => 'SWITCH',
            str_contains($p, 'PS5'), str_contains($p, 'PLAYSTATION 5') => 'PS5',
            str_contains($p, 'PS4'), str_contains($p, 'PLAYSTATION 4') => 'PS4',
            str_contains($p, 'SERIES')                                 => 'XBOX_SERIES',
            str_contains($p, 'XBOX ONE'), $p === 'XBOX'                => 'XBOX_ONE',
            str_contains($p, 'STEAM'), str_contains($p, 'PC')          => 'STEAM',
            default                                                    => null,
        };
    }
}
