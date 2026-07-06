{{-- ───────────── ESTILOS DE FORMULARIO ───────────── --}}
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

    .fld-section-title {
        font-size: .6875rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: rgb(113 113 122);
    }
</style>

<div class="grid grid-cols-3 gap-6">

    {{-- Columna izquierda: identidad y juego --}}
    <div class="col-span-1 space-y-4">

        <div class="rounded-lg border border-zinc-200 bg-white p-5 space-y-4">
            <div class="fld-section-title">Juego</div>

            {{-- Estado del juego seleccionado --}}
            <div id="selected-game-display">
            @if (isset($wooProduct))
                <div class="flex items-center gap-3 p-2 rounded-md border border-zinc-200">
                    @if ($wooProduct->image_url)
                        <img src="{{ $wooProduct->image_url }}" alt=""
                            class="w-12 h-16 object-cover rounded shrink-0 bg-zinc-100"
                            onerror="this.style.display='none'">
                    @else
                        <div class="w-12 h-16 rounded bg-zinc-100 shrink-0"></div>
                    @endif
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium truncate">{{ $wooProduct->name }}</div>
                        <div class="text-xs text-zinc-500 font-mono">
                            {{ $wooProduct->platform ?? $account->platform }} · producto #{{ $wooProduct->id }}
                        </div>
                    </div>
                </div>
            @else
                <div class="text-sm text-zinc-500 italic">Ningún juego seleccionado</div>
            @endif
            </div>

            <button type="button" onclick="openGamePicker()"
                    class="w-full rounded-lg bg-zinc-900 px-3 py-2 text-sm font-medium text-white hover:bg-zinc-700 transition">
                {{ $account->game ? 'Cambiar juego' : 'Seleccionar juego' }}
            </button>

            <input type="hidden" name="game_id" id="game_id" value="{{ old('game_id', $account->game_id) }}">
            @error('game_id')
                <div class="text-xs text-red-600">{{ $message }}</div>
            @enderror
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-5 space-y-4">
            <div class="fld-section-title">Identidad</div>

            <div>
                <label class="fld-label">Plataforma <span class="text-red-500">*</span></label>
                <select name="platform" id="platform" class="fld-select">
                    @foreach (['PS5', 'PS4', 'XBOX_ONE', 'XBOX_SERIES', 'SWITCH', 'SWITCH_2', 'STEAM'] as $p)
                        <option value="{{ $p }}" @selected(old('platform', $account->platform) === $p)>{{ $p }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="hidden" name="is_dual" value="0">
                    <input type="checkbox" name="is_dual" value="1" id="is_dual"
                        @checked(old('is_dual', $account->is_dual)) class="fld-check">
                    <span>Es cuenta DUAL</span>
                </label>
            </div>
            <div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="hidden" name="is_membership" value="0">
                    <input type="checkbox" name="is_membership" value="1" id="is_membership"
                        @checked(old('is_membership', $account->is_membership))
                        onchange="toggleMembershipDuration()" class="fld-check">
                    <span>Es membresía</span>
                </label>
            </div>

            <div id="membership-duration-wrapper">
                <label class="fld-label">Duración de membresía</label>
                <select name="membership_duration_months" id="membership_duration_months" class="fld-select">
                    <option value="">—</option>
                    @foreach (\App\Models\Account::MEMBERSHIP_DURATIONS as $m)
                        <option value="{{ $m }}"
                            @selected((string) old('membership_duration_months', $account->membership_duration_months) === (string) $m)>
                            {{ $m }} meses
                        </option>
                    @endforeach
                </select>
                @error('membership_duration_months')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="fld-label">Tipo <span class="text-red-500">*</span></label>
                <select name="account_type" id="account_type" class="fld-select" onchange="toggleParentAccount()">
                    @foreach (['INDEPENDIENTE', 'MADRE', 'HIJA'] as $t)
                        <option value="{{ $t }}" @selected(old('account_type', $account->account_type) === $t)>{{ $t }}</option>
                    @endforeach
                </select>
            </div>

            {{-- ───────── Vinculación MADRE / HIJA con buscador (accounts.picker) ───────── --}}
            @php
                // Semilla MADRE (para una cuenta HIJA): respeta old() ante errores de validación
                $parentId   = old('parent_account_id', $account->parent_account_id);
                $seedParent = null;
                if ($parentId) {
                    $p = \App\Models\Account::find($parentId, ['id', 'email']);
                    if ($p) $seedParent = ['id' => $p->id, 'email' => $p->email];
                }

                // Semilla HIJAS (para una cuenta MADRE)
                if (old('children_ids') !== null) {
                    $seedChildren = \App\Models\Account::whereIn('id', (array) old('children_ids'))
                        ->get(['id', 'email'])
                        ->map(fn ($c) => ['id' => $c->id, 'email' => $c->email])
                        ->values();
                } else {
                    $seedChildren = $account->exists
                        ? $account->children()->get(['id', 'email'])
                            ->map(fn ($c) => ['id' => $c->id, 'email' => $c->email])
                            ->values()
                        : collect();
                }
            @endphp

            {{-- HIJA → elige UNA madre (parent_account_id) --}}
            <div id="madre-picker" style="display:none;">
                <label class="fld-label">Cuenta madre <span class="text-red-500">*</span></label>
                <div class="relative">
                    <input type="text" id="madre-search" autocomplete="off"
                           placeholder="Buscar madre por email o región…" class="fld-input fld-mono">
                    <div id="madre-results"
                         class="hidden absolute z-20 mt-1 w-full max-h-48 overflow-y-auto rounded-md border border-zinc-200 bg-white shadow-lg"></div>
                </div>
                <div id="madre-selected" class="mt-2 flex flex-wrap gap-2"></div>
                <input type="hidden" name="parent_account_id" id="parent_account_id">
                @error('parent_account_id')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
                <p class="text-xs text-zinc-400 mt-1">Solo aplica a cuentas HIJA.</p>
            </div>

            {{-- MADRE → elige VARIAS hijas (children_ids[]) --}}
            <div id="hijas-picker" style="display:none;">
                <label class="fld-label">Hijas vinculadas <span class="text-zinc-400 font-normal">(opc.)</span></label>
                <div class="relative">
                    <input type="text" id="hijas-search" autocomplete="off"
                           placeholder="Buscar hija por email o región…" class="fld-input fld-mono">
                    <div id="hijas-results"
                         class="hidden absolute z-20 mt-1 w-full max-h-48 overflow-y-auto rounded-md border border-zinc-200 bg-white shadow-lg"></div>
                </div>
                <div id="hijas-chips" class="mt-2 flex flex-wrap gap-2"></div>
                <p class="text-xs text-zinc-400 mt-1">Las seleccionadas pasan a ser hijas de esta cuenta. Quitar un chip la desvincula al guardar.</p>
            </div>

            <div>
                <label class="fld-label">Región <span class="text-red-500">*</span></label>
                {{-- <input type="text" name="region" value="{{ old('region', $account->region) }}"
                       placeholder="USA, BRASIL, TURKIA…" required
                       class="fld-input fld-mono"> --}}
                <select name="region" id="region" class="fld-select">
                    @foreach (['HONG KONG','BRASIL','USA','ESPAÑA','UK','TURQUIA','INDIA','ARG','CAN','UCRANIA','INDONESIA'] as $r)
                        <option value="{{ $r }}"  @selected(old('region', $account->region) === $r)>{{ $r }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="fld-label">Status</label>
                <select name="status" class="fld-select">
                    @foreach (['active', 'blocked', 'reset', 'archived'] as $s)
                        <option value="{{ $s }}" @selected(old('status', $account->status) === $s)>{{ $s }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="fld-label">Gamer tag</label>
                <input type="text" name="gamer_tag" value="{{ old('gamer_tag', $account->gamer_tag) }}"
                       placeholder="PSN ID / Xbox Gamertag / Steam username"
                       class="fld-input fld-mono">
            </div>
            <div>
                <label class="fld-label">Nombre completo</label>
                <input type="text" name="full_name" value="{{ old('full_name', $account->full_name) }}"
                    placeholder="Nombre y apellido del titular"
                    class="fld-input">
                @error('full_name')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="fld-label">Fecha nacimiento</label>
                <input type="date" name="birth_date" value="{{ old('birth_date', $account->birth_date?->format('Y-m-d')) }}"
                       class="fld-input">
            </div>
        </div>
    </div>

    {{-- Columna central+derecha: credenciales, fechas, llaves --}}
    <div class="col-span-2 space-y-4">

        <div class="rounded-lg border border-zinc-200 bg-white p-5 space-y-4">
            <div class="fld-section-title">Credenciales</div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="fld-label">Email cuenta <span class="text-red-500">*</span></label>
                    <input type="email" name="email" value="{{ old('email', $account->email) }}" required
                           class="fld-input fld-mono">
                    @error('email')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="fld-label">Password cuenta <span class="text-red-500">*</span></label>
                    <input type="text" name="password" value="{{ old('password', $account->password) }}" required
                           class="fld-input fld-mono">
                </div>
                <div>
                    <label class="fld-label">Email del correo</label>
                    <input type="email" name="mail_email" value="{{ old('mail_email', $account->mail_email) }}"
                           class="fld-input fld-mono">
                    @error('mail_email')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="fld-label">Password del correo</label>
                    <input type="text" name="mail_password" value="{{ old('mail_password', $account->mail_password) }}"
                           class="fld-input fld-mono">
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-5 space-y-4">
            <div class="fld-section-title">Fechas</div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="fld-label">Creación</label>
                    <input type="date" name="created_date"
                           value="{{ old('created_date', $account->created_date?->format('Y-m-d')) }}"
                           class="fld-input">
                </div>
                <div>
                    <label class="fld-label">Compra</label>
                    <input type="date" name="purchased_date"
                           value="{{ old('purchased_date', $account->purchased_date?->format('Y-m-d')) }}"
                           class="fld-input">
                </div>
                <div>
                    <label class="fld-label">Reset</label>
                    <input type="date" name="reset_date"
                           value="{{ old('reset_date', $account->reset_date?->format('Y-m-d')) }}"
                           class="fld-input">
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-5 space-y-3">
            <div class="flex items-center justify-between">
                <div class="fld-section-title">Llaves de recuperación</div>
                <button type="button" onclick="addKey()"
                        class="text-xs text-zinc-700 hover:text-zinc-900 px-2 py-1 rounded border border-zinc-200 hover:bg-zinc-50 transition">
                    + Agregar llave
                </button>
            </div>

            {{-- ───── Carga masiva de llaves ───── --}}
            <div class="rounded-md border border-dashed border-zinc-300 bg-zinc-50/60 p-3 space-y-2">
                <label class="fld-label !mb-1">Pegar varias llaves de una</label>
                <textarea id="keys-bulk-input" rows="3"
                          placeholder="Pegá todas las llaves separadas por espacios, comas o saltos de línea.&#10;Ej: 2H9Bqb 973xKb SHUhye J3GQNK"
                          class="fld-textarea fld-mono"></textarea>
                <div class="flex items-center justify-between gap-2">
                    <label class="flex items-center gap-2 text-xs text-zinc-600">
                        <input type="checkbox" id="keys-bulk-replace" class="fld-check">
                        <span>Reemplazar las existentes</span>
                    </label>
                    <button type="button" onclick="bulkAddKeys()"
                            class="rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium px-3 py-1.5 transition">
                        Agregar a la lista
                    </button>
                </div>
                <div id="keys-bulk-feedback" class="text-xs text-zinc-500 hidden"></div>
            </div>

            <div id="keys-container" class="space-y-2"></div>

            {{-- Template oculto para clonar al agregar llaves --}}
            <template id="key-template">
                <div class="key-row flex items-center gap-2">
                    <input type="hidden" name="keys[__INDEX__][id]" value="">
                    <input type="number" name="keys[__INDEX__][position]" min="1" max="20"
                           placeholder="#" required
                           class="fld-input fld-mono w-16" style="width: 72px;">
                    <input type="text" name="keys[__INDEX__][value]" maxlength="64" required
                           placeholder="Valor de la llave"
                           class="fld-input fld-mono flex-1">
                    <button type="button" onclick="removeKey(this)"
                            class="text-xs text-red-600 hover:text-red-800 px-2">×</button>
                </div>
            </template>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-5 space-y-2">
            <label class="fld-section-title">Notas</label>
            <textarea name="notes" rows="3" class="fld-textarea">{{ old('notes', $account->notes) }}</textarea>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ $account->exists ? route('accounts.show', $account) : route('accounts.index') }}"
               class="rounded-lg bg-white px-4 py-2 text-sm font-medium text-zinc-700 ring-1 ring-inset ring-zinc-200 hover:bg-zinc-50 transition">
                Cancelar
            </a>
            <button type="submit"
                    class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 transition">
                {{ $account->exists ? 'Guardar cambios' : 'Crear cuenta' }}
            </button>
        </div>
    </div>
</div>

{{-- ───────────── MODAL DE SELECCIÓN DE JUEGO ───────────── --}}
<div id="game-picker-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-zinc-900/50 backdrop-blur-sm" onclick="closeGamePicker()"></div>

    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl max-h-[90vh] flex flex-col"
             onclick="event.stopPropagation()">

            <div class="px-6 py-4 border-b border-zinc-200 flex items-center justify-between gap-4">
                <h3 class="text-lg font-semibold">Seleccionar juego</h3>
                <button type="button" onclick="closeGamePicker()"
                        class="text-zinc-400 hover:text-zinc-900 text-2xl leading-none">×</button>
            </div>

            <div class="px-6 py-3 border-b border-zinc-100">
                <input type="text" id="game-picker-search" placeholder="Buscar por nombre…"
                       class="fld-input">
            </div>

            <div class="flex-1 overflow-y-auto p-6">
                <div id="game-picker-grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                    {{-- Cards renderizadas dinámicamente --}}
                </div>

                <div id="game-picker-empty" class="hidden text-center py-12 text-sm text-zinc-500">
                    No se encontraron juegos.
                </div>

                <div id="game-picker-loading" class="hidden text-center py-12 text-sm text-zinc-500">
                    Cargando…
                </div>
            </div>

            <div class="px-6 py-3 border-t border-zinc-200 flex items-center justify-between text-sm">
                <div id="game-picker-count" class="text-zinc-500"></div>
                <div class="flex items-center gap-2">
                    <button type="button" id="game-picker-prev" onclick="changeGamePickerPage(-1)"
                            class="px-3 py-1 rounded border border-zinc-200 text-zinc-700 hover:bg-zinc-50 disabled:opacity-40 disabled:cursor-not-allowed">
                        ← Anterior
                    </button>
                    <span id="game-picker-page" class="font-mono text-xs text-zinc-600 min-w-[60px] text-center"></span>
                    <button type="button" id="game-picker-next" onclick="changeGamePickerPage(1)"
                            class="px-3 py-1 rounded border border-zinc-200 text-zinc-700 hover:bg-zinc-50 disabled:opacity-40 disabled:cursor-not-allowed">
                        Siguiente →
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    /* ─────────── GAME PICKER ─────────── */
    const gamePicker = {
        page: 1,
        lastPage: 1,
        search: '',
        debounceTimer: null,
        url: @json(route('woo-products.picker')),
    };

    function openGamePicker() {
        document.getElementById('game-picker-modal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        gamePicker.page = 1;
        document.getElementById('game-picker-search').value = '';
        gamePicker.search = '';
        loadGamePickerPage();
        setTimeout(() => document.getElementById('game-picker-search').focus(), 50);
    }

    function closeGamePicker() {
        document.getElementById('game-picker-modal').classList.add('hidden');
        document.body.style.overflow = '';
    }

    function changeGamePickerPage(delta) {
        const newPage = gamePicker.page + delta;
        if (newPage < 1 || newPage > gamePicker.lastPage) return;
        gamePicker.page = newPage;
        loadGamePickerPage();
    }

    document.getElementById('game-picker-search').addEventListener('input', (e) => {
        gamePicker.search = e.target.value;
        gamePicker.page = 1;
        clearTimeout(gamePicker.debounceTimer);
        gamePicker.debounceTimer = setTimeout(loadGamePickerPage, 250);
    });

    async function loadGamePickerPage() {
        const grid = document.getElementById('game-picker-grid');
        const loading = document.getElementById('game-picker-loading');
        const empty = document.getElementById('game-picker-empty');

        grid.innerHTML = '';
        empty.classList.add('hidden');
        loading.classList.remove('hidden');

        const params = new URLSearchParams({
            page: gamePicker.page,
            search: gamePicker.search,
        });

        try {
            const res = await fetch(`${gamePicker.url}?${params}`, {
                headers: { 'Accept': 'application/json' },
            });
            const json = await res.json();

            loading.classList.add('hidden');
            gamePicker.lastPage = json.meta.last_page;

            if (json.data.length === 0) {
                empty.classList.remove('hidden');
            } else {
                json.data.forEach(p => grid.appendChild(renderProductCard(p)));
            }

            document.getElementById('game-picker-count').textContent =
                `${json.meta.total} productos`;
            document.getElementById('game-picker-page').textContent =
                `${json.meta.current_page} / ${json.meta.last_page}`;
            document.getElementById('game-picker-prev').disabled = gamePicker.page <= 1;
            document.getElementById('game-picker-next').disabled = gamePicker.page >= gamePicker.lastPage;
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
                <div class="text-xs font-medium line-clamp-2 leading-tight" title="${esc(product.name)}">
                    ${esc(product.name)}
                </div>
                ${platformBadge}
            </div>
            <button type="button"
                    data-game-id="${esc(product.game_id)}"
                    data-platform="${esc(product.platform || '')}"
                    data-name="${esc(product.name)}"
                    data-cover="${esc(product.image_url || '')}"
                    class="product-pick-btn mt-2 w-full rounded bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium px-2 py-1.5 transition">
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
        document.getElementById('game_id').value = gameId;

        if (platform) {
            const select = document.getElementById('platform');
            const match = Array.from(select.options)
                .find(o => o.value.toUpperCase() === platform.toUpperCase());
            if (match) select.value = match.value;
        }

        const display = document.getElementById('selected-game-display');
        const coverHtml = cover
            ? `<img src="${esc(cover)}" alt="" class="w-12 h-16 object-cover rounded shrink-0 bg-zinc-100" onerror="this.style.display='none'">`
            : `<div class="w-12 h-16 rounded bg-zinc-100 shrink-0"></div>`;
        display.innerHTML = `
            <div class="flex items-center gap-3 p-2 rounded-md border border-zinc-200">
                ${coverHtml}
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium truncate">${esc(name)}</div>
                    <div class="text-xs text-zinc-500 font-mono">${esc(platform || '')} · juego #${esc(gameId)}</div>
                </div>
            </div>
        `;
        closeGamePicker();
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !document.getElementById('game-picker-modal').classList.contains('hidden')) {
            closeGamePicker();
        }
    });

    /* ─────────── KEYS ─────────── */
    let keyIndex = 0;

    function addKey(data = {}) {
        const tpl = document.getElementById('key-template');
        const html = tpl.innerHTML.replaceAll('__INDEX__', keyIndex);
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        const row = wrapper.firstElementChild;

        if (data.id)       row.querySelector('input[name$="[id]"]').value       = data.id;
        if (data.position) row.querySelector('input[name$="[position]"]').value = data.position;
        if (data.value)    row.querySelector('input[name$="[value]"]').value    = data.value;

        if (!data.position) {
            const existing = document.querySelectorAll('#keys-container input[name$="[position]"]');
            const max = Array.from(existing).reduce((m, el) => Math.max(m, parseInt(el.value) || 0), 0);
            row.querySelector('input[name$="[position]"]').value = max + 1;
        }

        document.getElementById('keys-container').appendChild(row);
        keyIndex++;
    }

    function removeKey(button) {
        button.closest('.key-row').remove();
    }

    /* ─────────── CARGA MASIVA DE LLAVES ─────────── */
    function bulkAddKeys() {
        const textarea = document.getElementById('keys-bulk-input');
        const replace  = document.getElementById('keys-bulk-replace').checked;
        const feedback = document.getElementById('keys-bulk-feedback');

        const raw = textarea.value.trim();
        if (!raw) {
            showBulkFeedback('Pegá al menos una llave.', 'error');
            return;
        }

        // Separa por espacios, tabs, saltos de línea, comas y punto y coma
        let tokens = raw
            .split(/[\s,;]+/)
            .map(t => t.trim())
            .filter(Boolean);

        // Quita duplicados manteniendo el orden
        tokens = [...new Set(tokens)];

        if (tokens.length === 0) {
            showBulkFeedback('No se detectaron llaves válidas.', 'error');
            return;
        }

        // Recorta a 64 caracteres por las dudas (maxlength del input)
        tokens = tokens.map(t => t.slice(0, 64));

        if (replace) {
            document.getElementById('keys-container').innerHTML = '';
        }

        tokens.forEach(value => addKey({ value }));

        textarea.value = '';
        showBulkFeedback(
            `${tokens.length} llave${tokens.length === 1 ? '' : 's'} agregada${tokens.length === 1 ? '' : 's'}.`,
            'ok'
        );
    }

    function showBulkFeedback(msg, type) {
        const el = document.getElementById('keys-bulk-feedback');
        el.textContent = msg;
        el.classList.remove('hidden', 'text-zinc-500', 'text-red-600', 'text-emerald-600');
        el.classList.add(type === 'error' ? 'text-red-600' : 'text-emerald-600');
        clearTimeout(showBulkFeedback._t);
        showBulkFeedback._t = setTimeout(() => el.classList.add('hidden'), 4000);
    }

    // Permite pegar y procesar con Ctrl/Cmd + Enter dentro del textarea
    document.getElementById('keys-bulk-input').addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            bulkAddKeys();
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        @php
            $existingKeys = old('keys', $account->exists
                ? $account->keys->map(fn ($k) => [
                    'id'       => $k->id,
                    'position' => $k->position,
                    'value'    => $k->key_value,
                ])->values()->all()
                : []
            );
        @endphp
        const existingKeys = @json($existingKeys);

        existingKeys.forEach(k => addKey(k));
    });

    /* ─────────── MEMBRESÍA ─────────── */
    function toggleMembershipDuration() {
        const isMembership = document.getElementById('is_membership').checked;
        const wrapper = document.getElementById('membership-duration-wrapper');
        wrapper.style.display = isMembership ? '' : 'none';
        if (!isMembership) {
            document.getElementById('membership_duration_months').value = '';
        }
    }

    document.addEventListener('DOMContentLoaded', toggleMembershipDuration);

    /* ─────────── PICKER MADRE / HIJAS (accounts.picker) ─────────── */
    (function () {
        const PICKER_URL    = @json(route('accounts.picker'));
        const SELF_ID       = @json($account->id);          // null en create
        const SEED_PARENT   = @json($seedParent);
        const SEED_CHILDREN = @json($seedChildren);

        const selectedChildren = new Map();
        let madreTimer = null, hijasTimer = null;
        let madreAbort = null, hijasAbort = null;

        const $ = (id) => document.getElementById(id);

        async function fetchAccounts(type, term, signal) {
            const params = new URLSearchParams({ type });
            if (term)    params.set('search', term);
            if (SELF_ID) params.set('exclude', SELF_ID);
            const res = await fetch(`${PICKER_URL}?${params}`, {
                headers: { 'Accept': 'application/json' }, signal,
            });
            return res.json();
        }

        function renderResults(box, items, onPick, isDisabledFn) {
            if (!items.length) {
                box.innerHTML = '<div class="px-3 py-2 text-xs text-zinc-400">Sin resultados.</div>';
                return;
            }
            box.innerHTML = '';
            items.forEach(a => {
                const disabled = isDisabledFn ? isDisabledFn(a) : false;
                const row = document.createElement('button');
                row.type = 'button';
                row.disabled = disabled;
                row.className = 'w-full text-left px-3 py-2 text-sm flex items-center justify-between gap-2 '
                    + (disabled ? 'opacity-40 cursor-not-allowed' : 'hover:bg-zinc-50');
                row.innerHTML = `
                    <span class="font-mono truncate">${esc(a.email)}</span>
                    <span class="shrink-0 flex items-center gap-2 text-xs text-zinc-400">
                        ${a.region ? `<span>${esc(a.region)}</span>` : ''}
                        ${a.has_parent ? '<span class="text-amber-600">ya tiene madre</span>' : ''}
                    </span>`;
                if (!disabled) row.addEventListener('click', () => onPick(a));
                box.appendChild(row);
            });
        }

        /* MADRE (selección única) */
        async function runMadreSearch(term) {
            const box = $('madre-results');
            madreAbort?.abort(); madreAbort = new AbortController();
            box.classList.remove('hidden');
            box.innerHTML = '<div class="px-3 py-2 text-xs text-zinc-400">Buscando…</div>';
            try {
                const json = await fetchAccounts('MADRE', term, madreAbort.signal);
                renderResults(box, json.data || [], pickMadre);
            } catch (e) {
                if (e.name !== 'AbortError') box.innerHTML = '<div class="px-3 py-2 text-xs text-red-500">Error al buscar.</div>';
            }
        }
        function pickMadre(a) {
            $('parent_account_id').value = a.id;
            $('madre-selected').innerHTML = `
                <span class="inline-flex items-center gap-2 rounded-md bg-zinc-100 ring-1 ring-inset ring-zinc-200 px-2 py-1 text-sm">
                    <span class="font-mono truncate max-w-[260px]">${esc(a.email)}</span>
                    <button type="button" class="text-zinc-400 hover:text-red-600" data-clear-madre>×</button>
                </span>`;
            $('madre-selected').querySelector('[data-clear-madre]').addEventListener('click', clearMadre);
            $('madre-search').value = '';
            $('madre-results').classList.add('hidden');
        }
        function clearMadre() {
            $('parent_account_id').value = '';
            $('madre-selected').innerHTML = '';
        }

        /* HIJAS (selección múltiple + chips) */
        async function runHijasSearch(term) {
            const box = $('hijas-results');
            hijasAbort?.abort(); hijasAbort = new AbortController();
            box.classList.remove('hidden');
            box.innerHTML = '<div class="px-3 py-2 text-xs text-zinc-400">Buscando…</div>';
            try {
                const json = await fetchAccounts('HIJA', term, hijasAbort.signal);
                renderResults(box, json.data || [], pickHija, (a) => selectedChildren.has(a.id));
            } catch (e) {
                if (e.name !== 'AbortError') box.innerHTML = '<div class="px-3 py-2 text-xs text-red-500">Error al buscar.</div>';
            }
        }
        function pickHija(a) {
            if (selectedChildren.has(a.id)) return;
            selectedChildren.set(a.id, a.email);
            renderHijaChips();
            $('hijas-search').value = '';
            $('hijas-results').classList.add('hidden');
        }
        function removeHija(id) {
            selectedChildren.delete(id);
            renderHijaChips();
        }
        function renderHijaChips() {
            const wrap = $('hijas-chips');
            wrap.innerHTML = '';
            selectedChildren.forEach((email, id) => {
                const chip = document.createElement('span');
                chip.className = 'inline-flex items-center gap-1.5 rounded-md bg-emerald-50 ring-1 ring-inset ring-emerald-200 px-2 py-1 text-sm';
                chip.innerHTML = `
                    <span class="font-mono truncate max-w-[200px]">${esc(email)}</span>
                    <input type="hidden" name="children_ids[]" value="${esc(id)}">
                    <button type="button" class="text-emerald-600 hover:text-red-600">×</button>`;
                chip.querySelector('button').addEventListener('click', () => removeHija(id));
                wrap.appendChild(chip);
            });
        }

        /* Toggle global (lo llama el onchange del select de Tipo) */
        window.toggleParentAccount = function () {
            const type    = $('account_type')?.value;
            const isHija  = type === 'HIJA';
            const isMadre = type === 'MADRE';

            $('madre-picker').style.display = isHija ? '' : 'none';
            $('hijas-picker').style.display = isMadre ? '' : 'none';

            if (!isHija) {
                clearMadre();
                $('madre-search').value = '';
                $('madre-results').classList.add('hidden');
            }
            if (!isMadre) {
                selectedChildren.clear();
                renderHijaChips();
                $('hijas-search').value = '';
                $('hijas-results').classList.add('hidden');
            }
        };

        function init() {
            $('madre-search').addEventListener('input', () => {
                clearTimeout(madreTimer);
                const t = $('madre-search').value.trim();
                madreTimer = setTimeout(() => runMadreSearch(t), 250);
            });
            $('hijas-search').addEventListener('input', () => {
                clearTimeout(hijasTimer);
                const t = $('hijas-search').value.trim();
                hijasTimer = setTimeout(() => runHijasSearch(t), 250);
            });

            // Cerrar resultados al hacer click fuera del picker
            document.addEventListener('click', (e) => {
                if (!e.target.closest('#madre-picker')) $('madre-results')?.classList.add('hidden');
                if (!e.target.closest('#hijas-picker')) $('hijas-results')?.classList.add('hidden');
            });

            // Semillas de edición
            if (SEED_PARENT) pickMadre(SEED_PARENT);
            (SEED_CHILDREN || []).forEach(c => selectedChildren.set(c.id, c.email));
            renderHijaChips();

            // Estado inicial según el Tipo (oculta/limpia lo que no aplica)
            window.toggleParentAccount();

            // Guard: si es HIJA, exigir madre seleccionada
            const form = $('account_type').closest('form');
            form?.addEventListener('submit', (e) => {
                if ($('account_type').value === 'HIJA' && !$('parent_account_id').value) {
                    e.preventDefault();
                    alert('Seleccioná la cuenta madre antes de guardar.');
                }
            });
        }

        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
        else init();
    })();
</script>