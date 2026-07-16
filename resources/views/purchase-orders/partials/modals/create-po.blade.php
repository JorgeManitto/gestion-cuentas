{{-- ==================== MODAL: CREAR OC ==================== --}}
<dialog id="modal-create-po" class="rounded-xl p-0 backdrop:bg-zinc-900/40 backdrop:backdrop-blur-sm w-full max-w-md">
    <form method="POST" action="{{ route('purchase-orders.store') }}" class="p-5 space-y-4">
        @csrf
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold">Nueva Orden de Compra</h2>
            <button type="button" onclick="document.getElementById('modal-create-po').close()"
                    class="text-zinc-400 hover:text-zinc-700 text-xl leading-none">✕</button>
        </div>

        <div>
            <label class="fld-label">Juego</label>

            <div id="po-selected-game-display">
                <div class="text-sm text-zinc-500 italic">Ningún juego seleccionado</div>
            </div>

            <button type="button" onclick="openGamePicker()"
                    class="mt-2 w-full rounded-lg bg-zinc-900 px-3 py-2 text-sm font-medium text-white hover:bg-zinc-700 transition">
                Seleccionar juego
            </button>

            <input type="hidden" name="game_id" id="po-game-id">
            <input type="hidden" name="game_title" id="po-game-title">
        </div>

        <div>
            <label class="fld-label">Plataforma <span class="text-red-500">*</span></label>
            <select name="platform" id="po-platform" required class="fld-select">
                <option value="">Selecciona…</option>
                <option value="PS5">PS5</option>
                <option value="PS4">PS4</option>
                <option value="XBOX_SERIES">Xbox Series</option>
                <option value="XBOX_ONE">Xbox One</option>
                <option value="SWITCH_2">Switch 2</option>
                <option value="SWITCH">Switch</option>
                <option value="STEAM">Steam / PC</option>
            </select>

            <label class="flex items-center gap-2 text-sm pt-2">
                <input type="checkbox" name="is_dual" value="1" id="po-is-dual" class="fld-check">
                <span>Es cuenta DUAL</span>
            </label>
        </div>

        <div>
            <label class="fld-label">Región <span class="text-zinc-400 font-normal">(opcional)</span></label>
            <select name="region" class="fld-select">
                <option value="">—</option>
                @foreach (config('regions.list') as $r)
                    <option value="{{ $r }}">{{ $r }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="fld-label">Nota <span class="text-zinc-400 font-normal">(opcional)</span></label>
            <textarea name="notes_region" rows="2" class="fld-textarea"
                      placeholder="Nota que aparece debajo de la región"></textarea>
        </div>

        <div>
            <label class="fld-label">Tipo</label>
            <select name="account_type" class="fld-select">
                @foreach (['INDEPENDIENTE', 'MADRE', 'HIJA'] as $t)
                    <option value="{{ $t }}" @selected($t === 'INDEPENDIENTE')>{{ $t }}</option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="fld-label">Cantidad</label>
                <input type="number" name="quantity" min="1" value="1" required class="fld-input fld-mono">
            </div>
            <div>
                <label class="fld-label">Fecha llegada</label>
                <input type="date" name="arrival_date" class="fld-input">
            </div>
        </div>

        <div class="flex justify-end gap-2 pt-2">
            <button type="button" onclick="document.getElementById('modal-create-po').close()"
                    class="rounded-lg bg-white px-4 py-2 text-sm font-medium text-zinc-700 ring-1 ring-inset ring-zinc-200 hover:bg-zinc-50 transition">
                Cancelar
            </button>
            <button type="submit"
                    class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-700 transition">
                Crear
            </button>
        </div>
    </form>
</dialog>
