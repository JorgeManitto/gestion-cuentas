<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountKey extends Model
{
    protected $fillable = [
        'account_id',
        'key_value',
        'position',
        'used_at',
    ];

    protected $casts = [
        'used_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
