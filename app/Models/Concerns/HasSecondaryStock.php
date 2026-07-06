<?php

namespace App\Models\Concerns;

use Carbon\Carbon;
/**
 * Lógica de elegibilidad para STOCK SECUNDARIO.
 *
 * REGLA DE NEGOCIO
 * ----------------
 * Una cuenta solo puede MOSTRAR stock secundario o ENVIAR cupos secundarios cuando:
 *   1. Todos los cupos PRINCIPALES están completos (no quedan slots libres).
 *   2. Pasaron al menos N meses (por defecto 2) desde la ÚLTIMA VENTA PRINCIPAL.
 * Si NO se cumplen AMBAS, no se muestra stock secundario ni se envían cupos secundarios.
 *
 * SUPUESTOS (ajustá si tu modelo difiere — están aislados, ver más abajo)
 * ----------------------------------------------------------------------
 *   - "Cupos principales" = la capacidad normal de la cuenta. Un cupo está OCUPADO por
 *     cualquier assignment activa (incluye placeholders de uso manual). Se evalúa con
 *     el freeSlotsFor() que ya tenés en el modelo.
 *   - "Venta principal" = assignment CON datos de cliente reales (customer_name o
 *     customer_email no nulos). Definida en primarySalesQuery() para cambiarla en un solo lugar.
 *   - La fecha de la venta se toma de assigned_at; si es null, se cae a created_at.
 */
trait HasSecondaryStock
{
    /**
     * Meses de enfriamiento desde la última venta principal.
     * Override este método en el modelo Account si querés otro valor.
     */
    // public function secondaryCooldownMonths(): int
    // {
    //     return 2;
    // }

    /**
     * PUNTO DE AJUSTE 1 — ¿qué es una "venta principal"?
     *
     * Por defecto: assignment con datos de cliente reales (descarta placeholders).
     * Si tenés una columna dedicada, reemplazá el cuerpo, por ejemplo:
     *     return $this->assignments()->where('slot_type', 'principal');
     */
    protected function primarySalesQuery()
    {
        return $this->assignments()
            ->where(function ($q) {
                $q->whereNotNull('customer_name')
                  ->orWhereNotNull('customer_email');
            });
    }

    /**
     * PUNTO DE AJUSTE 2 — ¿están completos los cupos principales?
     *
     * Por defecto: ninguna plataforma cubierta tiene slots libres.
     * Si pasás $platform, evalúa solo esa consola.
     */
    public function primarySlotsFull(?string $platform = null): bool
    {
        $platforms = $platform !== null ? [$platform] : $this->coveredPlatforms();

        if (empty($platforms)) {
            return false; // sin plataformas cubiertas no hay cupos que completar
        }

        foreach ($platforms as $p) {
            if ($this->freeSlotsFor($p) > 0) {
                return false; // hay al menos un cupo principal libre
            }
        }

        return true;
    }

    /** Fecha de la última venta principal (o null si nunca hubo). */
    public function lastPrimarySaleAt(?string $platform = null): ?Carbon
    {
        $sale = $this->primarySalesQuery()
            ->when($platform !== null, fn ($q) => $q->where('platform', $platform))
            ->orderByRaw('COALESCE(assigned_at, created_at) DESC')
            ->first();

        if (! $sale) {
            return null;
        }

        $date = $sale->assigned_at ?? $sale->created_at;

        return $date instanceof Carbon ? $date : Carbon::parse($date);
    }

    /** Meses transcurridos desde la última venta principal (null si nunca hubo). */
    public function monthsSinceLastPrimarySale(?string $platform = null): ?float
    {
        $last = $this->lastPrimarySaleAt($platform);

        return $last ? round($last->floatDiffInMonths(now()), 1) : null;
    }

    /**
     * ¿Pasó el enfriamiento desde la última venta principal?
     *
     * NOTA: si NUNCA hubo venta principal, no hay "última venta" reciente que bloquee,
     * así que lo damos por cumplido. Si preferís exigir que haya existido una venta
     * principal antes de liberar secundario, cambiá `return true;` por `return false;`.
     */
    // public function secondaryCooldownPassed(?string $platform = null): bool
    // {
    //     $last = $this->lastPrimarySaleAt($platform);

    //     if (! $last) {
    //         return true;
    //     }

    //     return $last->lte(now()->subMonths($this->secondaryCooldownMonths()));
    // }

    /** La condición completa: ambas reglas a la vez. */
    // public function canOfferSecondary(?string $platform = null): bool
    // {
    //     return $this->primarySlotsFull($platform)
    //         && $this->secondaryCooldownPassed($platform);
    // }

    /**
     * Estado detallado para la UI: booleano final + por qué está bloqueado + cuándo se libera.
     */
    // public function secondaryEligibility(?string $platform = null): array
    // {
    //     $months      = $this->secondaryCooldownMonths();
    //     $lastSale    = $this->lastPrimarySaleAt($platform);
    //     $primaryFull = $this->primarySlotsFull($platform);
    //     $cooldownOk  = $this->secondaryCooldownPassed($platform);
    //     $availableAt = $lastSale ? $lastSale->copy()->addMonths($months) : null;

    //     $reasons = [];
    //     if (! $primaryFull) {
    //         $reasons[] = 'Todavía hay cupos principales libres.';
    //     }
    //     if (! $cooldownOk) {
    //         $reasons[] = $availableAt
    //             ? "No pasaron los {$months} meses desde la última venta principal (disponible el {$availableAt->format('Y-m-d')})."
    //             : "No pasaron los {$months} meses desde la última venta principal.";
    //     }

    //     return [
    //         'eligible'          => $primaryFull && $cooldownOk,
    //         'primary_full'      => $primaryFull,
    //         'cooldown_passed'   => $cooldownOk,
    //         'cooldown_months'   => $months,
    //         'last_primary_sale' => $lastSale,
    //         'months_since_sale' => $lastSale ? round($lastSale->floatDiffInMonths(now()), 1) : null,
    //         'available_at'      => $cooldownOk ? null : $availableAt,
    //         'reasons'           => $reasons,
    //     ];
    // }

    // trait HasSecondaryStock

    public function secondaryCooldownMonths(): int
    {
        return 2;
    }

    /** Reloj basado en la cascada del modelo: venta datada → compra → created_at. */
    public function secondaryCooldownPassed(?string $platform = null): bool
    {
        $ref = $this->secondaryStockReference();
        return $ref !== null
            && $ref->copy()->addMonths($this->secondaryCooldownMonths())->isPast();
    }

    public function canOfferSecondary(?string $platform = null): bool
    {
        return $this->primarySlotsFull($platform)
            && $this->secondaryCooldownPassed($platform);
    }

    public function secondaryEligibility(?string $platform = null): array
    {
        $months      = $this->secondaryCooldownMonths();
        $primaryFull = $this->primarySlotsFull($platform);
        $ref         = $this->secondaryStockReference();
        $lastSale    = $this->lastPrimarySaleAt($platform);   // sigue disponible para mostrar la venta REAL
        $availableAt = $ref?->copy()->addMonths($months);
        $cooldownOk  = $availableAt !== null && $availableAt->isPast();

        $reasons = [];
        if (! $primaryFull) {
            $reasons[] = 'Todavía hay cupos principales libres.';
        }
        if (! $cooldownOk) {
            $reasons[] = $availableAt
                ? "No pasaron los {$months} meses desde la referencia (disponible el {$availableAt->format('Y-m-d')})."
                : "Sin fecha de referencia para el enfriamiento.";
        }

        return [
            'eligible'          => $primaryFull && $cooldownOk,
            'primary_full'      => $primaryFull,
            'cooldown_passed'   => $cooldownOk,
            'cooldown_months'   => $months,
            'last_primary_sale' => $lastSale,                          // venta real (puede ser null)
            'months_since_sale' => $lastSale ? round($lastSale->floatDiffInMonths(now()), 1) : null,
            'available_at'      => $cooldownOk ? null : $availableAt,
            'reasons'           => $reasons,
        ];
}
}
