<div class="grid grid-cols-3 gap-6">

    {{-- Columna izquierda: identidad y juego --}}
    <div class="col-span-1 space-y-4">

        <div class="rounded-lg border border-zinc-200 bg-white p-5 space-y-4">
            <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Juego</div>

            {{-- Estado del juego seleccionado --}}
            <div id="selected-game-display">
                @if ($account->game)
                    <div class="flex items-center gap-3 p-2 rounded-md border border-zinc-200">
                        @if ($account->game->cover_image_url)
                            <img src="{{ $account->game->cover_image_url }}" alt=""
                                 class="w-12 h-16 object-cover rounded shrink-0 bg-zinc-100"
                                 onerror="this.style.display='none'">
                        @else
                            <div class="w-12 h-16 rounded bg-zinc-100 shrink-0"></div>
                        @endif
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium truncate">{{ $account->game->canonical_name }}</div>
                            <div class="text-xs text-zinc-500 font-mono">ID #{{ $account->game->id }}</div>
                        </div>
                    </div>
                @else
                    <div class="text-sm text-zinc-500 italic">Ningún juego seleccionado</div>
                @endif
            </div>

            <button type="button" onclick="openGamePicker()"
                    class="w-full rounded-md bg-zinc-900 px-3 py-2 text-sm font-medium text-white hover:bg-zinc-700">
                {{ $account->game ? 'Cambiar juego' : 'Seleccionar juego' }}
            </button>

            <input type="hidden" name="game_id" id="game_id" value="{{ old('game_id', $account->game_id) }}">
            @error('game_id')
                <div class="text-xs text-red-600">{{ $message }}</div>
            @enderror
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-5 space-y-4">
            <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Identidad</div>

            <div>
                <label class="block text-sm mb-1">Plataforma <span class="text-red-500">*</span></label>
                <select name="platform" class="w-full rounded-md border-zinc-300 text-sm">
                    @foreach (['DUAL', 'PS5', 'PS4', 'XBOX_ONE', 'XBOX_SERIES', 'SWITCH', 'SWITCH_2', 'STEAM'] as $p)
                        <option value="{{ $p }}" @selected(old('platform', $account->platform) === $p)>{{ $p }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm mb-1">Tipo <span class="text-red-500">*</span></label>
                <select name="account_type" class="w-full rounded-md border-zinc-300 text-sm">
                    @foreach (['INDEPENDIENTE', 'MADRE', 'HIJA'] as $t)
                        <option value="{{ $t }}" @selected(old('account_type', $account->account_type) === $t)>{{ $t }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm mb-1">Región <span class="text-red-500">*</span></label>
                <input type="text" name="region" value="{{ old('region', $account->region) }}"
                       placeholder="USA, BRASIL, TURKIA…" required
                       class="w-full rounded-md border-zinc-300 text-sm font-mono">
            </div>

            <div>
                <label class="block text-sm mb-1">Status</label>
                <select name="status" class="w-full rounded-md border-zinc-300 text-sm">
                    @foreach (['active', 'blocked', 'reset', 'archived'] as $s)
                        <option value="{{ $s }}" @selected(old('status', $account->status) === $s)>{{ $s }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm mb-1">Gamer tag</label>
                <input type="text" name="gamer_tag" value="{{ old('gamer_tag', $account->gamer_tag) }}"
                       placeholder="PSN ID / Xbox Gamertag / Steam username"
                       class="w-full rounded-md border-zinc-300 text-sm font-mono">
            </div>

            <div>
                <label class="block text-sm mb-1">Fecha nacimiento</label>
                <input type="date" name="birth_date" value="{{ old('birth_date', $account->birth_date?->format('Y-m-d')) }}"
                       class="w-full rounded-md border-zinc-300 text-sm">
            </div>
        </div>
    </div>

    {{-- Columna central+derecha: credenciales, fechas, llaves --}}
    <div class="col-span-2 space-y-4">

        <div class="rounded-lg border border-zinc-200 bg-white p-5 space-y-4">
            <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Credenciales</div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm mb-1">Email cuenta <span class="text-red-500">*</span></label>
                    <input type="email" name="email" value="{{ old('email', $account->email) }}" required
                           class="w-full rounded-md border-zinc-300 text-sm font-mono">
                    @error('email')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="block text-sm mb-1">Password cuenta <span class="text-red-500">*</span></label>
                    <input type="text" name="password" value="{{ old('password', $account->password) }}" required
                           class="w-full rounded-md border-zinc-300 text-sm font-mono">
                </div>
                <div>
                    <label class="block text-sm mb-1">Email del correo</label>
                    <input type="email" name="mail_email" value="{{ old('mail_email', $account->mail_email) }}"
                           class="w-full rounded-md border-zinc-300 text-sm font-mono">
                    @error('mail_email')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="block text-sm mb-1">Password del correo</label>
                    <input type="text" name="mail_password" value="{{ old('mail_password', $account->mail_password) }}"
                           class="w-full rounded-md border-zinc-300 text-sm font-mono">
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-5 space-y-4">
            <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Fechas</div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm mb-1">Creación</label>
                    <input type="date" name="created_date"
                           value="{{ old('created_date', $account->created_date?->format('Y-m-d')) }}"
                           class="w-full rounded-md border-zinc-300 text-sm">
                </div>
                <div>
                    <label class="block text-sm mb-1">Compra</label>
                    <input type="date" name="purchased_date"
                           value="{{ old('purchased_date', $account->purchased_date?->format('Y-m-d')) }}"
                           class="w-full rounded-md border-zinc-300 text-sm">
                </div>
                <div>
                    <label class="block text-sm mb-1">Reset</label>
                    <input type="date" name="reset_date"
                           value="{{ old('reset_date', $account->reset_date?->format('Y-m-d')) }}"
                           class="w-full rounded-md border-zinc-300 text-sm">
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-5 space-y-3">
            <div class="flex items-center justify-between">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Llaves de recuperación</div>
                <button type="button" onclick="addKey()"
                        class="text-xs text-zinc-700 hover:text-zinc-900 px-2 py-1 rounded border border-zinc-200 hover:bg-zinc-50">
                    + Agregar llave
                </button>
            </div>

            <div id="keys-container" class="space-y-2"></div>

            {{-- Template oculto para clonar al agregar llaves --}}
            <template id="key-template">
                <div class="key-row flex items-center gap-2">
                    <input type="hidden" name="keys[__INDEX__][id]" value="">
                    <input type="number" name="keys[__INDEX__][position]" min="1" max="20"
                           placeholder="#" required
                           class="w-16 rounded-md border-zinc-300 text-sm font-mono">
                    <input type="text" name="keys[__INDEX__][value]" maxlength="64" required
                           placeholder="Valor de la llave"
                           class="flex-1 rounded-md border-zinc-300 text-sm font-mono">
                    <button type="button" onclick="removeKey(this)"
                            class="text-xs text-red-600 hover:text-red-800 px-2">×</button>
                </div>
            </template>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-5 space-y-2">
            <label class="block text-xs font-medium uppercase tracking-wide text-zinc-500">Notas</label>
            <textarea name="notes" rows="3"
                      class="w-full rounded-md border-zinc-300 text-sm">{{ old('notes', $account->notes) }}</textarea>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ $account->exists ? route('accounts.show', $account) : route('accounts.index') }}"
               class="rounded-md bg-white px-4 py-2 text-sm font-medium text-zinc-700 ring-1 ring-inset ring-zinc-200 hover:bg-zinc-50">
                Cancelar
            </a>
            <button type="submit"
                    class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
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
                       class="w-full rounded-md border-zinc-300 text-sm">
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
        url: @json(route('games.picker')),
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

            // Render cards
            if (json.data.length === 0) {
                empty.classList.remove('hidden');
            } else {
                json.data.forEach(g => grid.appendChild(renderGameCard(g)));
            }

            // Estado del paginador
            document.getElementById('game-picker-count').textContent =
                `${json.meta.total} juegos`;
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

    function renderGameCard(game) {
        const card = document.createElement('div');
        card.className = 'rounded-lg border border-zinc-200 bg-white p-3 flex flex-col hover:border-zinc-400 transition';

        const cover = game.cover_image_url
            ? `<img src="${game.cover_image_url}" alt="" class="w-full aspect-[3/4] object-cover rounded bg-zinc-100" onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'w-full aspect-[3/4] rounded bg-zinc-100 flex items-center justify-center text-zinc-400 text-xs',textContent:'sin imagen'}))">`
            : `<div class="w-full aspect-[3/4] rounded bg-zinc-100 flex items-center justify-center text-zinc-400 text-xs">sin imagen</div>`;

        card.innerHTML = `
            ${cover}
            <div class="mt-2 flex-1">
                <div class="text-xs font-medium line-clamp-2 leading-tight" title="${game.canonical_name.replace(/"/g, '&quot;')}">
                    ${game.canonical_name}
                </div>
            </div>
            <button type="button" data-game-id="${game.id}" data-game-name="${game.canonical_name.replace(/"/g, '&quot;')}"
                    data-game-cover="${game.cover_image_url || ''}"
                    class="game-pick-btn mt-2 w-full rounded bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium px-2 py-1.5">
                Seleccionar
            </button>
        `;
        card.querySelector('.game-pick-btn').addEventListener('click', (e) => {
            const btn = e.currentTarget;
            selectGame(btn.dataset.gameId, btn.dataset.gameName, btn.dataset.gameCover);
        });
        return card;
    }

    function selectGame(id, name, cover) {
        document.getElementById('game_id').value = id;

        const display = document.getElementById('selected-game-display');
        const coverHtml = cover
            ? `<img src="${cover}" alt="" class="w-12 h-16 object-cover rounded shrink-0 bg-zinc-100" onerror="this.style.display='none'">`
            : `<div class="w-12 h-16 rounded bg-zinc-100 shrink-0"></div>`;
        display.innerHTML = `
            <div class="flex items-center gap-3 p-2 rounded-md border border-zinc-200">
                ${coverHtml}
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium truncate">${name}</div>
                    <div class="text-xs text-zinc-500 font-mono">ID #${id}</div>
                </div>
            </div>
        `;
        closeGamePicker();
    }

    // Cerrar con ESC
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
</script>
