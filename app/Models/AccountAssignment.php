<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountAssignment extends Model
{
    protected $fillable = [
        'account_id',
        'slot_number',
        'customer_name',
        'customer_email',
        'assigned_at',
        'status',
        'woo_order_id',
    ];

    protected $casts = [
        'assigned_at' => 'date',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
