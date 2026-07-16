{{-- ==================== MODAL: DETALLE CUENTA STOCK ==================== --}}
<dialog id="modal-stock-detail"
        class="rounded-xl p-0 backdrop:bg-zinc-900/50 backdrop:backdrop-blur-sm w-full max-w-xl">

    {{-- ---------- Cabecera ---------- --}}
    <div class="sticky top-0 z-10 border-b border-zinc-200 bg-white/95 backdrop-blur px-5 py-4">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="fld-label !mb-0.5">Cuenta de stock</div>
                <div class="flex items-center gap-2">
                    <span id="sd-email" class="font-mono text-sm font-medium text-zinc-900 break-all"></span>
                    <button type="button" onclick="copyField(this,'sd-email')"
                            title="Copiar email"
                            class="shrink-0 text-zinc-400 hover:text-zinc-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m11.25 5.5H12.75" />
                        </svg>
                    </button>
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-1.5">
                    <span id="sd-console-badge"  class="hidden items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset"></span>
                    <span id="sd-status-badge"   class="hidden items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"></span>
                    <span id="sd-type-badge"     class="hidden items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"></span>
                    <span id="sd-dual-badge"     class="hidden items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20">DUAL</span>
                </div>
            </div>
            <button type="button" onclick="document.getElementById('modal-stock-detail').close()"
                    class="shrink-0 text-zinc-400 hover:text-zinc-700 text-xl leading-none">✕</button>
        </div>
    </div>

    <div class="p-5 space-y-5">

        {{-- ---------- Acceso ---------- --}}
        <section>
            <div class="fld-label !mb-2 uppercase tracking-wide text-[11px] text-zinc-400">Acceso</div>
            <div class="rounded-lg border border-zinc-200 divide-y divide-zinc-100">
                <div class="flex items-center gap-2 px-3 py-2">
                    <span class="w-24 shrink-0 text-xs text-zinc-500">Password</span>
                    <span id="sd-password" class="flex-1 font-mono text-sm break-all"></span>
                    <button type="button" onclick="copyField(this,'sd-password')" title="Copiar password"
                            class="shrink-0 text-zinc-400 hover:text-zinc-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m11.25 5.5H12.75" /></svg>
                    </button>
                </div>
            </div>
        </section>

        {{-- ---------- Consola / Región ---------- --}}
        <section>
            <div class="fld-label !mb-2 uppercase tracking-wide text-[11px] text-zinc-400">Consola y región</div>
            <div class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div>
                    <div class="text-xs text-zinc-500 mb-0.5">Plataforma</div>
                    <div id="sd-platform" class="font-mono"></div>
                </div>
                <div>
                    <div class="text-xs text-zinc-500 mb-0.5">Consola</div>
                    <div id="sd-console" class="font-mono"></div>
                </div>
                <div>
                    <div class="text-xs text-zinc-500 mb-0.5">Región</div>
                    <div id="sd-region"></div>
                </div>
                <div>
                    <div class="text-xs text-zinc-500 mb-0.5">Gamer tag</div>
                    <div id="sd-gamer-tag"></div>
                </div>
            </div>
        </section>

        {{-- ---------- Correo asociado ---------- --}}
        <section>
            <div class="fld-label !mb-2 uppercase tracking-wide text-[11px] text-zinc-400">Correo asociado</div>
            <div class="rounded-lg border border-zinc-200 divide-y divide-zinc-100">
                <div class="flex items-center gap-2 px-3 py-2">
                    <span class="w-24 shrink-0 text-xs text-zinc-500">Mail</span>
                    <span id="sd-mail-email" class="flex-1 font-mono text-sm break-all"></span>
                    <button type="button" onclick="copyField(this,'sd-mail-email')" title="Copiar mail"
                            class="shrink-0 text-zinc-400 hover:text-zinc-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m11.25 5.5H12.75" /></svg>
                    </button>
                </div>
                <div class="flex items-center gap-2 px-3 py-2">
                    <span class="w-24 shrink-0 text-xs text-zinc-500">Password</span>
                    <span id="sd-mail-password" class="flex-1 font-mono text-sm break-all"></span>
                    <button type="button" onclick="copyField(this,'sd-mail-password')" title="Copiar password del mail"
                            class="shrink-0 text-zinc-400 hover:text-zinc-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m11.25 5.5H12.75" /></svg>
                    </button>
                </div>
            </div>
        </section>

        {{-- ---------- Otros datos ---------- --}}
        <section>
            <div class="fld-label !mb-2 uppercase tracking-wide text-[11px] text-zinc-400">Otros datos</div>
            <div class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div>
                    <div class="text-xs text-zinc-500 mb-0.5">Fecha de nacimiento</div>
                    <div id="sd-birth" class="font-mono"></div>
                </div>
                <div>
                    <div class="text-xs text-zinc-500 mb-0.5">Fecha de compra</div>
                    <div id="sd-purchased" class="font-mono"></div>
                </div>
            </div>
        </section>

        {{-- ---------- Llaves ---------- --}}
        <section>
            <div class="fld-label !mb-2 uppercase tracking-wide text-[11px] text-zinc-400">Llaves de recuperación</div>
            <div id="sd-keys" class="flex flex-wrap gap-1.5"></div>
            <div id="sd-keys-empty" class="hidden text-xs text-zinc-500">Sin llaves cargadas.</div>
        </section>

        {{-- ---------- Notas ---------- --}}
        <section id="sd-notes-section">
            <div class="fld-label !mb-2 uppercase tracking-wide text-[11px] text-zinc-400">Notas</div>
            <div id="sd-notes" class="rounded-lg bg-zinc-50 p-3 text-sm text-zinc-600 whitespace-pre-line"></div>
        </section>
    </div>

    {{-- ---------- Pie ---------- --}}
    <div class="sticky bottom-0 flex items-center justify-between gap-2 border-t border-zinc-200 bg-white px-5 py-3">
        <a id="sd-edit-link" href="#" target="_blank"
           class="inline-flex items-center gap-1.5 text-sm font-medium text-blue-600 hover:text-blue-800">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
            </svg>
            Editar cuenta
        </a>
        <button type="button" onclick="document.getElementById('modal-stock-detail').close()"
                class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-700 transition">
            Cerrar
        </button>
    </div>
</dialog>
