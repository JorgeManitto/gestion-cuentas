<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'order_item_id',
        'game_id',
        'game_title',
        'platform',
        'console_model',
        'region',
        'quantity',
        'status',
        'notes',
        'arrival_date',
    ];

    protected $casts = [
        'arrival_date' => 'date',
    ];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** Estados que cuentan como "todavía pendiente" (no entregada al inventario aún). */
    public function isActive(): bool
    {
        return in_array($this->status, ['pending', 'purchased']);
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'pending'   => 'amber',
            'purchased' => 'blue',
            'received'  => 'emerald',
            'cancelled' => 'zinc',
            default     => 'zinc',
        };
    }
}
