<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\WooProduct;
use App\Services\MatchmakingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public function __construct(private MatchmakingService $matchmaking) {}

    /**
     * POST /api/webhook/order
     *
     * Recibe el webhook del plugin de Woo. El plugin manda:
     *   {
     *     "orderId": "46158",
     *     "customerEmail": "...",
     *     "customerName": "...",
     *     "currency": "USD",
     *     "total": "$25.3 (USD)",
     *     "orderDate": "2026-05-10T04:01:54+00:00",
     *     "status": "processing",
     *     "items": [
     *       {
     *         "platform": "PlayStation",
     *         "gameTitle": "FC25 PS5",
     *         "consoleModel": "PS5",
     *         "quantity": 1,
     *         "gameId": 12345
     *       },
     *       ...
     *     ]
     *   }
     *
     * Idempotente: si llega 2 veces el mismo wc_order_id, actualiza el header
     * pero NO duplica items (la 2da llamada con --fresh-items=true los reemplaza,
     * sino los preserva tal cual están — protegiendo las asignaciones manuales).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'orderId'              => 'required|string',
            'customerEmail'        => 'required|email|max:255',
            'customerName'         => 'required|string|max:255',
            'currency'             => 'required|string|max:8',
            'total'                => 'nullable|string|max:64',
            'orderDate'            => 'required|date',
            'status'               => 'required|string|max:32',
            'items'                => 'required|array|min:1',
            'items.*.platform'     => 'required|string|max:32',
            'items.*.gameTitle'    => 'required|string|max:255',
            'items.*.consoleModel' => 'nullable|string|max:32',
            'items.*.quantity'     => 'required|integer|min:1',
            'items.*.gameId'       => 'nullable',   // viene como int o string desde Woo
        ]);

        $order = DB::transaction(function () use ($validated) {

            // 1) Crear o actualizar el header
            $order = Order::updateOrCreate(
                ['wc_order_id' => $validated['orderId']],
                [
                    'customer_name'  => $validated['customerName'],
                    'customer_email' => $validated['customerEmail'],
                    'currency'       => strtoupper($validated['currency']),
                    'total_raw'      => $validated['total'] ?? null,
                    'total_amount'   => $this->parseTotal($validated['total'] ?? null),
                    'wc_status'      => $validated['status'],
                    'order_date'     => $validated['orderDate'],
                ]
            );

            // 2) Si la order es nueva (sin items todavía), creamos los items.
            //    Si ya existían (re-envío manual del plugin), preservamos las asignaciones
            //    manuales que el operador ya hizo y solo actualizamos campos no críticos.
            if ($order->items()->doesntExist()) {
                foreach ($validated['items'] as $itemData) {
                    $this->createOrderItem($order, $itemData);
                }
            } else {
                Log::info("Webhook reenviado para order {$order->wc_order_id}, conservando items existentes");
            }

            return $order;
        });

        $order->load('items.game');

        return response()->json([
            'success'        => true,
            'order_id'       => $order->id,
            'wc_order_id'    => $order->wc_order_id,
            'items_count'    => $order->items->count(),
            'items_matched'  => $order->items->whereNotNull('game_id')->count(),
        ], 201);
    }

    /**
     * Crea un OrderItem resolviendo el game_id canónico y normalizando la plataforma.
     */
    private function createOrderItem(Order $order, array $data): OrderItem
    {
        // Resolver el wc_product_id → game_id canónico (vía woo_products)
        $wcProductId = $data['gameId'] ?? null;
        $wcProduct = $wcProductId
            ? WooProduct::find((int) $wcProductId)
            : null;

        $platformNormalized = $this->matchmaking->normalizePlatform(
            $data['platform'],
            $data['consoleModel'] ?? null
        );

        return $order->items()->create([
            'wc_product_id'        => $wcProductId,
            'game_id'              => $wcProduct?->game_id,
            'game_title'           => $data['gameTitle'],
            'platform'             => $data['platform'],
            'console_model_raw'    => $data['consoleModel'] ?? null,
            'platform_normalized'  => $platformNormalized,
            'quantity'             => (int) $data['quantity'],
            'fulfillment_status'   => 'pending',
        ]);
    }

    /**
     * Convierte "$25.3 (USD)" en 25.30.
     */
    private function parseTotal(?string $total): ?float
    {
        if (! $total) return null;
        if (preg_match('/[\d]+(?:[.,][\d]+)?/', $total, $m)) {
            return (float) str_replace(',', '.', $m[0]);
        }
        return null;
    }

    /**
     * GET /orders
     */
    public function index(Request $request)
    {
        $orders = Order::query()
            ->with(['items.game'])
            ->withCount('items')
            ->when($request->filled('wc_status'), fn ($q) => $q->where('wc_status', $request->wc_status))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->search;
                $q->where(function ($q) use ($s) {
                    $q->where('customer_email', 'like', "%$s%")
                      ->orWhere('customer_name', 'like', "%$s%")
                      ->orWhere('wc_order_id', 'like', "%$s%");
                });
            })
            ->orderByDesc('order_date')
            ->paginate(50)
            ->withQueryString();

        $stats = [
            'total'      => Order::count(),
            'pending'    => OrderItem::where('fulfillment_status', 'pending')->count(),
            'in_progress'=> OrderItem::where('fulfillment_status', 'in_progress')->count(),
            'delivered'  => OrderItem::where('fulfillment_status', 'delivered')->count(),
        ];

        return view('orders.index', compact('orders', 'stats'));
    }

    /**
     * GET /orders/{order}
     */
    public function show(Order $order)
    {
        $order->load([
            'items.game.products' => fn ($q) => $q->whereNotNull('image_url')->limit(1),
            'items.account.keys',
            'items.account.game',
            'items.purchaseOrders',
        ]);

        // Por cada item pendiente, precalculamos los candidatos
        $candidatesByItem = [];
        foreach ($order->items as $item) {
            if ($item->fulfillment_status === 'pending') {
                $candidatesByItem[$item->id] = $this->matchmaking->findCandidates($item);
            }
        }

        return view('orders.show', compact('order', 'candidatesByItem'));
    }

    /**
     * POST /items/{item}/assign
     * Devuelve JSON con el HTML del item re-renderizado si es AJAX
     * (para optimistic UI), sino redirect tradicional.
     */
    public function assignItem(Request $request, OrderItem $item)
    {
        $validated = $request->validate([
            'account_id'    => 'required|exists:accounts,id',
            'who_delivered' => 'required|string|max:100',
        ]);

        $account = Account::findOrFail($validated['account_id']);
        $ok = $this->matchmaking->assign($item, $account, $validated['who_delivered']);

        if (! $ok) {
            $msg = 'No se pudo asignar la cuenta — sin slots libres (puede haber cambiado por otra asignación reciente).';
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return back()->withErrors(['assign' => $msg]);
        }

        if ($request->expectsJson()) {
            // Re-renderizar el partial del item con la nueva data
            $fresh = $item->fresh(['account.keys', 'account.game', 'game.products', 'purchaseOrders']);
            return response()->json([
                'success'   => true,
                'message'   => "Cuenta {$account->email} asignada",
                'item_html' => view('orders.partials._item-card', [
                    'item'   => $fresh,
                    'result' => null,
                ])->render(),
            ]);
        }

        return redirect()->route('orders.show', $item->order_id)
            ->with('success', "Cuenta {$account->email} asignada al ítem.");
    }
}
