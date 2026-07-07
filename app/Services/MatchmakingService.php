<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountSecondaryAssignment;
use App\Models\OrderItem;
use App\Models\PurchaseOrder;
use App\Models\WooProduct;
use App\Services\Matchmaking\Candidate;
use App\Services\Matchmaking\MatchResult;
use Illuminate\Support\Facades\DB;

class MatchmakingService
{
    private const MIN_FREE_SLOTS = 1;
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
         // ── Caso 0:
        if ($item->is_pack) {
            return new MatchResult(
                candidates: [],
                emptyReason: 'Item de pack: se asigna con cuenta especial (stock secundario).',
            );
        }

        // ── Caso 1: no hay game_id resuelto ──
        if (! $item->game_id) {
            return new MatchResult(
                candidates: [],
                emptyReason: 'El producto Woo no está mapeado al catálogo. Importá woo_products o asigná manualmente.',
            );
        }

        $platforms     = $this->compatibleAccountPlatforms($item->platform_normalized);
        $dualPlatforms = $this->dualSiblingPlatforms($item->platform_normalized);

        $accounts = Account::query()
            ->where('game_id', $item->game_id)
            ->where('status', 'active')
            ->where(function ($q) use ($platforms, $dualPlatforms) {
                $q->whereIn('platform', $platforms);

                if (! empty($dualPlatforms)) {
                    $q->orWhere(fn ($q2) => $q2
                        ->where('is_dual', true)
                        ->whereIn('platform', $dualPlatforms));
                }
            })
            ->with(['assignments', 'keys'])
            ->get();

        // ── Caso 2: ninguna cuenta matchea por game+platform ──
        if ($accounts->isEmpty()) {
            return new MatchResult(
                candidates: [],
                emptyReason: $this->diagnoseEmpty($item, $platforms, ! empty($dualPlatforms)),
            );
        }

        // ── Construir candidatos ── (sin cambios desde acá)
        $candidates = [];
        $blockedByWindow = 0;

        foreach ($accounts as $acc) {
            $slotPlatform = $acc->resolveSlotPlatform($item->platform_normalized);
            if ($slotPlatform === null) continue;

            if ($acc->isMembershipSaleClosed()) {
                $blockedByWindow++;
                continue;
            }

            $remaining     = $acc->freeSlotsFor($slotPlatform);
            $capacity      = $acc->capacityFor($slotPlatform);
            $isTimeBlocked = $acc->isTimeBlocked();
            $unlockAt      = $acc->timeBlockUnlockAt();

            if ($remaining < self::MIN_FREE_SLOTS) continue;

            // ── NUEVO: sin keys disponibles no se puede entregar ──
            if ($acc->keys->whereNull('used_at')->isEmpty()) continue;

            $blockReason = null;
            if ($isTimeBlocked && $unlockAt) {
                $blockReason = "Bloqueada por regla Nintendo (4° uso). Desbloquea " . $unlockAt->format('d/m/Y');
            }

            $candidates[] = new Candidate(
                account:           $acc,
                remainingSlots:    $remaining,
                usedSlots:         $capacity - $remaining,
                capacity:          $capacity,
                isPostReset:       $acc->isPostReset(),
                isTimeBlocked:     $isTimeBlocked,
                timeBlockUnlockAt: $unlockAt,
                blockReason:       $blockReason,
                selectionReason: Candidate::buildSelectionReason($acc, $remaining, $slotPlatform),
            );
        }

        if (empty($candidates)) {
            if ($blockedByWindow > 0) {
                return new MatchResult(
                    candidates: [],
                    emptyReason: "Hay {$blockedByWindow} cuenta(s) compatible(s) pero están fuera de la ventana de venta (membresía por vencer o vencida).",
                );
            }
            return new MatchResult(
                candidates: [],
                emptyReason: "Hay {$accounts->count()} cuenta(s) compatible(s) pero todas sin cupos libres",
            );
        }

        usort($candidates, function (Candidate $a, Candidate $b) {
            if ($a->isTimeBlocked !== $b->isTimeBlocked) {
                return $a->isTimeBlocked ? 1 : -1;
            }
            if ($a->remainingSlots !== $b->remainingSlots) {
                return $b->remainingSlots - $a->remainingSlots;
            }
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
        $account->loadMissing(['assignments', 'keys']);

        if ($account->isMembershipSaleClosed()) {
            return false;
        }
        

        $slotPlatform = $account->resolveSlotPlatform($item->platform_normalized);
        if ($slotPlatform === null || $account->freeSlotsFor($slotPlatform) < self::MIN_FREE_SLOTS) {
            return false;
        }

        $key = $account->keys->whereNull('used_at')->sortBy('position')->first();
        if (! $key) return false;

        // Slots usados SOLO de esta plataforma
        $usedSlots = $account->assignments
            ->where('status', 'active')
            ->where('platform', $slotPlatform)
            ->pluck('slot_number')
            ->all();

        $capacity = $account->capacityFor($slotPlatform);
        $freeSlot = null;
        for ($i = 1; $i <= $capacity; $i++) {
            if (! in_array($i, $usedSlots, true)) { $freeSlot = $i; break; }
        }
        if ($freeSlot === null) return false;

        DB::transaction(function () use ($item, $account, $whoDelivered, $freeSlot, $key, $slotPlatform) {
            $account->assignments()->updateOrCreate(
                [
                    'order_item_id' => $item->id,
                ],
                [
                    'platform'       => $slotPlatform,
                    'slot_number'    => $freeSlot,
                    'key_value'      => $key->key_value,
                    'key_position'   => $key->position,
                    'customer_name'  => $item->order->customer_name,
                    'customer_email' => $item->order->customer_email,
                    'assigned_at'    => now(),
                    'status'         => 'active',
                    'woo_order_id'   => $item->order->wc_order_id,
                    'order_item_id'  => $item->id,
                ]
            );

            $item->update([
                'account_id'         => $account->id,
                'who_delivered'      => $whoDelivered,
                'fulfillment_status' => 'delivered',
            ]);

            $key->delete();
        });

        return true;
    }

    // ──────────────────────── PRIVADOS ────────────────────────

    private function compatibleAccountPlatforms(?string $normalized): array
    {
        return match ($normalized) {
            'PS5'         => ['PS5'],
            'PS4'         => ['PS4'],
            'XBOX_SERIES' => ['XBOX_SERIES'],
            'XBOX_ONE'    => ['XBOX_ONE'],
            'SWITCH'      => ['SWITCH', 'SWITCH_2'],
            'SWITCH_2'    => ['SWITCH_2'],
            default       => [$normalized ?? 'UNKNOWN'],
        };
    }

    /**
     * ¿El pedido es de PlayStation? En ese caso, además de las cuentas de la
     * consola exacta, también sirven las cuentas duales (is_dual = true),
     * que cubren PS4 y PS5 simultáneamente.
     */
    private function matchesDualPlayStation(?string $normalized): bool
    {
        return in_array($normalized, ['PS4', 'PS5'], true);
    }

    /**
     * Diagnóstica por qué no hay candidatas, distinguiendo:
     *   - no hay del juego en general
     *   - hay del juego pero no de la plataforma pedida
     *   - hay pero todas inactive
     */
    private function diagnoseEmpty(OrderItem $item, array $platforms, bool $includeDual = false): string
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
        if ($includeDual) {
            $platformList .= ' (ni duales)';
        }

        return "Hay $sameGame cuenta(s) de '{$item->game->canonical_name}' pero ninguna en plataforma [$platformList]";
    }

    /**
     * Si el pedido pertenece a una familia con cuentas duales, devuelve las
     * plataformas que una cuenta dual (is_dual=true) puede cubrir.
     * Si no aplica dual, devuelve [].
     *
     *  - PlayStation: una dual cubre PS4 y PS5.
     *  - Nintendo:    una dual cubre SWITCH y SWITCH_2.
     */
    private function dualSiblingPlatforms(?string $normalized): array
    {
        return match ($normalized) {
            'PS4', 'PS5'         => ['PS4', 'PS5'],
            'SWITCH', 'SWITCH_2' => ['SWITCH', 'SWITCH_2'],
            default              => [],
        };
    }
    /**
     * Sugerencias de reseteo para un item: cuentas compatibles (mismo juego + plataforma)
     * que NO tienen cupos libres ahora pero ya son reseteables (todos los slots ocupados
     * + cumplieron la ventana de reseteo). El operador puede resetear una para liberar cupos.
     *
     * No se pisa con findCandidates(): por definición estas cuentas tienen 0 cupos libres,
     * así que nunca aparecen como candidatas asignables.
     */
    public function findResettableSuggestions(OrderItem $item): \Illuminate\Support\Collection
    {
        if (! $item->game_id) {
            return collect();
        }

        $platforms     = $this->compatibleAccountPlatforms($item->platform_normalized);
        $dualPlatforms = $this->dualSiblingPlatforms($item->platform_normalized);

        // Cuentas que YA tienen una OC de reset abierta (pendiente):
        // no las volvemos a sugerir para no pedir el reseteo dos veces.
        $accountsWithOpenReset = PurchaseOrder::query()
            ->where('type', 'reset')
            ->where('status', 'pending')
            ->whereNotNull('account_id')
            ->pluck('account_id');

        return Account::query()
            ->where('game_id', $item->game_id)
            ->where('status', 'active')
            ->whereNotIn('id', $accountsWithOpenReset)   // ← excluye las ya enviadas a reset
            ->where(function ($q) use ($platforms, $dualPlatforms) {
                $q->whereIn('platform', $platforms);
                if (! empty($dualPlatforms)) {
                    $q->orWhere(fn ($q2) => $q2
                        ->where('is_dual', true)
                        ->whereIn('platform', $dualPlatforms));
                }
            })
            ->with(['assignments', 'keys'])
            ->get()
            ->filter(function (Account $acc) use ($item) {
                $slotPlatform = $acc->resolveSlotPlatform($item->platform_normalized);

                return $slotPlatform !== null
                    && ! $acc->isMembershipSaleClosed()
                    && $acc->isResettableStock($slotPlatform);   // ← scoped al pool que pide el item
            })
            ->sortByDesc(fn (Account $acc) => $acc->stockRotationAgeInDays() ?? -1)
            ->values();
    }

    /**
     * Universo de cuentas elegibles para packs: cualquier cuenta PlayStation activa
     * (las únicas con stock secundario). SIN filtro por juego — el operador elige
     * la cuenta que le indicó el cliente. El cupo libre se chequea en PHP.
     */
    public function secondaryCandidatesQuery(?string $search = null)
    {
        $cutoff = now()->subMonths(Account::SECONDARY_ELIGIBLE_MONTHS);

        return Account::query()
            ->where('status', 'active')
            ->whereIn('platform', ['PS4', 'PS5'])
            ->whereHas('keys', fn ($q) => $q->whereNull('used_at'))

            ->whereDoesntHave('assignments', fn ($q) => $q
                ->where('status', 'active')
                ->where('assigned_at', '>', $cutoff))

            ->when($search, function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('email', 'like', "%$search%")
                    ->orWhere('gamer_tag', 'like', "%$search%")
                    ->orWhere('region', 'like', "%$search%")
                    ->orWhereHas('game', function ($g) use ($search) {           // ← NUEVO
                        $g->where('canonical_name', 'like', "%$search%")
                            ->orWhereHas('products', fn ($p) => $p->where('name', 'like', "%$search%"));
                    });
                });
            })
            ->with(['assignments', 'secondaryAssignments', 'keys', 'game.products'])  // ← game.products para el cover
            ->orderBy('email');
    }

    /**
     * Mejor cuenta secundaria elegible que cubre un juego concreto de un pack.
     *
     * `$wcProductId` es el id del WooProduct que eligió el cliente; lo resolvemos a
     * game_id canónico y buscamos entre las cuentas con stock secundario una que tenga
     * ese juego y cupo secundario libre (preferentemente en la plataforma pedida).
     * Devuelve la más "vieja" en rotación (misma preferencia que el resto del sistema).
     */
    public function bestSecondaryForPackGame(?int $wcProductId, ?string $platform): ?Account
    {
        if (! $wcProductId) {
            return null;
        }

        $gameId = WooProduct::find($wcProductId)?->game_id;
        if (! $gameId) {
            return null;
        }

        // 'ps5' → 'PS5' (los slots secundarios viven en PS4/PS5).
        $wantPlatform = WooProduct::normalizePlatform($platform);

        return $this->secondaryCandidatesQuery()
            ->where('game_id', $gameId)
            ->get()
            ->filter(fn (Account $acc) => $acc->isSecondaryStockEligible())
            ->filter(fn (Account $acc) => $this->hasFreeSecondarySlotFor($acc, $wantPlatform))
            ->sortByDesc(fn (Account $acc) => $acc->stockRotationAgeInDays() ?? -1)
            ->first();
    }

    /** Cache por request de la existencia de stock secundario global (packs sin desglose). */
    private ?bool $hasSecondaryStockCache = null;

    /**
     * ¿Existe AL MENOS una cuenta secundaria elegible con cupo libre? Se usa para el
     * estado del índice en packs sin juegos preseleccionados. Memoizado por request.
     */
    public function hasSecondaryStock(): bool
    {
        if ($this->hasSecondaryStockCache !== null) {
            return $this->hasSecondaryStockCache;
        }

        return $this->hasSecondaryStockCache = $this->secondaryCandidatesQuery()
            ->get()
            ->contains(fn (Account $acc) => $acc->isSecondaryStockEligible()
                && $this->hasFreeSecondarySlotFor($acc, null));
    }

    /**
     * ¿La cuenta tiene algún cupo secundario libre? Si se pide una plataforma concreta,
     * intenta esa (o la única que cubre); si no, cualquiera de sus plataformas secundarias.
     */
    private function hasFreeSecondarySlotFor(Account $account, ?string $platform): bool
    {
        if ($platform !== null) {
            $slot = $account->resolveSlotPlatform($platform);
            if ($slot !== null) {
                return $account->secondaryFreeSlotsFor($slot) > 0;
            }
        }

        foreach ($account->secondaryPlatforms() as $p) {
            if ($account->secondaryFreeSlotsFor($p) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Plataforma de slot secundario a consumir para esta cuenta+item:
     *   1) la del item si la cuenta la cubre y tiene cupo
     *   2) sino, la primera plataforma secundaria cubierta con cupo libre
     */
    private function pickSecondarySlotPlatform(Account $account, OrderItem $item): ?string
    {
        $preferred = $account->resolveSlotPlatform($item->platform_normalized);
        if ($preferred !== null && $account->secondaryFreeSlotsFor($preferred) > 0) {
            return $preferred;
        }
        foreach ($account->secondaryPlatforms() as $p) {
            if ($account->secondaryFreeSlotsFor($p) > 0) {
                return $p;
            }
        }
        return null;
    }

    /**
     * Asigna varias cuentas de pack al item. Cada cuenta consume UN slot
     * secundario + UNA llave. Devuelve los AccountSecondaryAssignment creados
     * (para que el controller mande el mail por cada uno). Tolera fallos parciales.
     */
    public function assignSecondaryMany(OrderItem $item, array $accountIds, string $whoDelivered, array $meta = []): array
    {
        $item->loadMissing('order');

        $accounts = Account::whereIn('id', $accountIds)
            ->with(['secondaryAssignments', 'keys'])
            ->get();

        $assigned = [];   // AccountSecondaryAssignment[]
        $failed   = [];

        foreach ($accounts as $account) {
            $sa = $this->assignSecondarySlot($item, $account, $meta[$account->id] ?? []);
            if ($sa) {
                $assigned[] = $sa;
            } else {
                $failed[] = ['account_id' => $account->id, 'email' => $account->email, 'reason' => 'sin slot o sin llave libre'];
            }
        }

        // El pack queda entregado si al menos una cuenta entró.
        if (! empty($assigned)) {
            $item->update([
                'who_delivered'      => $whoDelivered,
                'fulfillment_status' => 'delivered',
            ]);
        }

        return compact('assigned', 'failed');
    }

    /** Crea UNA fila de assignment secundario + consume una llave. Idempotente por (item, cuenta). */
    private function assignSecondarySlot(OrderItem $item, Account $account, array $meta = []): ?AccountSecondaryAssignment
    {
        // Ya asignada a este item → no duplicar ni consumir otra llave.
        $existing = $account->secondaryAssignments
            ->where('order_item_id', $item->id)
            ->where('status', 'active')
            ->first();
        if ($existing) {
            return $existing;
        }

        $slotPlatform = $this->pickSecondarySlotPlatform($account, $item);
        if ($slotPlatform === null) {
            return null;
        }

        // Sin llave disponible no se puede entregar (igual que assign() normal).
        $key = $account->keys->whereNull('used_at')->sortBy('position')->first();
        if (! $key) {
            return null;
        }

        $usedSlots = $account->secondaryAssignments
            ->where('status', 'active')
            ->where('platform', $slotPlatform)
            ->pluck('slot_number')
            ->all();

        $capacity = $account->secondaryCapacityFor($slotPlatform);
        $freeSlot = null;
        for ($i = 1; $i <= $capacity; $i++) {
            if (! in_array($i, $usedSlots, true)) { $freeSlot = $i; break; }
        }
        if ($freeSlot === null) {
            return null;
        }

        return DB::transaction(function () use ($item, $account, $freeSlot, $slotPlatform, $key, $meta) {
            $sa = $account->secondaryAssignments()->updateOrCreate(
                ['order_item_id' => $item->id],
                [
                    'platform'        => $slotPlatform,
                    'slot_number'     => $freeSlot,
                    'key_value'       => $key->key_value,
                    'key_position'    => $key->position,
                    'customer_name'   => $item->order->customer_name,
                    'customer_email'  => $item->order->customer_email,
                    'assigned_at'     => now(),
                    'status'          => 'active',
                    'woo_order_id'    => $item->order->wc_order_id,
                    'pack_game_id'    => $meta['pack_game_id'] ?? null,
                    'pack_game_title' => $meta['pack_game_title'] ?? null,
                ]
            );

            $key->delete();   // consume la llave, igual que en la entrega normal

            // refresca en memoria por si el mismo account vuelve en el loop
            $account->load(['secondaryAssignments', 'keys']);

            return $sa;
        });
    }
}
