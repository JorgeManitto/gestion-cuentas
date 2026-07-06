@extends('layouts.app')
@section('title', 'Orders')

@section('content')

{{-- Stats --}}
<div id="orders-stats" class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
    @include('orders.partials._stats')
</div>

    {{-- Filtros --}}
    <form method="GET" class="mb-6">
        <div class="flex flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm sm:flex-row sm:items-end">

            {{-- Buscar --}}
            <div class="flex-1">
                <label class="mb-1.5 block text-xs font-medium text-zinc-600">Buscar</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-zinc-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="m21 21-4.35-4.35M17 11a6 6 0 1 1-12 0 6 6 0 0 1 12 0Z" />
                        </svg>
                    </span>
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="email, nombre, order ID…"
                        class="w-full rounded-lg border-zinc-300 bg-zinc-50 py-2 pl-9 pr-3 text-sm font-mono
                                transition focus:border-zinc-900 focus:bg-white focus:ring-1 focus:ring-zinc-900">
                </div>
            </div>

            {{-- Status --}}
            <div class="sm:w-48">
                <label class="mb-1.5 block text-xs font-medium text-zinc-600">Status (Woo)</label>
                <div class="relative">
                    <select name="wc_status">
                        <option value="">Todos los estados</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(request('wc_status') === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-zinc-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                        </svg>
                    </span>
                </div>
            </div>
           {{-- Consola (multi) --}}
            <div class="sm:w-56">
                <label class="mb-1.5 block text-xs font-medium text-zinc-600">Consola</label>
                <div class="relative" id="console-filter">
                    <button type="button" id="console-toggle"
                            class="flex w-full items-center justify-between rounded-lg border border-zinc-300 bg-zinc-50 py-2 pl-3 pr-3 text-sm
                                transition focus:border-zinc-900 focus:bg-white focus:ring-1 focus:ring-zinc-900">
                        <span id="console-label" class="truncate text-zinc-700">Todas</span>
                        <svg class="h-4 w-4 shrink-0 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                        </svg>
                    </button>

                    <div id="console-menu"
                        class="absolute z-20 mt-1 hidden max-h-60 w-full overflow-auto rounded-lg border border-zinc-200 bg-white py-1 shadow-lg">
                        @forelse ($consoles as $c)
                            <label class="flex cursor-pointer items-center gap-2 px-3 py-1.5 text-sm hover:bg-zinc-50">
                                <input type="checkbox" name="console[]" value="{{ $c }}"
                                    class="console-check rounded border-zinc-300"
                                    @checked(in_array($c, (array) request('console')))>
                                <span>{{ $c }}</span>
                            </label>
                        @empty
                            <span class="block px-3 py-1.5 text-sm text-zinc-400">Sin consolas</span>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Acciones --}}
            <div class="flex items-center gap-2">
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium
                            text-white shadow-sm transition hover:bg-zinc-700 focus:outline-none focus:ring-2
                            focus:ring-zinc-900 focus:ring-offset-1">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3 4.5h18M6 12h12M10 19.5h4" />
                    </svg>
                    Filtrar
                </button>

                @if (request()->hasAny(['search', 'wc_status', 'console']))
                    <a href="{{ route('orders.index', ['clear' => 1]) }}"
                        class="inline-flex items-center rounded-lg border border-zinc-200 px-3 py-2 text-sm
                                text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900">
                            Limpiar
                        </a>
                @endif
            </div>
        </div>
    </form>

{{-- Barra de acción bulk (oculta hasta seleccionar) --}}
<div id="bulk-bar"
     class="mb-3 hidden items-center justify-between rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-2.5">
    <span class="text-sm text-zinc-600">
        <span id="bulk-count" class="font-semibold">0</span> orden(es) seleccionada(s)
    </span>
    <button type="button" id="bulk-delete-btn"
            class="rounded-md bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700">
        Eliminar seleccionadas
    </button>
</div>

<div class="overflow-x-auto rounded-lg border border-zinc-200 bg-white">
    <table class="min-w-full text-sm">
        <thead class="bg-zinc-50 border-b border-zinc-200 text-xs uppercase tracking-wide text-zinc-600">
            <tr>
                <th class="px-4 py-2 text-left">
                    <input type="checkbox" id="select-all"
                        class="rounded border-zinc-300"
                        onclick="event.stopPropagation()">
                </th>
                <th class="px-4 py-2 text-left font-medium">Order</th>
                <th class="px-4 py-2 text-left font-medium">Items</th>
                <th class="px-4 py-2 text-left font-medium">Envio</th>
                <th class="px-4 py-2 text-left font-medium">Consola</th> 
                <th class="px-4 py-2 text-left font-medium">Cliente</th>
                <th class="px-4 py-2 text-left font-medium">Estado Woo</th>
                <th class="px-4 py-2 text-left font-medium">Fecha</th>
            </tr>
        </thead>
        <tbody id="orders-tbody" data-signature="{{ $signature }}" class="divide-y divide-zinc-100">
            @include('orders.partials._rows')
        </tbody>
    </table>
</div>

<div id="orders-pagination" class="mt-4">{{ $orders->links() }}</div>

<script>
(function () {
    const CSRF       = '{{ csrf_token() }}';
    const DELETE_URL = '{{ route('orders.bulk-destroy') }}';
    const POLL_URL   = '{{ route('orders.poll') }}';
    const POLL_MS    = 10000;

    const selectAll = document.getElementById('select-all');
    const bar       = document.getElementById('bulk-bar');
    const countEl   = document.getElementById('bulk-count');
    const deleteBtn = document.getElementById('bulk-delete-btn');

    const statsBox  = document.getElementById('orders-stats');
    const tbody     = document.getElementById('orders-tbody');
    const pager     = document.getElementById('orders-pagination');

    let deleting  = false;
    let signature = tbody?.dataset.signature || null;
    let timer     = null;

    const checks   = () => Array.from(document.querySelectorAll('.row-check'));
    const selected = () => checks().filter(c => c.checked).map(c => c.value);

    function refresh() {
        const n = selected().length;
        countEl.textContent = n;
        bar.classList.toggle('hidden', n === 0);
        bar.classList.toggle('flex', n > 0);
        if (selectAll) {
            selectAll.checked = n > 0 && n === checks().length;
            selectAll.indeterminate = n > 0 && n < checks().length;
        }
    }

    function bindChecks() {
        checks().forEach(c => {
            c.removeEventListener('change', refresh);
            c.addEventListener('change', refresh);
        });
    }

    selectAll?.addEventListener('change', () => {
        checks().forEach(c => c.checked = selectAll.checked);
        refresh();
    });
    bindChecks();

    // ── Bulk delete ──
    deleteBtn?.addEventListener('click', async () => {
        const ids = selected();
        if (!ids.length) return;
        if (!confirm(`¿Eliminar ${ids.length} orden(es)? Esta acción no se puede deshacer.`)) return;

        deleting = true;
        deleteBtn.disabled = true;
        try {
            const res = await fetch(DELETE_URL, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ order_ids: ids }),
            });
            const data = await res.json();
            if (res.ok && data.success) {
                location.reload();
            } else {
                alert(data.message || 'No se pudieron eliminar las órdenes.');
                deleteBtn.disabled = false;
                deleting = false;
            }
        } catch (e) {
            alert('Error de red al eliminar.');
            deleteBtn.disabled = false;
            deleting = false;
        }
    });

    // ── Polling cada 10s ──
    async function poll() {
        if (deleting || document.hidden) return;   // no pisar la UI en medio de algo

        try {
            const url = new URL(POLL_URL, window.location.origin);
            url.search = window.location.search;   // preserva console[] repetidos

            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();

            if (data.signature === signature) return; // nada cambió → no repintar
            signature = data.signature;

            const prev = new Set(selected());          // preservar selección

            statsBox.innerHTML = data.stats_html;
            tbody.innerHTML    = data.rows_html;
            pager.innerHTML    = data.pagination_html;

            bindChecks();
            checks().forEach(c => { if (prev.has(c.value)) c.checked = true; });
            refresh();
        } catch (_) {
            // silencioso: reintenta en el próximo tick
        }
    }

    function start() { stop(); timer = setInterval(poll, POLL_MS); }
    function stop()  { if (timer) { clearInterval(timer); timer = null; } }

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) stop();
        else { poll(); start(); }   // al volver, refresca ya y reanuda
    });

    start();
})();
</script>
<script>
(function () {
    const wrap = document.getElementById('console-filter');
    if (!wrap) return;
    const toggle = document.getElementById('console-toggle');
    const menu   = document.getElementById('console-menu');
    const label  = document.getElementById('console-label');
    const boxes  = () => Array.from(menu.querySelectorAll('.console-check'));

    function syncLabel() {
        const checked = boxes().filter(b => b.checked);
        if (checked.length === 0)      label.textContent = 'Todas';
        else if (checked.length === 1) label.textContent = checked[0].value;
        else                           label.textContent = checked.length + ' consolas';
    }

    toggle.addEventListener('click', () => menu.classList.toggle('hidden'));
    menu.addEventListener('change', syncLabel);
    document.addEventListener('click', (e) => { if (!wrap.contains(e.target)) menu.classList.add('hidden'); });

    syncLabel();
})();
</script>
@endsection
