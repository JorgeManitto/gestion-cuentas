{{-- ==================== MODAL: COMPLETAR OC ==================== --}}
<dialog id="modal-complete-po" class="rounded-xl p-0 backdrop:bg-zinc-900/40 backdrop:backdrop-blur-sm w-full max-w-lg">
    <form method="POST" id="form-complete-po" class="p-5 space-y-4">
        @csrf
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold">Completar orden</h2>
            <button type="button" onclick="document.getElementById('modal-complete-po').close()"
                    class="text-zinc-400 hover:text-zinc-700 text-xl leading-none">✕</button>
        </div>

        <div id="complete-po-info" class="text-sm text-zinc-600 rounded-lg bg-zinc-50 ring-1 ring-inset ring-zinc-100 px-3 py-2"></div>

        <div class="rounded-lg border border-zinc-200 bg-zinc-50/60 p-4 space-y-3">
            <div class="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-zinc-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                </svg>
                Filtrar cuentas
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="fld-label">Buscar por email</label>
                    <input type="text" id="complete-filter-email" placeholder="email…"
                        class="fld-input fld-mono" autocomplete="off">
                </div>
                <div>
                    <label class="fld-label">Región</label>
                    <select id="complete-filter-region" class="fld-select">
                        <option value="">Todas</option>
                        @foreach ($stockForComplete->pluck('region')->filter()->unique()->sort()->values() as $r)
                            <option value="{{ $r }}">{{ $r }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="border-t border-zinc-200 pt-3">
                <label class="fld-label">Cuenta de Stock <span class="text-red-500">*</span></label>
                <select name="account_id" required class="fld-select bg-white">
                    <option value="">Selecciona una cuenta…</option>
                    @foreach ($stockForComplete as $acc)
                        <option value="{{ $acc->id }}"
                            data-platform="{{ $acc->platform }}"
                                data-region="{{ $acc->region }}"
                                data-keys="{{ json_encode($acc->keys->map(fn ($k) => ['position' => $k->position, 'value' => $k->key_value])->values()) }}">
                            {{ $acc->email }} — {{ $acc->platform }}{{ $acc->region ? ' / ' . $acc->region : '' }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-zinc-500">Solo se muestran cuentas activas sin juego asignado.</p>
                <div id="complete-existing-keys"
                    class="hidden mt-2 rounded-md bg-amber-50 ring-1 ring-inset ring-amber-200 px-3 py-2 text-xs text-amber-800">
                    <div class="font-medium mb-1">Esta cuenta ya tiene estas llaves:</div>
                    <div id="complete-existing-keys-list" class="flex flex-wrap gap-1.5"></div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="fld-label">Plataforma <span class="text-red-500">*</span></label>
                <select name="platform" id="complete-platform" required class="fld-select">
                    <option value="">Selecciona…</option>
                    @foreach (['PS5','PS4','XBOX_SERIES','XBOX_ONE','SWITCH_2','SWITCH','STEAM'] as $p)
                        <option value="{{ $p }}">{{ $p }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="fld-label">Fecha de compra <span class="text-red-500">*</span></label>
                <input type="date" name="purchase_date" required value="{{ now()->format('Y-m-d') }}" class="fld-input">
            </div>
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_dual" value="1" id="complete-is-dual" class="fld-check">
            <span>Es cuenta DUAL</span>
        </label>

        <div class="hidden">
            <label class="fld-label">Monto USD <span class="text-zinc-400 font-normal">(opc.)</span></label>
            <input type="number" name="cost_usd" step="0.01" min="0" class="fld-input fld-mono">
        </div>



        <div class="flex justify-end gap-2 pt-2">
            <button type="button" onclick="document.getElementById('modal-complete-po').close()"
                    class="rounded-lg bg-white px-4 py-2 text-sm font-medium text-zinc-700 ring-1 ring-inset ring-zinc-200 hover:bg-zinc-50 transition">
                Cancelar
            </button>
            <button type="submit"
                    class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 transition">
                Completar
            </button>
        </div>
    </form>
</dialog>
