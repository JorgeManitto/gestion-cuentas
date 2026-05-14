<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Game;
use App\Models\OrderItem;
use App\Models\PurchaseOrder;
use App\Models\WooProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    /**
     * Pestañas: ordenes (default) | stock
     */
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'ordenes');

        $orders = PurchaseOrder::query()
            ->with(['game.products', 'orderItem.order'])
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
        $stockAccounts = collect();
        $uniqueRegions = collect();
        if ($tab === 'stock') {
            $stockAccounts = Account::query()
                ->whereNull('game_id')
                ->when($request->filled('q'), function ($q) use ($request) {
                    $term = '%' . strtolower($request->q) . '%';
                    $q->where(function ($w) use ($term) {
                        $w->whereRaw('LOWER(email) LIKE ?', [$term])
                          ->orWhereRaw('LOWER(region) LIKE ?', [$term])
                          ->orWhereRaw('LOWER(platform) LIKE ?', [$term]);
                    });
                })
                ->when($request->filled('platform') && $request->platform !== 'all',
                    fn ($q) => $q->where('platform', $request->platform))
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
            ->orderBy('platform')
            ->orderBy('region')
            ->get(['id', 'email', 'platform', 'region']);

            // dd($orders);
        return view('purchase-orders.index', compact(
            'orders', 'stats', 'coversByTitle', 'tab',
            'stockAccounts', 'uniqueRegions',
            'gamesCatalog', 'stockForComplete'
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
        ]);

        $qty = $data['quantity'];

        DB::transaction(function () use ($data, $qty) {
            for ($i = 0; $i < $qty; $i++) {
                PurchaseOrder::create([
                    'game_id'      => $data['game_id'] ?? null,
                    'game_title'   => $data['game_title'],
                    'platform'     => $data['platform'],
                    'region'       => $data['region'] ?: 'sin especificar',
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
            'account_id'    => 'required|exists:accounts,id',
            'purchase_date' => 'required|date',
            'cost_usd'      => 'nullable|numeric|min:0',
        ]);

        $account = Account::findOrFail($data['account_id']);

        // Resolver game_id: el de la OC o crear/buscar uno por title
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

        DB::transaction(function () use ($purchaseOrder, $account, $gameId, $data) {
            $account->update([
                'game_id'        => $gameId,
                'purchased_date' => $data['purchase_date'],
            ]);

            $purchaseOrder->update([
                'status'        => 'received',
                'arrival_date'  => $purchaseOrder->arrival_date ?? now(),
                'notes'         => trim(($purchaseOrder->notes ?? '')
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
            'region'        => 'sin especificar',
            'quantity'      => $item->quantity,
            'status'        => 'pending',
            'notes'         => "Generada desde order #{$item->order->wc_order_id}, ítem #{$item->id}",
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'purchase_order_id' => $po->id,
                'item_html' => view('orders.partials._item-card', [
                    'item'   => $item->fresh(['account.keys', 'account.game', 'game.products', 'purchaseOrders']),
                    'result' => null,
                ])->render(),
            ], 201);
        }

        return redirect()->route('orders.show', $item->order_id)
            ->with('success', "OC #{$po->id} generada");
    }
}