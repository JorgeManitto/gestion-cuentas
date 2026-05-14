@extends('layouts.app')
@section('title', 'Compras')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-zinc-900">Compras</h1>
        <p class="text-zinc-600">Gestiona Órdenes de Compra y Stock de Cuentas</p>

        {{-- Tabs --}}
        <div class="mt-4 flex flex-wrap gap-2 border-b border-zinc-200 pb-3">
            <a href="{{ route('purchase-orders.index', ['tab' => 'ordenes']) }}"
               class="px-4 py-2 rounded-md text-sm font-medium {{ $tab === 'ordenes' ? 'bg-zinc-900 text-white' : 'bg-white border border-zinc-200 text-zinc-700 hover:bg-zinc-50' }}">
                Órdenes de Compra
            </a>
            <a href="{{ route('purchase-orders.index', ['tab' => 'stock']) }}"
               class="px-4 py-2 rounded-md text-sm font-medium {{ $tab === 'stock' ? 'bg-zinc-900 text-white' : 'bg-white border border-zinc-200 text-zinc-700 hover:bg-zinc-50' }}">
                Stock de Cuentas
            </a>
        </div>
    </div>

    {{-- Flash messages --}}
    @if (session('success'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="rounded-md border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800">
            {{ $errors->first() }}
        </div>
    @endif

    @if ($tab === 'ordenes')
        {{-- ==================== TAB: ÓRDENES ==================== --}}

        {{-- Stats --}}
        <div class="grid grid-cols-3 gap-3">
            @foreach ([
                'pending'   => ['Pendientes',   'amber'],
                'purchased' => ['Compradas',    'blue'],
                'received'  => ['Recibidas',    'emerald'],
            ] as $key => [$label, $color])
                <a href="{{ route('purchase-orders.index', ['tab' => 'ordenes', 'status' => $key]) }}"
                   class="block rounded-lg border border-zinc-200 bg-white p-4 hover:border-zinc-300 transition">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ $label }}</span>
                        <span class="h-2 w-2 rounded-full bg-{{ $color }}-500"></span>
                    </div>
                    <div class="mt-2 font-mono text-2xl font-semibold">{{ $stats[$key] }}</div>
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
                            // Portada: 1) game.cover_image_url (WooProduct vía relación)
                            //         2) lookup por title en WooProduct (cargado en el controller)
                            //         3) placeholder
                            $cover = $po->cover_image_url
                                ?? ($coversByTitle[$po->game_title] ?? null)
                                ?? asset('images/default-game-gray.svg');
                        @endphp
                        <tr class="hover:bg-zinc-50">
                            <td class="px-4 py-2.5 font-mono text-xs">#{{ $po->id }}</td>
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-3">
                                    <button type="button"
                                            onclick="openPreview('{{ e($cover) }}', '{{ e($po->game_title) }}')"
                                            class="block h-14 w-10 shrink-0 overflow-hidden rounded border bg-zinc-100 hover:opacity-80 transition">
                                        <img src="{{ $cover }}" alt="{{ $po->game_title }}"
                                             class="h-full w-full object-cover" loading="lazy">
                                    </button>
                                    <div class="min-w-0">
                                        <div class="font-medium max-w-[260px] truncate">{{ $po->game_title }}</div>
                                        @if ($po->game)
                                            <div class="text-xs text-zinc-500 truncate">→ {{ $po->game->canonical_name }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-2.5">
                                <span class="font-mono text-xs px-1.5 py-0.5 rounded bg-zinc-100">{{ $po->platform }}</span>
                                @if ($po->console_model)
                                    <div class="text-xs text-zinc-500 mt-0.5 font-mono">{{ $po->console_model }}</div>
                                @endif
                                @if ($po->region && $po->region !== 'sin especificar')
                                    <div class="text-xs text-zinc-500 mt-0.5">{{ $po->region }}</div>
                                @endif
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
                                    @if ($po->status !== 'received')
                                        @php
                                            $poData = [
                                                'id'         => $po->id,
                                                'game_title' => $po->game_title,
                                                'platform'   => $po->platform,
                                                'region'     => $po->region,
                                            ];
                                        @endphp
                                        <button type="button"
                                                onclick='openComplete(@json($poData))'
                                                title="Completar con cuenta de stock"
                                                class="inline-flex items-center justify-center h-8 w-8 rounded-md bg-zinc-900 text-white hover:bg-zinc-800">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.27 6.96 12 12.01l8.73-5.05M12 22.08V12"/></svg>
                                        </button>
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
                            <td colspan="9" class="px-4 py-12 text-center text-zinc-500">
                                No hay órdenes de compra. Se generan automáticamente cuando un item no tiene stock disponible.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $orders->links() }}</div>

    @else
        {{-- ==================== TAB: STOCK ==================== --}}
        <div class="rounded-lg border border-zinc-200 bg-white p-4">
            <p class="text-sm text-zinc-600 mb-4">Cuentas sin juego vinculado. Ordenadas por región.</p>

            <form method="GET" action="{{ route('purchase-orders.index') }}" class="flex flex-wrap gap-2 mb-4">
                <input type="hidden" name="tab" value="stock">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar por email, región…"
                       class="rounded-md border-zinc-300 text-sm px-3 py-1.5 w-64">
                <select name="platform" class="rounded-md border-zinc-300 text-sm px-3 py-1.5">
                    <option value="all">Todas las consolas</option>
                    @foreach (['DUAL','PS4','PS5','XBOX_ONE','XBOX_SERIES','SWITCH','SWITCH_2','STEAM'] as $p)
                        <option value="{{ $p }}" @selected(request('platform') === $p)>{{ $p }}</option>
                    @endforeach
                </select>
                <select name="region" class="rounded-md border-zinc-300 text-sm px-3 py-1.5">
                    <option value="all">Todas las regiones</option>
                    @foreach ($uniqueRegions as $r)
                        <option value="{{ $r }}" @selected(request('region') === $r)>{{ $r }}</option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-md bg-zinc-900 px-4 py-1.5 text-sm font-medium text-white hover:bg-zinc-800">
                    Filtrar
                </button>
            </form>

            <div class="overflow-x-auto rounded-lg border border-zinc-200">
                <table class="min-w-full text-sm">
                    <thead class="bg-zinc-50 border-b border-zinc-200 text-xs uppercase tracking-wide text-zinc-600">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium">Email</th>
                            <th class="px-4 py-2 text-left font-medium">Plataforma</th>
                            <th class="px-4 py-2 text-left font-medium">Región</th>
                            <th class="px-4 py-2 text-left font-medium">Estado</th>
                            <th class="px-4 py-2 text-left font-medium">Comprada</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @forelse ($stockAccounts as $acc)
                            <tr class="hover:bg-zinc-50">
                                <td class="px-4 py-2.5 font-medium">{{ $acc->email }}</td>
                                <td class="px-4 py-2.5 font-mono text-xs">{{ $acc->platform }}</td>
                                <td class="px-4 py-2.5">{{ $acc->region ?? '—' }}</td>
                                <td class="px-4 py-2.5">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                        @class([
                                            'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20' => $acc->status === 'active',
                                            'bg-zinc-100 text-zinc-700' => $acc->status !== 'active',
                                        ])">
                                        {{ $acc->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 font-mono text-xs text-zinc-500">
                                    {{ $acc->purchased_date?->format('Y-m-d') ?? '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-12 text-center text-zinc-500">
                                    No hay cuentas sin juego con los filtros aplicados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

{{-- ==================== MODAL: CREAR OC ==================== --}}
<dialog id="modal-create-po" class="rounded-lg p-0 backdrop:bg-black/40 w-full max-w-md">
    <form method="POST" action="{{ route('purchase-orders.store') }}" class="p-5 space-y-4">
        @csrf
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold">Nueva Orden de Compra</h2>
            <button type="button" onclick="document.getElementById('modal-create-po').close()"
                    class="text-zinc-500 hover:text-zinc-700">✕</button>
        </div>

        <div class="space-y-1.5 relative">
            <label class="text-sm font-medium">Título del juego</label>
            <input type="text" name="game_title" id="po-game-title" required autocomplete="off"
                   placeholder="Ej: God of War Ragnarok"
                   class="w-full rounded-md border-zinc-300 text-sm px-3 py-1.5"
                   oninput="filterGames(this.value)">
            <input type="hidden" name="game_id" id="po-game-id">
            <div id="po-game-suggestions"
                 class="absolute z-50 left-0 right-0 mt-1 max-h-48 overflow-auto rounded-md border bg-white shadow-md hidden"></div>
        </div>

        <div class="space-y-1.5">
            <label class="text-sm font-medium">Plataforma</label>
            <select name="platform" required class="w-full rounded-md border-zinc-300 text-sm px-3 py-1.5">
                <option value="">Selecciona…</option>
                <option value="DUAL">DUAL (PS4+PS5)</option>
                <option value="PS5">PS5</option>
                <option value="PS4">PS4</option>
                <option value="XBOX_SERIES">Xbox Series</option>
                <option value="XBOX_ONE">Xbox One</option>
                <option value="SWITCH_2">Switch 2</option>
                <option value="SWITCH">Switch</option>
                <option value="STEAM">Steam / PC</option>
            </select>
        </div>

        <div class="space-y-1.5">
            <label class="text-sm font-medium">Región <span class="text-zinc-400 font-normal">(opcional)</span></label>
            <select name="region" class="w-full rounded-md border-zinc-300 text-sm px-3 py-1.5">
                <option value="">—</option>
                @foreach (['HONG KONG','BRASIL','USA','ESPAÑA','UK','TURQUIA','INDIA','ARG','CAN'] as $r)
                    <option value="{{ $r }}">{{ $r }}</option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1.5">
                <label class="text-sm font-medium">Cantidad</label>
                <input type="number" name="quantity" min="1" value="1" required
                       class="w-full rounded-md border-zinc-300 text-sm px-3 py-1.5">
            </div>
            <div class="space-y-1.5">
                <label class="text-sm font-medium">Fecha llegada</label>
                <input type="date" name="arrival_date" class="w-full rounded-md border-zinc-300 text-sm px-3 py-1.5">
            </div>
        </div>

        <div class="flex justify-end gap-2 pt-2">
            <button type="button" onclick="document.getElementById('modal-create-po').close()"
                    class="rounded-md border border-zinc-300 px-4 py-1.5 text-sm font-medium hover:bg-zinc-50">
                Cancelar
            </button>
            <button type="submit" class="rounded-md bg-zinc-900 px-4 py-1.5 text-sm font-medium text-white hover:bg-zinc-800">
                Crear
            </button>
        </div>
    </form>
</dialog>

{{-- ==================== MODAL: COMPLETAR OC ==================== --}}
<dialog id="modal-complete-po" class="rounded-lg p-0 backdrop:bg-black/40 w-full max-w-lg">
    <form method="POST" id="form-complete-po" class="p-5 space-y-4">
        @csrf
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold">Completar orden</h2>
            <button type="button" onclick="document.getElementById('modal-complete-po').close()"
                    class="text-zinc-500 hover:text-zinc-700">✕</button>
        </div>

        <div id="complete-po-info" class="text-sm text-zinc-600 rounded-md bg-zinc-50 px-3 py-2"></div>

        <div class="space-y-1.5">
            <label class="text-sm font-medium">Cuenta de Stock</label>
            <select name="account_id" required class="w-full rounded-md border-zinc-300 text-sm px-3 py-1.5">
                <option value="">Selecciona una cuenta…</option>
                @foreach ($stockForComplete as $acc)
                    <option value="{{ $acc->id }}" data-platform="{{ $acc->platform }}">
                        {{ $acc->email }} — {{ $acc->platform }}{{ $acc->region ? ' / ' . $acc->region : '' }}
                    </option>
                @endforeach
            </select>
            <p class="text-xs text-zinc-500">Solo se muestran cuentas activas sin juego asignado.</p>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1.5">
                <label class="text-sm font-medium">Fecha de compra</label>
                <input type="date" name="purchase_date" required value="{{ now()->format('Y-m-d') }}"
                       class="w-full rounded-md border-zinc-300 text-sm px-3 py-1.5">
            </div>
            <div class="space-y-1.5">
                <label class="text-sm font-medium">Monto USD <span class="text-zinc-400 font-normal">(opc.)</span></label>
                <input type="number" name="cost_usd" step="0.01" min="0"
                       class="w-full rounded-md border-zinc-300 text-sm px-3 py-1.5">
            </div>
        </div>

        <div class="flex justify-end gap-2 pt-2">
            <button type="button" onclick="document.getElementById('modal-complete-po').close()"
                    class="rounded-md border border-zinc-300 px-4 py-1.5 text-sm font-medium hover:bg-zinc-50">
                Cancelar
            </button>
            <button type="submit" class="rounded-md bg-emerald-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-emerald-700">
                Completar
            </button>
        </div>
    </form>
</dialog>

{{-- ==================== MODAL: PREVIEW IMAGEN ==================== --}}
<dialog id="modal-preview" class="rounded-lg p-0 backdrop:bg-black/70 bg-transparent">
    <button type="button" onclick="document.getElementById('modal-preview').close()"
            class="absolute top-2 right-2 z-10 text-white bg-black/50 rounded-full h-8 w-8 flex items-center justify-center">✕</button>
    <img id="preview-img" src="" alt="" class="max-h-[80vh] w-auto rounded-md">
</dialog>

@php
    $gamesPayload = $gamesCatalog->map(fn ($g) => [
        'id'    => $g->id,
        'name'  => $g->canonical_name,
        'cover' => $g->cover_image_url,
    ])->values();
@endphp
<script>
    // Catálogo de juegos (id + canonical_name + cover) para autocompletar
    const GAMES = @json($gamesPayload);

    function filterGames(query) {
        const box = document.getElementById('po-game-suggestions');
        const q = query.trim().toLowerCase();
        document.getElementById('po-game-id').value = ''; // si cambia el texto, deselecciona
        if (q.length < 2) { box.classList.add('hidden'); return; }
        const matches = GAMES.filter(g => g.name.toLowerCase().includes(q)).slice(0, 8);
        if (matches.length === 0) { box.classList.add('hidden'); return; }
        box.innerHTML = matches.map(g => `
            <button type="button"
                    class="w-full text-left px-3 py-2 text-sm hover:bg-zinc-50 border-b last:border-b-0 flex items-center gap-2"
                    onclick="selectGame(${g.id}, ${JSON.stringify(g.name)})">
                ${g.cover ? `<img src="${g.cover}" class="h-8 w-6 object-cover rounded">` : '<div class="h-8 w-6 bg-zinc-100 rounded"></div>'}
                <span class="truncate">${g.name}</span>
            </button>
        `).join('');
        box.classList.remove('hidden');
    }

    function selectGame(id, name) {
        document.getElementById('po-game-title').value = name;
        document.getElementById('po-game-id').value = id;
        document.getElementById('po-game-suggestions').classList.add('hidden');
    }

    // Cerrar sugerencias al click fuera
    document.addEventListener('click', (e) => {
        if (!e.target.closest('#po-game-title') && !e.target.closest('#po-game-suggestions')) {
            document.getElementById('po-game-suggestions')?.classList.add('hidden');
        }
    });

    function openComplete(po) {
        const form = document.getElementById('form-complete-po');
        form.action = `/purchase-orders/${po.id}/complete`;
        document.getElementById('complete-po-info').innerHTML =
            `OC <strong>#${po.id}</strong> · ${po.game_title} · ${po.platform}` +
            (po.region && po.region !== 'sin especificar' ? ` · ${po.region}` : '');

        // Filtrar el select para que sólo muestre cuentas compatibles con la plataforma de la OC
        const select = form.querySelector('select[name="account_id"]');
        const platformPo = (po.platform || '').toUpperCase();
        Array.from(select.options).forEach(opt => {
            if (!opt.value) { opt.hidden = false; return; }
            const accPlatform = (opt.dataset.platform || '').toUpperCase();
            // PS4/PS5 son compatibles con DUAL, etc. — regla simple: mismo prefijo de plataforma
            const family = (p) => p.startsWith('PS') || p === 'DUAL' ? 'PS'
                            : p.startsWith('XBOX') ? 'XBOX'
                            : p.startsWith('SWITCH') ? 'NIN'
                            : p;
            opt.hidden = family(accPlatform) !== family(platformPo);
        });
        select.value = '';

        document.getElementById('modal-complete-po').showModal();
    }

    function openPreview(url, title) {
        const img = document.getElementById('preview-img');
        img.src = url;
        img.alt = title;
        document.getElementById('modal-preview').showModal();
    }
</script>
@endsection