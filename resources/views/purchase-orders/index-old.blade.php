@extends('layouts.app')
@section('title', 'Compras')

@section('content')
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
</style>
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-zinc-900">Compras</h1>
        <p class="text-zinc-600">Gestiona Órdenes de Compra y Stock de Cuentas</p>

        {{-- Tabs --}}
        <div class="mt-4 inline-flex gap-1 rounded-lg border border-zinc-200 bg-zinc-50 p-1">
            <a href="{{ route('purchase-orders.index', ['tab' => 'ordenes']) }}"
            class="px-4 py-1.5 rounded-md text-sm font-medium transition {{ $tab === 'ordenes' ? 'bg-white shadow-sm text-zinc-900' : 'text-zinc-600 hover:text-zinc-900' }}">
                Órdenes de Compra
            </a>
            <a href="{{ route('purchase-orders.index', ['tab' => 'stock']) }}"
            class="px-4 py-1.5 rounded-md text-sm font-medium transition {{ $tab === 'stock' ? 'bg-white shadow-sm text-zinc-900' : 'text-zinc-600 hover:text-zinc-900' }}">
                Stock de Cuentas
            </a>
        </div>
    </div>

    {{-- Flash messages --}}
    @if (session('success'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="rounded-md border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800">
            {{ $errors->first() }}
        </div>
    @endif

    @if ($tab === 'ordenes')
        {{-- ==================== TAB: ÓRDENES ==================== --}}

        {{-- Stats --}}
        <div class="grid grid-cols-3 gap-3">
            @foreach ([
                'pending'   => ['Pendientes', 'amber'],
                'purchased' => ['Compradas',  'blue'],
                'received'  => ['Recibidas',  'emerald'],
            ] as $key => [$label, $color])
                <a href="{{ route('purchase-orders.index', ['tab' => 'ordenes', 'status' => $key]) }}"
                class="group relative block overflow-hidden rounded-xl border border-zinc-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md {{ request('status') === $key ? 'ring-2 ring-'.$color.'-500/40' : '' }}">
                    <span class="absolute inset-y-0 left-0 w-1 bg-{{ $color }}-500"></span>
                    <div class="flex items-center justify-between pl-2">
                        <span class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ $label }}</span>
                        <span class="h-2 w-2 rounded-full bg-{{ $color }}-500"></span>
                    </div>
                    <div class="mt-2 pl-2 font-mono text-3xl font-semibold tabular-nums text-zinc-900">{{ $stats[$key] }}</div>
                </a>
            @endforeach
        </div>

        {{-- Toolbar --}}
        <div class="flex justify-end">
            <button type="button" onclick="document.getElementById('modal-create-po').showModal()"
                    class="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800">
                + Agregar orden
            </button>
        </div>

        {{-- Tabla --}}
        <div class="overflow-x-auto rounded-lg border border-zinc-200 bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-zinc-50 border-b border-zinc-200 text-xs uppercase tracking-wide text-zinc-600">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium">OC</th>
                        <th class="px-4 py-2 text-left font-medium">Juego</th>
                        <th class="px-4 py-2 text-left font-medium">Plataforma</th>
                        <th class="px-4 py-2 text-left font-medium">Cant.</th>
                        <th class="px-4 py-2 text-left font-medium">Origen</th>
                        <th class="px-4 py-2 text-left font-medium">Status</th>
                        <th class="px-4 py-2 text-left font-medium">Creada</th>
                        <th class="px-4 py-2 text-left font-medium">Llegada</th>
                       
                        <th class="px-4 py-2 text-right font-medium">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse ($orders as $po)
                        @php
                            // Portada resuelta igual que en accounts:
                            //   1) producto del juego para la plataforma de la OC
                            //   2) primer producto del juego
                            //   3) lookup por título en WooProduct (solo OC sin juego, lo arma el controller)
                            //   4) placeholder
                            $product = $po->game
                                ? ($po->game->productForPlatform($po->platform) ?? $po->game->products->first())
                                : null;

                            $cover = $product?->image_url
                                ?? ($coversByTitle[$po->game_title] ?? null)
                                ?? asset('images/default-game-gray.svg');
                        @endphp
                        <tr class="hover:bg-zinc-50">
                            <td class="px-4 py-2.5 font-mono text-xs">#{{ $po->id }}</td>
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-3">
                                    <button type="button"
                                            onclick="openPreview('{{ e($cover) }}', '{{ e($po->game_title) }}')"
                                            class="block h-14 w-10 shrink-0 overflow-hidden rounded border bg-zinc-100 hover:opacity-80 transition">
                                        <img src="{{ $cover }}" alt="{{ $po->game_title }}"
                                             class="h-full w-full object-cover" loading="lazy">
                                    </button>
                                    <div class="min-w-0">
                                        <div class="font-medium max-w-[260px] truncate">{{ $po->game_title }}</div>
                                        @if ($po->game)
                                            <div class="text-xs text-zinc-500 truncate">→ {{ $po->game->canonical_name }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-2.5">
                                <span class="font-mono text-xs px-1.5 py-0.5 rounded bg-zinc-100">{{ $po->platform }}</span>
                                @if ($po->console_model)
                                    <div class="text-xs text-zinc-500 mt-0.5 font-mono">{{ $po->console_model }}</div>
                                @endif
                                @if ($po->region && $po->region !== 'sin especificar')
                                    <div class="text-xs text-zinc-500 mt-0.5">{{ $po->region }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 font-mono text-xs">{{ $po->quantity }}</td>
                            <td class="px-4 py-2.5">
                                @if ($po->orderItem)
                                    <a href="{{ route('orders.show', $po->orderItem->order_id) }}"
                                       class="text-xs font-mono hover:underline">
                                        Order #{{ $po->orderItem->order->wc_order_id }}
                                    </a>
                                @else
                                    <span class="text-xs text-zinc-400">manual</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5">
                                @php $color = $po->statusColor(); @endphp
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-{{ $color }}-50 px-2 py-0.5 text-xs font-medium text-{{ $color }}-700 ring-1 ring-inset ring-{{ $color }}-600/20">
                                    <span class="h-1.5 w-1.5 rounded-full bg-{{ $color }}-500"></span>
                                    {{ $po->status }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5 font-mono text-xs text-zinc-500">{{ $po->created_at->format('Y-m-d') }}</td>
                            <td class="px-4 py-2.5 font-mono text-xs text-zinc-500">{{ $po->arrival_date?->format('Y-m-d') ?? '—' }}</td>
                            
                            <td class="px-4 py-2.5">
                                <div class="flex items-center justify-end gap-1">
                                    @if ($po->status !== 'received')
                                        @php
                                            $poData = [
                                                'id'         => $po->id,
                                                'game_title' => $po->game_title,
                                                'platform'   => $po->platform,
                                                'region'     => $po->region,
                                                'is_dual'    => (bool) $po->is_dual,
                                            ];
                                        @endphp
                                        <button type="button"
                                                onclick='openComplete(@json($poData))'
                                                title="Completar con cuenta de stock"
                                                class="inline-flex items-center justify-center h-8 w-8 rounded-md bg-zinc-900 text-white hover:bg-zinc-800">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.27 6.96 12 12.01l8.73-5.05M12 22.08V12"/></svg>
                                        </button>
                                    @endif
                                    <form method="POST" action="{{ route('purchase-orders.destroy', $po) }}"
                                          onsubmit="return confirm('¿Eliminar la OC #{{ $po->id }}? Esta acción no se puede deshacer.')">
                                        @csrf @method('DELETE')
                                        <button type="submit" title="Eliminar"
                                                class="inline-flex items-center justify-center h-8 w-8 rounded-md bg-red-600 text-white hover:bg-red-700">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-16 text-center">
                                <div class="mx-auto flex max-w-sm flex-col items-center gap-3 text-zinc-400">
                                    <svg class="h-10 w-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                                        <path d="M3.27 6.96 12 12.01l8.73-5.05M12 22.08V12"/>
                                    </svg>
                                    <p class="text-sm">No hay órdenes de compra. Se generan automáticamente cuando un ítem no tiene stock disponible.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $orders->links() }}</div>

    @else
        {{-- ==================== TAB: STOCK ==================== --}}
        <div class="rounded-lg border border-zinc-200 bg-white p-4">
           <div class="flex items-start justify-between mb-4">
                <p class="text-sm text-zinc-600">Cuentas sin juego vinculado. Ordenadas por región.</p>
                <button type="button" onclick="document.getElementById('modal-create-stock').showModal()"
                        class="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 shrink-0">
                    + Agregar cuenta
                </button>
            </div>

            <form method="GET" action="{{ route('purchase-orders.index') }}" class="flex flex-wrap gap-2 mb-4">
                <input type="hidden" name="tab" value="stock">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar por email, región…"
                       class="rounded-md border-zinc-300 text-sm px-3 py-1.5 w-64">
                <select name="platform" class="rounded-md border-zinc-300 text-sm px-3 py-1.5">
                    <option value="all">Todas las consolas</option>
                    @foreach (['DUAL','PS4','PS5','XBOX_ONE','XBOX_SERIES','SWITCH','SWITCH_2','STEAM'] as $p)
                        <option value="{{ $p }}" @selected(request('platform') === $p)>{{ $p }}</option>
                    @endforeach
                </select>
                <select name="region" class="rounded-md border-zinc-300 text-sm px-3 py-1.5">
                    <option value="all">Todas las regiones</option>
                    @foreach ($uniqueRegions as $r)
                        <option value="{{ $r }}" @selected(request('region') === $r)>{{ $r }}</option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-md bg-zinc-900 px-4 py-1.5 text-sm font-medium text-white hover:bg-zinc-800">
                    Filtrar
                </button>
            </form>

            <div class="overflow-x-auto rounded-lg border border-zinc-200">
                <table class="min-w-full text-sm">
                    <thead class="bg-zinc-50 border-b border-zinc-200 text-xs uppercase tracking-wide text-zinc-600">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium">Email</th>
                            <th class="px-4 py-2 text-left font-medium">Consola</th>
                            <th class="px-4 py-2 text-left font-medium">Región</th>
                            <th class="px-4 py-2 text-left font-medium">Estado</th>
                            
                             <th class="px-4 py-2 text-left font-medium">Tipo</th>
                            <th class="px-4 py-2 text-left font-medium">Comprada</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @forelse ($stockAccounts as $acc)
                            @php
                                $accData = [
                                    'id'             => $acc->id,
                                    'email'          => $acc->email,
                                    'password'       => $acc->password,
                                    'platform'       => $acc->platform,
                                    'type_console'   => $acc->type_console,
                                    'region'         => $acc->region,
                                    'account_type'   => $acc->account_type,
                                    'is_dual'        => (bool) $acc->is_dual,
                                    'status'         => $acc->status,
                                    'mail_email'     => $acc->mail_email,
                                    'mail_password'  => $acc->mail_password,
                                    'gamer_tag'      => $acc->gamer_tag,
                                    'birth_date'     => optional($acc->birth_date)->format('Y-m-d'),
                                    'purchased_date' => optional($acc->purchased_date)->format('Y-m-d'),
                                    'notes'          => $acc->notes,
                                    'keys'           => $acc->keys->map(fn ($k) => [
                                                            'position' => $k->position,
                                                            'value'    => $k->key_value,
                                                        ])->values(),
                                ];
                            @endphp
                            <tr class="hover:bg-zinc-50 cursor-pointer" onclick='openStockDetail(@json($accData))'>
                                <td class="px-4 py-2.5 font-medium">{{ $acc->email }}</td>
                                <td class="px-4 py-2.5 font-mono text-xs">{{ $acc->type_console ?? '—' }}</td>
                                <td class="px-4 py-2.5">{{ $acc->region ?? '—' }}</td>
                                <td class="px-4 py-2.5">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                        @class([
                                            'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20' => $acc->status === 'active',
                                            'bg-zinc-100 text-zinc-700' => $acc->status !== 'active',
                                        ])">
                                        {{ $acc->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5">
                                @php
                                    $typeColor = match($acc->account_type) {
                                        'MADRE' => 'violet',
                                        'HIJA'  => 'sky',
                                        default => 'zinc',
                                    };
                                @endphp
                                <span class="inline-flex items-center rounded-full bg-{{ $typeColor }}-50 px-2 py-0.5 text-xs font-medium text-{{ $typeColor }}-700 ring-1 ring-inset ring-{{ $typeColor }}-600/20">
                                    {{ $acc->account_type ?? 'INDEPENDIENTE' }}
                                </span>
                            </td>
                                <td class="px-4 py-2.5 font-mono text-xs text-zinc-500">
                                    {{ $acc->purchased_date?->format('Y-m-d') ?? '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-12 text-center text-zinc-500">
                                    No hay cuentas sin juego con los filtros aplicados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

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
                @foreach (['HONG KONG','BRASIL','USA','ESPAÑA','UK','TURQUIA','INDIA','ARG','CAN','UCRANIA','INDONESIA'] as $r)
                    <option value="{{ $r }}">{{ $r }}</option>
                @endforeach
            </select>
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

{{-- ==================== MODAL: PREVIEW IMAGEN ==================== --}}
<dialog id="modal-preview" class="rounded-lg p-0 backdrop:bg-black/70 bg-transparent">
    <button type="button" onclick="document.getElementById('modal-preview').close()"
            class="absolute top-2 right-2 z-10 text-white bg-black/50 rounded-full h-8 w-8 flex items-center justify-center">✕</button>
    <img id="preview-img" src="" alt="" class="max-h-[80vh] w-auto rounded-md">
</dialog>
{{-- ==================== MODAL: CREAR CUENTA DE STOCK ==================== --}}
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
                <label class="fld-label">Región <span class="text-zinc-400 font-normal">(opcional)</span></label>
                <select name="region" class="fld-select">
                    <option value="">—</option>
                    @foreach (['HONG KONG','BRASIL','USA','ESPAÑA','UK','TURQUIA','INDIA','ARG','CAN','UCRANIA','INDONESIA'] as $r)
                        <option value="{{ $r }}">{{ $r }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <label class="flex items-center gap-2 text-sm">
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
            <label class="fld-label">Fecha de compra <span class="text-zinc-400 font-normal">(opc.)</span></label>
            <input type="date" name="purchased_date" class="fld-input">
        </div>

         <div>
                <label class="fld-label">Tipo <span class="text-red-500">*</span></label>
            <select name="account_type" id="account_type" class="fld-select" onchange="toggleParentAccount()">
                @foreach (['INDEPENDIENTE', 'MADRE', 'HIJA'] as $t)
                    <option value="{{ $t }}">{{ $t }}</option>
                @endforeach
            </select>
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


<script>
/* ─────────── GAME PICKER (mismo endpoint que accounts) ─────────── */
const gamePicker = {
    page: 1,
    lastPage: 1,
    search: '',
    debounceTimer: null,
    url: @json(route('woo-products.picker')),
};

function openGamePicker() {
    gamePicker.page = 1;
    gamePicker.search = '';
    document.getElementById('gp-search').value = '';
    document.getElementById('modal-game-picker').showModal();
    loadGamePickerPage();
    setTimeout(() => document.getElementById('gp-search').focus(), 50);
}

function closeGamePicker() {
    document.getElementById('modal-game-picker').close();
}

function changeGamePickerPage(delta) {
    const newPage = gamePicker.page + delta;
    if (newPage < 1 || newPage > gamePicker.lastPage) return;
    gamePicker.page = newPage;
    loadGamePickerPage();
}

document.getElementById('gp-search').addEventListener('input', (e) => {
    gamePicker.search = e.target.value;
    gamePicker.page = 1;
    clearTimeout(gamePicker.debounceTimer);
    gamePicker.debounceTimer = setTimeout(loadGamePickerPage, 250);
});

async function loadGamePickerPage() {
    const grid = document.getElementById('gp-grid');
    const loading = document.getElementById('gp-loading');
    const empty = document.getElementById('gp-empty');

    grid.innerHTML = '';
    empty.classList.add('hidden');
    loading.classList.remove('hidden');

    const params = new URLSearchParams({ page: gamePicker.page, search: gamePicker.search });

    try {
        const res = await fetch(`${gamePicker.url}?${params}`, { headers: { 'Accept': 'application/json' } });
        const json = await res.json();

        loading.classList.add('hidden');
        gamePicker.lastPage = json.meta.last_page;

        if (json.data.length === 0) {
            empty.classList.remove('hidden');
        } else {
            json.data.forEach(p => grid.appendChild(renderProductCard(p)));
        }

        document.getElementById('gp-count').textContent = `${json.meta.total} productos`;
        document.getElementById('gp-page').textContent = `${json.meta.current_page} / ${json.meta.last_page}`;
        document.getElementById('gp-prev').disabled = gamePicker.page <= 1;
        document.getElementById('gp-next').disabled = gamePicker.page >= gamePicker.lastPage;
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
            <div class="text-xs font-medium line-clamp-2 leading-tight" title="${esc(product.name)}">${esc(product.name)}</div>
            ${platformBadge}
        </div>
        <button type="button"
                data-game-id="${esc(product.game_id)}"
                data-platform="${esc(product.platform || '')}"
                data-name="${esc(product.name)}"
                data-cover="${esc(product.image_url || '')}"
                class="product-pick-btn mt-2 w-full rounded bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium px-2 py-1.5">
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
    // game_id es nullable|exists en el controller: si el producto no tiene juego, lo dejamos vacío
    document.getElementById('po-game-id').value = (gameId && gameId !== 'null') ? gameId : '';
    document.getElementById('po-game-title').value = name;

    if (platform) {
        const select = document.getElementById('po-platform');
        const dual = document.getElementById('po-is-dual');
        const up = platform.toUpperCase();

        if (up === 'DUAL') {
            // Producto marcado como DUAL: dejamos la plataforma para que el operador la elija
            // y marcamos el flag. (DUAL ya no es una plataforma en sí.)
            dual.checked = true;
        } else {
            const match = Array.from(select.options).find(o => o.value.toUpperCase() === up);
            if (match) select.value = match.value;
            dual.checked = false;
        }
    }

    const display = document.getElementById('po-selected-game-display');
    const coverHtml = cover
        ? `<img src="${esc(cover)}" alt="" class="w-12 h-16 object-cover rounded shrink-0 bg-zinc-100" onerror="this.style.display='none'">`
        : `<div class="w-12 h-16 rounded bg-zinc-100 shrink-0"></div>`;
    display.innerHTML = `
        <div class="flex items-center gap-3 p-2 rounded-md border border-zinc-200">
            ${coverHtml}
            <div class="flex-1 min-w-0">
                <div class="text-sm font-medium truncate">${esc(name)}</div>
                <div class="text-xs text-zinc-500 font-mono">${esc(platform || '')}${(gameId && gameId !== 'null') ? ' · juego #' + esc(gameId) : ''}</div>
            </div>
        </div>
    `;
    closeGamePicker();
}

/* Guard: evitar enviar sin juego seleccionado (game_title es hidden, no lo valida el browser) */
document.querySelector('#modal-create-po form').addEventListener('submit', (e) => {
    if (!document.getElementById('po-game-title').value.trim()) {
        e.preventDefault();
        alert('Seleccioná un juego antes de crear la orden.');
    }
});

    let completePoPlatform = '';

    function openComplete(po) {
        const form = document.getElementById('form-complete-po');
        form.action = `/purchase-orders/${po.id}/complete`;
        document.getElementById('complete-po-info').innerHTML =
            `OC <strong>#${po.id}</strong> · ${po.game_title} · ${po.platform}` +
            (po.region && po.region !== 'sin especificar' ? ` · ${po.region}` : '');

        completePoPlatform = (po.platform || '').toUpperCase();

        completePoPlatform = (po.platform || '').toUpperCase();

        // Prellenar plataforma e is_dual con los datos de la OC
        const platformSelect = document.getElementById('complete-platform');
        const matchPlatform = Array.from(platformSelect.options)
            .find(o => o.value.toUpperCase() === completePoPlatform);
        platformSelect.value = matchPlatform ? matchPlatform.value : '';

        document.getElementById('complete-is-dual').checked = !!po.is_dual;

        // Reset de los filtros al abrir
        document.getElementById('complete-filter-email').value = '';
        document.getElementById('complete-filter-region').value = '';

        applyCompleteFilters();

        const select = form.querySelector('select[name="account_id"]');
        select.value = '';
        usedPositions = [];
        document.getElementById('complete-existing-keys').classList.add('hidden');
        document.getElementById('complete-existing-keys-list').innerHTML = '';

        document.getElementById('modal-complete-po').showModal();
    }

    function applyCompleteFilters() {
        const select  = document.querySelector('#form-complete-po select[name="account_id"]');
        const emailQ  = document.getElementById('complete-filter-email').value.trim().toLowerCase();
        const regionQ = document.getElementById('complete-filter-region').value;

        const family = (p) => p.startsWith('PS') ? 'PS'
                    : p.startsWith('XBOX') ? 'XBOX'
                    : p.startsWith('SWITCH') ? 'NIN'
                    : p;

        Array.from(select.options).forEach(opt => {
            if (!opt.value) { opt.hidden = false; return; }

            const accPlatform = (opt.dataset.platform || '').toUpperCase();
            const accRegion   = opt.dataset.region || '';
            const accEmail    = opt.textContent.toLowerCase();

            const matchPlatform = family(accPlatform) === family(completePoPlatform);
            const matchEmail    = !emailQ  || accEmail.includes(emailQ);
            const matchRegion   = !regionQ || accRegion === regionQ;

            opt.hidden = !(matchPlatform && matchEmail && matchRegion);
        });

        // Si la cuenta seleccionada quedó oculta, deseleccionar y limpiar las llaves
        if (select.selectedOptions[0] && select.selectedOptions[0].hidden) {
            select.value = '';
            renderExistingKeys();
        }
    }

    // Listeners de los filtros (se ejecutan al cargar la página, una sola vez)
    document.getElementById('complete-filter-email').addEventListener('input', applyCompleteFilters);
    document.getElementById('complete-filter-region').addEventListener('change', applyCompleteFilters);

    let usedPositions = [];

    const completeAccountSelect = document.querySelector('#form-complete-po select[name="account_id"]');
    completeAccountSelect.addEventListener('change', renderExistingKeys);

    function renderExistingKeys() {
        const opt   = completeAccountSelect.selectedOptions[0];
        const panel = document.getElementById('complete-existing-keys');
        const list  = document.getElementById('complete-existing-keys-list');
        list.innerHTML = '';

        let keys = [];
        try { keys = JSON.parse(opt?.dataset.keys || '[]'); } catch { keys = []; }

        if (!opt || !opt.value || keys.length === 0) {
            panel.classList.add('hidden');
            usedPositions = [];
            return;
        }

        usedPositions = keys.map(k => Number(k.position));

        keys.sort((a, b) => a.position - b.position).forEach(k => {
            const chip = document.createElement('span');
            chip.className = 'inline-flex items-center gap-1 rounded bg-white ring-1 ring-inset ring-amber-300 px-1.5 py-0.5 font-mono';
            chip.textContent = `#${k.position} · ${esc(k.value)}`;
            list.appendChild(chip);
        });
        panel.classList.remove('hidden');
    }

    function openPreview(url, title) {
        const img = document.getElementById('preview-img');
        img.src = url;
        img.alt = title;
        document.getElementById('modal-preview').showModal();
    }

    function openStockDetail(acc) {
        const set = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = (val === null || val === undefined || val === '') ? '—' : val;
        };

        set('sd-email',         acc.email);
        set('sd-password',      acc.password);
        set('sd-platform',      acc.platform);
        set('sd-console',       acc.type_console);
        set('sd-region',        acc.region);
        set('sd-type',          acc.account_type);
        set('sd-dual',          acc.is_dual ? 'Sí' : 'No');
        set('sd-status',        acc.status);
        set('sd-gamer-tag',     acc.gamer_tag);
        set('sd-birth',         acc.birth_date);
        set('sd-mail-email',    acc.mail_email);
        set('sd-mail-password', acc.mail_password);
        set('sd-purchased',     acc.purchased_date);
        set('sd-notes',         acc.notes);

        // Llaves
        const wrap  = document.getElementById('sd-keys');
        const empty = document.getElementById('sd-keys-empty');
        wrap.innerHTML = '';
        const keys = acc.keys || [];

        if (keys.length === 0) {
            empty.classList.remove('hidden');
        } else {
            empty.classList.add('hidden');
            keys.sort((a, b) => a.position - b.position).forEach(k => {
                const chip = document.createElement('span');
                chip.className = 'inline-flex items-center gap-1 rounded bg-zinc-100 ring-1 ring-inset ring-zinc-200 px-1.5 py-0.5 font-mono text-xs';
                chip.textContent = `#${k.position} · ${k.value}`;
                wrap.appendChild(chip);
            });
        }

        document.getElementById('modal-stock-detail').showModal();
    }


    let stockKeyIndex = 0;

    function addStockKey(data = {}) {
        const tpl = document.getElementById('stock-key-template');
        const html = tpl.innerHTML.replaceAll('__INDEX__', stockKeyIndex);
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        const row = wrapper.firstElementChild;

        if (data.position) row.querySelector('input[name$="[position]"]').value = data.position;
        if (data.value)    row.querySelector('input[name$="[value]"]').value    = data.value;

        if (!data.position) {
            const existing  = document.querySelectorAll('#stock-keys-container input[name$="[position]"]');
            const maxInForm = Array.from(existing).reduce((m, el) => Math.max(m, parseInt(el.value) || 0), 0);
            row.querySelector('input[name$="[position]"]').value = maxInForm + 1;
        }

        document.getElementById('stock-keys-container').appendChild(row);
        stockKeyIndex++;
    }

    function removeStockKey(btn) {
        btn.closest('.key-row').remove();
    }

    function bulkAddStockKeys() {
        const ta = document.getElementById('stock-keys-bulk');
        let tokens = ta.value.trim().split(/[\s,;]+/).map(t => t.trim()).filter(Boolean);
        tokens = [...new Set(tokens)].map(t => t.slice(0, 64));
        tokens.forEach(value => addStockKey({ value }));
        ta.value = '';
    }

    // Quita filas vacías antes de enviar (igual que en complete)
    document.getElementById('form-create-stock').addEventListener('submit', () => {
        document.querySelectorAll('#stock-keys-container .key-row').forEach(row => {
            if (!row.querySelector('input[name$="[value]"]').value.trim()) row.remove();
        });
    });
</script>
@endsection