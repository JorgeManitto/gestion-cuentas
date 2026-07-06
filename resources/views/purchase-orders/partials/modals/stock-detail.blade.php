{{-- ==================== MODAL: DETALLE CUENTA STOCK ==================== --}}
<dialog id="modal-stock-detail" class="rounded-xl p-0 backdrop:bg-zinc-900/40 backdrop:backdrop-blur-sm w-full max-w-lg">
    <div class="p-5 space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold">Detalle de cuenta</h2>
            <button type="button" onclick="document.getElementById('modal-stock-detail').close()"
                    class="text-zinc-400 hover:text-zinc-700 text-xl leading-none">✕</button>
        </div>

        @if (isset($acc))
            <a href="{{ route('accounts.edit', $acc->id) }}" class="text-blue-500 hover:text-blue-700" target="_blank">Editar</a>
        @endif
        <div class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
            <div class="col-span-2">
                <div class="fld-label !mb-0.5">Email</div>
                <div id="sd-email" class="font-mono break-all"></div>
            </div>
            <div class="col-span-2">
                <div class="fld-label !mb-0.5">Password</div>
                <div id="sd-password" class="font-mono break-all"></div>
            </div>

            <div>
                <div class="fld-label !mb-0.5">Plataforma</div>
                <div id="sd-platform" class="font-mono"></div>
            </div>
            <div>
                <div class="fld-label !mb-0.5">Consola</div>
                <div id="sd-console" class="font-mono"></div>
            </div>

            <div>
                <div class="fld-label !mb-0.5">Región</div>
                <div id="sd-region"></div>
            </div>
            <div>
                <div class="fld-label !mb-0.5">Tipo</div>
                <div id="sd-type"></div>
            </div>

            <div>
                <div class="fld-label !mb-0.5">DUAL</div>
                <div id="sd-dual"></div>
            </div>
            <div>
                <div class="fld-label !mb-0.5">Estado</div>
                <div id="sd-status"></div>
            </div>

            <div>
                <div class="fld-label !mb-0.5">Gamer tag</div>
                <div id="sd-gamer-tag"></div>
            </div>
            <div>
                <div class="fld-label !mb-0.5">Fecha de nacimiento</div>
                <div id="sd-birth" class="font-mono"></div>
            </div>

            <div>
                <div class="fld-label !mb-0.5">Mail asociado</div>
                <div id="sd-mail-email" class="font-mono break-all"></div>
            </div>
            <div>
                <div class="fld-label !mb-0.5">Pass del mail</div>
                <div id="sd-mail-password" class="font-mono break-all"></div>
            </div>

            <div>
                <div class="fld-label !mb-0.5">Fecha de compra</div>
                <div id="sd-purchased" class="font-mono"></div>
            </div>
        </div>

        <div>
            <div class="fld-label !mb-1">Llaves de recuperación</div>
            <div id="sd-keys" class="flex flex-wrap gap-1.5"></div>
            <div id="sd-keys-empty" class="hidden text-xs text-zinc-500">Sin llaves cargadas.</div>
        </div>

        <div>
            <div class="fld-label !mb-1">Notas</div>
            <div id="sd-notes" class="text-sm text-zinc-600 whitespace-pre-line"></div>
        </div>

        <div class="flex justify-end pt-2">
            <button type="button" onclick="document.getElementById('modal-stock-detail').close()"
                    class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-700 transition">
                Cerrar
            </button>
        </div>
    </div>
</dialog>
