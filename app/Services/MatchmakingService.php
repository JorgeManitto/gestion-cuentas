<?php

namespace App\Services;

use App\Models\Account;
use App\Models\OrderItem;
use App\Services\Matchmaking\Candidate;
use App\Services\Matchmaking\MatchResult;
use Illuminate\Support\Facades\DB;

class MatchmakingService
{
    /**
     * Mapea la plataforma/consola del plugin WC a nuestros valores internos
     * de accounts.platform.
     */
    public function normalizePlatform(string $platform, ?string $consoleModel): string
    {
        $cm = strtolower($consoleModel ?? '');

        return match (true) {
            str_contains($cm, 'ps5')         => 'PS5',
            str_contains($cm, 'ps4')         => 'PS4',
            str_contains($cm, 'xbox series') => 'XBOX_SERIES',
            str_contains($cm, 'xbox one')    => 'XBOX_ONE',
            str_contains($cm, 'switch 2')    => 'SWITCH_2',
            str_contains($cm, 'switch 1')    => 'SWITCH',
            str_contains($cm, 'switch')      => 'SWITCH',
            default                          => 'UNKNOWN',
        };
    }

    /**
     * Encuentra candidatas + razones + best.
     */
    public function findCandidates(OrderItem $item): MatchResult
    {
        // ── Caso 1: no hay game_id resuelto ──
        if (! $item->game_id) {
            return new MatchResult(
                candidates: [],
                emptyReason: 'El producto Woo no está mapeado al catálogo. Importá woo_products o asigná manualmente.',
            );
        }

        $platforms = $this->compatibleAccountPlatforms($item->platform_normalized);

        // ── Traer accounts candidatas con TODO lo necesario eager loaded ──
        $accounts = Account::query()
            ->where('game_id', $item->game_id)
            ->whereIn('platform', $platforms)
            ->where('status', 'active')
            ->with(['assignments', 'keys'])
            ->get();

        // ── Caso 2: ninguna cuenta matchea por game+platform ──
        if ($accounts->isEmpty()) {
            return new MatchResult(
                candidates: [],
                emptyReason: $this->diagnoseEmpty($item, $platforms),
            );
        }

        // ── Construir candidatos ──
        $candidates = [];

        foreach ($accounts as $acc) {
            $remaining     = $acc->freeSlots();
            $isTimeBlocked = $acc->isTimeBlocked();
            $unlockAt      = $acc->timeBlockUnlockAt();

            if ($remaining <= 0) continue;  // sin cupos, ni mostramos

            $blockReason = null;
            if ($isTimeBlocked && $unlockAt) {
                $blockReason = "Bloqueada por regla Nintendo (4° uso). Desbloquea " . $unlockAt->format('d/m/Y');
            }

            $candidates[] = new Candidate(
                account:           $acc,
                remainingSlots:    $remaining,
                usedSlots:         $acc->capacity() - $remaining,
                capacity:          $acc->capacity(),
                isPostReset:       $acc->isPostReset(),
                isTimeBlocked:     $isTimeBlocked,
                timeBlockUnlockAt: $unlockAt,
                blockReason:       $blockReason,
                selectionReason:   Candidate::buildSelectionReason($acc, $remaining),
            );
        }

        // ── Caso 3: hay cuentas pero todas sin cupos libres ──
        if (empty($candidates)) {
            return new MatchResult(
                candidates: [],
                emptyReason: "Hay {$accounts->count()} cuenta(s) compatible(s) pero todas sin cupos libres",
            );
        }

        // ── Ordenar: no-bloqueadas primero, después por más cupos libres ──
        usort($candidates, function (Candidate $a, Candidate $b) {
            if ($a->isTimeBlocked !== $b->isTimeBlocked) {
                return $a->isTimeBlocked ? 1 : -1;
            }
            if ($a->remainingSlots !== $b->remainingSlots) {
                return $b->remainingSlots - $a->remainingSlots;
            }
            // Empate: la que tiene más llaves disponibles primero
            $aKeys = $a->account->keys->count();
            $bKeys = $b->account->keys->count();
            return $bKeys - $aKeys;
        });

        return new MatchResult($candidates);
    }

    /**
     * Asigna una cuenta a un item.
     */
    public function assign(OrderItem $item, Account $account, string $whoDelivered): bool
    {
        $account->loadMissing('assignments');

        if ($account->freeSlots() <= 0) {
            return false;
        }

        $usedSlots = $account->assignments
            ->where('status', 'active')
            ->pluck('slot_number')
            ->all();

        // Encontrar primer slot libre dentro de la capacidad efectiva
        $capacity = $account->capacity();
        $freeSlot = null;
        for ($i = 1; $i <= $capacity; $i++) {
            if (! in_array($i, $usedSlots, true)) {
                $freeSlot = $i;
                break;
            }
        }
        if ($freeSlot === null) return false;

        DB::transaction(function () use ($item, $account, $whoDelivered, $freeSlot) {
            $account->assignments()->create([
                'slot_number'    => $freeSlot,
                'customer_name'  => $item->order->customer_name,
                'customer_email' => $item->order->customer_email,
                'assigned_at'    => now(),
                'status'         => 'active',
                'woo_order_id'   => $item->order->wc_order_id,
            ]);

            $item->update([
                'account_id'         => $account->id,
                'who_delivered'      => $whoDelivered,
                'fulfillment_status' => 'in_progress',
            ]);
        });

        return true;
    }

    // ──────────────────────── PRIVADOS ────────────────────────

    private function compatibleAccountPlatforms(?string $normalized): array
    {
        return match ($normalized) {
            'PS5'         => ['PS5', 'DUAL'],
            'PS4'         => ['PS4', 'DUAL'],
            'XBOX_SERIES' => ['XBOX_SERIES'],
            'XBOX_ONE'    => ['XBOX_ONE'],
            'SWITCH'      => ['SWITCH', 'SWITCH_2'],
            'SWITCH_2'    => ['SWITCH_2'],
            default       => [$normalized ?? 'UNKNOWN'],
        };
    }

    /**
     * Diagnóstica por qué no hay candidatas, distinguiendo:
     *   - no hay del juego en general
     *   - hay del juego pero no de la plataforma pedida
     *   - hay pero todas inactive
     */
    private function diagnoseEmpty(OrderItem $item, array $platforms): string
    {
        $sameGame = Account::where('game_id', $item->game_id)
            ->where('status', 'active')
            ->count();

        if ($sameGame === 0) {
            $anyGame = Account::where('game_id', $item->game_id)->count();
            if ($anyGame === 0) {
                return "No tenemos ninguna cuenta de '{$item->game->canonical_name}' en el inventario";
            }
            return "Las cuentas de '{$item->game->canonical_name}' están todas en estado bloqueado o archivado";
        }

        $platformList = implode(', ', $platforms);
        return "Hay $sameGame cuenta(s) de '{$item->game->canonical_name}' pero ninguna en plataforma [$platformList]";
    }
}
