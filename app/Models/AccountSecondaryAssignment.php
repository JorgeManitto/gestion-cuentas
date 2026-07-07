<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountSecondaryAssignment extends Model
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
        'pack_game_id',
        'pack_game_title',
    ];

    protected $casts = [
        'assigned_at' => 'date',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
    
}