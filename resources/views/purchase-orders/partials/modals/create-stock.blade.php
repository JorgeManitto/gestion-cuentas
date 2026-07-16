{{-- ==================== MODAL: CREAR CUENTA DE STOCK ==================== --}}
{{-- Requiere el endpoint accounts.picker (AccountPickerController). Ya NO usa $linkableAccounts. --}}
<dialog id="modal-create-stock" class="rounded-xl p-0 backdrop:bg-zinc-900/40 backdrop:backdrop-blur-sm w-full max-w-lg">
    <form method="POST" id="form-create-stock" action="{{ route('purchase-orders.stock.store') }}" class="p-5 space-y-4">
        @csrf
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold">Nueva cuenta de stock</h2>
            <button type="button" onclick="document.getElementById('modal-create-stock').close()"
                    class="text-zinc-400 hover:text-zinc-700 text-xl leading-none">✕</button>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="fld-label">Email <span class="text-red-500">*</span></label>
                <input type="email" name="email" required autocomplete="off" class="fld-input fld-mono">
            </div>
            <div>
                <label class="fld-label">Password <span class="text-red-500">*</span></label>
                <input type="text" name="password" required autocomplete="off" class="fld-input fld-mono">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div class="hidden">
                <label class="fld-label">Plataforma <span class="text-red-500">*</span></label>
                <select name="platform" required class="fld-select">
                    <option value="">Selecciona…</option>
                    @foreach (['PS5','PS4','XBOX_SERIES','XBOX_ONE','SWITCH_2','SWITCH','STEAM'] as $p)
                        <option @selected($p == 'PS4') value="{{ $p }}">{{ $p }}</option>
                    @endforeach
                </select>
            </div>
            <div class="">
                <label class="fld-label">Consola <span class="text-red-500">*</span></label>
                <select name="type_console" required class="fld-select">
                    @foreach (['PLAYSTATION', 'XBOX', 'NINTENDO','STEAM'] as $p)
                        <option @selected($p == 'PLAYSTATION') value="{{ $p }}">{{ $p }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="fld-label">Región <span class="text-red-500">*</span></label>
                <select name="region" class="fld-select" required>
                    @foreach (config('regions.list') as $r)
                        <option value="{{ $r }}">{{ $r }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <label class="hidden items-center gap-2 text-sm" >
            <input type="checkbox" name="is_dual" value="1" class="fld-check">
            <span>Es cuenta DUAL</span>
        </label>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="fld-label">Mail asociado <span class="text-zinc-400 font-normal">(opc.)</span></label>
                <input type="email" name="mail_email" autocomplete="off" class="fld-input fld-mono">
            </div>
            <div>
                <label class="fld-label">Pass del mail <span class="text-zinc-400 font-normal">(opc.)</span></label>
                <input type="text" name="mail_password" autocomplete="off" class="fld-input fld-mono">
            </div>
        </div>

        <div>
            <label class="fld-label">Fecha de creación <span class="text-zinc-400 font-normal">(opc.)</span></label>
            <input type="date" name="created_date" class="fld-input">
        </div>

        <div>
            <label class="fld-label">Tipo <span class="text-red-500">*</span></label>
            <select name="account_type" id="account_type" class="fld-select" onchange="toggleParentAccount()">
                @foreach (['INDEPENDIENTE', 'MADRE', 'HIJA'] as $t)
                    <option value="{{ $t }}">{{ $t }}</option>
                @endforeach
            </select>
        </div>

        {{-- ===== HIJA -> elige UNA madre (parent_account_id) ===== --}}
        <div id="madre-picker" class="hidden">
            <label class="fld-label">Madre a vincular <span class="text-red-500">*</span></label>
            <div class="relative">
                <input type="text" id="madre-search" autocomplete="off"
                       placeholder="Buscar madre por email o región…" class="fld-input fld-mono">
                <div id="madre-results"
                     class="hidden absolute z-20 mt-1 w-full max-h-48 overflow-y-auto rounded-md border border-zinc-200 bg-white shadow-lg"></div>
            </div>
            <div id="madre-selected" class="mt-2 flex flex-wrap gap-2"></div>
            <input type="hidden" name="parent_account_id" id="parent_account_id">
            <p class="mt-1 text-xs text-zinc-500">Esta cuenta quedará vinculada como <strong>hija</strong> de la madre seleccionada.</p>
        </div>

        {{-- ===== MADRE -> elige VARIAS hijas (children_ids[]) ===== --}}
        <div id="hijas-picker" class="hidden">
            <label class="fld-label">Hijas a vincular <span class="text-zinc-400 font-normal">(opc.)</span></label>
            <div class="relative">
                <input type="text" id="hijas-search" autocomplete="off"
                       placeholder="Buscar hija por email o región…" class="fld-input fld-mono">
                <div id="hijas-results"
                     class="hidden absolute z-20 mt-1 w-full max-h-48 overflow-y-auto rounded-md border border-zinc-200 bg-white shadow-lg"></div>
            </div>
            <div id="hijas-chips" class="mt-2 flex flex-wrap gap-2"></div>
            <p class="mt-1 text-xs text-zinc-500">Las seleccionadas pasarán a ser <strong>hijas</strong> de esta cuenta.</p>
        </div>
        <div>
            <label class="fld-label">Nombre completo <span class="text-zinc-400 font-normal">(opc.)</span></label>
            <input type="text" name="full_name" autocomplete="off" class="fld-input">
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="fld-label">Gamer tag <span class="text-zinc-400 font-normal">(opc.)</span></label>
                <input type="text" name="gamer_tag" autocomplete="off" class="fld-input">
            </div>
            <div>
                <label class="fld-label">Fecha de nacimiento <span class="text-zinc-400 font-normal">(opc.)</span></label>
                <input type="date" name="birth_date" class="fld-input">
            </div>
        </div>

        {{-- Llaves de recuperación (movidas desde "Completar orden") --}}
        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <label class="fld-label !mb-0">Llaves de recuperación <span class="text-zinc-400 font-normal">(opc.)</span></label>
                <button type="button" onclick="addStockKey()"
                        class="text-xs text-zinc-700 hover:text-zinc-900 px-2 py-1 rounded border border-zinc-200 hover:bg-zinc-50 transition">
                    + Agregar llave
                </button>
            </div>

            <div class="rounded-md border border-dashed border-zinc-300 bg-zinc-50/60 p-3 space-y-2">
                <textarea id="stock-keys-bulk" rows="2"
                        placeholder="Pegá varias llaves separadas por espacios, comas o saltos de línea."
                        class="fld-textarea fld-mono"></textarea>
                <div class="flex justify-end">
                    <button type="button" onclick="bulkAddStockKeys()"
                            class="rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium px-3 py-1.5 transition">
                        Agregar a la lista
                    </button>
                </div>
            </div>

            <div id="stock-keys-container" class="space-y-2"></div>

            <template id="stock-key-template">
                <div class="key-row flex items-center gap-2">
                    <input type="number" name="keys[__INDEX__][position]" min="1" max="20" placeholder="#"
                        class="fld-input fld-mono" style="width:72px;">
                    <input type="text" name="keys[__INDEX__][value]" maxlength="64" placeholder="Valor de la llave"
                        class="fld-input fld-mono flex-1">
                    <button type="button" onclick="removeStockKey(this)"
                            class="text-xs text-red-600 hover:text-red-800 px-2">×</button>
                </div>
            </template>
        </div>

        <div>
            <label class="fld-label">Notas <span class="text-zinc-400 font-normal">(opc.)</span></label>
            <textarea name="notes" rows="2" class="fld-textarea"></textarea>
        </div>

        <div class="flex justify-end gap-2 pt-2">
            <button type="button" onclick="document.getElementById('modal-create-stock').close()"
                    class="rounded-lg bg-white px-4 py-2 text-sm font-medium text-zinc-700 ring-1 ring-inset ring-zinc-200 hover:bg-zinc-50 transition">
                Cancelar
            </button>
            <button type="submit"
                    class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-700 transition">
                Agregar
            </button>
        </div>
    </form>
</dialog>

<script>
(function () {
    const PICKER_URL = @json(route('accounts.picker'));

    const selectedChildren = new Map();   // id -> email
    let madreTimer = null, hijasTimer = null;
    let madreAbort = null, hijasAbort = null;

    const $ = (id) => document.getElementById(id);
    const escAcc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[c]));

    async function fetchAccounts(type, term, signal) {
        const params = new URLSearchParams({ type });
        if (term) params.set('search', term);
        const res = await fetch(`${PICKER_URL}?${params}`, {
            headers: { 'Accept': 'application/json' },
            signal,
        });
        return res.json();
    }

    /* ---------------- MADRE (selección única) ---------------- */
    function onMadreInput() {
        clearTimeout(madreTimer);
        const term = $('madre-search').value.trim();
        madreTimer = setTimeout(() => runMadreSearch(term), 250);
    }

    async function runMadreSearch(term) {
        const box = $('madre-results');
        madreAbort?.abort();
        madreAbort = new AbortController();
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
                <span class="font-mono truncate max-w-[260px]">${escAcc(a.email)}</span>
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

    /* ---------------- HIJAS (selección múltiple + chips) ---------------- */
    function onHijasInput() {
        clearTimeout(hijasTimer);
        const term = $('hijas-search').value.trim();
        hijasTimer = setTimeout(() => runHijasSearch(term), 250);
    }

    async function runHijasSearch(term) {
        const box = $('hijas-results');
        hijasAbort?.abort();
        hijasAbort = new AbortController();
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
                <span class="font-mono truncate max-w-[200px]">${escAcc(email)}</span>
                <input type="hidden" name="children_ids[]" value="${escAcc(id)}">
                <button type="button" class="text-emerald-600 hover:text-red-600">×</button>`;
            chip.querySelector('button').addEventListener('click', () => removeHija(id));
            wrap.appendChild(chip);
        });
    }

    /* ---------------- Render genérico de resultados ---------------- */
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
                <span class="font-mono truncate">${escAcc(a.email)}</span>
                <span class="shrink-0 flex items-center gap-2 text-xs text-zinc-400">
                    ${a.region ? `<span>${escAcc(a.region)}</span>` : ''}
                    ${a.has_parent ? '<span class="text-amber-600">ya tiene madre</span>' : ''}
                </span>`;
            if (!disabled) row.addEventListener('click', () => onPick(a));
            box.appendChild(row);
        });
    }

    /* ---------------- Toggle según Tipo ---------------- */
    window.toggleParentAccount = function () {
        const type      = $('account_type')?.value;
        const madreWrap = $('madre-picker');
        const hijasWrap = $('hijas-picker');
        if (!madreWrap || !hijasWrap) return;

        const isHija  = type === 'HIJA';
        const isMadre = type === 'MADRE';

        madreWrap.classList.toggle('hidden', !isHija);
        hijasWrap.classList.toggle('hidden', !isMadre);

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

    /* ---------------- Listeners ---------------- */
    $('madre-search').addEventListener('input', onMadreInput);
    $('hijas-search').addEventListener('input', onHijasInput);

    // Cerrar resultados al hacer click fuera del picker correspondiente
    document.addEventListener('click', (e) => {
        if (!e.target.closest('#madre-picker')) $('madre-results')?.classList.add('hidden');
        if (!e.target.closest('#hijas-picker')) $('hijas-results')?.classList.add('hidden');
    });

    // Guard: si es HIJA, exigir madre seleccionada (el hidden no lo valida el browser)
    $('form-create-stock').addEventListener('submit', (e) => {
        if ($('account_type').value === 'HIJA' && !$('parent_account_id').value) {
            e.preventDefault();
            alert('Seleccioná la cuenta madre antes de guardar.');
        }
    });

    // Estado inicial
    window.toggleParentAccount();
})();
</script>