@extends('layouts.app')
@section('title', 'Compras')

@section('content')
<style>
    .fld-label {
        display: block;
        font-size: .8125rem;
        font-weight: 500;
        color: rgb(63 63 70);
        margin-bottom: .375rem;
    }
    .fld-input,
    .fld-select,
    .fld-textarea {
        width: 100%;
        border-radius: .5rem;
        border: 1px solid rgb(212 212 216);
        background-color: #fff;
        padding: .5rem .75rem;
        font-size: .875rem;
        line-height: 1.25rem;
        color: rgb(24 24 27);
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / .04);
        transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
    }
    .fld-input::placeholder,
    .fld-textarea::placeholder { color: rgb(161 161 170); }

    .fld-input:hover:not(:focus),
    .fld-select:hover:not(:focus),
    .fld-textarea:hover:not(:focus) { border-color: rgb(161 161 170); }

    .fld-input:focus,
    .fld-select:focus,
    .fld-textarea:focus {
        outline: none;
        border-color: rgb(16 185 129);
        box-shadow: 0 0 0 3px rgb(16 185 129 / .18);
    }
    .fld-mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }

    .fld-select {
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%2371717a'%3E%3Cpath fill-rule='evenodd' d='M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z' clip-rule='evenodd'/%3E%3C/svg%3E");
        background-position: right .55rem center;
        background-repeat: no-repeat;
        background-size: 1.1em;
        padding-right: 2.25rem;
    }

    .fld-check {
        height: 1rem; width: 1rem;
        border-radius: .25rem;
        border: 1px solid rgb(212 212 216);
        color: rgb(5 150 105);
        cursor: pointer;
    }
    .fld-check:focus { box-shadow: 0 0 0 3px rgb(16 185 129 / .25); }
</style>
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-zinc-900">Compras</h1>
        <p class="text-zinc-600">Gestiona Órdenes de Compra y Stock de Cuentas</p>

        {{-- Tabs --}}
        <div class="mt-4 inline-flex gap-1 rounded-lg border border-zinc-200 bg-zinc-50 p-1">
            <a href="{{ route('purchase-orders.index', ['tab' => 'ordenes']) }}"
            class="px-4 py-1.5 rounded-md text-sm font-medium transition {{ $tab === 'ordenes' ? 'bg-white shadow-sm text-zinc-900' : 'text-zinc-600 hover:text-zinc-900' }}">
                Órdenes de Compra
            </a>
            <a href="{{ route('purchase-orders.index', ['tab' => 'stock']) }}"
            class="px-4 py-1.5 rounded-md text-sm font-medium transition {{ $tab === 'stock' ? 'bg-white shadow-sm text-zinc-900' : 'text-zinc-600 hover:text-zinc-900' }}">
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
        @include('purchase-orders.partials.tab-ordenes')
    @else
        @include('purchase-orders.partials.tab-stock')
    @endif
</div>

{{-- ==================== MODALES ==================== --}}
@include('purchase-orders.partials.modals.stock-detail')
@include('purchase-orders.partials.modals.create-po')
@include('purchase-orders.partials.modals.complete-po')
@include('purchase-orders.partials.modals.preview')
@include('purchase-orders.partials.modals.create-stock')
@include('purchase-orders.partials.modals.game-picker')

<script>
/* ─────────── GAME PICKER (mismo endpoint que accounts) ─────────── */
const gamePicker = {
    page: 1,
    lastPage: 1,
    search: '',
    debounceTimer: null,
    url: @json(route('woo-products.picker')),
};

function openGamePicker() {
    gamePicker.page = 1;
    gamePicker.search = '';
    document.getElementById('gp-search').value = '';
    document.getElementById('modal-game-picker').showModal();
    loadGamePickerPage();
    setTimeout(() => document.getElementById('gp-search').focus(), 50);
}

function closeGamePicker() {
    document.getElementById('modal-game-picker').close();
}

function changeGamePickerPage(delta) {
    const newPage = gamePicker.page + delta;
    if (newPage < 1 || newPage > gamePicker.lastPage) return;
    gamePicker.page = newPage;
    loadGamePickerPage();
}

document.getElementById('gp-search').addEventListener('input', (e) => {
    gamePicker.search = e.target.value;
    gamePicker.page = 1;
    clearTimeout(gamePicker.debounceTimer);
    gamePicker.debounceTimer = setTimeout(loadGamePickerPage, 250);
});

async function loadGamePickerPage() {
    const grid = document.getElementById('gp-grid');
    const loading = document.getElementById('gp-loading');
    const empty = document.getElementById('gp-empty');

    grid.innerHTML = '';
    empty.classList.add('hidden');
    loading.classList.remove('hidden');

    const params = new URLSearchParams({ page: gamePicker.page, search: gamePicker.search });

    try {
        const res = await fetch(`${gamePicker.url}?${params}`, { headers: { 'Accept': 'application/json' } });
        const json = await res.json();

        loading.classList.add('hidden');
        gamePicker.lastPage = json.meta.last_page;

        if (json.data.length === 0) {
            empty.classList.remove('hidden');
        } else {
            json.data.forEach(p => grid.appendChild(renderProductCard(p)));
        }

        document.getElementById('gp-count').textContent = `${json.meta.total} productos`;
        document.getElementById('gp-page').textContent = `${json.meta.current_page} / ${json.meta.last_page}`;
        document.getElementById('gp-prev').disabled = gamePicker.page <= 1;
        document.getElementById('gp-next').disabled = gamePicker.page >= gamePicker.lastPage;
    } catch (err) {
        loading.classList.add('hidden');
        empty.classList.remove('hidden');
        empty.textContent = 'Error al cargar juegos. Reintentá.';
    }
}

function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[c]));
}

function renderProductCard(product) {
    const card = document.createElement('div');
    card.className = 'rounded-lg border border-zinc-200 bg-white p-3 flex flex-col hover:border-zinc-400 transition';

    const cover = product.image_url
        ? `<img src="${esc(product.image_url)}" alt="" class="w-full aspect-[3/4] object-cover rounded bg-zinc-100" onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'w-full aspect-[3/4] rounded bg-zinc-100 flex items-center justify-center text-zinc-400 text-xs',textContent:'sin imagen'}))">`
        : `<div class="w-full aspect-[3/4] rounded bg-zinc-100 flex items-center justify-center text-zinc-400 text-xs">sin imagen</div>`;

    const platformBadge = product.platform
        ? `<span class="inline-block mt-1 text-[10px] font-mono uppercase px-1.5 py-0.5 rounded bg-zinc-100 text-zinc-600">${esc(product.platform)}</span>`
        : '';

    card.innerHTML = `
        ${cover}
        <div class="mt-2 flex-1">
            <div class="text-xs font-medium line-clamp-2 leading-tight" title="${esc(product.name)}">${esc(product.name)}</div>
            ${platformBadge}
        </div>
        <button type="button"
                data-game-id="${esc(product.game_id)}"
                data-platform="${esc(product.platform || '')}"
                data-name="${esc(product.name)}"
                data-cover="${esc(product.image_url || '')}"
                class="product-pick-btn mt-2 w-full rounded bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium px-2 py-1.5">
            Seleccionar
        </button>
    `;
    card.querySelector('.product-pick-btn').addEventListener('click', (e) => {
        const b = e.currentTarget.dataset;
        selectProduct(b.gameId, b.name, b.platform, b.cover);
    });
    return card;
}

function selectProduct(gameId, name, platform, cover) {
    // game_id es nullable|exists en el controller: si el producto no tiene juego, lo dejamos vacío
    document.getElementById('po-game-id').value = (gameId && gameId !== 'null') ? gameId : '';
    document.getElementById('po-game-title').value = name;

    if (platform) {
        const select = document.getElementById('po-platform');
        const dual = document.getElementById('po-is-dual');
        const up = platform.toUpperCase();

        if (up === 'DUAL') {
            // Producto marcado como DUAL: dejamos la plataforma para que el operador la elija
            // y marcamos el flag. (DUAL ya no es una plataforma en sí.)
            dual.checked = true;
        } else {
            const match = Array.from(select.options).find(o => o.value.toUpperCase() === up);
            if (match) select.value = match.value;
            dual.checked = false;
        }
    }

    const display = document.getElementById('po-selected-game-display');
    const coverHtml = cover
        ? `<img src="${esc(cover)}" alt="" class="w-12 h-16 object-cover rounded shrink-0 bg-zinc-100" onerror="this.style.display='none'">`
        : `<div class="w-12 h-16 rounded bg-zinc-100 shrink-0"></div>`;
    display.innerHTML = `
        <div class="flex items-center gap-3 p-2 rounded-md border border-zinc-200">
            ${coverHtml}
            <div class="flex-1 min-w-0">
                <div class="text-sm font-medium truncate">${esc(name)}</div>
                <div class="text-xs text-zinc-500 font-mono">${esc(platform || '')}${(gameId && gameId !== 'null') ? ' · juego #' + esc(gameId) : ''}</div>
            </div>
        </div>
    `;
    closeGamePicker();
}

/* Guard: evitar enviar sin juego seleccionado (game_title es hidden, no lo valida el browser) */
document.querySelector('#modal-create-po form').addEventListener('submit', (e) => {
    if (!document.getElementById('po-game-title').value.trim()) {
        e.preventDefault();
        alert('Seleccioná un juego antes de crear la orden.');
    }
});

    let completePoPlatform = '';

    function openComplete(po) {
        const form = document.getElementById('form-complete-po');
        form.action = `/purchase-orders/${po.id}/complete`;
        document.getElementById('complete-po-info').innerHTML =
            `OC <strong>#${po.id}</strong> · ${po.game_title} · ${po.platform}` +
            (po.region && po.region !== 'sin especificar' ? ` · ${po.region}` : '');

        completePoPlatform = (po.platform || '').toUpperCase();

        completePoPlatform = (po.platform || '').toUpperCase();

        // Prellenar plataforma e is_dual con los datos de la OC
        const platformSelect = document.getElementById('complete-platform');
        const matchPlatform = Array.from(platformSelect.options)
            .find(o => o.value.toUpperCase() === completePoPlatform);
        platformSelect.value = matchPlatform ? matchPlatform.value : '';

        document.getElementById('complete-is-dual').checked = !!po.is_dual;

        // Reset de los filtros al abrir
        document.getElementById('complete-filter-email').value = '';
        document.getElementById('complete-filter-region').value = '';

        applyCompleteFilters();

        const select = form.querySelector('select[name="account_id"]');
        select.value = '';
        usedPositions = [];
        document.getElementById('complete-existing-keys').classList.add('hidden');
        document.getElementById('complete-existing-keys-list').innerHTML = '';

        document.getElementById('modal-complete-po').showModal();
    }

    function applyCompleteFilters() {
        const select  = document.querySelector('#form-complete-po select[name="account_id"]');
        const emailQ  = document.getElementById('complete-filter-email').value.trim().toLowerCase();
        const regionQ = document.getElementById('complete-filter-region').value;

        const family = (p) => p.startsWith('PS') ? 'PS'
                    : p.startsWith('XBOX') ? 'XBOX'
                    : p.startsWith('SWITCH') ? 'NIN'
                    : p;

        Array.from(select.options).forEach(opt => {
            if (!opt.value) { opt.hidden = false; return; }

            const accPlatform = (opt.dataset.platform || '').toUpperCase();
            const accRegion   = opt.dataset.region || '';
            const accEmail    = opt.textContent.toLowerCase();

            const matchPlatform = family(accPlatform) === family(completePoPlatform);
            const matchEmail    = !emailQ  || accEmail.includes(emailQ);
            const matchRegion   = !regionQ || accRegion === regionQ;

            opt.hidden = !(matchPlatform && matchEmail && matchRegion);
        });

        // Si la cuenta seleccionada quedó oculta, deseleccionar y limpiar las llaves
        if (select.selectedOptions[0] && select.selectedOptions[0].hidden) {
            select.value = '';
            renderExistingKeys();
        }
    }

    // Listeners de los filtros (se ejecutan al cargar la página, una sola vez)
    document.getElementById('complete-filter-email').addEventListener('input', applyCompleteFilters);
    document.getElementById('complete-filter-region').addEventListener('change', applyCompleteFilters);

    let usedPositions = [];

    const completeAccountSelect = document.querySelector('#form-complete-po select[name="account_id"]');
    completeAccountSelect.addEventListener('change', renderExistingKeys);

    function renderExistingKeys() {
        const opt   = completeAccountSelect.selectedOptions[0];
        const panel = document.getElementById('complete-existing-keys');
        const list  = document.getElementById('complete-existing-keys-list');
        list.innerHTML = '';

        let keys = [];
        try { keys = JSON.parse(opt?.dataset.keys || '[]'); } catch { keys = []; }

        if (!opt || !opt.value || keys.length === 0) {
            panel.classList.add('hidden');
            usedPositions = [];
            return;
        }

        usedPositions = keys.map(k => Number(k.position));

        keys.sort((a, b) => a.position - b.position).forEach(k => {
            const chip = document.createElement('span');
            chip.className = 'inline-flex items-center gap-1 rounded bg-white ring-1 ring-inset ring-amber-300 px-1.5 py-0.5 font-mono';
            chip.textContent = `#${k.position} · ${esc(k.value)}`;
            list.appendChild(chip);
        });
        panel.classList.remove('hidden');
    }

    function openPreview(url, title) {
        const img = document.getElementById('preview-img');
        img.src = url;
        img.alt = title;
        document.getElementById('modal-preview').showModal();
    }

    function openStockDetail(acc) {
        const set = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = (val === null || val === undefined || val === '') ? '—' : val;
        };

        set('sd-email',         acc.email);
        set('sd-password',      acc.password);
        set('sd-platform',      acc.platform);
        set('sd-console',       acc.type_console);
        set('sd-region',        acc.region);
        set('sd-type',          acc.account_type);
        set('sd-dual',          acc.is_dual ? 'Sí' : 'No');
        set('sd-status',        acc.status);
        set('sd-gamer-tag',     acc.gamer_tag);
        set('sd-birth',         acc.birth_date);
        set('sd-mail-email',    acc.mail_email);
        set('sd-mail-password', acc.mail_password);
        set('sd-purchased',     acc.purchased_date);
        set('sd-notes',         acc.notes);

        // Llaves
        const wrap  = document.getElementById('sd-keys');
        const empty = document.getElementById('sd-keys-empty');
        wrap.innerHTML = '';
        const keys = acc.keys || [];

        if (keys.length === 0) {
            empty.classList.remove('hidden');
        } else {
            empty.classList.add('hidden');
            keys.sort((a, b) => a.position - b.position).forEach(k => {
                const chip = document.createElement('span');
                chip.className = 'inline-flex items-center gap-1 rounded bg-zinc-100 ring-1 ring-inset ring-zinc-200 px-1.5 py-0.5 font-mono text-xs';
                chip.textContent = `#${k.position} · ${k.value}`;
                wrap.appendChild(chip);
            });
        }

        document.getElementById('modal-stock-detail').showModal();
    }


    let stockKeyIndex = 0;

    function addStockKey(data = {}) {
        const tpl = document.getElementById('stock-key-template');
        const html = tpl.innerHTML.replaceAll('__INDEX__', stockKeyIndex);
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        const row = wrapper.firstElementChild;

        if (data.position) row.querySelector('input[name$="[position]"]').value = data.position;
        if (data.value)    row.querySelector('input[name$="[value]"]').value    = data.value;

        if (!data.position) {
            const existing  = document.querySelectorAll('#stock-keys-container input[name$="[position]"]');
            const maxInForm = Array.from(existing).reduce((m, el) => Math.max(m, parseInt(el.value) || 0), 0);
            row.querySelector('input[name$="[position]"]').value = maxInForm + 1;
        }

        document.getElementById('stock-keys-container').appendChild(row);
        stockKeyIndex++;
    }

    function removeStockKey(btn) {
        btn.closest('.key-row').remove();
    }

    function bulkAddStockKeys() {
        const ta = document.getElementById('stock-keys-bulk');
        let tokens = ta.value.trim().split(/[\s,;]+/).map(t => t.trim()).filter(Boolean);
        tokens = [...new Set(tokens)].map(t => t.slice(0, 64));
        tokens.forEach(value => addStockKey({ value }));
        ta.value = '';
    }

    // Quita filas vacías antes de enviar (igual que en complete)
    document.getElementById('form-create-stock').addEventListener('submit', () => {
        document.querySelectorAll('#stock-keys-container .key-row').forEach(row => {
            if (!row.querySelector('input[name$="[value]"]').value.trim()) row.remove();
        });
    });
</script>
@endsection
