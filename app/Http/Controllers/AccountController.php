<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountRequest;
use App\Models\Account;
use App\Models\AccountAssignment;
use App\Models\AccountSecondaryAssignment;
use App\Models\Game;
use App\Models\WooProduct;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    /**
     * GET /accounts
     */
    public function index(Request $request)
    {
        $orphan = $request->boolean('orphan');

        $sortable = [
            'email'          => 'email',
            'platform'       => 'platform',
            'is_dual'        => 'is_dual',
            'region'         => 'region',
            'account_type'   => 'account_type',
            'keys_count'     => 'keys_count',
            'reset_date'     => 'reset_date',
            'purchased_date' => 'purchased_date',
            'status'         => 'status',
            'created_at'     => 'created_at',
        ];
        
        $sort      = array_key_exists($request->input('sort'), $sortable) ? $request->input('sort') : 'created_at';
        $direction = strtolower($request->input('direction')) === 'asc' ? 'asc' : 'desc';

        // Filtros compartidos (TODOS menos `status`): así los stats de status
        // funcionan como facetas del mismo conjunto filtrado. Devuelve una query
        // nueva cada vez que se invoca para poder contar sin efectos colaterales.
        $filtered = function () use ($request, $orphan) {
            return Account::query()
                // Por defecto: solo cuentas con juego válido (comportamiento actual).
                // Con ?orphan=1: invertimos y mostramos las que NO tienen juego (game_id roto/null).
                ->when(! $orphan, fn ($q) => $q->whereHas('game'))
                ->when($orphan,   fn ($q) => $q->whereDoesntHave('game'))
                ->when($request->filled('game_id'),  fn ($q) => $q->where('game_id', $request->game_id))
                ->when($request->filled('platform'), function ($q) use ($request) {
                    // El filtro de plataforma ahora es múltiple (platform[]=PS4&platform[]=PS5).
                    // Aceptamos también el valor simple por compatibilidad con enlaces viejos.
                    $vals = array_filter((array) $request->input('platform'), fn ($v) => $v !== '' && $v !== null);
                    if ($vals) {
                        $q->whereIn('platform', $vals);
                    }
                })
                ->when($request->filled('type_console'), fn ($q) => $q->where('type_console', $request->type_console))
                ->when($request->filled('is_dual'), fn ($q) => $q->where('is_dual', $request->boolean('is_dual')))
                ->when($request->filled('region'),   fn ($q) => $q->where('region', $request->region))
                ->when($request->filled('type'),     fn ($q) => $q->where('account_type', $request->type))
                ->when($request->boolean('few_keys'), fn ($q) => $q->has('keys', '<', 2))
                ->when($request->filled('search'), function ($q) use ($request) {
                    $s = $request->search;
                    $q->where(function ($q) use ($s) {
                        $q->where('email', 'like', "%$s%")
                        ->orWhere('gamer_tag', 'like', "%$s%")
                        ->orWhere('mail_email', 'like', "%$s%")
                        ->orWhereHas('game', function ($q) use ($s) {
                            $q->where('canonical_name', 'like', "%$s%")
                                ->orWhereHas('products', fn ($q) => $q->where('name', 'like', "%$s%"));
                        });
                    });
                });
        };

        $accounts = $filtered()
            ->with(['game.products', 'assignments'])
            ->withCount(['assignments as active_assignments_count' => fn ($q) => $q->where('status', 'active')])
            ->withCount('keys')
            // 'disabled' es un pseudo-status: agrupa todo lo que NO es active
            // (blocked, reset, archived) = mismo criterio que Account::isDisabled().
            ->when($request->filled('status'), fn ($q) => $request->status === 'disabled'
                ? $q->where('status', '!=', 'active')
                : $q->where('status', $request->status))
            ->orderBy($sortable[$sort], $direction)
            ->paginate(50)
            ->withQueryString();

        // Stats: reflejan los filtros activos (menos el propio status).
        $stats = [
            'total'    => $filtered()->count(),
            'active'   => $filtered()->where('status', 'active')->count(),
            // Deshabilitadas: cualquier status != active (Account::isDisabled()).
            'disabled' => $filtered()->where('status', '!=', 'active')->count(),
            'blocked'  => $filtered()->where('status', 'blocked')->count(),
            'archived' => $filtered()->where('status', 'archived')->count(),
            // Cuentas reseteables: mismo criterio que /stock/reseteables
            // (pre-filtro SQL + chequeo PHP de capacidad/ventana temporal), acotado
            // al conjunto filtrado actual.
            'resettable' => $filtered()
                ->resettableCandidates()
                ->with('assignments')
                ->get()
                ->filter(fn (Account $a) => $a->isResettableStock())
                ->count(),
        ];

        // Para los filtros: opciones distintas que ya hay en la base
        $platforms    = Account::query()->select('platform')->distinct()->orderBy('platform')->pluck('platform')->filter()->values();
        $regions      = Account::query()->select('region')->distinct()->orderBy('region')->pluck('region');
        $typeConsoles = Account::query()->select('type_console')->whereNotNull('type_console')->where('type_console', '!=', '')->distinct()->orderBy('type_console')->pluck('type_console');

        return view('accounts.index', compact('accounts', 'stats', 'platforms', 'regions', 'typeConsoles', 'orphan'));
    }

    /**
     * GET /accounts/{account}
     */
    public function show(Account $account)
    {
        $account->load(['game.products', 'parent.game', 'children', 'keys', 'assignments', 'secondaryAssignments']);

        $wooProduct = $account->coverProduct();

        $usageByPlatform = collect($account->coveredPlatforms())
            ->mapWithKeys(fn ($p) => [$p => [
                'used'     => $account->assignments->where('status', 'active')->where('platform', $p)->count(),
                'capacity' => $account->capacityFor($p),
                'free'     => $account->freeSlotsFor($p),
            ]]);

        $secondaryUsageByPlatform = collect($account->secondaryPlatforms())
            ->mapWithKeys(fn ($p) => [$p => [
                'used'     => $account->secondaryAssignments->where('status', 'active')->where('platform', $p)->count(),
                'capacity' => $account->secondaryCapacityFor($p),
                'free'     => $account->secondaryFreeSlotsFor($p),
            ]]);

        return view('accounts.show', compact('account', 'usageByPlatform', 'secondaryUsageByPlatform', 'wooProduct'));
    }

    /**
     * GET /accounts/create
     */
    public function create()
    {
        $account = new Account([
            'platform'     => 'PS4',
            'is_dual'      => false,
            'account_type' => 'INDEPENDIENTE',
            'status'       => 'active',
        ]);

        $motherAccounts = $this->motherAccountOptions();

        return view('accounts.create', compact('account', 'motherAccounts'));
    }

    /**
     * POST /accounts
     */
    public function store(AccountRequest $request)
    {
        $account = DB::transaction(function () use ($request) {
            $account = Account::create($request->safe()->except('keys', 'children_ids'));
            $this->syncKeys($account, $request->validated()['keys'] ?? []);
            $this->syncChildren($account, $request->validated()['children_ids'] ?? []);
            return $account;
        });

        return redirect()->route('accounts.show', $account)
            ->with('success', "Cuenta {$account->email} creada.");
    }

    /**
     * GET /accounts/{account}/edit
     */

    public function edit(Account $account)
    {
        $account->load(['keys', 'game.products', 'assignments', 'parent', 'children']);

        $wooProduct     = $account->coverProduct();
        $motherAccounts = $this->motherAccountOptions($account);

        $secondaryUsageByPlatform = collect($account->secondaryPlatforms())
        ->mapWithKeys(fn ($p) => [$p => [
            'used'     => $account->secondaryAssignments->where('status', 'active')->where('platform', $p)->count(),
            'capacity' => $account->secondaryCapacityFor($p),
            'free'     => $account->secondaryFreeSlotsFor($p),
        ]]);

        return view('accounts.edit', compact('account', 'wooProduct', 'motherAccounts', 'secondaryUsageByPlatform'));
    }
    /**
     * PUT /accounts/{account}
     */
    public function update(AccountRequest $request, Account $account)
    {
        DB::transaction(function () use ($request, $account) {
            $account->update($request->safe()->except('keys', 'children_ids'));
            $this->syncKeys($account, $request->validated()['keys'] ?? []);
            $this->syncChildren($account, $request->validated()['children_ids'] ?? []);
        });

        return redirect()->route('accounts.show', $account)
            ->with('success', "Cuenta {$account->email} actualizada.");
    }

    /**
     * DELETE /accounts/{account}
     */
    public function destroy(Account $account)
    {
        if ($account->assignments()->where('status', 'active')->exists()) {
            return back()->withErrors([
                'delete' => 'No podés eliminar una cuenta con asignaciones activas. Revocá las asignaciones primero.',
            ]);
        }

        $email = $account->email;
        $account->delete();   // soft delete

        return redirect()->route('accounts.index')
            ->with('success', "Cuenta {$email} eliminada (soft-delete, recuperable).");
    }

    /**
     * Sincroniza las llaves del form con las existentes:
     *   - llaves con `id` que llegan: se actualizan
     *   - llaves sin `id`: se crean
     *   - llaves que NO llegan pero existían: se eliminan
     *
     * Conserva el `used_at` de las llaves que ya tenían historial.
     */
    private function syncKeys(Account $account, array $keys): void
    {
        $incomingIds = collect($keys)->pluck('id')->filter()->all();

        // Eliminar las que no vinieron en el form
        $account->keys()
            ->when(! empty($incomingIds), fn ($q) => $q->whereNotIn('id', $incomingIds))
            ->delete();

        // Upsert de las que vinieron
        foreach ($keys as $k) {
            if (! empty($k['id'])) {
                $account->keys()->where('id', $k['id'])->update([
                    'position'  => $k['position'],
                    'key_value' => $k['value'],
                ]);
            } else {
                $account->keys()->create([
                    'position'  => $k['position'],
                    'key_value' => $k['value'],
                ]);
            }
        }
    }

    // ──────────────────────── ACCIONES OPERATIVAS ────────────────────────

    /**
     * POST /accounts/{account}/disable
     * Deshabilita una cuenta con motivo + notas.
     */
    public function disable(Request $request, Account $account)
    {
        $validated = $request->validate([
            'reason' => 'required|in:' . implode(',', array_keys(Account::DISABLE_REASONS)),
            'notes'  => 'nullable|string|max:2000',
        ]);

        $account->update([
            'status'         => 'blocked',
            'disable_reason' => $validated['reason'],
            'notes'          => $validated['notes'] ?? $account->notes,
        ]);

        $label = Account::DISABLE_REASONS[$validated['reason']];
        return back()->with('success', "Cuenta deshabilitada · motivo: $label");
    }

    /**
     * POST /accounts/{account}/enable
     * Rehabilita una cuenta. Limpia el motivo.
     */
    public function enable(Account $account)
    {
        $account->update([
            'status'         => 'active',
            'disable_reason' => null,
        ]);

        return back()->with('success', "Cuenta {$account->email} habilitada.");
    }

    /**
     * POST /accounts/{account}/usage
     * Incrementa o decrementa "usos manuales".
     *
     * +1 → crea un assignment placeholder (sin datos de cliente) en el próximo slot libre
     * -1 → borra el placeholder más reciente. NO borra assignments con datos de cliente
     *      reales — para eso el operador tiene que ir al detalle de la order correspondiente.
     */
    public function incrementUsage(Request $request, Account $account)
    {
        $account->load('assignments');

        $covered  = $account->coveredPlatforms();
        $platform = $request->input('platform');

        if ($platform !== null) {
            // El operador eligió consola (caso PS4/PS5)
            if (! in_array($platform, $covered, true)) {
                return back()->withErrors(['usage' => 'Plataforma inválida para esta cuenta.']);
            }
            if ($account->freeSlotsFor($platform) <= 0) {
                return back()->withErrors(['usage' => "No hay slots libres en {$platform}."]);
            }
        } else {
            // Comportamiento actual: primera consola con cupo libre
            $platform = collect($covered)->first(fn ($p) => $account->freeSlotsFor($p) > 0);

            if ($platform === null) {
                return back()->withErrors(['usage' => 'No hay slots libres en esta cuenta.']);
            }
        }

        $usedSlots = $account->assignments
            ->where('status', 'active')
            ->where('platform', $platform)   // ← faltaba
            ->pluck('slot_number')
            ->all();
        $freeSlot  = null;
        for ($i = 1; $i <= $account->capacity(); $i++) {
            if (! in_array($i, $usedSlots, true)) { $freeSlot = $i; break; }
        }

        $account->assignments()->create([
            'slot_number'    => $freeSlot,
            'platform'       => $platform,
            'status'         => 'active',
            'assigned_at'    => null,
            'customer_name'  => null,
            'customer_email' => null,
        ]);

        return back()->with('success', "Uso manual agregado · {$platform} slot {$freeSlot}.");
    }

    public function decrementUsage(Request $request, Account $account)
    {
        $query = $account->assignments()
            ->where('status', 'active')
            ->whereNull('customer_name')
            ->whereNull('customer_email');

        $platform = $request->input('platform');

        if ($platform !== null) {
            if (! in_array($platform, $account->coveredPlatforms(), true)) {
                return back()->withErrors(['usage' => 'Plataforma inválida para esta cuenta.']);
            }
            $query->where('platform', $platform);   // solo libera placeholders de esa consola
        }

        $placeholder = $query->orderByDesc('id')->first();

        if (! $placeholder) {
            return back()->withErrors([
                'usage' => $platform
                    ? "No hay placeholders para liberar en {$platform}."
                    : 'No hay placeholders para liberar. Las asignaciones con datos de cliente solo se pueden revocar desde la order correspondiente.',
            ]);
        }

        $slot = $placeholder->slot_number;
        $plat = $placeholder->platform;
        $placeholder->delete();

        return back()->with('success', "Slot $slot ({$plat}) liberado.");
    }

    /**
     * POST /accounts/{account}/reset
     * Registra un reset de la cuenta.
     *
     * Efectos:
     *   - reset_date pasa a hoy → la capacidad efectiva baja a maxAfterReset() (PSN: mitad)
     *   - Todas las assignments activas pasan a status='expired'
     *     → mantienen el historial pero ya no cuentan como slots ocupados
     */
    public function reset(Request $request, Account $account)
    {
        // Bloqueo: no permitir reset dentro de los 180 días de la fecha base
        // (último reset, o compra si nunca se reseteó).
        if ($account->isResetOnCooldown()) {
            $until = $account->resetCooldownUntil();
            $base  = $account->reset_date ? 'último reset' : 'compra';

            return back()->withErrors([
                'reset' => sprintf(
                    'No podés resetear esta cuenta todavía. Fecha de %s: %s. Próximo reset disponible: %s (faltan %d día[s]).',
                    $base,
                    $account->resetCooldownBaseDate()->format('Y-m-d'),
                    $until->format('Y-m-d'),
                    (int) ceil(now()->diffInDays($until, false))
                ),
            ]);
        }

    
        $data = $request->validate([
            'key_id' => 'nullable|exists:account_keys,id',
        ]);

        $result = DB::transaction(function () use ($account, $data) {
            $expiredCount = $account->assignments()
                ->where('status', 'active')
                ->update([
                    'status'     => 'expired',
                    'updated_at' => now(),
                ]);

            $account->update(['reset_date' => now()]);

            // Consumir la llave mostrada en el modal (solo si sigue libre y es de esta cuenta)
            $consumedKey = null;
            if (! empty($data['key_id'])) {
                $key = $account->keys()
                    ->where('id', $data['key_id'])
                    ->whereNull('used_at')
                    ->first();

                if ($key) {
                    $key->update(['used_at' => now()]);
                    $consumedKey = $key;
                }
            }

            return [
                'expired'     => $expiredCount,
                'newCapacity' => $account->fresh()->capacity(),
                'consumedKey' => $consumedKey,
            ];
        });

        $msg = sprintf(
            'Cuenta reseteada. %d asignación(es) marcada(s) como expiradas. Capacidad reducida a %d.',
            $result['expired'],
            $result['newCapacity']
        );

        if ($result['consumedKey']) {
            $msg .= sprintf(' Llave #%d consumida.', $result['consumedKey']->position);
        }

        return back()->with('success', $msg);
    }

    /**
     * POST /accounts/{account}/reset-snooze
     * POSPONE la cuenta N meses en los recomendados a resetear.
     *
     * Se usa cuando no se conoce la fecha real de reset (se reseteó desde Sony sin
     * registrarlo acá). Guarda reset_snooze_until = hoy + N meses; mientras el plazo
     * esté vigente la cuenta no aparece en los recomendados, aunque ya cumpla la
     * ventana real de 6 meses. Cumplido el plazo vuelven las reglas normales.
     * No pisa purchased_date ni reset_date.
     */
    public function snoozeReset(Request $request, Account $account)
    {
        $data = $request->validate([
            'months' => 'required|integer|min:1|max:60',
        ]);

        $until = now()->addMonths((int) $data['months'])->startOfDay();
        $account->update(['reset_snooze_until' => $until]);

        return back()->with('success', sprintf(
            'Cuenta pospuesta %d mes(es). Vuelve a recomendarse a resetear el %s.',
            (int) $data['months'],
            $until->format('Y-m-d')
        ));
    }

    /**
     * POST /accounts/{account}/purchased-date
     * Actualiza la fecha de compra desde el input inline del listado.
     * Acepta vacío para limpiarla. Responde JSON para la edición vía fetch.
     */
    public function updatePurchasedDate(Request $request, Account $account)
    {
        $data = $request->validate([
            'purchased_date' => 'nullable|date',
        ]);

        $account->update([
            'purchased_date' => $data['purchased_date'] ?? null,
        ]);

        return response()->json([
            'ok'             => true,
            'purchased_date' => $account->purchased_date?->format('Y-m-d'),
        ]);
    }

    /**
     * DELETE /accounts/{account}/reset-snooze
     * Cancela la prórroga: la cuenta vuelve a evaluarse por las reglas normales.
     */
    public function clearResetSnooze(Account $account)
    {
        $account->update(['reset_snooze_until' => null]);

        return back()->with('success', 'Prórroga cancelada. La cuenta vuelve a evaluarse por compra/reset reales.');
    }

    /** Cuentas elegibles como madre. Excluye la cuenta actual al editar. */
    private function motherAccountOptions(?Account $exclude = null)
    {
        return Account::query()
            ->where('account_type', 'MADRE')
            ->when($exclude, fn ($q) => $q->whereKeyNot($exclude->getKey()))
            ->orderBy('email')
            ->get(['id', 'email', 'platform']);
    }

    /**
     * GET /accounts/mismatched
     * Cuentas cuyo juego NO tiene un producto para la plataforma de la cuenta,
     * o que no tienen juego asignado. Para corregirlas a mano.
     */
    public function mismatched(Request $request)
    {
        $accounts = Account::query()
            ->with(['game.products'])
            ->whereNull('game_id')                  // solo cuentas SIN juego
            ->where(function ($q) {                  // que NO sean hija
                $q->where('account_type', '!=', 'hija')
                ->orWhereNull('account_type');
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->search;
                $q->where(fn ($w) => $w->where('email', 'like', "%$s%")
                                    ->orWhere('gamer_tag', 'like', "%$s%"));
            })
            ->orderBy('platform')
            ->orderBy('email')
            ->paginate(50)
            ->withQueryString();

        return view('accounts.mismatched', compact('accounts'));
    }

    /**
     * GET /accounts/{account}/reassign
     * Buscador de juegos para reapuntar la cuenta.
     */
    public function reassignForm(Request $request, Account $account)
    {
        $account->load('game.products');

        // Por defecto buscamos por el nombre del juego actual ya limpio,
        // así arriba aparecen las variantes del mismo título.
        $q = $request->input('q', $account->game ? $account->game->displayName() : '');

        $games = Game::query()
            ->with('products')
            ->when($q !== '', fn ($query) => $query->where('canonical_name', 'like', "%$q%")
                                                ->orWhere('normalized_name', 'like', "%$q%"))
            ->orderBy('canonical_name')
            ->limit(40)
            ->get();

        $wantPlatform = $this->normPlatform($account->platform);

        return view('accounts.reassign', compact('account', 'games', 'q', 'wantPlatform'));
    }

    /**
     * POST /accounts/{account}/reassign
     */
    public function reassign(Request $request, Account $account)
    {
        $data = $request->validate(['game_id' => 'nullable|exists:games,id']);
        $account->update(['game_id' => $data['game_id'] ?? null]);

        return redirect()->route('accounts.mismatched')
            ->with('success', "Cuenta {$account->email} reasignada.");
    }

    /** Normaliza la plataforma para comparar (mismo criterio que la vista). */
    private function normPlatform(?string $p): string
    {
        $p = strtolower(str_replace([' ', '-', '|', '/'], '', (string) $p));
        return $p === 'xboxseriesxs' ? 'xboxseries' : $p;
    }

    /** Vincula/desvincula hijas de una cuenta MADRE según children_ids[]. */
    private function syncChildren(Account $account, array $childrenIds): void
    {
        // Si la cuenta no es (o dejó de ser) MADRE, soltamos cualquier hija que tuviera
        if ($account->account_type !== 'MADRE') {
            $account->children()->update(['parent_account_id' => null]);
            return;
        }

        // Desvincular las que ya no están en la selección
        $account->children()
            ->when(! empty($childrenIds), fn ($q) => $q->whereNotIn('id', $childrenIds))
            ->update(['parent_account_id' => null]);

        // Vincular las seleccionadas (sin apuntarse a sí misma)
        if (! empty($childrenIds)) {
            Account::whereIn('id', $childrenIds)
                ->whereKeyNot($account->getKey())
                ->update(['parent_account_id' => $account->id]);
        }
    }
    function exportAccountWithNoGame()
    {
        $accounts = Account::query()
            ->whereNull('game_id')
            ->with(['game.products'])
            ->get();

        $csvData = "ID,Email,Platform,Account Type,Status\n";
        foreach ($accounts as $account) {
            $csvData .= "{$account->id},\"{$account->email}\",\"{$account->platform}\",\"{$account->account_type}\",\"{$account->status}\"\n";
        }

        return response($csvData)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="accounts_without_game.csv"');
    }
    /**
     * GET /accounts/secondary-stock
     * Estado de elegibilidad de stock secundario por cuenta.
     * Misma regla que el modal de packs (isSecondaryStockEligible()).
     */
    public function secondaryStock(Request $request)
    {
        $eligible = Account::query()
            ->with(['game.products', 'assignments', 'secondaryAssignments', 'keys'])
            ->whereHas('game')
            ->where('status', 'active')
            ->whereIn('platform', ['PS4', 'PS5'])          // secundario solo aplica a PS
            ->whereHas('keys', fn ($q) => $q->whereNull('used_at'), '>=', 2)  // más de 1 llave disponible
            ->when($request->filled('platform'), fn ($q) => $q->where('platform', $request->platform))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->search;
                $q->where(function ($w) use ($s) {
                    $w->where('email', 'like', "%$s%")
                    ->orWhere('gamer_tag', 'like', "%$s%")
                    ->orWhereHas('game', function ($g) use ($s) {
                        $g->where('canonical_name', 'like', "%$s%")
                            ->orWhereHas('products', fn ($p) => $p->where('name', 'like', "%$s%"));
                    });
                });
            })
            ->orderBy('email')
            ->get()
            ->filter(fn (Account $account) => $account->isSecondaryStockEligible())  // ← regla nueva
            ->values();

        // Array que consume la vista ($account->secondary)
        $eligible->each(fn (Account $a) => $a->secondary = $a->secondaryEligibility());

        // Paginación manual (el filtro fino es PHP, no SQL) — igual que secondaryCandidates
        $page    = max(1, (int) $request->integer('page', 1));
        $perPage = 50;

        $accounts = new LengthAwarePaginator(
            $eligible->forPage($page, $perPage)->values(),
            $eligible->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $platforms = Account::query()->select('platform')->distinct()->orderBy('platform')->pluck('platform');

        return view('accounts.secondary-stock', compact('accounts', 'platforms'));
    }

    /**
     * Núcleo compartido: devuelve los WooProduct de $targetPlatforms que NO tienen
     * ninguna cuenta cubriéndolos (game_id + plataforma). Le adjunta a cada producto
     * un atributo dinámico `normalized_platform` para mostrar/exportar.
     */
    private function computeProductsWithoutAccount(array $targetPlatforms): \Illuminate\Support\Collection
    {
        // Cobertura por game_id y por canonical_name normalizado.
        $coveredByGame      = [];
        $coveredByCanonical = [];

        Account::query()
            ->whereNotNull('game_id')
            ->whereIn('platform', $targetPlatforms)   // PS4/PS5 (las duales también caen acá)
            ->with('game:id,canonical_name')          // necesitamos el canonical para la 2da clave
            ->get()
            ->each(function (Account $account) use (&$coveredByGame, &$coveredByCanonical, $targetPlatforms) {
                $canonicalKey = $this->canonicalKey(optional($account->game)->canonical_name);

                foreach ($account->coveredPlatforms() as $p) {
                    if (! in_array($p, $targetPlatforms, true)) {
                        continue;
                    }

                    $coveredByGame[$account->game_id][$p] = true;

                    if ($canonicalKey !== null) {
                        $coveredByCanonical[$canonicalKey][$p] = true;
                    }
                }
            });

        return WooProduct::query()
            ->with('game')
            ->get()
            ->filter(function (WooProduct $product) use ($coveredByGame, $coveredByCanonical, $targetPlatforms) {
                $platform = WooProduct::normalizePlatform($product->platform);

                if (! in_array($platform, $targetPlatforms, true)) {
                    return false;
                }

                $canonicalKey = $this->canonicalKey(optional($product->game)->canonical_name);

                $hasAccount =
                    ($product->game_id && isset($coveredByGame[$product->game_id][$platform]))
                    || ($canonicalKey !== null && isset($coveredByCanonical[$canonicalKey][$platform]));

                return ! $hasAccount;
            })
            ->map(function (WooProduct $product) {
                $product->normalized_platform = WooProduct::normalizePlatform($product->platform);
                return $product;
            })
            ->sortBy([
                ['normalized_platform', 'asc'],
                ['name', 'asc'],
            ])
            ->values();
    }

    /** Normaliza un canonical_name para usarlo como clave de cobertura. */
    private function canonicalKey(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $key = mb_strtolower(trim($name));

        return $key === '' ? null : $key;
    }

    /** Aplica los filtros de la vista (search + plataforma) a la colección ya calculada. */
    private function applyMissingFilters(\Illuminate\Support\Collection $rows, Request $request): \Illuminate\Support\Collection
    {
        return $rows
            ->when($request->filled('platform'), fn ($c) => $c->where('normalized_platform', $request->platform))
            ->when($request->filled('search'), function ($c) use ($request) {
                $s = mb_strtolower($request->search);
                return $c->filter(function (WooProduct $p) use ($s) {
                    return str_contains(mb_strtolower($p->name), $s)
                        || str_contains(mb_strtolower(optional($p->game)->canonical_name ?? ''), $s);
                });
            })
            ->values();
    }

    /**
     * GET /accounts/products-without-account
     * Vista de productos PS4/PS5 sin ninguna cuenta.
     */
    public function productsWithoutAccount(Request $request)
    {
        $targetPlatforms = ['PS4', 'PS5'];

        $rows = $this->applyMissingFilters(
            $this->computeProductsWithoutAccount($targetPlatforms),
            $request
        );

        $stats = [
            'total' => $rows->count(),
            'ps4'   => $rows->where('normalized_platform', 'PS4')->count(),
            'ps5'   => $rows->where('normalized_platform', 'PS5')->count(),
        ];

        // Paginación manual (el cálculo es PHP, no SQL).
        $page    = max(1, (int) $request->integer('page', 1));
        $perPage = 50;

        $products = new LengthAwarePaginator(
            $rows->forPage($page, $perPage),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('accounts.products-without-account', compact('products', 'stats', 'targetPlatforms'));
    }

    /**
     * GET /accounts/products-without-account/export
     * Descarga CSV (respeta los filtros activos).
     */
    public function exportProductsWithoutAccount(Request $request)
    {
        $targetPlatforms = ['PS4', 'PS5'];

        $rows = $this->applyMissingFilters(
            $this->computeProductsWithoutAccount($targetPlatforms),
            $request
        );

        $esc = fn ($v) => '"' . str_replace('"', '""', (string) $v) . '"';

        $csv = "Woo ID,Product Name,Platform\n";
        foreach ($rows as $p) {
            $csv .= implode(',', [
                $p->id,
                $esc($p->name),
                $esc($p->normalized_platform),
            ]) . "\n";
        }

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="productos_sin_cuenta_ps.csv"');
    }

    /**
     * Núcleo: WooProducts de $targetPlatforms que SÍ tienen cuenta(s) cubriéndolos
     * pero con 0 cupos disponibles (activa + slot libre).
     *
     * "Disponible" = cuenta status=active con freeSlotsFor() > 0.
     * Si un juego solo tiene cuentas bloqueadas/archivadas, su cupo disponible es 0
     * → también entra acá (no queda en un limbo entre este reporte y el de "sin cuenta").
     */
    private function computeProductsSoldOut(array $targetPlatforms): \Illuminate\Support\Collection
    {
        // game_id => plataforma => ['total','active','blocked','capacity','free']
        $cover = [];

        Account::query()
            ->whereNotNull('game_id')
            ->whereIn('platform', $targetPlatforms)   // PS4/PS5 (duales incluidas)
            ->with('assignments')                     // necesario para freeSlotsFor()
            ->get()
            ->each(function (Account $account) use (&$cover, $targetPlatforms) {
                foreach ($account->coveredPlatforms() as $p) {
                    if (! in_array($p, $targetPlatforms, true)) {
                        continue;
                    }

                    $cover[$account->game_id][$p]['total'] =
                        ($cover[$account->game_id][$p]['total'] ?? 0) + 1;

                    if ($account->status === 'active') {
                        $cover[$account->game_id][$p]['active']   = ($cover[$account->game_id][$p]['active']   ?? 0) + 1;
                        $cover[$account->game_id][$p]['capacity'] = ($cover[$account->game_id][$p]['capacity'] ?? 0) + $account->capacityFor($p);
                        $cover[$account->game_id][$p]['free']     = ($cover[$account->game_id][$p]['free']     ?? 0) + $account->freeSlotsFor($p);
                    } else {
                        $cover[$account->game_id][$p]['blocked']  = ($cover[$account->game_id][$p]['blocked']  ?? 0) + 1;
                    }
                }
            });

        return WooProduct::query()
            ->with('game')
            ->get()
            ->filter(function (WooProduct $product) use ($cover, $targetPlatforms) {
                $platform = WooProduct::normalizePlatform($product->platform);

                if (! in_array($platform, $targetPlatforms, true) || ! $product->game_id) {
                    return false;   // no es PS o no tiene juego → no aplica
                }

                $s = $cover[$product->game_id][$platform] ?? null;

                // Tiene al menos una cuenta cubriéndolo, pero 0 cupos disponibles.
                return $s !== null && (int) ($s['free'] ?? 0) === 0;
            })
            ->map(function (WooProduct $product) use ($cover) {
                $platform = WooProduct::normalizePlatform($product->platform);
                $s        = $cover[$product->game_id][$platform];

                $product->normalized_platform = $platform;
                $product->accounts_total      = $s['total']    ?? 0;
                $product->accounts_active     = $s['active']   ?? 0;
                $product->accounts_blocked    = $s['blocked']  ?? 0;
                $product->capacity_total      = $s['capacity'] ?? 0;
                $product->free_total          = $s['free']     ?? 0;
                $product->used_total          = ($s['capacity'] ?? 0) - ($s['free'] ?? 0);

                return $product;
            })
            ->sortBy([
                ['normalized_platform', 'asc'],
                ['name', 'asc'],
            ])
            ->values();
    }

    /**
     * GET /accounts/products-sold-out
     * Productos PS4/PS5 con cuenta pero sin cupo disponible.
     */
    public function productsSoldOut(Request $request)
    {
        $targetPlatforms = ['PS4', 'PS5'];

        $rows = $this->applyMissingFilters(
            $this->computeProductsSoldOut($targetPlatforms),
            $request
        );

        $stats = [
            'total' => $rows->count(),
            'ps4'   => $rows->where('normalized_platform', 'PS4')->count(),
            'ps5'   => $rows->where('normalized_platform', 'PS5')->count(),
        ];

        $page    = max(1, (int) $request->integer('page', 1));
        $perPage = 50;

        $products = new LengthAwarePaginator(
            $rows->forPage($page, $perPage),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('accounts.products-sold-out', compact('products', 'stats', 'targetPlatforms'));
    }

    /**
     * GET /accounts/products-sold-out/export
     */
    public function exportProductsSoldOut(Request $request)
    {
        $targetPlatforms = ['PS4', 'PS5'];

        $rows = $this->applyMissingFilters(
            $this->computeProductsSoldOut($targetPlatforms),
            $request
        );

        $esc = fn ($v) => '"' . str_replace('"', '""', (string) $v) . '"';

        $csv = "Product ID,Game ID,Game,Product Name,Platform,Cuentas activas,Cuentas bloqueadas,Usados,Capacidad\n";
        foreach ($rows as $p) {
            $csv .= implode(',', [
                $p->id,
                $p->game_id ?? '',
                $esc(optional($p->game)->canonical_name ?? ''),
                $esc($p->name),
                $esc($p->normalized_platform),
                $p->accounts_active,
                $p->accounts_blocked,
                $p->used_total,
                $p->capacity_total,
            ]) . "\n";
        }

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="productos_sin_cupo_ps.csv"');
    }
    /**
     * POST /accounts/{account}/secondary-usage
     * Igual que incrementUsage pero sobre los slots SECUNDARIOS (4 PS4 + 4 PS5).
     */
    public function incrementSecondaryUsage(Request $request, Account $account)
    {
        $account->load('secondaryAssignments');

        $covered = $account->secondaryPlatforms();

        if (empty($covered)) {
            return back()->withErrors(['secondary' => 'Esta cuenta no admite stock secundario.']);
        }

        $platform = $request->input('platform');

        if ($platform !== null) {
            if (! in_array($platform, $covered, true)) {
                return back()->withErrors(['secondary' => 'Plataforma inválida para stock secundario.']);
            }
            if ($account->secondaryFreeSlotsFor($platform) <= 0) {
                return back()->withErrors(['secondary' => "No hay slots secundarios libres en {$platform}."]);
            }
        } else {
            $platform = collect($covered)->first(fn ($p) => $account->secondaryFreeSlotsFor($p) > 0);

            if ($platform === null) {
                return back()->withErrors(['secondary' => 'No hay slots secundarios libres en esta cuenta.']);
            }
        }

        $usedSlots = $account->secondaryAssignments
            ->where('status', 'active')
            ->where('platform', $platform)
            ->pluck('slot_number')
            ->all();

        $freeSlot = null;
        for ($i = 1; $i <= $account->secondaryCapacityFor($platform); $i++) {
            if (! in_array($i, $usedSlots, true)) { $freeSlot = $i; break; }
        }

        $account->secondaryAssignments()->create([
            'slot_number'    => $freeSlot,
            'platform'       => $platform,
            'status'         => 'active',
            'assigned_at'    => null,
            'customer_name'  => null,
            'customer_email' => null,
        ]);

        return back()->with('success', "Uso secundario agregado · {$platform} slot {$freeSlot}.");
    }

    public function decrementSecondaryUsage(Request $request, Account $account)
    {
        $query = $account->secondaryAssignments()
            ->where('status', 'active')
            ->whereNull('customer_name')
            ->whereNull('customer_email');

        $platform = $request->input('platform');

        if ($platform !== null) {
            if (! in_array($platform, $account->coveredPlatforms(), true)) {
                return back()->withErrors(['secondary' => 'Plataforma inválida para esta cuenta.']);
            }
            $query->where('platform', $platform);
        }

        $placeholder = $query->orderByDesc('id')->first();

        if (! $placeholder) {
            return back()->withErrors([
                'secondary' => $platform
                    ? "No hay placeholders secundarios para liberar en {$platform}."
                    : 'No hay placeholders secundarios para liberar.',
            ]);
        }

        $slot = $placeholder->slot_number;
        $plat = $placeholder->platform;
        $placeholder->delete();

        return back()->with('success', "Slot secundario $slot ({$plat}) liberado.");
    }
    /**
     * POST /accounts/{account}/assignments/{assignment}/status
     * Manda una asignación PRIMARIA activa a expired o revoked.
     */
    public function updateAssignmentStatus(Request $request, Account $account, AccountAssignment $assignment)
    {
        abort_unless($assignment->account_id === $account->id, 404);

        $data = $request->validate([
            'status' => 'required|in:expired,revoked',
        ]);

        if ($assignment->status !== 'active') {
            return back()->withErrors(['usage' => 'Esa asignación ya no está activa.']);
        }

        $restoredKey = DB::transaction(function () use ($account, $assignment, $data) {
            $assignment->update(['status' => $data['status']]);

            // Al revocar un uso, la última llave consumida vuelve al listado disponible.
            if ($data['status'] !== 'revoked') {
                return null;
            }

            $key = $account->keys()
                ->whereNotNull('used_at')
                ->orderByDesc('used_at')
                ->orderByDesc('position')
                ->first();

            if ($key) {
                $key->update(['used_at' => null]);
            }

            return $key;
        });

        $label = $data['status'] === 'expired' ? 'expirada' : 'revocada';
        $msg   = "Slot {$assignment->slot_number} ({$assignment->platform}) marcado como {$label}.";

        if ($restoredKey) {
            $msg .= sprintf(' Llave #%d devuelta al listado de recuperación.', $restoredKey->position);
        }

        return back()->with('success', $msg);
    }

    /**
     * POST /accounts/{account}/secondary-assignments/{assignment}/status
     * Igual pero sobre asignaciones SECUNDARIAS.
     */
    public function updateSecondaryAssignmentStatus(Request $request, Account $account, AccountSecondaryAssignment $assignment)
    {
        abort_unless($assignment->account_id === $account->id, 404);

        $data = $request->validate([
            'status' => 'required|in:expired,revoked',
        ]);

        if ($assignment->status !== 'active') {
            return back()->withErrors(['secondary' => 'Esa asignación ya no está activa.']);
        }

        $restoredKey = DB::transaction(function () use ($account, $assignment, $data) {
            $assignment->update(['status' => $data['status']]);

            // Al revocar un uso, la última llave consumida vuelve al listado disponible.
            if ($data['status'] !== 'revoked') {
                return null;
            }

            $key = $account->keys()
                ->whereNotNull('used_at')
                ->orderByDesc('used_at')
                ->orderByDesc('position')
                ->first();

            if ($key) {
                $key->update(['used_at' => null]);
            }

            return $key;
        });

        $label = $data['status'] === 'expired' ? 'expirada' : 'revocada';
        $msg   = "Slot secundario {$assignment->slot_number} ({$assignment->platform}) marcado como {$label}.";

        if ($restoredKey) {
            $msg .= sprintf(' Llave #%d devuelta al listado de recuperación.', $restoredKey->position);
        }

        return back()->with('success', $msg);
    }

    /**
     * Núcleo: cuentas cuyo game_id apunta a un juego de OTRA consola.
     * Compara coveredPlatforms() de la cuenta contra las plataformas del juego
     * (productos + sufijo del canonical_name). Mismatch = intersección vacía.
     */
    private function computePlatformMismatches(): \Illuminate\Support\Collection
    {
        return Account::query()
            ->with(['game.products'])
            ->whereNotNull('game_id')
            ->whereHas('game')
            ->orderBy('platform')
            ->orderBy('email')
            ->get()
            ->filter(function (Account $account) {
                $account->game_platforms = $this->gamePlatformsFor($account->game);

                // Sin ninguna señal de plataforma del juego → no podemos afirmar nada.
                if (empty($account->game_platforms)) {
                    return false;
                }

                // Errónea si NINGUNA plataforma de la cuenta está cubierta por el juego.
                return empty(array_intersect($account->coveredPlatforms(), $account->game_platforms));
            })
            ->values();
    }

    /** Plataformas que el juego realmente ofrece: productos + sufijo del nombre. */
    private function gamePlatformsFor(?Game $game): array
    {
        if (! $game) return [];

        $fromProducts = $game->products
            ->map(fn ($p) => WooProduct::normalizePlatform($p->platform))
            ->filter()
            ->all();

        $fromName = Game::platformFromName($game->canonical_name);

        return array_values(array_unique(array_merge(
            $fromProducts,
            $fromName ? [$fromName] : []
        )));
    }

    /** Filtros de la vista (search + plataforma de la cuenta). */
    private function applyMismatchFilters(\Illuminate\Support\Collection $rows, Request $request): \Illuminate\Support\Collection
    {
        return $rows
            ->when($request->filled('platform'), fn ($c) => $c->where('platform', $request->platform))
            ->when($request->filled('search'), function ($c) use ($request) {
                $s = mb_strtolower($request->search);
                return $c->filter(fn (Account $a) =>
                    str_contains(mb_strtolower($a->email), $s)
                    || str_contains(mb_strtolower((string) $a->gamer_tag), $s)
                    || str_contains(mb_strtolower(optional($a->game)->canonical_name ?? ''), $s)
                );
            })
            ->values();
    }

    /**
     * GET /accounts/platform-mismatch
     * Cuentas con juego de otra consola (típico de imports masivos).
     */
    public function platformMismatch(Request $request)
    {
        $rows = $this->applyMismatchFilters($this->computePlatformMismatches(), $request);

        $stats = [
            'total' => $rows->count(),
            'ps4'   => $rows->where('platform', 'PS4')->count(),
            'ps5'   => $rows->where('platform', 'PS5')->count(),
        ];

        $page    = max(1, (int) $request->integer('page', 1));
        $perPage = 50;

        $accounts = new LengthAwarePaginator(
            $rows->forPage($page, $perPage),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $platforms = Account::query()->select('platform')->distinct()->orderBy('platform')->pluck('platform');

        return view('accounts.platform-mismatch', compact('accounts', 'stats', 'platforms'));
    }

    /**
     * GET /accounts/platform-mismatch/export
     */
    public function exportPlatformMismatch(Request $request)
    {
        $rows = $this->applyMismatchFilters($this->computePlatformMismatches(), $request);

        $esc = fn ($v) => '"' . str_replace('"', '""', (string) $v) . '"';

        $csv = "Account ID,Email,Gamer Tag,Plataforma cuenta,Game ID,Juego,Plataforma(s) del juego\n";
        foreach ($rows as $a) {
            $csv .= implode(',', [
                $a->id,
                $esc($a->email),
                $esc($a->gamer_tag ?? ''),
                $esc($a->platform),
                $a->game_id ?? '',
                $esc(optional($a->game)->canonical_name ?? ''),
                $esc(implode(' / ', $a->game_platforms ?? [])),
            ]) . "\n";
        }

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="cuentas_plataforma_erronea.csv"');
    }
}
