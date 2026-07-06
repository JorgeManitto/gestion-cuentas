<input type="hidden" id="add-item-csrf" value="{{ csrf_token() }}">

<div id="add-item-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-zinc-900/50 backdrop-blur-sm" onclick="closeAddItem()"></div>

    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl max-h-[90vh] flex flex-col"
             onclick="event.stopPropagation()">

            <div class="px-6 py-4 border-b border-zinc-200 flex items-center justify-between gap-4">
                <h3 class="text-lg font-semibold">Agregar item / reemplazar</h3>
                <button type="button" onclick="closeAddItem()"
                        class="text-zinc-400 hover:text-zinc-900 text-2xl leading-none">×</button>
            </div>

            {{-- ¿A qué items reemplaza? (opcional) --}}
            <div class="px-6 py-3 border-b border-zinc-100 bg-zinc-50/60">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-zinc-500 mb-2">
                    Reemplaza a (opcional)
                </div>
                <div class="flex flex-wrap gap-x-4 gap-y-1.5">
                    @forelse ($order->items->where('fulfillment_status', 'pending')->whereNull('replaced_by_item_id') as $it)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" class="add-item-replace h-4 w-4 rounded border-zinc-300 text-emerald-600"
                                   value="{{ $it->id }}">
                            <span>{{ $it->game_title }}</span>
                            <span class="text-xs text-zinc-400 font-mono">{{ $it->platform_normalized }} · #{{ $it->id }}</span>
                        </label>
                    @empty
                        <span class="text-sm text-zinc-400 italic">No hay items pendientes para reemplazar.</span>
                    @endforelse
                </div>
            </div>

            <div class="px-6 py-3 border-b border-zinc-100">
                <input type="text" id="add-item-search" placeholder="Buscar producto por nombre…" class="fld-input">
            </div>

            <div class="flex-1 overflow-y-auto p-6">
                <div id="add-item-grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4"></div>
                <div id="add-item-empty"   class="hidden text-center py-12 text-sm text-zinc-500">No se encontraron productos.</div>
                <div id="add-item-loading" class="hidden text-center py-12 text-sm text-zinc-500">Cargando…</div>
            </div>

            {{-- Paginación --}}
            <div class="px-6 py-3 border-t border-zinc-200 flex items-center justify-between text-sm">
                <div id="add-item-count" class="text-zinc-500"></div>
                <div class="flex items-center gap-2">
                    <button type="button" id="add-item-prev" onclick="changeAddItemPage(-1)"
                            class="px-3 py-1 rounded border border-zinc-200 text-zinc-700 hover:bg-zinc-50 disabled:opacity-40 disabled:cursor-not-allowed">← Anterior</button>
                    <span id="add-item-page" class="font-mono text-xs text-zinc-600 min-w-[60px] text-center"></span>
                    <button type="button" id="add-item-next" onclick="changeAddItemPage(1)"
                            class="px-3 py-1 rounded border border-zinc-200 text-zinc-700 hover:bg-zinc-50 disabled:opacity-40 disabled:cursor-not-allowed">Siguiente →</button>
                </div>
            </div>

            {{-- Acciones: seleccionado + Aceptar / Cancelar --}}
            <div class="px-6 py-3 border-t border-zinc-200 flex items-center justify-between gap-4 bg-zinc-50/60">
                <div id="add-item-selected" class="text-sm text-zinc-500 italic min-w-0 truncate">
                    Ningún producto seleccionado
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" onclick="closeAddItem()"
                            class="rounded-md bg-white px-3 py-1.5 text-sm font-medium text-zinc-700 ring-1 ring-inset ring-zinc-300 hover:bg-zinc-100">
                        Cancelar
                    </button>
                    <button type="button" id="add-item-accept" onclick="confirmAddItem()" disabled
                            class="rounded-md bg-emerald-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed">
                        Aceptar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const addItem = {
    page: 1, lastPage: 1, search: '', debounceTimer: null,
    pickerUrl: @json(route('woo-products.picker')),
    addUrl:    @json(route('orders.add-item', $order)),
    selected:  null,   // { gameId, platform, name }
};

function aiEsc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[c]));
}

function openAddItem() {
    document.getElementById('add-item-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    addItem.page = 1;
    addItem.search = '';
    clearAddItemSelection();
    document.getElementById('add-item-search').value = '';
    document.querySelectorAll('.add-item-replace').forEach(c => c.checked = false);
    loadAddItemPage();
    setTimeout(() => document.getElementById('add-item-search').focus(), 50);
}
function closeAddItem() {
    document.getElementById('add-item-modal').classList.add('hidden');
    document.body.style.overflow = '';
}
function changeAddItemPage(delta) {
    const p = addItem.page + delta;
    if (p < 1 || p > addItem.lastPage) return;
    addItem.page = p;
    loadAddItemPage();
}

document.getElementById('add-item-search').addEventListener('input', (e) => {
    addItem.search = e.target.value;
    addItem.page = 1;
    clearTimeout(addItem.debounceTimer);
    addItem.debounceTimer = setTimeout(loadAddItemPage, 250);
});

async function loadAddItemPage() {
    const grid = document.getElementById('add-item-grid');
    const loading = document.getElementById('add-item-loading');
    const empty = document.getElementById('add-item-empty');

    grid.innerHTML = '';
    empty.classList.add('hidden');
    loading.classList.remove('hidden');

    const params = new URLSearchParams({ page: addItem.page, search: addItem.search });

    try {
        const res = await fetch(`${addItem.pickerUrl}?${params}`, { headers: { 'Accept': 'application/json' } });
        const json = await res.json();
        loading.classList.add('hidden');
        addItem.lastPage = json.meta.last_page;

        if (json.data.length === 0) {
            empty.classList.remove('hidden');
        } else {
            json.data.forEach(p => grid.appendChild(renderAddItemCard(p)));
        }

        document.getElementById('add-item-count').textContent = `${json.meta.total} productos`;
        document.getElementById('add-item-page').textContent  = `${json.meta.current_page} / ${json.meta.last_page}`;
        document.getElementById('add-item-prev').disabled = addItem.page <= 1;
        document.getElementById('add-item-next').disabled = addItem.page >= addItem.lastPage;
    } catch (err) {
        loading.classList.add('hidden');
        empty.textContent = 'Error al cargar productos. Reintentá.';
        empty.classList.remove('hidden');
    }
}

function renderAddItemCard(product) {
    const card = document.createElement('div');
    card.className = 'add-item-card rounded-lg border border-zinc-200 bg-white p-3 flex flex-col hover:border-zinc-400 transition';
    card.dataset.gameId = product.game_id;

    const cover = product.image_url
        ? `<img src="${aiEsc(product.image_url)}" alt="" class="w-full aspect-[3/4] object-cover rounded bg-zinc-100" onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'w-full aspect-[3/4] rounded bg-zinc-100 flex items-center justify-center text-zinc-400 text-xs',textContent:'sin imagen'}))">`
        : `<div class="w-full aspect-[3/4] rounded bg-zinc-100 flex items-center justify-center text-zinc-400 text-xs">sin imagen</div>`;

    const platformBadge = product.platform
        ? `<span class="inline-block mt-1 text-[10px] font-mono uppercase px-1.5 py-0.5 rounded bg-zinc-100 text-zinc-600">${aiEsc(product.platform)}</span>`
        : '';

    card.innerHTML = `
        ${cover}
        <div class="mt-2 flex-1">
            <div class="text-xs font-medium line-clamp-2 leading-tight" title="${aiEsc(product.name)}">${aiEsc(product.name)}</div>
            ${platformBadge}
        </div>
        <button type="button"
                data-game-id="${aiEsc(product.game_id)}"
                data-platform="${aiEsc(product.platform || '')}"
                data-name="${aiEsc(product.name)}"
                class="add-item-pick-btn mt-2 w-full rounded bg-zinc-100 hover:bg-zinc-200 text-zinc-700 text-xs font-medium px-2 py-1.5 transition">
            Seleccionar
        </button>`;

    card.querySelector('.add-item-pick-btn').addEventListener('click', (e) => {
        const b = e.currentTarget.dataset;
        selectAddItem(b.gameId, b.platform, b.name, card);  
    });
    return card;
}

/* Marca el producto sin enviar nada todavía. */
function selectAddItem(gameId, platform, name, selectedCard) {
    addItem.selected = { gameId, platform, name };

    // Resaltar SOLO la card clickeada, resetear el resto
    document.querySelectorAll('.add-item-card').forEach(card => {
        const isSel = card === selectedCard;   // ← por referencia, no por gameId
        card.classList.toggle('ring-2', isSel);
        card.classList.toggle('ring-emerald-500', isSel);
        card.classList.toggle('border-emerald-500', isSel);
        const btn = card.querySelector('.add-item-pick-btn');
        if (btn) {
            btn.textContent = isSel ? '✓ Seleccionado' : 'Seleccionar';
            btn.classList.toggle('bg-emerald-600', isSel);
            btn.classList.toggle('text-white', isSel);
            btn.classList.toggle('hover:bg-emerald-700', isSel);
            btn.classList.toggle('bg-zinc-100', !isSel);
            btn.classList.toggle('text-zinc-700', !isSel);
            btn.classList.toggle('hover:bg-zinc-200', !isSel);
        }
    });

    document.getElementById('add-item-selected').classList.remove('italic', 'text-zinc-500');
    document.getElementById('add-item-selected').classList.add('text-zinc-900', 'font-medium');
    document.getElementById('add-item-selected').textContent = `Seleccionado: ${name}`;
    document.getElementById('add-item-accept').disabled = false;
}

function clearAddItemSelection() {
    addItem.selected = null;
    document.getElementById('add-item-accept').disabled = true;
    const sel = document.getElementById('add-item-selected');
    sel.textContent = 'Ningún producto seleccionado';
    sel.classList.add('italic', 'text-zinc-500');
    sel.classList.remove('text-zinc-900', 'font-medium');
}

/* Aceptar → recién acá se hace el POST. */
async function confirmAddItem() {
    if (!addItem.selected) return;

    const { gameId, platform } = addItem.selected;
    const replaces = Array.from(document.querySelectorAll('.add-item-replace:checked')).map(c => c.value);

    const acceptBtn = document.getElementById('add-item-accept');
    acceptBtn.disabled = true;
    const oldLabel = acceptBtn.textContent;
    acceptBtn.textContent = 'Agregando…';

    const fd = new FormData();
    fd.append('_token', document.getElementById('add-item-csrf').value);
    fd.append('game_id', gameId);
    if (platform) fd.append('platform', platform);
    replaces.forEach(id => fd.append('replaces[]', id));

    try {
        const res = await fetch(addItem.addUrl, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: fd,
        });
        const data = await res.json();
        if (!res.ok || !data.success) throw new Error(data.message || 'No se pudo agregar el item');

        const itemsList = document.getElementById('items-list');
        itemsList.insertAdjacentHTML('beforeend', data.new_card_html.trim());
        bindFormHandlers(itemsList.lastElementChild);

        (data.replaced_cards || []).forEach(c => {
            const old = document.querySelector(`[data-item-card-id="${c.item_id}"]`);
            if (old) replaceItemCard(old, c.item_html);
        });

        showToast(data.message || 'Item agregado', 'success');
        location.reload();
        closeAddItem();
    } catch (err) {
        acceptBtn.disabled = false;
        acceptBtn.textContent = oldLabel;
        showToast(err.message || 'Error al agregar item', 'error');
    }
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !document.getElementById('add-item-modal').classList.contains('hidden')) {
        closeAddItem();
    }
});
</script>