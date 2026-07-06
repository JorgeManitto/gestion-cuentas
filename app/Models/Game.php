<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    protected $fillable = [
        'canonical_name',
        'slug',
        'normalized_name',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(WooProduct::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    /**
     * Cover representativo del juego: primera imagen no-null entre sus productos.
     * Cargá la relación products antes (with('products')) para evitar N+1.
     */
    protected function coverImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->products
                ->pluck('image_url')
                ->filter()
                ->first()
        );
    }

    public function productForPlatform(?string $platform): ?WooProduct
    {
        if (! $this->relationLoaded('products')) {
            $this->load('products');
        }

        return $this->products->first(
            fn ($p) => WooProduct::normalizePlatform($p->platform) === $platform
        );
    }

    /** Saca el sufijo de plataforma del final del nombre ("Dead Space - STEAM" → "Dead Space"). */
    public static function stripPlatform(?string $name): string
    {
        $clean = preg_replace(
            '/[\s\-\(]+(?:ps5|ps4|steam|pc|xbox(?:\s+one|\s+series\s*x[\s\/\|]?s)?|nintendo\s+switch(?:\s*2)?|switch(?:\s*2)?)\)?\s*$/i',
            '',
            (string) $name
        );
        return trim($clean) !== '' ? trim($clean) : (string) $name;
    }

    public function displayName(): string
    {
        return static::stripPlatform($this->canonical_name);
    }
    /** Detecta la plataforma a partir del sufijo del nombre. Null si no hay pista. */
    public static function platformFromName(?string $name): ?string
    {
        if (! $name) return null;
        $n = mb_strtoupper($name);

        return match (true) {
            str_contains($n, 'SWITCH 2'), str_contains($n, 'SWITCH2')  => 'SWITCH_2',
            str_contains($n, 'SWITCH')                                 => 'SWITCH',
            str_contains($n, 'PS5'), str_contains($n, 'PLAYSTATION 5') => 'PS5',
            str_contains($n, 'PS4'), str_contains($n, 'PLAYSTATION 4') => 'PS4',
            str_contains($n, 'SERIES')                                 => 'XBOX_SERIES',
            str_contains($n, 'XBOX ONE')                               => 'XBOX_ONE',
            str_contains($n, 'STEAM'), str_contains($n, ' PC')         => 'STEAM',
            default                                                    => null,
        };
    }
}
