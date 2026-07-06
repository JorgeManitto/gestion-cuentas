{{-- ==================== TAB: ÓRDENES ==================== --}}

{{-- Stats --}}
<div class="grid grid-cols-3 gap-3">
    @foreach ([
        'pending'   => ['Pendientes', 'amber'],
        'purchased' => ['Compradas',  'blue'],
        'received'  => ['Recibidas',  'emerald'],
    ] as $key => [$label, $color])
        <a href="{{ route('purchase-orders.index', ['tab' => 'ordenes', 'status' => $key]) }}"
        class="group relative block overflow-hidden rounded-xl border border-zinc-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md {{ request('status') === $key ? 'ring-2 ring-'.$color.'-500/40' : '' }}">
            <span class="absolute inset-y-0 left-0 w-1 bg-{{ $color }}-500"></span>
            <div class="flex items-center justify-between pl-2">
                <span class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ $label }}</span>
                <span class="h-2 w-2 rounded-full bg-{{ $color }}-500"></span>
            </div>
            <div class="mt-2 pl-2 font-mono text-3xl font-semibold tabular-nums text-zinc-900">{{ $stats[$key] }}</div>
        </a>
    @endforeach
</div>

{{-- Toolbar --}}
<div class="flex justify-end">
    <button type="button" onclick="document.getElementById('modal-create-po').showModal()"
            class="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800">
        + Agregar orden
    </button>
</div>

{{-- Tabla --}}
<div class="overflow-x-auto rounded-lg border border-zinc-200 bg-white">
    <table class="min-w-full text-sm">
        <thead class="bg-zinc-50 border-b border-zinc-200 text-xs uppercase tracking-wide text-zinc-600">
            <tr>
                <th class="px-4 py-2 text-left font-medium">OC</th>
                <th class="px-4 py-2 text-left font-medium">Juego</th>
                <th class="px-4 py-2 text-left font-medium">Plataforma</th>
                <th class="px-4 py-2 text-left font-medium">Región</th>
                <th class="px-4 py-2 text-left font-medium">Cant.</th>
                <th class="px-4 py-2 text-left font-medium">Origen</th>
                <th class="px-4 py-2 text-left font-medium">Status</th>
                <th class="px-4 py-2 text-left font-medium">Creada</th>
                <th class="px-4 py-2 text-left font-medium">Llegada</th>

                <th class="px-4 py-2 text-right font-medium">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-100">
            @forelse ($orders as $po)
                @php
                    // Portada resuelta igual que en accounts:
                    //   1) producto del juego para la plataforma de la OC
                    //   2) primer producto del juego
                    //   3) lookup por título en WooProduct (solo OC sin juego, lo arma el controller)
                    //   4) placeholder
                    $product = $po->game
                        ? ($po->game->productForPlatform($po->platform) ?? $po->game->products->first())
                        : null;

                    $cover = $product?->image_url
                        ?? ($coversByTitle[$po->game_title] ?? null)
                        ?? asset('images/default-game-gray.svg');
                @endphp
                <tr class="hover:bg-zinc-50">
                    <td class="px-4 py-2.5 font-mono text-xs">#{{ $po->id }}</td>
                    <td class="px-4 py-2.5">
                        <div class="flex items-center gap-3">
                            {{-- <button type="button"
                                    onclick="openPreview('{{ e($cover) }}', '{{ e($po->game_title) }}')"
                                    class="block h-14 w-10 shrink-0 overflow-hidden rounded border bg-zinc-100 hover:opacity-80 transition">
                                <img src="{{ $cover }}" alt="{{ $po->game_title }}"
                                     class="h-full w-full object-cover" loading="lazy">
                            </button> --}}
                            @if (($po->type ?? 'purchase') === 'reset')
                                <a href="{{ route('accounts.show', $po->account) }}"
                                {{-- <a href="/stock/reseteables?search={{ urlencode($po->game_title) }}" --}}
                                class="block h-14 w-10 shrink-0 overflow-hidden rounded border bg-zinc-100 hover:opacity-80 transition">
                                    <img src="{{ $cover }}" alt="{{ $po->game_title }}"
                                        class="h-full w-full object-cover" loading="lazy">
                                </a>
                            @else
                                <button type="button"
                                        onclick="openPreview('{{ e($cover) }}', '{{ e($po->game_title) }}')"
                                        class="block h-14 w-10 shrink-0 overflow-hidden rounded border bg-zinc-100 hover:opacity-80 transition">
                                    <img src="{{ $cover }}" alt="{{ $po->game_title }}"
                                        class="h-full w-full object-cover" loading="lazy">
                                </button>
                            @endif
                            {{-- <div class="min-w-0">
                                <div class="font-medium max-w-[260px] truncate">{{ $po->game_title }}</div>
                                @if ($po->game)
                                    <div class="text-xs text-zinc-500 truncate">→ {{ $po->game->canonical_name }}</div>
                                @endif
                            </div> --}}
                            <div class="min-w-0">
                                @if (($po->type ?? 'purchase') === 'reset')
                                    <a href="{{ route('accounts.show', $po->account) }}"
                                    class="block font-medium max-w-[260px] truncate text-amber-700 hover:underline">
                                        {{ $po->game_title }}
                                    </a>
                                    <span class="mt-0.5 inline-flex items-center gap-1 rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-700">↻ Reset</span>
                                @else
                                    <div class="font-medium max-w-[260px] truncate">{{ $po->game_title }}</div>
                                    @if ($po->game)
                                        {{-- <div class="text-xs text-zinc-500 truncate">→ {{ $po->game->canonical_name }}</div> --}}
                                    @endif
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-2.5">
                        <span class="font-mono text-xs px-1.5 py-0.5 rounded bg-zinc-100">{{ $po->platform }}</span>
                        {{-- @if ($po->console_model)
                            <div class="text-xs text-zinc-500 mt-0.5 font-mono">{{ $po->console_model }}</div>
                        @endif --}}
                        
                    </td>
                    <td class="px-4 py-2.5 font-mono text-xs">
                        @if ($po->region && $po->region !== 'sin especificar')<div class="text-xs text-zinc-700 mt-0.5">{{ $po->region }}</div>@endif
                        @if ($po->notes_region && $po->region !== 'sin especificar') <div class="text-xs text-zinc-400">{{ $po->notes_region }}</div>@endif
                    </td>
                    <td class="px-4 py-2.5 font-mono text-xs">{{ $po->quantity }}</td>
                    <td class="px-4 py-2.5">
                        @if ($po->orderItem)
                            <a href="{{ route('orders.show', $po->orderItem->order_id) }}"
                               class="text-xs font-mono hover:underline">
                                Order #{{ $po->orderItem->order->wc_order_id }}
                            </a>
                        @else
                            <span class="text-xs text-zinc-400">manual</span>
                        @endif
                    </td>
                    <td class="px-4 py-2.5">
                        @php $color = $po->statusColor(); @endphp
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-{{ $color }}-50 px-2 py-0.5 text-xs font-medium text-{{ $color }}-700 ring-1 ring-inset ring-{{ $color }}-600/20">
                            <span class="h-1.5 w-1.5 rounded-full bg-{{ $color }}-500"></span>
                            {{ $po->status }}
                        </span>
                    </td>
                    <td class="px-4 py-2.5 font-mono text-xs text-zinc-500">{{ $po->created_at->format('Y-m-d') }}</td>
                    <td class="px-4 py-2.5 font-mono text-xs text-zinc-500">{{ $po->arrival_date?->format('Y-m-d') ?? '—' }}</td>

                    <td class="px-4 py-2.5">
                        <div class="flex items-center justify-end gap-1">
                            {{-- @if ($po->status !== 'received')
                                @php
                                    $poData = [
                                        'id'         => $po->id,
                                        'game_title' => $po->game_title,
                                        'platform'   => $po->platform,
                                        'region'     => $po->region,
                                        'is_dual'    => (bool) $po->is_dual,
                                    ];
                                @endphp
                                <button type="button"
                                        onclick='openComplete(@json($poData))'
                                        title="Completar con cuenta de stock"
                                        class="inline-flex items-center justify-center h-8 w-8 rounded-md bg-zinc-900 text-white hover:bg-zinc-800">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.27 6.96 12 12.01l8.73-5.05M12 22.08V12"/></svg>
                                </button>
                            @endif --}}
                            @if ($po->status !== 'received')
                                @if (($po->type ?? 'purchase') === 'reset')
                                    @if ($po->account)
                                        @php $resetKey = $po->account->nextAvailableKey(); @endphp
                                        <button type="button"
                                                class="po-reset-trigger inline-flex items-center justify-center h-8 px-2.5 gap-1 rounded-md bg-amber-500 text-white hover:bg-amber-600 text-xs font-medium"
                                                title="Resetear cuenta"
                                                data-email="{{ $po->account->email }}"
                                                data-password="{{ $po->account->password }}"
                                                data-key="{{ $resetKey?->key_value }}"
                                                data-key-id="{{ $resetKey?->id }}"
                                                data-action="{{ route('purchase-orders.reset', $po) }}">
                                            ↻ Resetear
                                        </button>
                                    @else
                                        {{-- Fallback: sin cuenta asociada → elegir manualmente en reseteables --}}
                                        <a href="/stock/reseteables?search={{ urlencode($po->game_title) }}"
                                        title="Elegir cuenta para resetear"
                                        class="inline-flex items-center justify-center h-8 px-2.5 gap-1 rounded-md bg-amber-500 text-white hover:bg-amber-600 text-xs font-medium">
                                            ↻ Resetear
                                        </a>
                                    @endif
                                @else
                                    @php
                                        $poData = [
                                            'id'         => $po->id,
                                            'game_title' => $po->game_title,
                                            'platform'   => $po->platform,
                                            'region'     => $po->region,
                                            'is_dual'    => (bool) $po->is_dual,
                                        ];
                                    @endphp
                                    <button type="button"
                                            onclick='openComplete(@json($poData))'
                                            title="Completar con cuenta de stock"
                                            class="inline-flex items-center justify-center h-8 w-8 rounded-md bg-zinc-900 text-white hover:bg-zinc-800">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.27 6.96 12 12.01l8.73-5.05M12 22.08V12"/></svg>
                                    </button>
                                @endif
                            @endif
                            <form method="POST" action="{{ route('purchase-orders.destroy', $po) }}"
                                  onsubmit="return confirm('¿Eliminar la OC #{{ $po->id }}? Esta acción no se puede deshacer.')">
                                @csrf @method('DELETE')
                                <button type="submit" title="Eliminar"
                                        class="inline-flex items-center justify-center h-8 w-8 rounded-md bg-red-600 text-white hover:bg-red-700">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/></svg>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="px-4 py-16 text-center">
                        <div class="mx-auto flex max-w-sm flex-col items-center gap-3 text-zinc-400">
                            <svg class="h-10 w-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                                <path d="M3.27 6.96 12 12.01l8.73-5.05M12 22.08V12"/>
                            </svg>
                            <p class="text-sm">No hay órdenes de compra. Se generan automáticamente cuando un ítem no tiene stock disponible.</p>
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $orders->links() }}</div>
{{-- Modal de reset (OCs) --}}
<div id="po-reset-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div id="po-reset-backdrop" class="absolute inset-0 bg-black/40"></div>

    <div class="relative w-full max-w-md rounded-lg bg-white shadow-xl">
        <div class="border-b px-5 py-3">
            <h2 class="text-base font-semibold">Resetear cuenta en la plataforma</h2>
        </div>

        <div class="space-y-3 px-5 py-4 text-sm">
            <p class="text-zinc-500">
                Ingresá a la plataforma con estos datos y realizá el reset manualmente.
                La llave que se muestra se consumirá al confirmar. Al terminar, la OC se marca como recibida.
            </p>

            <div class="rounded border bg-zinc-50 px-3 py-2 font-mono text-xs space-y-1">
                <div><span class="text-zinc-400">Email:</span> <span id="po-rm-email"></span></div>
                <div><span class="text-zinc-400">Password:</span> <span id="po-rm-password"></span></div>
                <div><span class="text-zinc-400">Llave:</span> <span id="po-rm-key"></span></div>
            </div>

            <p class="font-medium">¿Ya realizaste el reseteo en la plataforma?</p>
        </div>

        <form method="POST" id="po-rm-form" class="flex justify-end gap-2 border-t px-5 py-3">
            @csrf
            <input type="hidden" name="key_id" id="po-rm-key-id">
            <button type="button" id="po-rm-cancel"
                    class="rounded border px-3 py-1.5 text-sm text-zinc-600 hover:bg-zinc-100">
                Cancelar
            </button>
            <button type="submit"
                    class="rounded bg-amber-500 hover:bg-amber-600 px-4 py-1.5 text-sm font-medium text-white">
                Sí, ya reseteé
            </button>
        </form>
    </div>
</div>

<script>
(function () {
    const modal = document.getElementById('po-reset-modal');
    if (!modal) return;
    const close = () => modal.classList.add('hidden');

    document.querySelectorAll('.po-reset-trigger').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.getElementById('po-rm-email').textContent    = btn.dataset.email || '—';
            document.getElementById('po-rm-password').textContent = btn.dataset.password || '—';
            document.getElementById('po-rm-key').textContent      = btn.dataset.key || 'sin llave disponible';
            document.getElementById('po-rm-key-id').value         = btn.dataset.keyId || '';
            document.getElementById('po-rm-form').action          = btn.dataset.action;
            modal.classList.remove('hidden');
        });
    });

    document.getElementById('po-rm-cancel').addEventListener('click', close);
    document.getElementById('po-reset-backdrop').addEventListener('click', close);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
})();
</script>
