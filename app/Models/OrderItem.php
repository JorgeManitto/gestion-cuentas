<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'wc_product_id',
        'game_id',
        'game_title',
        'platform',
        'console_model_raw',
        'platform_normalized',
        'quantity',
        'account_id',
        'activation_key',
        'who_delivered',
        'delivery_date',
        'fulfillment_status',
    ];

    protected $casts = [
        'delivery_date' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function wooProduct(): BelongsTo
    {
        return $this->belongsTo(WooProduct::class, 'wc_product_id');
    }

    public function purchaseOrders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchaseOrder::class)->orderByDesc('created_at');
    }

    /** La OC activa más reciente para este item, o null. */
    public function activePurchaseOrder(): ?PurchaseOrder
    {
        return $this->purchaseOrders
            ->whereIn('status', ['pending', 'purchased'])
            ->first();
    }

    public function fulfillmentColor(): string
    {
        return match ($this->fulfillment_status) {
            'pending'     => 'amber',
            'in_progress' => 'blue',
            'delivered'   => 'emerald',
            'cancelled'   => 'zinc',
            'failed'      => 'red',
            default       => 'zinc',
        };
    }
}
