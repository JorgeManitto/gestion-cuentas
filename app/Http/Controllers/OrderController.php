<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\WooProduct;
use App\Services\MatchmakingService;
use App\Services\OrderPresence;
use App\Services\WooOrderClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Mail\AccountDeliveryMail;
use Illuminate\Support\Facades\Mail;
use App\Mail\GameChangeNotificationMail;
use App\Models\AccountSecondaryAssignment;

class OrderController extends Controller
{
    public function __construct(
        private MatchmakingService $matchmaking,
        private WooOrderClient $woo,
        private OrderPresence $presence,
    ) {}

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
            'items.*.price'        => 'nullable|numeric',
            'items.*.price_sale'   => 'nullable|numeric',
            'items.*.consoleModel' => 'nullable|string|max:32',
            'items.*.quantity'     => 'required|integer|min:1',
            'items.*.gameId'       => 'nullable',   // viene como int o string desde Woo
            'items.*.isMembership' => 'nullable',
            'items.*.isPreOrden'   => 'nullable',
            'items.*.isPack'       => 'nullable',
            'items.*.qr'           => 'nullable|string|max:255',
            'items.*.packGames'              => 'nullable|array',
            'items.*.packGames.*.gameId'     => 'nullable',
            'items.*.packGames.*.gameTitle'  => 'nullable|string|max:255',
            'items.*.packGames.*.platform'   => 'nullable|string|max:32',
        ]);

        $order = DB::transaction(function () use ($validated) {

            // ¿Algún item es membresía? (normalizamos true/1/"1"/"yes"/"on")
            $hasMembership = collect($validated['items'])->contains(
                fn ($item) => filter_var($item['isMembership'] ?? false, FILTER_VALIDATE_BOOLEAN)
                // ↑ si el plugin manda otra cosa, cambiá SOLO esta línea, p.ej:
                //   fn ($item) => ($item['type'] ?? null) === 'membership'
            );
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
                    'has_membership' => $hasMembership,
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

        $isPack = filter_var($data['isPack'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return $order->items()->create([
            'wc_product_id'        => $wcProductId,
            'game_id'              => $wcProduct?->game_id,
            'game_title'           => $data['gameTitle'],
            'platform'             => $data['platform'],
            'console_model_raw'    => $data['consoleModel'] ?? null,
            'platform_normalized'  => $platformNormalized,
            'quantity'             => (int) $data['quantity'],
            'price'                => $data['price'] ?? null,
            'price_sale'           => $data['price_sale'] ?? null,
            'is_preorden'          => filter_var($data['isPreOrden'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'is_pack'              => $isPack,
            'qr'                   => ($data['qr'] ?? '') !== '' ? $data['qr'] : null,
            'pack_games'           => $isPack ? $this->normalizePackGames($data['packGames'] ?? null) : null,
            'fulfillment_status'   => 'pending',
        ]);
    }

    /**
     * Normaliza el arreglo packGames de Woo a la forma que guardamos:
     *   [{ game_id: int|null, game_title: string, platform: string|null }, ...]
     * Devuelve null si no vino nada usable (pack "viejo" sin desglose).
     */
    private function normalizePackGames($packGames): ?array
    {
        if (! is_array($packGames) || empty($packGames)) {
            return null;
        }

        $normalized = collect($packGames)
            ->filter(fn ($g) => is_array($g))
            ->map(fn ($g) => [
                'game_id'    => isset($g['gameId']) && $g['gameId'] !== '' ? (int) $g['gameId'] : null,
                'game_title' => $g['gameTitle'] ?? '—',
                'platform'   => $g['platform'] ?? null,
            ])
            ->values()
            ->all();

        return empty($normalized) ? null : $normalized;
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
        // Botón "Limpiar"
        if ($request->boolean('clear')) {
            session()->forget('orders_filters');
            return redirect()->route('orders.index');
        }

        $page = (int) $request->input('page');

        // Ahora 'page' también dispara el guardado/restauración
        if ($request->hasAny(['search', 'wc_status', 'console', 'page'])) {
            // Guardamos filtros + página actual (sin vacíos)
            $filters = array_filter([
                'search'    => $request->input('search'),
                'wc_status' => $request->input('wc_status'),
                'console'   => array_filter((array) $request->input('console')),
                // page 1 es el default → no hace falta persistirla
                'page'      => $page > 1 ? $page : null,
            ], fn ($v) => $v !== null && $v !== '' && $v !== []);

            $filters
                ? session(['orders_filters' => $filters])
                : session()->forget('orders_filters');
        } elseif ($filters = session('orders_filters')) {
            // URL pelada pero hay estado guardado → redirigimos con filtros + página
            return redirect()->route('orders.index', $filters);
        }

        [$orders, $stats] = $this->queryOrders($request);
        $signature = $this->ordersSignature($orders, $stats);
        $consoles  = $this->availableConsoles();
        $statuses  = $this->availableStatuses();

        return view('orders.index', compact('orders', 'stats', 'signature', 'consoles', 'statuses'));
    }

    /**
     * GET /orders/poll  → JSON con las partes dinámicas re-renderizadas.
     */
    public function poll(Request $request): JsonResponse
    {
        [$orders, $stats] = $this->queryOrders($request);
        // que los links de paginación apunten a /orders, no a /orders/poll
        $orders->withPath(route('orders.index'));

        return response()->json([
            'signature'       => $this->ordersSignature($orders, $stats),
            'stats_html'      => view('orders.partials._stats', compact('stats'))->render(),
            'rows_html'       => view('orders.partials._rows', compact('orders'))->render(),
            'pagination_html' => $orders->links()->toHtml(),
        ]);
    }

    /**
     * Query compartido entre index y poll (mismos filtros, misma paginación).
     */
    private function queryOrders(Request $request): array
    {
        $orders = Order::query()
            ->with(['items.game', 'items.wooProduct'])
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
            ->when($request->filled('console'), function ($q) use ($request) {
                // Acepta uno o varios: ?console[]=PS5&console[]=Xbox
                $consoles = array_filter((array) $request->console, fn ($c) => $c !== '' && $c !== null);

                if (empty($consoles)) {
                    return; // nada seleccionado → no filtramos
                }

                $q->whereHas('items', fn ($iq) =>
                    $iq->whereIn('platform_normalized', $consoles)
                    // Si preferís el modelo crudo: $iq->whereIn('console_model_raw', $consoles)
                );
            })
            ->orderByDesc('order_date')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total'       => Order::count(),
            'pending'     => OrderItem::where('fulfillment_status', 'pending')->count(),
            'in_progress' => OrderItem::where('fulfillment_status', 'in_progress')->count(),
            'delivered'   => OrderItem::where('fulfillment_status', 'delivered')->count(),
        ];

        $this->attachMatchmakingStatus($orders);
        $this->attachPresence($orders);

        return [$orders, $stats];
    }

    /**
     * Adjunta a cada orden de la página quién tiene el control ahora mismo
     * (para pintar el candado en el listado). Atributo transitorio.
     */
    private function attachPresence($orders): void
    {
        $ids     = $orders->getCollection()->pluck('id')->all();
        $holders = $this->presence->holdersFor($ids);

        foreach ($orders->getCollection() as $order) {
            $order->setAttribute('presence_holder', $holders[$order->id] ?? null);
        }
    }

    /**
     * Firma del estado visible. Si no cambia, el front no repinta.
     * Incluye fulfillment_status de cada item para detectar asignaciones.
     */
    private function ordersSignature($orders, array $stats): string
    {
        $rows = $orders->getCollection()->map(fn ($o) => [
            $o->id,
            $o->wc_status,
            $o->items_count,
            $o->items->pluck('fulfillment_status')->implode(','),
            $o->mm_status['state']   ?? null,                                   // ← NUEVO
            ($o->mm_status['ready'] ?? 0).'-'.($o->mm_status['oc'] ?? 0).'-'.($o->mm_status['missing'] ?? 0), // ← NUEVO
            $o->presence_holder['id'] ?? null,                                  // ← candado (quién controla)
        ])->all();

        return md5(json_encode([
            'page'  => $orders->currentPage(),
            'total' => $orders->total(),
            'stats' => $stats,
            'rows'  => $rows,
        ]));
    }

    public function show(Order $order)
    {
        $order->load([
            'items.wooProduct',
            'items.game',
            'items.account.keys',
            'items.account.game',
            'items.assignment',
            'items.secondaryAssignments.account.game.products',
            'items.purchaseOrders',
        ]);

        $candidatesByItem       = [];
        $resetSuggestionsByItem = [];
        $packSuggestionsByItem  = [];

        foreach ($order->items as $item) {
            // Packs con juegos preseleccionados: sugerimos una cuenta secundaria por juego.
            if ($item->fulfillment_status === 'pending' && $item->is_pack && $item->packGames()) {
                $packSuggestionsByItem[$item->id] = $this->packSuggestions($item);
            }

            if ($item->fulfillment_status !== 'pending' || $item->is_pack) {   // ← + is_pack
                continue;
            }

            $candidates = $this->matchmaking->findCandidates($item);
            $candidatesByItem[$item->id] = $candidates;

            // ¿Hay una cuenta lista para enviar ya mismo?
            $hasAvailable = $candidates && ! $candidates->isEmpty() && $candidates->best();

            // Solo buscamos/mostramos sugerencias de reseteo si NO hay cuenta disponible.
            if (! $hasAvailable) {
                $suggestions = $this->matchmaking->findResettableSuggestions($item);
                if ($suggestions->isNotEmpty()) {
                    $resetSuggestionsByItem[$item->id] = $suggestions;
                }
            }
        }

        return view('orders.show', compact('order', 'candidatesByItem', 'resetSuggestionsByItem', 'packSuggestionsByItem'));
    }

    /**
     * POST /orders/{order}/heartbeat
     *
     * "Heartbeat" estilo WordPress: el cliente late cada pocos segundos mientras
     * tiene la orden abierta. Registra/refresca la presencia del usuario y
     * devuelve los OTROS usuarios que están mirando la misma orden ahora.
     *
     * Con `leave=1` (lo manda navigator.sendBeacon al cerrar la pestaña) se
     * quita al usuario en vez de refrescarlo.
     */
    public function heartbeat(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        $name = $user->name ?: $user->email;

        // El payload trae: viewers (otros presentes), controller (quién manda) y
        // has_control (si soy yo). `take=1` arrebata el control explícitamente.
        $payload = $request->boolean('leave')
            ? $this->presence->leave($order->id, $user->id)
            : $this->presence->beat($order->id, $user->id, $name, $request->boolean('take'));

        return response()->json($payload + ['interval' => OrderPresence::INTERVAL]);
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
            

        $item->loadMissing('order');
        $processingStatus = config('services.woo.processing_status', 'processing');

        if (! $item->order || $item->order->wc_status !== $processingStatus) {
            $msg = 'Solo se puede enviar cuando la orden está en "processing"'
                . ($item->order ? " (actual: {$item->order->wc_status})." : '.');
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return back()->withErrors(['assign' => $msg]);
        }

        $account = Account::findOrFail($validated['account_id']);
        $ok = $this->matchmaking->assign($item, $account, $validated['who_delivered']);

        if (! $ok) {
            $msg = 'No se pudo asignar la cuenta — sin slots libres (puede haber cambiado por otra asignación reciente).';
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return back()->withErrors(['assign' => $msg]);
        }

        // ── NUEVO: si éste era el último item sin asignar, completar en Woo ──
        $orderCompleted = $this->maybeCompleteOrderInWoo($item->order_id);
        $this->sendDeliveryEmail($item);
        if ($request->expectsJson()) {
            $fresh = $item->fresh(['account.keys', 'account.game', 'wooProduct', 'purchaseOrders']);
            return response()->json([
                'success'          => true,
                'message'          => "Cuenta {$account->email} asignada",
                'order_completed'  => $orderCompleted,   // ← el front puede mostrar un toast distinto
                'item_html'        => view('orders.partials._item-card', [
                    'item'   => $fresh,
                    'result' => null,
                ])->render(),
            ]);
        }

        $flash = $orderCompleted
            ? "Cuenta {$account->email} asignada. ¡Orden completa! Marcada como completada en Woo."
            : "Cuenta {$account->email} asignada al ítem.";

        return redirect()->route('orders.show', $item->order_id)->with('success', $flash);
    }

    /**
     * POST /orders/{order}/assign-all
     * Asigna la mejor candidata a cada item pendiente que tenga una disponible.
     * Solo opera si la orden está en "processing".
     */
    public function assignAll(Request $request, Order $order)
    {
        $processingStatus = config('services.woo.processing_status', 'processing');

        if ($order->wc_status !== $processingStatus) {
            $msg = "Solo se pueden enviar productos con la orden en \"processing\" (actual: {$order->wc_status}).";
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return back()->withErrors(['assign' => $msg]);
        }

        $who = auth()->user()->email;
        $order->load('items');

        $assigned = [];
        $failed   = [];

        foreach ($order->items as $item) {
            if ($item->fulfillment_status !== 'pending' || $item->is_pack) {   // ← + is_pack
                continue;
            }
           
            // Re-calculamos en cada vuelta: la asignación anterior pudo ocupar slots.
            $result = $this->matchmaking->findCandidates($item);
            $best   = ($result && ! $result->isEmpty()) ? $result->best() : null;

            if (! $best) {
                $failed[] = ['item_id' => $item->id, 'reason' => 'sin candidata'];
                continue;
            }

            $ok = $this->matchmaking->assign($item, $best->account, $who);

            if ($ok) {
                $this->sendDeliveryEmail($item);
                $assigned[] = $item->id;
            } else {
                $failed[] = ['item_id' => $item->id, 'reason' => 'sin slots libres'];
            }
        }

        $orderCompleted = $this->maybeCompleteOrderInWoo($order->id);

        if ($request->expectsJson()) {
            $cards = [];
            foreach ($assigned as $itemId) {
                $fresh = OrderItem::with([
                    'account.keys', 'account.game', 'wooProduct', 'purchaseOrders', 'assignment',
                ])->find($itemId);

                $cards[] = [
                    'item_id'   => $itemId,
                    'item_html' => view('orders.partials._item-card', [
                        'item'   => $fresh,
                        'result' => null,
                    ])->render(),
                ];
            }

            $message = count($assigned) . ' producto(s) enviado(s)'
                    . (count($failed) ? ', ' . count($failed) . ' sin asignar' : '');

            return response()->json([
                'success'         => true,
                'assigned_count'  => count($assigned),
                'failed'          => $failed,
                'order_completed' => $orderCompleted,
                'cards'           => $cards,
                'message'         => $message,
            ]);
        }

        return redirect()->route('orders.show', $order->id)
            ->with('success', count($assigned) . ' producto(s) enviado(s).');
    }

    /**
     * Si TODOS los items de la orden ya tienen cuenta asignada, le pide a Woo
     * que pase la orden a 'completed'. Idempotente: no re-dispara si ya está
     * en ese estado. Devuelve true si efectivamente completó ahora.
     */
    private function maybeCompleteOrderInWoo(int $orderId): bool
    {
        $order = Order::with('items')->findOrFail($orderId);

        $completeStatus = config('services.woo.complete_status', 'completed');

        // Ya estaba completada → no reenviar
        if ($order->wc_status === $completeStatus) {
            return false;
        }

        $pendingLeft  = $order->items->whereNotIn('fulfillment_status', ['delivered', 'replaced']);
        $hasDelivered = $order->items->where('fulfillment_status', 'delivered')->isNotEmpty();
        $allDone      = $order->items->isNotEmpty() && $pendingLeft->isEmpty() && $hasDelivered;

        if (! $allDone) {
            return false;
        }

        $sent = $this->woo->setStatus($order->wc_order_id, $completeStatus);

        if ($sent) {
            // Reflejamos el cambio localmente para no depender del webhook de vuelta
            $order->update(['wc_status' => $completeStatus]);
            Log::info("Order {$order->wc_order_id}: todos los items asignados → completada en Woo");
            return true;
        }

        // Falló la llamada a Woo, pero la asignación local SÍ quedó hecha.
        // No revertimos nada — el operador puede reintentar o el estado se
        // corrige manualmente. Queda el log de error en WooOrderClient.
        return false;
    }
    /**
     * POST /api/webhook/set-status
     *
     * Recibe del plugin de Woo cuando cambia el estado de una orden:
     *   { "orderId": 46158, "status": "completed" }
     *
     * Status posibles de Woo: pending, processing, on-hold, completed,
     * cancelled, refunded, failed (y custom si el shop los tiene).
     *
     * El plugin a veces manda "wc-completed" en lugar de "completed" —
     * normalizamos el prefijo.
     *
     * Si la orden no existe localmente todavía (el webhook de status llegó
     * antes que el webhook de creación de la order), respondemos 202 para
     * que Woo no reintente eternamente y dejamos rastro en logs.
     */
    public function setStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'orderId' => 'required',
            'status'  => 'required|string|max:32',
        ]);

        $wcOrderId = (string) $validated['orderId'];
        $newStatus = preg_replace('/^wc-/', '', strtolower($validated['status']));

        $order = Order::where('wc_order_id', $wcOrderId)->first();

        if (! $order) {
            Log::warning("set-status: order {$wcOrderId} no existe localmente todavía");
            return response()->json(['success' => false, 'message' => 'Order not found locally'], 202);
        }

        $previousStatus = $order->wc_status;
        $order->update(['wc_status' => $newStatus]);

        Log::info("set-status: order {$order->wc_order_id} · {$previousStatus} → {$newStatus}");

        return response()->json([
            'success'         => true,
            'wc_order_id'     => $order->wc_order_id,
            'previous_status' => $previousStatus,
            'new_status'      => $newStatus,
        ]);
    }
    /**
     * Envía el mail de entrega al cliente con credenciales + código.
     * Tolerante a fallos: nunca rompe el flujo de asignación.
     */
    private function sendDeliveryEmail(OrderItem $item): void
    {
        try {
            $item->loadMissing(['order', 'account', 'assignment']);

            if (! $item->order?->customer_email || ! $item->account) {
                Log::warning("Mail de entrega omitido para item {$item->id}: falta email u cuenta");
                return;
            }

            Mail::to($item->order->customer_email)
                ->bcc(array_filter([
                    config('services.delivery_mail.support_bcc'),
                    $item->account->email,   // copia a la cuenta, como en el sistema viejo
                ]))
                ->queue(new AccountDeliveryMail($item));

            Log::info("Mail de entrega enviado · item {$item->id} · {$item->order->customer_email}");
        } catch (\Throwable $e) {
            Log::error("Fallo al enviar mail de entrega · item {$item->id}: {$e->getMessage()}");
        }
    }
    /**
     * DELETE /orders/bulk
     * Elimina varias órdenes a la vez (con sus items, dentro de una transacción).
     */
    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'order_ids'   => 'required|array|min:1',
            'order_ids.*' => 'integer|exists:orders,id',
        ]);

        $ids = $validated['order_ids'];

        $deleted = DB::transaction(function () use ($ids) {
            $orders = Order::with('items')->whereIn('id', $ids)->get();

            foreach ($orders as $order) {
                // OJO: si algún item tiene cuenta asignada, acá deberías LIBERAR
                // el slot de la cuenta antes de borrar, sino queda ocupado por
                // una orden que ya no existe. Ver nota abajo.
                $order->items()->delete();
            }

            return Order::whereIn('id', $ids)->delete();
        });

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'deleted' => $deleted,
                'message' => "{$deleted} orden(es) eliminada(s).",
            ]);
        }

        return redirect()->route('orders.index')->with('success', "{$deleted} orden(es) eliminada(s).");
    }
    /**
     * Lista de consolas disponibles para el dropdown de filtros.
     * Se deriva de los valores reales en order_items para no hardcodear
     * nada que dependa de normalizePlatform().
     */
    private function availableConsoles(): \Illuminate\Support\Collection
    {
        return OrderItem::query()
            ->whereNotNull('platform_normalized')
            ->where('platform_normalized', '!=', '')
            ->distinct()
            ->orderBy('platform_normalized')
            ->pluck('platform_normalized');
    }

    /**
     * Estados para el dropdown de filtros. Combina los conocidos del modelo
     * (para que pre-orden aparezca aunque no haya órdenes con ese estado todavía)
     * con los que realmente existen en la base.
     */
    private function availableStatuses(): \Illuminate\Support\Collection
    {
        $inDb = Order::query()
            ->whereNotNull('wc_status')
            ->where('wc_status', '!=', '')
            ->distinct()
            ->pluck('wc_status');

        return collect(Order::STATUS_LABELS)->keys()
            ->merge($inDb)
            ->unique()
            ->mapWithKeys(fn ($value) => [
                $value => Order::STATUS_LABELS[$value] ?? ucfirst($value),
            ]);
    }

    /**
     * POST /orders/{order}/add-item
     * Agrega un item nuevo (producto del catálogo) a la orden y, opcionalmente,
     * marca otros items como 'replaced' por éste.
     */
    public function addItem(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'game_id'       => 'required|integer|exists:games,id',
            'wc_product_id' => 'nullable|integer|exists:woo_products,id',
            'platform'      => 'nullable|string|max:32',
            'replaces'      => 'nullable|array',
            'replaces.*'    => 'integer|exists:order_items,id',
        ]);

        try {
            $newItem = DB::transaction(function () use ($order, $validated) {

                // Producto Woo para nombre/imagen/wc_product_id.
                // El modal manda el wc_product_id exacto elegido; sólo si no viene
                // caemos al viejo resuelto por game_id [+ plataforma].
                $woo = ! empty($validated['wc_product_id'])
                    ? WooProduct::find($validated['wc_product_id'])
                    : WooProduct::where('game_id', $validated['game_id'])
                        ->when($validated['platform'] ?? null, fn ($q, $p) => $q->where('platform', $p))
                        ->first();

                $model              = $validated['platform'] ?? $woo?->platform;        // "PS4"
                $family             = $this->platformFamily($model);                    // "PlayStation"
                $platformNormalized = $this->matchmaking->normalizePlatform($family, $model); // "PS4"

                $newItem = $order->items()->create([
                    'wc_product_id'       => $woo?->id,
                    'game_id'             => $validated['game_id'],
                    'game_title'          => $woo?->name ?? "Producto #{$validated['game_id']}",
                    'platform'            => $family,               // PlayStation
                    'console_model_raw'   => $model,                // PS4
                    'platform_normalized' => $platformNormalized,   // PS4
                    'quantity'            => 1,
                    'fulfillment_status'  => 'pending',
                ]);

                // Marcar reemplazos (si se pidieron)
                $replaceIds = $validated['replaces'] ?? [];
                if ($replaceIds) {
                    $covered = OrderItem::where('order_id', $order->id)
                        ->whereIn('id', $replaceIds)
                        ->lockForUpdate()
                        ->get();

                    if ($covered->count() !== count(array_unique($replaceIds))) {
                        throw new \RuntimeException('Algún item no pertenece a esta orden.');
                    }

                    $bad = $covered->first(fn ($it) =>
                        $it->fulfillment_status !== 'pending' || $it->replaced_by_item_id !== null
                    );
                    if ($bad) {
                        throw new \RuntimeException(
                            "El item #{$bad->id} ya no se puede reemplazar (status: {$bad->fulfillment_status})."
                        );
                    }

                    OrderItem::whereIn('id', $covered->pluck('id'))->update([
                        'replaced_by_item_id' => $newItem->id,
                        'fulfillment_status'  => 'replaced',
                    ]);
                }

                return $newItem;
            });
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        // Re-render para el front
        $order->load([
            'items.wooProduct', 'items.game', 'items.account.keys',
            'items.account.game', 'items.assignment', 'items.purchaseOrders',
            'items.replacements',
        ]);

        $fresh      = $order->items->firstWhere('id', $newItem->id);
        $newResult  = $this->matchmaking->findCandidates($fresh);

        $newCardHtml = view('orders.partials._item-card', [
            'item' => $fresh, 'result' => $newResult, 'order' => $order,
        ])->render();

        $replacedCards = $order->items
            ->where('replaced_by_item_id', $newItem->id)
            ->map(fn ($it) => [
                'item_id'   => $it->id,
                'item_html' => view('orders.partials._item-card', [
                    'item' => $it, 'result' => null, 'order' => $order,
                ])->render(),
            ])->values();

        return response()->json([
            'success'        => true,
            'message'        => 'Item agregado a la orden',
            'new_item_id'    => $newItem->id,
            'new_card_html'  => $newCardHtml,
            'replaced_cards' => $replacedCards,
        ]);
    }

    /**
     * Modelo de consola → familia (lo que espera normalizePlatform / guarda el webhook).
     * Ajustá las familias de Xbox/Nintendo/PC a lo que realmente use tu normalizePlatform;
     * para PlayStation tu webhook usa "PlayStation", así que mantenemos esa convención.
     */
    private function platformFamily(?string $model): ?string
    {
        if (! $model) {
            return null;
        }

        return match (strtoupper($model)) {
            'PS3', 'PS4', 'PS5'                   => 'PlayStation',
            'XBOX_ONE', 'XBOX_SERIES', 'XBOX_360' => 'Xbox',
            'SWITCH', 'SWITCH_2'                  => 'Nintendo',
            'STEAM', 'PC'                         => 'PC',
            default                               => $model, // desconocido → lo dejamos tal cual
        };
    }
    /**
     * DELETE /order-items/{item}
     * Elimina un item de reemplazo y devuelve a 'pending' los items que reemplazaba.
     */
    public function destroyItem(Request $request, OrderItem $item): JsonResponse
    {
        // Si ya tiene cuenta asignada, hay que liberar el slot primero.
        // Por seguridad lo bloqueamos acá (ver nota abajo).
        if ($item->account_id || $item->fulfillment_status === 'delivered') {
            return response()->json([
                'success' => false,
                'message' => 'Este item ya tiene una cuenta asignada. Desasignala antes de eliminarlo.',
            ], 422);
        }

        if ($item->activePurchaseOrder()) {
            return response()->json([
                'success' => false,
                'message' => 'Este item tiene una OC activa (pendiente/comprada). Cancelala antes de eliminar.',
            ], 422);
        }

        $orderId = $item->order_id;

        DB::transaction(function () use ($item) {
            // Revertir los items que este reemplazaba: vuelven a pending y se desvinculan.
            OrderItem::where('replaced_by_item_id', $item->id)->update([
                'replaced_by_item_id' => null,
                'fulfillment_status'  => 'pending',
            ]);

            // Si llegó a generar OCs, las limpiamos (opcional; sacá esta línea si no aplica).
            $item->purchaseOrders()->delete();

            $item->delete();
        });

        return response()->json([
            'success'  => true,
            'message'  => 'Item eliminado y reemplazos revertidos',
            'order_id' => $orderId,
        ]);
    }

    /**
     * Adjunta a cada orden de la página su estado de matchmaking (atributo transitorio).
     */
    private function attachMatchmakingStatus($orders): void
    {
        foreach ($orders->getCollection() as $order) {
            $order->setAttribute('mm_status', $this->matchmakingStatus($order));
        }
    }

    /**
     * Estado de matchmaking de una orden, derivado de sus items pendientes.
     * Reusa MatchmakingService para no divergir de lo que muestra show().
     */
    private function matchmakingStatus(Order $order): array
    {
        $pending = $order->items->where('fulfillment_status', 'pending');

        // Sin pendientes → no aplica (ya entregada o todo reemplazado/cancelado)
        if ($pending->isEmpty()) {
            $delivered = $order->items->where('fulfillment_status', 'delivered')->isNotEmpty();
            return [
                'state'   => $delivered ? 'done' : 'na',
                'label'   => $delivered ? 'Entregada' : '—',
                'color'   => $delivered ? 'emerald' : 'zinc',
                'ready'   => 0, 'oc' => 0, 'missing' => 0,
            ];
        }

        $ready = $oc = $missing = 0;

        foreach ($pending as $item) {
            // Packs: no usan stock primario, sino cuentas SECUNDARIAS.
            if ($item->is_pack) {
                $this->packHasSecondaryStock($item) ? $ready++ : $missing++;
                continue;
            }

            $result = $this->matchmaking->findCandidates($item);

            if ($result && ! $result->isEmpty() && $result->best()) {
                $ready++;                       // hay cuenta lista para asignar
            } elseif ($item->activePurchaseOrder()) {
                $oc++;                          // ya se creó una OC
            } else {
                $missing++;                     // ni cuenta ni OC
            }
        }

        // Estado principal de la fila (prioridad: lo más bloqueante primero)
        [$state, $label, $color] = match (true) {
            $missing > 0 => ['missing', 'Sin cuenta',        'red'],
            $oc      > 0 => ['oc',      'OC creada',         'blue'],
            default      => ['ready',   'Lista para enviar', 'emerald'],
        };

        return compact('state', 'label', 'color', 'ready', 'oc', 'missing');
    }

    /**
     * ¿El pack tiene stock SECUNDARIO disponible para considerarse "listo para enviar"
     * en el índice? Con juegos preseleccionados, exige una cuenta secundaria por cada
     * juego; sin desglose (packs viejos), basta con que exista alguna cuenta secundaria.
     */
    private function packHasSecondaryStock(OrderItem $item): bool
    {
        $games = $item->packGames();

        if ($games) {
            foreach ($games as $g) {
                if (! $this->matchmaking->bestSecondaryForPackGame($g['game_id'] ?? null, $g['platform'] ?? null)) {
                    return false;   // algún juego sin cuenta secundaria → no listo
                }
            }
            return true;
        }

        return $this->matchmaking->hasSecondaryStock();
    }

    /**
     * POST /items/{item}/notify-game-change
     * Envía al cliente el correo de "cambio de juego" cuando no hay stock.
     */
    public function notifyGameChange(Request $request, OrderItem $item)
    {
        $item->loadMissing('order');

        if (! $item->order?->customer_email) {
            $msg = 'La orden no tiene email de cliente.';
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $msg], 422)
                : back()->withErrors(['notify' => $msg]);
        }

        try {
            Mail::to($item->order->customer_email)
                ->bcc(array_filter([config('services.delivery_mail.support_bcc')]))
                ->queue(new GameChangeNotificationMail($item));

            Log::info("Noti cambio de juego enviada · item {$item->id} · {$item->order->customer_email}");
        } catch (\Throwable $e) {
            Log::error("Fallo noti cambio de juego · item {$item->id}: {$e->getMessage()}");
            $msg = 'No se pudo enviar el correo. Reintentá.';
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $msg], 500)
                : back()->withErrors(['notify' => $msg]);
        }

        $msg = "Notificación enviada a {$item->order->customer_email}";
        return $request->expectsJson()
            ? response()->json(['success' => true, 'message' => $msg])
            : back()->with('success', $msg);
    }

    /**
     * GET /items/{item}/secondary-candidates  → JSON con cuentas de pack disponibles.
     */
    public function secondaryCandidates(Request $request, OrderItem $item): JsonResponse
    {
        $perPage = 24;
        $page    = max(1, (int) $request->input('page', 1));

        $eligible = $this->matchmaking
            ->secondaryCandidatesQuery($request->input('search'))
            ->get()
            ->filter(fn (Account $acc) => $acc->isSecondaryStockEligible())  // cupos primarios llenos
            ->values();

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $eligible->forPage($page, $perPage)->values(),
            $eligible->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        $data = collect($paginator->items())->map(fn (Account $acc) => $this->serializeSecondaryAccount($acc));

        return response()->json([
            'success' => true,
            'data'    => $data,
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    /**
     * Serializa una cuenta secundaria para el JSON del picker / sugerencias del pack.
     * Requiere game.products y secondaryAssignments eager-loaded (sin queries extra).
     */
    private function serializeSecondaryAccount(Account $acc): array
    {
        $slots = collect($acc->secondaryPlatforms())
            ->mapWithKeys(fn ($p) => [$p => [
                'free'     => $acc->secondaryFreeSlotsFor($p),
                'capacity' => $acc->secondaryCapacityFor($p),
            ]]);

        $cover = $acc->coverProduct();

        return [
            'id'        => $acc->id,
            'email'     => $acc->email,
            'platform'  => $acc->platform,
            'is_dual'   => (bool) $acc->is_dual,
            'region'    => $acc->region,
            'gamer_tag' => $acc->gamer_tag,
            'game'      => $acc->game?->canonical_name,
            'product'   => $cover?->name,
            'image_url' => $cover?->image_url,
            'free'      => (int) $slots->sum('free'),
            'slots'     => $slots->map(fn ($v, $k) => "$k {$v['free']}/{$v['capacity']}")->values()->all(),
        ];
    }

    /**
     * Para un ítem de pack con juegos preseleccionados, arma la sugerencia de cuenta
     * secundaria por cada juego (alineado con el orden de packGames). Cada entrada:
     *   ['game' => packGame, 'suggestion' => cuenta serializada|null]
     */
    private function packSuggestions(OrderItem $item): array
    {
        // Precargamos los WooProduct de los juegos del pack para portada/nombre (sin N+1).
        $productIds = collect($item->packGames())->pluck('game_id')->filter()->unique();
        $products   = WooProduct::whereIn('id', $productIds)->get()->keyBy('id');

        return collect($item->packGames())->map(function (array $g) use ($products) {
            $product = isset($g['game_id']) ? $products->get($g['game_id']) : null;

            $account = $this->matchmaking->bestSecondaryForPackGame(
                $g['game_id'] ?? null,
                $g['platform'] ?? null,
            );

            return [
                'game' => [
                    'game_id'    => $g['game_id'] ?? null,
                    'game_title' => $g['game_title'] ?? ($product?->name ?? '—'),
                    'platform'   => $g['platform'] ?? null,
                    'image_url'  => $product?->image_url,
                ],
                'suggestion' => $account ? $this->serializeSecondaryAccount($account) : null,
            ];
        })->all();
    }

    /**
     * POST /items/{item}/assign-secondary  → asigna cuenta de pack al item.
     *
     * Acepta dos formatos:
     *   - Legacy (packs sin juegos preseleccionados): { account_ids: [1,2,3] }
     *   - Por slot (pack con packGames): { selections: [{ account_id, pack_game_id, pack_game_title }] }
     */
    public function assignSecondary(Request $request, OrderItem $item): JsonResponse
    {
        $validated = $request->validate([
            'account_ids'                   => 'nullable|array',
            'account_ids.*'                 => 'integer|exists:accounts,id',
            'selections'                    => 'nullable|array',
            'selections.*.account_id'       => 'required_with:selections|integer|exists:accounts,id',
            'selections.*.pack_game_id'     => 'nullable|integer',
            'selections.*.pack_game_title'  => 'nullable|string|max:255',
        ]);

        $item->loadMissing('order');
        $processingStatus = config('services.woo.processing_status', 'processing');

        if (! $item->order || $item->order->wc_status !== $processingStatus) {
            return response()->json(['success' => false, 'message' => 'Solo se puede enviar con la orden en "processing".'], 422);
        }
        if (! $item->is_pack) {
            return response()->json(['success' => false, 'message' => 'Este item no es de pack.'], 422);
        }

        // Normalizamos ambos formatos a lista de account_ids + meta por cuenta.
        $meta = [];
        if (! empty($validated['selections'])) {
            $accountIds = [];
            foreach ($validated['selections'] as $sel) {
                $accId = (int) $sel['account_id'];
                $accountIds[] = $accId;
                $meta[$accId] = [
                    'pack_game_id'    => $sel['pack_game_id'] ?? null,
                    'pack_game_title' => $sel['pack_game_title'] ?? null,
                ];
            }
        } else {
            $accountIds = $validated['account_ids'] ?? [];
        }

        $accountIds = array_values(array_unique($accountIds));
        if (empty($accountIds)) {
            return response()->json(['success' => false, 'message' => 'No se seleccionó ninguna cuenta.'], 422);
        }

        $result = $this->matchmaking->assignSecondaryMany($item, $accountIds, auth()->user()->email, $meta);

        if (empty($result['assigned'])) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo asignar ninguna cuenta — sin slots secundarios o llaves libres.',
                'failed'  => $result['failed'],
            ], 422);
        }

        // ── NUEVO: mail de entrega por cada cuenta del pack ──
        foreach ($result['assigned'] as $sa) {
            $this->sendSecondaryDeliveryEmail($item, $sa);
        }

        $orderCompleted = $this->maybeCompleteOrderInWoo($item->order_id);
        $fresh = $item->fresh(['secondaryAssignments.account.game.products', 'wooProduct', 'game', 'purchaseOrders']);

        $msg = count($result['assigned']) . ' cuenta(s) de pack asignada(s)'
            . (count($result['failed']) ? ', ' . count($result['failed']) . ' sin slot' : '');

        return response()->json([
            'success'         => true,
            'message'         => $msg,
            'order_completed' => $orderCompleted,
            'failed'          => $result['failed'],
            'item_html'       => view('orders.partials._item-card', ['item' => $fresh, 'result' => null])->render(),
        ]);
    }
    /**
     * Mail de entrega para UNA cuenta de un pack (stock secundario).
     * Tolerante a fallos: nunca rompe la asignación.
     */
    private function sendSecondaryDeliveryEmail(OrderItem $item, AccountSecondaryAssignment $sa): void
    {
        try {
            $item->loadMissing('order');
            $sa->loadMissing('account');

            if (! $item->order?->customer_email || ! $sa->account) {
                Log::warning("Mail de pack omitido · item {$item->id} · sa {$sa->id}: falta email o cuenta");
                return;
            }

            Mail::to($item->order->customer_email)
                ->bcc(array_filter([
                    config('services.delivery_mail.support_bcc'),
                    $sa->account->email,
                ]))
                ->queue(new AccountDeliveryMail($item, $sa));

            Log::info("Mail de pack enviado · item {$item->id} · cuenta {$sa->account->email} · {$item->order->customer_email}");
        } catch (\Throwable $e) {
            Log::error("Fallo mail de pack · item {$item->id} · sa {$sa->id}: {$e->getMessage()}");
        }
    }

    /**
     * POST /items/{item}/resend-delivery
     * Reenvía el/los correo(s) de entrega con las credenciales y la llave YA
     * asignadas. No reasigna ni cambia nada: reutiliza la cuenta y la llave que
     * el ítem ya tiene. Sirve para reenviar al cliente si perdió el correo.
     */
    public function resendDelivery(Request $request, OrderItem $item)
    {
        $item->loadMissing(['order', 'account', 'assignment', 'secondaryAssignments.account']);

        $fail = function (string $msg) use ($request) {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $msg], 422)
                : back()->withErrors(['resend' => $msg]);
        };

        if (! $item->order?->customer_email) {
            return $fail('La orden no tiene email de cliente.');
        }

        if ($item->is_pack) {
            // Pack: un correo por cada cuenta secundaria entregada.
            $sent = 0;
            foreach ($item->secondaryAssignments as $sa) {
                if ($sa->account) {
                    $this->sendSecondaryDeliveryEmail($item, $sa);
                    $sent++;
                }
            }

            if ($sent === 0) {
                return $fail('Este pack todavía no tiene cuentas asignadas para reenviar.');
            }

            $msg = "Reenviado: {$sent} correo(s) del pack a {$item->order->customer_email}.";
        } else {
            if (! $item->account) {
                return $fail('Este ítem todavía no tiene una cuenta asignada.');
            }

            $this->sendDeliveryEmail($item);
            $msg = "Credenciales reenviadas a {$item->order->customer_email}.";
        }

        Log::info("Reenvío manual de entrega · item {$item->id} · {$item->order->customer_email}");

        return $request->expectsJson()
            ? response()->json(['success' => true, 'message' => $msg])
            : back()->with('success', $msg);
    }
}
