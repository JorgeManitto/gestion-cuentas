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
}
