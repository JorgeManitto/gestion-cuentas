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
        'price',
        'price_sale',
        'is_preorden',
        'is_pack',
        'account_id',
        'activation_key',
        'who_delivered',
        'delivery_date',
        'fulfillment_status',
        'replaced_by_item_id',
    ];

    protected $casts = [
        'delivery_date' => 'datetime',
        'price'         => 'decimal:2',
        'price_sale'    => 'decimal:2',
        'is_preorden'   => 'boolean',
        'is_pack'       => 'boolean',
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

    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'replaced_by_item_id');
    }

    public function replacements(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderItem::class, 'replaced_by_item_id');
    }

    public function fulfillmentColor(): string
    {
        return match ($this->fulfillment_status) {
            'pending'     => 'amber',
            'in_progress' => 'blue',
            'delivered'   => 'emerald',
            'replaced'    => 'zinc',   // ← nuevo
            'cancelled'   => 'zinc',
            'failed'      => 'red',
            default       => 'zinc',
        };
    }
    public function assignment(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AccountAssignment::class);
    }

    public function secondaryAssignments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AccountSecondaryAssignment::class, 'order_item_id');
    }
    
}