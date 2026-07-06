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
        'has_membership',
    ];

    protected $casts = [
        'order_date'   => 'datetime',
        'total_amount' => 'decimal:2',
        'has_membership' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Mapa canónico de estados de Woo (value => etiqueta visible).
     * Única fuente de verdad para labels: la usan tanto las instancias
     * (statusLabel) como el dropdown de filtros en el controller.
     */
    public const STATUS_LABELS = [
        'pending'    => 'Pendiente de pago',
        'pre-orden'  => 'Pre orden',
        'processing' => 'Procesando',
        'on-hold'    => 'En espera',
        'completed'  => 'Completada',
        'cancelled'  => 'Cancelada',
        'refunded'   => 'Reembolsada',
        'failed'     => 'Fallida',
    ];

    public function statusColor(): string
    {
        return match ($this->wc_status) {
            'pending'    => 'amber',
            'pre-orden'  => 'violet',   // elegí el color que prefieras
            'processing' => 'blue',
            'on-hold'    => 'amber',
            'completed'  => 'emerald',
            'cancelled'  => 'zinc',
            'refunded'   => 'zinc',
            'failed'     => 'red',
            default      => 'zinc',
        };
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->wc_status] ?? $this->wc_status;
    }

    /**
     * Progreso de ASIGNACIÓN — ortogonal al status de Woo.
     * No es un "status" que compita con wc_status: solo dice cuántos
     * items ya tienen cuenta asignada y cuántos le faltan al operador.
     */
    public function assignmentProgress(): array
    {
        $relevant = $this->items->where('fulfillment_status', '!=', 'replaced');
        $total    = $relevant->count();
        $assigned = $relevant->whereNotNull('account_id')->count();

        return [
            'total'     => $total,
            'assigned'  => $assigned,
            'remaining' => $total - $assigned,
            'complete'  => $total > 0 && $assigned === $total,
        ];
    }
    
}
/**
 * Config. Definí estas constantes en wp-config.php, NO acá:
 *  
 *   define('PACK_API_BASE', 'https://gestion-cuentas.manitto.cloud/api/pack/candidates');
 *   define('PACK_API_KEY',  '3327805692dd67a56e77b9219b28dc26cc38d4d6080d0b0916633584d8419fa2');
 *
 * Así la key no queda en el repo del plugin y podés tener valores
 * distintos en staging/prod.
 */