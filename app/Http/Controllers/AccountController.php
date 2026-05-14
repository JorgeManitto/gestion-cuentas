<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountRequest;
use App\Models\Account;
use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    /**
     * GET /accounts
     */
    public function index(Request $request)
    {
        $accounts = Account::query()
            ->with(['game'])
            ->withCount(['assignments as active_assignments_count' => fn ($q) => $q->where('status', 'active')])
            ->withCount('keys')
            ->when($request->filled('game_id'),  fn ($q) => $q->where('game_id', $request->game_id))
            ->when($request->filled('platform'), fn ($q) => $q->where('platform', $request->platform))
            ->when($request->filled('region'),   fn ($q) => $q->where('region', $request->region))
            ->when($request->filled('status'),   fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('type'),     fn ($q) => $q->where('account_type', $request->type))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->search;
                $q->where(function ($q) use ($s) {
                    $q->where('email', 'like', "%$s%")
                      ->orWhere('gamer_tag', 'like', "%$s%")
                      ->orWhere('mail_email', 'like', "%$s%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        $stats = [
            'total'    => Account::count(),
            'active'   => Account::where('status', 'active')->count(),
            'blocked'  => Account::where('status', 'blocked')->count(),
            'archived' => Account::where('status', 'archived')->count(),
        ];

        // Para los filtros: opciones distintas que ya hay en la base
        $platforms = Account::query()->select('platform')->distinct()->orderBy('platform')->pluck('platform');
        $regions   = Account::query()->select('region')->distinct()->orderBy('region')->pluck('region');

        return view('accounts.index', compact('accounts', 'stats', 'platforms', 'regions'));
    }

    /**
     * GET /accounts/{account}
     */
    public function show(Account $account)
    {
        $account->load(['game', 'parent.game', 'children', 'keys', 'assignments']);
        return view('accounts.show', compact('account'));
    }

    /**
     * GET /accounts/create
     */
    public function create()
    {
        $account = new Account(['platform' => 'DUAL', 'account_type' => 'INDEPENDIENTE', 'status' => 'active']);
        return view('accounts.create', compact('account'));
    }

    /**
     * POST /accounts
     */
    public function store(AccountRequest $request)
    {
        $account = DB::transaction(function () use ($request) {
            $account = Account::create($request->safe()->except('keys'));
            $this->syncKeys($account, $request->validated()['keys'] ?? []);
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
        $account->load(['keys', 'game.products' => fn ($q) => $q->whereNotNull('image_url')->limit(1)]);
        return view('accounts.edit', compact('account'));
    }

    /**
     * PUT /accounts/{account}
     */
    public function update(AccountRequest $request, Account $account)
    {
        DB::transaction(function () use ($request, $account) {
            $account->update($request->safe()->except('keys'));
            $this->syncKeys($account, $request->validated()['keys'] ?? []);
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
    public function incrementUsage(Account $account)
    {
        $account->load('assignments');

        if ($account->freeSlots() <= 0) {
            return back()->withErrors(['usage' => 'No hay slots libres en esta cuenta.']);
        }

        $usedSlots = $account->assignments
            ->where('status', 'active')
            ->pluck('slot_number')
            ->all();

        $capacity = $account->capacity();
        $freeSlot = null;
        for ($i = 1; $i <= $capacity; $i++) {
            if (! in_array($i, $usedSlots, true)) {
                $freeSlot = $i;
                break;
            }
        }

        if ($freeSlot === null) {
            return back()->withErrors(['usage' => 'No hay slots libres en esta cuenta.']);
        }

        $account->assignments()->create([
            'slot_number'  => $freeSlot,
            'status'       => 'active',
            'assigned_at'  => null,           // placeholder explícito: sin fecha
            'customer_name'  => null,
            'customer_email' => null,
        ]);

        return back()->with('success', "Uso manual agregado · slot $freeSlot ocupado.");
    }

    public function decrementUsage(Account $account)
    {
        // Buscar el placeholder más reciente (sin datos de cliente)
        $placeholder = $account->assignments()
            ->where('status', 'active')
            ->whereNull('customer_name')
            ->whereNull('customer_email')
            ->orderByDesc('id')
            ->first();

        if (! $placeholder) {
            return back()->withErrors([
                'usage' => 'No hay placeholders para liberar. Las asignaciones con datos de cliente solo se pueden revocar desde la order correspondiente.',
            ]);
        }

        $slot = $placeholder->slot_number;
        $placeholder->delete();

        return back()->with('success', "Slot $slot liberado.");
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
    public function reset(Account $account)
    {
        $result = DB::transaction(function () use ($account) {
            $expiredCount = $account->assignments()
                ->where('status', 'active')
                ->update([
                    'status'     => 'expired',
                    'updated_at' => now(),
                ]);

            $account->update(['reset_date' => now()]);

            return [
                'expired'     => $expiredCount,
                'newCapacity' => $account->fresh()->capacity(),
            ];
        });

        return back()->with('success', sprintf(
            'Cuenta reseteada. %d asignación(es) marcada(s) como expiradas. Capacidad reducida a %d.',
            $result['expired'],
            $result['newCapacity']
        ));
    }
}
