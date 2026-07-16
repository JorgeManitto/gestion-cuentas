<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Game;
use App\Models\OrderItem;
use App\Models\PurchaseOrder;
use App\Models\WooProduct;
use App\Services\MatchmakingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PurchaseOrderController extends Controller
{
    public function __construct(private MatchmakingService $matchmaking) {}
    /**
     * Pestañas: ordenes (default) | stock
     */
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'ordenes');

        $orders = PurchaseOrder::query()
            ->with(['game.products', 'orderItem.order', 'account.keys'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        // Para OCs sin game_id, buscamos la portada por match de nombre en WooProduct
        $unlinkedTitles = $orders->getCollection()
            ->whereNull('game_id')
            ->pluck('game_title')
            ->filter()
            ->unique()
            ->values();

        $coversByTitle = $unlinkedTitles->isNotEmpty()
            ? WooProduct::whereIn('name', $unlinkedTitles)
                ->whereNotNull('image_url')
                ->pluck('image_url', 'name')
            : collect();
        $stats = [
            'pending'   => PurchaseOrder::where('status', 'pending')->count(),
            'purchased' => PurchaseOrder::where('status', 'purchased')->count(),
            'received'  => PurchaseOrder::where('status', 'received')->count(),
        ];

        // --- Datos para pestaña "Stock" ---
        $stockAccounts  = collect();
        $uniqueRegions  = collect();
        $uniqueConsoles = collect();
        if ($tab === 'stock') {
            $stockAccounts = Account::query()
                ->whereNull('game_id')
                // ->where('account_type', '!=', 'INDEPENDIENTE')
                ->whereNotNull('account_type')
                ->with('keys')
                ->when($request->filled('q'), function ($q) use ($request) {
                    $term = '%' . strtolower($request->q) . '%';
                    $q->where(function ($w) use ($term) {
                        $w->whereRaw('LOWER(email) LIKE ?', [$term])
                          ->orWhereRaw('LOWER(region) LIKE ?', [$term])
                          ->orWhereRaw('LOWER(type_console) LIKE ?', [$term]);
                    });
                })
                ->when($request->filled('type_console') && $request->type_console !== 'all',
                    fn ($q) => $q->where('type_console', $request->type_console))
                ->when($request->filled('region') && $request->region !== 'all',
                    fn ($q) => $q->where('region', $request->region))
                ->orderByRaw('region IS NULL, region ASC')
                ->get();

            $uniqueRegions = Account::query()
                ->whereNull('game_id')
                ->whereNotNull('region')
                ->distinct()
                ->orderBy('region')
                ->pluck('region');

            $uniqueConsoles = Account::query()
                ->whereNull('game_id')
                ->whereNotNull('type_console')
                ->distinct()
                ->orderBy('type_console')
                ->pluck('type_console');
        }

        // Catálogo de juegos para autocompletar en el modal "Crear OC"
        $gamesCatalog = Game::query()
            ->with('products:id,game_id,image_url')
            ->orderBy('canonical_name')
            ->get(['id', 'canonical_name', 'slug']);

        // Cuentas sin juego, para el modal "Completar OC"
        $stockForComplete = Account::query()
            ->whereNull('game_id')
            
            ->where('status', 'active')
            ->with('keys:id,account_id,position,key_value')
            ->orderBy('platform')
            ->orderBy('region')
            ->get(['id', 'email', 'platform', 'region']);

        $linkableAccounts = Account::query()
            ->whereIn('account_type', ['MADRE', 'HIJA'])
            ->orderBy('account_type')
            ->orderBy('email')
            ->get(['id', 'email', 'account_type', 'parent_account_id']);    

            // dd($orders);
        return view('purchase-orders.index', compact(
            'orders', 'stats', 'coversByTitle', 'tab',
            'stockAccounts', 'uniqueRegions', 'uniqueConsoles',
            'gamesCatalog', 'stockForComplete',
            'linkableAccounts'
        ));
    }

    /**
     * POST /purchase-orders — alta manual desde el modal
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'game_id'      => 'nullable|exists:games,id',
            'game_title'   => 'required|string|max:255',
            'platform'     => 'required|string|max:50',
            'region'       => 'nullable|string|max:50',
            'quantity'     => 'required|integer|min:1|max:50',
            'arrival_date' => 'nullable|date',
            'is_dual'      => 'nullable|boolean',
            'notes_region' => 'nullable|string|max:2000',
        ]);

        $qty    = $data['quantity'];
        $isDual = $request->boolean('is_dual');

        DB::transaction(function () use ($data, $isDual, $qty) {
            for ($i = 0; $i < $qty; $i++) {
                PurchaseOrder::create([
                    'game_id'      => $data['game_id'] ?? null,
                    'game_title'   => $data['game_title'],
                    'platform'     => $data['platform'],
                    'is_dual'      => $isDual,
                    'region'       => $data['region'] ?: 'sin especificar',
                    'notes_region' => $data['notes_region'] ?? null,
                    'quantity'     => 1,
                    'status'       => 'pending',
                    'arrival_date' => $data['arrival_date'] ?? null,
                    'notes'        => 'Creada manualmente',
                ]);
            }
        });

        return redirect()->route('purchase-orders.index')
            ->with('success', "Se generaron {$qty} OC para {$data['game_title']}.");
    }

    /**
     * DELETE /purchase-orders/{id}
     */
    public function destroy(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->delete();

        return redirect()->route('purchase-orders.index')
            ->with('success', "OC #{$purchaseOrder->id} eliminada.");
    }

    /**
     * POST /purchase-orders/{id}/complete
     * Marca la OC como recibida y vincula la cuenta de stock al juego.
     */
    public function complete(Request $request, PurchaseOrder $purchaseOrder)
    {
        $data = $request->validate([
            'account_id'      => 'required|exists:accounts,id',
            'platform'        => 'required|string|max:50',
            'is_dual'         => 'nullable|boolean',
            'purchase_date'   => 'required|date',
            'cost_usd'        => 'nullable|numeric|min:0',
            'keys'            => 'nullable|array',
            'keys.*.position' => 'required|integer|min:1|max:20',
            'keys.*.value'    => 'required|string|max:64',
        ]);

        $account = Account::findOrFail($data['account_id']);

        $gameId = $purchaseOrder->game_id;
        if (! $gameId && $purchaseOrder->game_title) {
            $game = Game::firstOrCreate(
                ['canonical_name' => $purchaseOrder->game_title],
                [
                    'slug'            => \Illuminate\Support\Str::slug($purchaseOrder->game_title),
                    'normalized_name' => mb_strtolower($purchaseOrder->game_title),
                ]
            );
            $gameId = $game->id;
        }

        DB::transaction(function () use ($purchaseOrder, $account, $gameId, $data, $request) {
            $account->update([
                'game_id'        => $gameId,
                'platform'       => $data['platform'],
                'is_dual'        => $request->boolean('is_dual'),
                'purchased_date' => $data['purchase_date'],
            ]);

            // Llaves de recuperación (aditivo)
            foreach ($data['keys'] ?? [] as $k) {
                $account->keys()->updateOrCreate([
                    'position'  => $k['position'],
                ], [
                    'key_value' => $k['value'],
                ]);
            }

            $purchaseOrder->update([
                'status'       => 'received',
                'arrival_date' => $purchaseOrder->arrival_date ?? now(),
                'notes'        => trim(($purchaseOrder->notes ?? '')
                    . "\nCompletada con cuenta {$account->email}"
                    . (isset($data['cost_usd']) ? " · USD {$data['cost_usd']}" : '')),
            ]);
        });

        return redirect()->route('purchase-orders.index')
            ->with('success', "OC #{$purchaseOrder->id} completada con {$account->email}.");
    }

    /**
     * POST /items/{item}/purchase-order — sin cambios funcionales, lo dejo
     */
    public function storeFromItem(Request $request, OrderItem $item)
    {
        $data = $request->validate([
            'region'       => 'required|string|max:50',
            'notes_region' => 'nullable|string|max:2000',
        ]);

        $existing = PurchaseOrder::where('order_item_id', $item->id)
            ->whereIn('status', ['pending', 'purchased'])
            ->first();

        if ($existing) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => "Ya existe la OC #{$existing->id} para este ítem",
                ], 409);
            }
            return back()->withErrors(['po' => "Ya existe la OC #{$existing->id} para este ítem"]);
        }

        $po = PurchaseOrder::create([
            'order_item_id' => $item->id,
            'game_id'       => $item->game_id,
            'game_title'    => $item->game_title,
            'platform'      => $item->platform_normalized ?? $item->platform,
            'console_model' => $item->console_model_raw,
            'region'        => $data['region'],
            'notes_region'  => $data['notes_region'] ?? null, 
            'quantity'      => $item->quantity,
            'status'        => 'pending',
            'notes'         => "Generada desde order #{$item->order->wc_order_id}, ítem #{$item->id}",
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'purchase_order_id' => $po->id,
                'item_html' => view('orders.partials._item-card', [
                    'item'   => $item->fresh(['order', 'account.keys', 'account.game', 'game.products', 'purchaseOrders']),
                    'result' => null,
                ])->render(),
            ], 201);
        }

        return redirect()->route('orders.show', $item->order_id)
            ->with('success', "OC #{$po->id} generada");
    }
    public function storeStockAccount(Request $request)
    {
        $data = $request->validate([
            'email'             => 'required|email|max:255',
            'password'          => 'required|string|max:255',
            'platform'          => 'required|string|max:50',
            'type_console'      => 'required|string|max:50',
            'region'            => 'nullable|string|max:50',
            'is_dual'           => 'nullable|boolean',
            'account_type'      => 'nullable|string|max:50',
            'parent_account_id' => ['nullable', 'exists:accounts,id', Rule::requiredIf(fn () => $request->account_type === 'HIJA')],
            'children_ids'      => 'nullable|array',
            'children_ids.*'    => 'exists:accounts,id',
            'mail_email'        => 'nullable|email|max:255',
            'mail_password'     => 'nullable|string|max:255',
            'created_date'      => 'nullable|date',
            'gamer_tag'         => 'nullable|string|max:255',
            'full_name'         => 'nullable|string|max:255',
            'birth_date'        => 'nullable|date',
            'notes'             => 'nullable|string|max:1000',
            'keys'              => 'nullable|array',
            'keys.*.position'   => 'required|integer|min:1|max:20',
            'keys.*.value'      => 'required|string|max:64',
        ]);

        $isDual      = $request->boolean('is_dual');
        $accountType = $data['account_type'] ?? 'INDEPENDIENTE';

        // Derivar la plataforma desde la consola elegida. El <select name="platform">
        // del modal está oculto y siempre manda 'PS4', así que acá lo normalizamos
        // según type_console para no guardar todo en PS4.
        $data['platform'] = match (strtoupper($data['type_console'])) {
            'NINTENDO'    => 'SWITCH',
            'XBOX'        => 'XBOX_ONE',
            'STEAM'       => 'STEAM',
            'PLAYSTATION' => 'PS4',
            default       => $data['platform'],
        };

        DB::transaction(function () use ($data, $isDual, $accountType) {
            $account = Account::create([
                'game_id'           => null,
                'parent_account_id' => $accountType === 'HIJA' ? ($data['parent_account_id'] ?? null) : null,
                'platform'          => $data['platform'],
                'type_console'      => $data['type_console'],
                'is_dual'           => $isDual,
                'account_type'      => $accountType,
                'region'            => $data['region'] ?: null,
                'email'             => $data['email'],
                'password'          => $data['password'],
                'mail_email'        => $data['mail_email'] ?? null,
                'mail_password'     => $data['mail_password'] ?? null,
                'created_date'      => $data['created_date'] ?? null,
                'gamer_tag'         => $data['gamer_tag'] ?? null,
                'full_name'         => $data['full_name'] ?? null,
                'birth_date'        => $data['birth_date'] ?? null,
                'status'            => 'active',
                'notes'             => $data['notes'] ?? 'Cuenta de stock creada manualmente',
            ]);

            // Llaves de recuperación (mismo patrón que complete)
            foreach ($data['keys'] ?? [] as $k) {
                $account->keys()->updateOrCreate(
                    ['position'  => $k['position']],
                    ['key_value' => $k['value']]
                );
            }

            // Si es MADRE, las cuentas elegidas pasan a ser sus hijas
            if ($accountType === 'MADRE' && ! empty($data['children_ids'])) {
                Account::whereIn('id', $data['children_ids'])
                    ->update(['parent_account_id' => $account->id]);
            }
        });

        return redirect()->route('purchase-orders.index', ['tab' => 'stock'])
            ->with('success', "Cuenta {$data['email']} agregada al stock.");
    }
    /**
     * POST /items/{item}/send-to-reset
     * Crea una "OC de reset": marca el ítem como enviado a resetear para que
     * OTRO empleado haga el reset desde /stock/reseteables. Aparece en la lista de OCs.
     */
    public function sendToReset(Request $request, OrderItem $item)
    {
        $data = $request->validate([
            'account_id' => 'required|exists:accounts,id',
        ]);

        // Evitar duplicar
        $existing = PurchaseOrder::where('order_item_id', $item->id)
            ->whereIn('status', ['pending', 'purchased'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => "Ya existe la OC #{$existing->id} para este ítem",
            ], 409);
        }

        $item->loadMissing('order');

        // La cuenta elegida tiene que seguir siendo reseteable para este ítem
        $account = $this->matchmaking->findResettableSuggestions($item)
            ->firstWhere('id', (int) $data['account_id']);

        if (! $account) {
            return response()->json([
                'success' => false,
                'message' => 'Esa cuenta ya no está disponible para resetear (puede haber cambiado).',
            ], 422);
        }

        $po = PurchaseOrder::create([
            'order_item_id' => $item->id,
            'account_id'    => $account->id,
            'game_id'       => $item->game_id,
            'game_title'    => $item->game_title,
            'platform'      => $item->platform_normalized ?? $item->platform,
            'console_model' => $item->console_model_raw,
            'region'        => 'sin especificar',
            'quantity'      => $item->quantity,
            'status'        => 'pending',
            'type'          => 'reset',
            'notes'         => "Enviada a resetear desde order #{$item->order->wc_order_id}, ítem #{$item->id} · cuenta {$account->email}",
        ]);

        return response()->json([
            'success'           => true,
            'message'           => "Ítem enviado a resetear con {$account->email}",
            'purchase_order_id' => $po->id,
            'item_html'         => view('orders.partials._item-card', [
                'item'   => $item->fresh(['order', 'account.keys', 'account.game', 'game.products', 'purchaseOrders']),
                'result' => null,
            ])->render(),
        ], 201);
    }

    /**
     * POST /purchase-orders/{purchaseOrder}/reset
     * Resetea la cuenta asociada a una OC de tipo 'reset' y marca la OC como recibida.
     */
    public function reset(Request $request, PurchaseOrder $purchaseOrder)
    {
        $account = $purchaseOrder->account;

        if (! $account) {
            return back()->withErrors([
                'reset' => 'Esta OC no tiene una cuenta asociada para resetear.',
            ]);
        }

        // Bloqueo por cooldown (180 días desde último reset / compra)
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

        $result = DB::transaction(function () use ($account, $data, $purchaseOrder) {
            $expiredCount = $account->assignments()
                ->where('status', 'active')
                ->update(['status' => 'expired', 'updated_at' => now()]);

            $account->update(['reset_date' => now()]);

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

            // ── quitar el pending de la OC ──
            $purchaseOrder->update([
                'status'       => 'received',
                'arrival_date' => $purchaseOrder->arrival_date ?? now(),
                'notes'        => trim(($purchaseOrder->notes ?? '')
                    . "\nReset realizado sobre {$account->email}"),
            ]);

            return [
                'expired'     => $expiredCount,
                'newCapacity' => $account->fresh()->capacity(),
                'consumedKey' => $consumedKey,
            ];
        });

        $msg = sprintf(
            'Cuenta %s reseteada. %d asignación(es) expirada(s). Capacidad reducida a %d. OC #%d recibida.',
            $account->email,
            $result['expired'],
            $result['newCapacity'],
            $purchaseOrder->id
        );

        if ($result['consumedKey']) {
            $msg .= sprintf(' Llave #%d consumida.', $result['consumedKey']->position);
        }

        return back()->with('success', $msg);
    }
}