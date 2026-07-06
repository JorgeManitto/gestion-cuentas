@extends('layouts.app')

@section('content')
<div class=" mx-auto p-4">
    <h1 class="text-2xl font-bold mb-1">Asignación masiva de juego</h1>
    <p class="text-sm text-gray-500 mb-4">Asigná o reasigná el juego de varias cuentas a la vez.</p>

    @if (session('success'))
        <div class="mb-4 rounded bg-green-100 text-green-800 px-4 py-3">{{ session('success') }}</div>
    @endif
    @error('account_ids')
        <div class="mb-4 rounded bg-red-100 text-red-800 px-4 py-3">{{ $message }}</div>
    @enderror

    {{-- Filtros (GET) --}}
    <form method="GET" class="flex flex-wrap gap-2 mb-4">
        <input type="text" name="search" value="{{ request('search') }}"
               placeholder="Email o gamer tag…"
               class="border rounded px-3 py-2 text-sm">

        <select name="platform" class="border rounded px-3 py-2 text-sm">
            <option value="">Todas las plataformas</option>
            @foreach ($platforms as $p)
                <option value="{{ $p }}" @selected(request('platform') === $p)>{{ $p }}</option>
            @endforeach
        </select>

        <select name="game" class="border rounded px-3 py-2 text-sm">
            <option value="">Todos los juegos</option>
            <option value="none" @selected(request('game') === 'none')>— Sin juego —</option>
            @foreach ($games as $g)
                <option value="{{ $g->id }}" @selected((string) request('game') === (string) $g->id)>
                    {{ $g->canonical_name }}
                </option>
            @endforeach
        </select>

        <button class="bg-gray-800 text-white rounded px-4 py-2 text-sm">Filtrar</button>
    </form>

    {{-- Form principal (POST) --}}
    <form method="POST" action="{{ route('accounts.bulk-assign.store') }}" id="bulkForm">
        @csrf
        <input type="hidden" name="game_id" id="game_id">
        {{-- contexto para "todas las del filtro" --}}
        <input type="hidden" name="filter_search"   value="{{ request('search') }}">
        <input type="hidden" name="filter_platform" value="{{ request('platform') }}">
        <input type="hidden" name="filter_game"      value="{{ request('game') }}">
        <input type="hidden" name="select_all" id="select_all" value="0">

        {{-- Barra de acción --}}
        <div class="flex flex-wrap items-center gap-3 mb-3 p-3 border rounded bg-gray-50">
            <button type="button" id="pickGameBtn"
                    class="bg-indigo-600 text-white rounded px-4 py-2 text-sm">
                Elegir juego…
            </button>
            <div id="chosenGame" class="text-sm text-gray-600">Ningún juego seleccionado</div>

            <div class="ml-auto flex items-center gap-3">
                <span id="selCount" class="text-sm text-gray-500">0 seleccionadas</span>
                <button type="submit" id="applyBtn" disabled
                        class="bg-green-600 disabled:opacity-40 text-white rounded px-4 py-2 text-sm">
                    Asignar a seleccionadas
                </button>
            </div>
        </div>

        {{-- Seleccionar TODAS las del filtro (cruza páginas) --}}
        @if ($filteredTotal > $accounts->count())
            <label class="flex items-center gap-2 text-sm mb-2 text-amber-700">
                <input type="checkbox" id="selectAllFiltered">
                Seleccionar las {{ $filteredTotal }} cuentas que coinciden con el filtro (todas las páginas)
            </label>
        @endif

        <div class="overflow-x-auto border rounded">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="p-2 w-8"><input type="checkbox" id="checkPage"></th>
                        <th class="p-2 text-left">Juego</th>
                        <th class="p-2 text-left">Email</th>
                        <th class="p-2 text-left">Gamer tag</th>
                        <th class="p-2 text-left">Plataforma</th>
                        <th class="p-2 text-left">Tipo</th>
                        <th class="p-2 text-left">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($accounts as $account)
                        <tr class="border-t hover:bg-gray-50">
                            <td class="p-2">
                                <input type="checkbox" name="account_ids[]"
                                       value="{{ $account->id }}" class="rowCheck">
                            </td>
                            <td class="p-2">
                                @if ($account->game)
                                    {{ $account->game->displayName() }}
                                @else
                                    <span class="text-gray-400 italic">Sin juego</span>
                                @endif
                            </td>
                            <td class="p-2">{{ $account->email }}</td>
                            <td class="p-2">{{ $account->gamer_tag ?? '—' }}</td>
                            <td class="p-2">{{ $account->platform }}</td>
                            <td class="p-2">{{ $account->account_type ?? '—' }}</td>
                            <td class="p-2">{{ $account->status }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-4 text-center text-gray-400">No hay cuentas con este filtro.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $accounts->links() }}</div>
    </form>
</div>

{{-- Modal picker --}}
<div id="pickerModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg w-full max-w-3xl max-h-[85vh] flex flex-col">
        <div class="p-4 border-b flex items-center gap-2">
            <input type="text" id="pickerSearch" placeholder="Buscar juego o producto…"
                   class="border rounded px-3 py-2 text-sm flex-1">
            <button type="button" id="closePicker" class="text-gray-500 px-2">✕</button>
        </div>
        <div id="pickerResults" class="p-4 overflow-y-auto grid grid-cols-2 md:grid-cols-3 gap-3"></div>
        <div class="p-3 border-t flex items-center justify-between text-sm">
            <button type="button" id="pickerPrev" class="px-3 py-1 border rounded disabled:opacity-40">Anterior</button>
            <span id="pickerPage" class="text-gray-500"></span>
            <button type="button" id="pickerNext" class="px-3 py-1 border rounded disabled:opacity-40">Siguiente</button>
        </div>
    </div>
</div>

<script>
(() => {
    const pickerUrl = "{{ route('woo-products.picker') }}";
    const filteredTotal = {{ $filteredTotal }};

    const $ = (id) => document.getElementById(id);
    const modal   = $('pickerModal');
    const results = $('pickerResults');
    const gameIdInput = $('game_id');
    const applyBtn = $('applyBtn');
    let page = 1, lastPage = 1, searchTimer = null;

    // ---- selección de cuentas ----
    const rowChecks = () => Array.from(document.querySelectorAll('.rowCheck'));
    const selectAllFiltered = $('selectAllFiltered');

    function refreshState() {
        const allFiltered = selectAllFiltered && selectAllFiltered.checked;
        const selected = allFiltered ? filteredTotal : rowChecks().filter(c => c.checked).length;

        $('select_all').value = allFiltered ? '1' : '0';
        $('selCount').textContent = allFiltered
            ? `${selected} (todas las del filtro)`
            : `${selected} seleccionadas`;

        rowChecks().forEach(c => { c.disabled = allFiltered; });

        const hasGame = gameIdInput.value !== '';
        applyBtn.disabled = !(hasGame && selected > 0);
        applyBtn.textContent = allFiltered ? 'Asignar a todas las del filtro' : 'Asignar a seleccionadas';
    }

    $('checkPage').addEventListener('change', (e) => {
        rowChecks().forEach(c => { if (!c.disabled) c.checked = e.target.checked; });
        refreshState();
    });
    rowChecks().forEach(c => c.addEventListener('change', refreshState));
    selectAllFiltered && selectAllFiltered.addEventListener('change', refreshState);

    // ---- confirmación al aplicar en masa ----
    $('bulkForm').addEventListener('submit', (e) => {
        if ($('select_all').value === '1') {
            if (!confirm(`Vas a asignar el juego a ${filteredTotal} cuenta(s) del filtro. ¿Confirmás?`)) {
                e.preventDefault();
            }
        }
    });

    // ---- modal / picker ----
    function openModal() { modal.classList.remove('hidden'); modal.classList.add('flex'); load(); }
    function closeModal() { modal.classList.add('hidden'); modal.classList.remove('flex'); }

    $('pickGameBtn').addEventListener('click', openModal);
    $('closePicker').addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

    $('pickerSearch').addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { page = 1; load(); }, 300);
    });
    $('pickerPrev').addEventListener('click', () => { if (page > 1) { page--; load(); } });
    $('pickerNext').addEventListener('click', () => { if (page < lastPage) { page++; load(); } });

    async function load() {
        const search = encodeURIComponent($('pickerSearch').value.trim());
        results.innerHTML = '<div class="col-span-full text-center text-gray-400 py-6">Cargando…</div>';

        const res = await fetch(`${pickerUrl}?search=${search}&page=${page}`, {
            headers: { 'Accept': 'application/json' }
        });
        const json = await res.json();

        page = json.meta.current_page;
        lastPage = json.meta.last_page;
        $('pickerPage').textContent = `Página ${page} de ${lastPage} · ${json.meta.total} resultados`;
        $('pickerPrev').disabled = page <= 1;
        $('pickerNext').disabled = page >= lastPage;

        results.innerHTML = '';
        if (!json.data.length) {
            results.innerHTML = '<div class="col-span-full text-center text-gray-400 py-6">Sin resultados.</div>';
            return;
        }

        json.data.forEach(p => {
            const card = document.createElement('button');
            card.type = 'button';
            card.className = 'border rounded p-2 text-left hover:border-indigo-500 hover:shadow flex flex-col gap-1';
            card.innerHTML = `
                ${p.image_url ? `<img src="${p.image_url}" class="w-full h-24 object-cover rounded" loading="lazy">` : ''}
                <div class="font-medium text-sm leading-tight">${escapeHtml(p.game_name ?? p.name)}</div>
                <div class="text-xs text-gray-500">${escapeHtml(p.name)}</div>
                ${p.platform ? `<span class="text-[10px] inline-block bg-gray-200 rounded px-1 py-0.5 mt-auto w-max">${p.platform}</span>` : ''}
            `;
            card.addEventListener('click', () => {
                gameIdInput.value = p.game_id;
                $('chosenGame').innerHTML =
                    `Juego: <strong>${escapeHtml(p.game_name ?? p.name)}</strong>` +
                    (p.platform ? ` <span class="text-xs text-gray-400">(${p.platform})</span>` : '');
                closeModal();
                refreshState();
            });
            results.appendChild(card);
        });
    }

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, m => (
            {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]
        ));
    }

    refreshState();
})();
</script>
@endsection