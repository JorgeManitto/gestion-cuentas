<?php

namespace App\Services\Matchmaking;

use App\Models\Account;
use Carbon\Carbon;

/**
 * Una cuenta candidata para asignar a un OrderItem, con los datos
 * necesarios para mostrar al operador POR QUÉ la elegimos.
 */
readonly class Candidate
{
    public function __construct(
        public Account $account,
        public int     $remainingSlots,
        public int     $usedSlots,
        public int     $capacity,
        public bool    $isPostReset,
        public bool    $isTimeBlocked,
        public ?Carbon $timeBlockUnlockAt,
        public ?string $blockReason,
        public string  $selectionReason,
    ) {}

    /** Para mostrar en la UI: "3 cupos libres (1/4) · 8 llaves · comprada 14/03/2026" */
   public static function buildSelectionReason(Account $account, int $remaining, string $platform): string
    {
        $capacity = $account->capacityFor($platform);   // ← por consola, no total
        $used     = $capacity - $remaining;
        $keys     = $account->relationLoaded('keys')
            ? $account->keys->count()
            : $account->keys()->count();

        $parts = [];
        $parts[] = "$platform: $remaining cupo" . ($remaining === 1 ? '' : 's')
                . " libre" . ($remaining === 1 ? '' : 's')
                . " ($used/$capacity)";
        $parts[] = "$keys llave" . ($keys === 1 ? '' : 's');

        if ($account->isPostReset()) {
            $parts[] = "post-reset";
        }
        if ($account->purchased_date) {
            $parts[] = "comprada " . $account->purchased_date->format('d/m/Y');
        }

        return implode(' · ', $parts);
    }
}
