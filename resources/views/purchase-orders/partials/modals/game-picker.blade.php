{{-- ==================== MODAL: PICKER DE JUEGO ==================== --}}
<dialog id="modal-game-picker" class="rounded-xl p-0 backdrop:bg-zinc-900/50 w-full max-w-5xl">
    <div class="flex flex-col max-h-[90vh]">
        <div class="px-6 py-4 border-b border-zinc-200 flex items-center justify-between gap-4">
            <h3 class="text-lg font-semibold">Seleccionar juego</h3>
            <button type="button" onclick="closeGamePicker()"
                    class="text-zinc-400 hover:text-zinc-900 text-2xl leading-none">×</button>
        </div>

        <div class="px-6 py-3 border-b border-zinc-100">
            <input type="text" id="gp-search" placeholder="Buscar por nombre…"
                   class="w-full rounded-md border-zinc-300 text-sm">
        </div>

        <div class="flex-1 overflow-y-auto p-6">
            <div id="gp-grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4"></div>
            <div id="gp-empty" class="hidden text-center py-12 text-sm text-zinc-500">No se encontraron juegos.</div>
            <div id="gp-loading" class="hidden text-center py-12 text-sm text-zinc-500">Cargando…</div>
        </div>

        <div class="px-6 py-3 border-t border-zinc-200 flex items-center justify-between text-sm">
            <div id="gp-count" class="text-zinc-500"></div>
            <div class="flex items-center gap-2">
                <button type="button" id="gp-prev" onclick="changeGamePickerPage(-1)"
                        class="px-3 py-1 rounded border border-zinc-200 text-zinc-700 hover:bg-zinc-50 disabled:opacity-40 disabled:cursor-not-allowed">← Anterior</button>
                <span id="gp-page" class="font-mono text-xs text-zinc-600 min-w-[60px] text-center"></span>
                <button type="button" id="gp-next" onclick="changeGamePickerPage(1)"
                        class="px-3 py-1 rounded border border-zinc-200 text-zinc-700 hover:bg-zinc-50 disabled:opacity-40 disabled:cursor-not-allowed">Siguiente →</button>
            </div>
        </div>
    </div>
</dialog>
