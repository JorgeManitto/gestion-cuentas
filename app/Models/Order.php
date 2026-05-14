<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'wc_order_id',
        'customer_name',
        'customer_email',
        'currency',
        'total_raw',
        'total_amount',
        'wc_status',
        'order_date',
    ];

    protected $casts = [
        'order_date'   => 'datetime',
        'total_amount' => 'decimal:2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Status agregado: lo que ve el operador en el listado.
     * Si todos los items están delivered → "delivered". Si alguno aún pending → "pending". Etc.
     */
    public function fulfillmentSummary(): string
    {
        $statuses = $this->items->pluck('fulfillment_status')->unique();
        if ($statuses->isEmpty())               return 'empty';
        if ($statuses->count() === 1)           return $statuses->first();
        if ($statuses->contains('failed'))      return 'failed';
        if ($statuses->contains('pending'))     return 'mixed';
        if ($statuses->contains('in_progress')) return 'in_progress';
        return 'delivered';
    }

    public function fulfillmentColor(): string
    {
        return match ($this->fulfillmentSummary()) {
            'pending'     => 'amber',
            'in_progress' => 'blue',
            'mixed'       => 'amber',
            'delivered'   => 'emerald',
            'failed'      => 'red',
            'cancelled'   => 'zinc',
            default       => 'zinc',
        };
    }

    /** Color para el status que vino de Woo (pago). */
    public function wcStatusColor(): string
    {
        return match ($this->wc_status) {
            'pending'    => 'amber',
            'processing' => 'blue',
            'on-hold'    => 'amber',
            'completed'  => 'emerald',
            'cancelled'  => 'zinc',
            'refunded'   => 'zinc',
            'failed'     => 'red',
            default      => 'zinc',
        };
    }
}
