<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountAssignment extends Model
{
    protected $fillable = [
        'account_id',
        'slot_number',
        'platform',  
        'customer_name',
        'customer_email',
        'assigned_at',
        'status',
        'woo_order_id',
        'key_value',
        'key_position',
        'order_item_id',
    ];

    protected $casts = [
        'assigned_at' => 'date',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
    /**
     * Mapea el slot a su consola.
     * Slots 1-2 = PS4 (columnas Y/Z del xlsx); slots 3-4 = PS5 (columnas AA/AB).
     */
    private function slotPlatform(int $slot): string
    {
        return $slot <= 2 ? 'PS4' : 'PS5';
    }
}
