@php
    $itemColor = $item->fulfillmentColor();
    $cover     = $item->wooProduct?->image_url
              ?? $item->game?->products->pluck('image_url')->filter()->first();
    $activePO  = $item->activePurchaseOrder();

    // ── NUEVO: ¿la orden permite enviar? ──
    $processingStatus = config('services.woo.processing_status', 'processing');
    $orderStatus = ($order ?? null)?->wc_status ?? $item->order?->wc_status;
    $canAssign   = $orderStatus === $processingStatus;
@endphp
<div class="rounded-lg border border-zinc-200 bg-white overflow-hidden"
     data-item-card data-item-card-id="{{ $item->id }}">

    {{-- Header del item --}}
    <div class="px-5 py-4 border-b border-zinc-100 bg-zinc-50/50 flex items-start gap-4">
        @if ($cover)
            <img src="{{ $cover }}" alt=""
                 class="w-12 h-16 object-cover rounded shrink-0 bg-zinc-100"
                 onerror="this.style.display='none'">
        @endif
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="font-medium">{{ $item->game_title }}</span>
                @if ($item->is_preorden)
                    <span class="inline-flex items-center gap-1 rounded-full bg-violet-50 px-2 py-0.5 text-xs font-medium text-violet-700 ring-1 ring-inset ring-violet-600/20">
                        🕒 Preorden
                    </span>
                @endif
                @if ($item->is_pack)
                    <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700 ring-1 ring-inset ring-indigo-600/20">
                        📦 Pack
                    </span>
                @endif
            </div>
            @if ($item->game)
                <div class="text-xs text-zinc-500">Match por id → {{ $item->wc_product_id }}</div>
            @else
                <div class="text-xs text-amber-700">⚠ sin matchear al catálogo (wc_product_id: {{ $item->wc_product_id ?? '—' }})</div>
            @endif
            <div class="flex gap-3 mt-2 text-xs text-zinc-500 flex-wrap">
                <span><span class="text-zinc-400">Id:</span> <span class="font-mono">{{ $item->id }}</span></span>
                <span><span class="text-zinc-400">Plataforma:</span> <span class="font-mono">{{ $item->platform }}</span></span>
                <span><span class="text-zinc-400">Consola:</span> <span class="font-mono">{{ $item->console_model_raw ?? '—' }}</span></span>
                <span><span class="text-zinc-400">Norm:</span> <span class="font-mono">{{ $item->platform_normalized ?? '—' }}</span></span>
                <span><span class="text-zinc-400">Cantidad:</span> <span class="font-mono">{{ $item->quantity }}</span></span>
                @if (! is_null($item->price))
                    @php $currency = ($order ?? null)?->currency ?? $item->order?->currency; @endphp
                    <span>
                        <span class="text-zinc-400">Precio:</span>
                        @if (! is_null($item->price_sale))
                            <span class="font-mono line-through text-zinc-400">{{ $item->price }}</span>
                            <span class="font-mono text-emerald-700">{{ $item->price_sale }}</span>
                        @else
                            <span class="font-mono">{{ $item->price }}</span>
                        @endif
                        @if ($currency)
                            <span class="text-zinc-400">USD</span>
                            {{-- <span class="text-zinc-400">{{ $currency }}</span> --}}
                        @endif
                    </span>
                @endif
            </div>
            
        </div>
        <span class="inline-flex items-center gap-1.5 rounded-full bg-{{ $itemColor }}-50 px-2 py-0.5 text-xs font-medium text-{{ $itemColor }}-700 ring-1 ring-inset ring-{{ $itemColor }}-600/20 shrink-0">
            <span class="h-1.5 w-1.5 rounded-full bg-{{ $itemColor }}-500"></span>
            {{ str_replace('_', ' ', $item->fulfillment_status) }}
        </span>
        @if ($item->fulfillment_status === 'replaced')
            <span class="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-600 ring-1 ring-inset ring-zinc-300">
                Reemplazado por #{{ $item->replaced_by_item_id }}
            </span>
            {{-- y ocultá acá los botones de asignar / generar OC para este item --}}
        @elseif ($item->replacements->isNotEmpty())
            <div class="text-xs text-zinc-500 mt-1">
                Reemplaza: {{ $item->replacements->pluck('id')->map(fn ($id) => "#$id")->implode(', ') }}
            </div>
        @endif
        @if ($item->fulfillment_status !== 'delivered' && ! $item->account_id)
            <button type="button"
                onclick="deleteReplacementItem({{ $item->id }})"
                class="text-xs text-red-600 hover:text-red-800 underline">
                Eliminar
            </button>
        @endif
    </div>

    {{-- Cuerpo --}}
    <div class="px-5 py-4">

        @if ($item->fulfillment_status === 'pending')
            @if (! $canAssign)
                <div class="mb-3 rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-800">
                    Envío deshabilitado: la orden está en "{{ $orderStatus ?? '—' }}".
                    Solo se puede enviar cuando está en "{{ $processingStatus }}".
                </div>
            @endif

             @if ($item->is_pack)
                {{-- ────── ITEM DE PACK: stock secundario ────── --}}
                <div class="rounded-md border-2 border-indigo-200 bg-indigo-50/40 p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="text-xs font-medium uppercase tracking-wide text-indigo-700 mb-1">
                                Item de pack
                            </div>
                            <div class="text-sm text-zinc-700">
                                Requiere una cuenta especial (stock secundario).
                                Elegí una de las cuentas con cupo de pack disponible.
                            </div>
                        </div>

                        @if ($canAssign)
                            <button type="button"
                                    data-pack-assign
                                    data-candidates-url="{{ route('items.secondary-candidates', $item) }}"
                                    data-assign-url="{{ route('items.assign-secondary', $item) }}"
                                    data-item-id="{{ $item->id }}"
                                    data-item-title="{{ $item->game_title }}"
                                    class="shrink-0 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 whitespace-nowrap">
                                Asignar cuenta de pack
                            </button>
                        @else
                            <button type="button" disabled
                                    class="shrink-0 rounded-md bg-zinc-200 px-4 py-2 text-sm font-medium text-zinc-400 cursor-not-allowed">
                                Asignar
                            </button>
                        @endif
                    </div>
                </div>

            @elseif (! $result || $result->isEmpty())
                {{-- ────── SIN CANDIDATAS ────── --}}
                <div class="rounded-md bg-amber-50 border border-amber-200 px-4 py-3">
                    <div class="flex items-start gap-2">
                        <svg class="w-4 h-4 text-amber-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div class="flex-1">
                            <div class="font-medium text-amber-900 text-sm">Sin cuentas para asignar</div>
                            <div class="text-xs text-amber-800 mt-0.5">
                                {{ $result?->emptyReason ?? 'No se encontraron candidatas.' }}
                            </div>
                        </div>
                    </div>

                    {{-- Botón "Generar OC" o badge "OC ya creada" --}}
                    @if ($activePO)
                        <div class="mt-3 inline-flex items-center gap-2 rounded-md bg-white border border-amber-200 px-3 py-1.5 text-xs">
                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-{{ $activePO->statusColor() }}-100 text-{{ $activePO->statusColor() }}-700 font-medium">
                                OC #{{ $activePO->id }}
                            </span>
                            <span class="text-zinc-600">{{ $activePO->status }}</span>
                            @if ($activePO->arrival_date)
                                <span class="text-zinc-400">· llega {{ $activePO->arrival_date->format('d/m/Y') }}</span>
                            @endif
                        </div>
                    @else
                        <div class="flex gap-4">
                            
                            <div class="mt-3">
                                <button type="button"
                                        data-generate-po
                                        data-item-id="{{ $item->id }}"
                                        data-item-title="{{ $item->game_title }}"
                                        data-url="{{ route('items.purchase-order.store', $item) }}"
                                        class="rounded-md bg-amber-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-amber-700">
                                    Generar Orden de Compra
                                </button>
                            </div>
                            @php
                                // ¿Hay cuentas reseteables para este ítem? (misma fuente que la sección de sugerencias)
                                $resettable    = $resettableSuggestions ?? null;
                                $hasResettable = $resettable instanceof \Illuminate\Support\Collection
                                    ? $resettable->isNotEmpty()
                                    : false;

                                // Datos para el modal de selección
                                $resettableData = $hasResettable
                                    ? $resettable->map(fn ($acc) => [
                                        'id'       => $acc->id,
                                        'email'    => $acc->email,
                                        'platform' => $acc->platform,
                                        'region'   => $acc->region,
                                        'isDual'   => (bool) $acc->is_dual,
                                        'slots'    => $acc->maxAfterReset(),
                                        'source'   => $acc->stockRotationSource(),
                                        'ageDays'  => $acc->stockRotationAgeInDays(),
                                    ])->values()
                                    : collect();
                            @endphp

                            @if ($hasResettable)
                                <div class="mt-3">
                                    <button type="button"
                                        data-send-to-reset
                                        data-item-id="{{ $item->id }}"
                                        data-item-title="{{ $item->game_title }}"
                                        data-url="{{ route('items.send-to-reset', $item) }}"
                                        data-accounts='@json($resettableData)'
                                        class="inline-flex items-center gap-1.5 rounded-md bg-amber-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-amber-600">
                                        ↻ Enviar a resetear
                                    </button>
                                </div>
                            @endif

                            <button type="button"
                                    data-notify-game-change
                                    data-item-id="{{ $item->id }}"
                                    data-url="{{ route('items.notify-game-change', $item) }}"
                                    class="mt-3 inline-flex items-center gap-1.5 rounded-md bg-zinc-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-zinc-800">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 0 0 2.22 0L21 8M5 19h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2z"/>
                                </svg>
                                Enviar noti cambio de juego
                            </button>
                        </div>
                    @endif
                </div>

            @else
                @php
                    $best   = $result->best();
                    $others = $result->others();
                @endphp

                {{-- ────── CANDIDATA SUGERIDA ────── --}}
                <div class="rounded-md border-2 border-emerald-300 bg-emerald-50/30 p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                <span class="text-xs font-medium uppercase tracking-wide text-emerald-700">
                                    Cuenta sugerida
                                </span>
                                @if ($best->isTimeBlocked)
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">
                                        ⚠ bloqueada temporalmente
                                    </span>
                                @endif
                                @if ($best->isPostReset)
                                    <span class="inline-flex items-center rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-700">
                                        post-reset
                                    </span>
                                @endif
                            </div>

                            <div class="font-mono text-sm font-semibold">{{ $best->account->email }}</div>
                            <div class="text-xs text-zinc-600 mt-1">{{ $best->selectionReason }}</div>

                            @if ($best->blockReason)
                                <div class="text-xs text-amber-700 mt-1.5">ⓘ {{ $best->blockReason }}</div>
                            @endif

                            <div class="flex flex-wrap gap-2 mt-2">
                                <span class="text-xs px-1.5 py-0.5 rounded bg-white border border-zinc-200 text-zinc-700 font-mono">{{ $best->account->platform }}</span>
                                <span class="text-xs px-1.5 py-0.5 rounded bg-white border border-zinc-200 text-zinc-700">{{ $best->account->region }}</span>
                                <span class="text-xs px-1.5 py-0.5 rounded bg-white border border-zinc-200 text-zinc-700">{{ $best->account->account_type }}</span>
                                @if ($best->account->gamer_tag)
                                    <span class="text-xs px-1.5 py-0.5 rounded bg-white border border-zinc-200 text-zinc-700 font-mono">{{ $best->account->gamer_tag }}</span>
                                @endif
                            </div>
                        </div>

                        @if ($canAssign)
                            <form method="POST" action="{{ route('items.assign', $item) }}"
                                class="flex flex-col items-end gap-2 shrink-0"
                                data-assign-form
                                data-assign-confirm="¿Asignar {{ $best->account->email }} a este ítem?">
                                @csrf
                                <input type="hidden" name="account_id" value="{{ $best->account->id }}">
                                <input type="text" name="who_delivered" required maxlength="100" value="{{ auth()->user()->email }}"
                                    placeholder="Quien entrega"
                                    class="rounded-md border-zinc-300 text-xs px-2 py-1 w-32 hidden">
                                <button type="submit"
                                        class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 whitespace-nowrap disabled:opacity-60 disabled:cursor-wait">
                                    <span data-btn-label>Asignar y enviar</span>
                                    <span data-btn-loading class="hidden">⏳ Asignando…</span>
                                </button>
                            </form>
                        @else
                            <div class="shrink-0 text-right">
                                <button type="button" disabled
                                        class="rounded-md bg-zinc-200 px-4 py-2 text-sm font-medium text-zinc-400 cursor-not-allowed whitespace-nowrap">
                                    Enviar
                                </button>
                            </div>
                        @endif
                    </div>
                </div>

                @if (count($others) > 0)
                    <details class="mt-3 group">
                        <summary class="cursor-pointer text-xs text-zinc-600 hover:text-zinc-900 select-none">
                            Ver {{ count($others) }} cuenta(s) alternativa(s)
                            <span class="text-zinc-400 group-open:hidden">▾</span>
                            <span class="text-zinc-400 hidden group-open:inline">▴</span>
                        </summary>
                        <div class="mt-2 space-y-2">
                            @foreach ($others as $c)
                                <div class="flex items-center justify-between gap-3 p-3 rounded-md border border-zinc-200 hover:bg-zinc-50">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-0.5 flex-wrap">
                                            <span class="font-mono text-sm">{{ $c->account->email }}</span>
                                            <span class="text-xs px-1.5 py-0.5 rounded bg-zinc-100 text-zinc-700 font-mono">{{ $c->account->platform }}</span>
                                            @if ($c->isTimeBlocked)
                                                <span class="text-xs px-1.5 py-0.5 rounded bg-amber-100 text-amber-700">bloqueada</span>
                                            @endif
                                        </div>
                                        <div class="text-xs text-zinc-500">{{ $c->selectionReason }}</div>
                                        @if ($c->blockReason)
                                            <div class="text-xs text-amber-700 mt-0.5">{{ $c->blockReason }}</div>
                                        @endif
                                    </div>
                                    @if ($canAssign)
                                        <form method="POST" action="{{ route('items.assign', $item) }}"
                                            class="flex items-center gap-2 shrink-0"
                                            data-assign-form
                                            data-assign-confirm="¿Asignar {{ $c->account->email }} a este ítem?">
                                            @csrf
                                            <input type="hidden" name="account_id" value="{{ $c->account->id }}">
                                            <input type="text" name="who_delivered" required maxlength="100"
                                                placeholder="Quien entrega" value="{{ auth()->user()->email }}"
                                                class="rounded-md border-zinc-300 text-xs px-2 py-1 w-28 hidden">
                                            <button type="submit"
                                                    class="rounded-md bg-white px-3 py-1.5 text-xs font-medium text-zinc-700 ring-1 ring-inset ring-zinc-300 hover:bg-zinc-100 disabled:opacity-60 disabled:cursor-wait">
                                                <span data-btn-label>Usar esta</span>
                                                <span data-btn-loading class="hidden">⏳</span>
                                            </button>
                                        </form>
                                    @else
                                        <button type="button" disabled
                                                class="rounded-md bg-zinc-100 px-3 py-1.5 text-xs font-medium text-zinc-400 cursor-not-allowed shrink-0">
                                            Enviar
                                        </button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </details>
                @endif
            @endif

        @elseif ($item->account || ($item->is_pack && $item->secondaryAssignments->isNotEmpty()))

            @if ($item->is_pack)
                {{-- ────── PACK ASIGNADO · varias cuentas (stock secundario) ────── --}}
                <div class="space-y-2">
                    <div class="text-xs font-medium uppercase tracking-wide text-indigo-700">
                        Cuentas del pack ({{ $item->secondaryAssignments->count() }})
                    </div>
                    @foreach ($item->secondaryAssignments as $sa)
                        @php $cover = $sa->account?->coverProduct(); @endphp
                        <div class="rounded-md border border-indigo-200 bg-indigo-50/40 px-3 py-2">

                            {{-- Cabecera: imagen + nombre del producto --}}
                            <div class="flex items-start gap-3 mb-2">
                                @if ($cover?->image_url)
                                    <img src="{{ $cover->image_url }}" alt=""
                                        class="w-10 h-14 object-cover rounded shrink-0 bg-zinc-100"
                                        onerror="this.style.display='none'">
                                @endif
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium break-words">
                                        {{ $cover?->name ?? $sa->account?->game?->canonical_name ?? '—' }}
                                    </div>
                                    <div class="text-xs text-zinc-500 font-mono">
                                        {{ $sa->platform }} · slot secundario #{{ $sa->slot_number }}
                                        @if ($sa->assigned_at) · {{ $sa->assigned_at->format('d/m/Y') }} @endif
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <div class="text-xs text-zinc-500 mb-0.5">Email cuenta</div>
                                    <div class="font-mono break-all">{{ $sa->account?->email ?? '—' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-zinc-500 mb-0.5">Password</div>
                                    <div class="font-mono break-all">{{ $sa->account?->password ?? '—' }}</div>
                                </div>
                                @if ($sa->account?->mail_email)
                                    <div>
                                        <div class="text-xs text-zinc-500 mb-0.5">Email del correo</div>
                                        <div class="font-mono break-all">{{ $sa->account->mail_email }}</div>
                                    </div>
                                @endif
                                @if ($sa->account?->mail_password)
                                    <div>
                                        <div class="text-xs text-zinc-500 mb-0.5">Password del correo</div>
                                        <div class="font-mono break-all">{{ $sa->account->mail_password }}</div>
                                    </div>
                                @endif
                                @if ($sa->key_value)
                                    <div class="col-span-2">
                                        <div class="text-xs text-zinc-500 mb-0.5">Llave entregada</div>
                                        <div class="font-mono break-all">{{ $sa->key_value }}</div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                    <div class="text-xs text-zinc-500">
                        Entregada por <span class="font-medium">{{ $item->who_delivered ?? '—' }}</span>
                    </div>
                </div>

            @else
                {{-- ────── ITEM ASIGNADO · una cuenta (normal) ────── --}}
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <div class="text-xs text-zinc-500 mb-0.5">Email cuenta</div>
                        <div class="font-mono break-all">{{ $item->account->email }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-zinc-500 mb-0.5">Password</div>
                        <div class="font-mono break-all">{{ $item->account->password }}</div>
                    </div>
                    @if ($item->account->mail_email)
                        <div>
                            <div class="text-xs text-zinc-500 mb-0.5">Email del correo</div>
                            <div class="font-mono break-all">{{ $item->account->mail_email }}</div>
                        </div>
                    @endif
                    @if ($item->account->mail_password)
                        <div>
                            <div class="text-xs text-zinc-500 mb-0.5">Password del correo</div>
                            <div class="font-mono break-all">{{ $item->account->mail_password }}</div>
                        </div>
                    @endif
                </div>

                @if ($item->account->keys->isNotEmpty())
                    <div class="mt-3">
                        <div class="text-xs text-zinc-500 mb-1">Llaves</div>
                        <div class="flex flex-wrap gap-1">
                            @foreach ($item->account->keys as $k)
                                <span class="font-mono text-xs px-2 py-1 rounded bg-zinc-100 {{ $k->used_at ? 'line-through text-zinc-400' : '' }}">{{ $k->key_value }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="text-xs text-zinc-500 mt-3">Llave entregada</div>
                <div class="font-mono text-sm">{{ $item->assignment?->key_value ?? '—' }}</div>

                <div class="mt-3 text-xs text-zinc-500">
                    Entregada por <span class="font-medium">{{ $item->who_delivered ?? '—' }}</span>
                </div>
            @endif

        @endif

    </div>
</div>
