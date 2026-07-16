@extends('layouts.app')
@section('title', 'Cuentas')

@section('content')

<div class="mb-4 flex items-center justify-between">
    <h1 class="text-xl font-semibold">Cuentas</h1>
    <div class="flex items-center gap-2">

        <a href="{{ route('accounts.mismatched') }}"
            class="rounded-md border border-zinc-300 px-3 py-1.5 text-sm font-medium text-zinc-700 hover:bg-zinc-100">
                Juegos a corregir
            </a>
        <a href="{{ route('accounts.create') }}"
           class="rounded-md bg-zinc-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-zinc-700">
            + Nueva cuenta
        </a>
    </div>
</div>

{{-- Stats (clickeables: aplican el filtro de status) --}}
@php
    $currentStatus = request('status');
    $statCards = [
        ['key' => 'total',   'label' => 'Total',      'color' => 'zinc',    'status' => null,      'ring' => 'ring-zinc-400 border-zinc-400'],
        ['key' => 'active',  'label' => 'Activas',    'color' => 'emerald', 'status' => 'active',  'ring' => 'ring-emerald-400 border-emerald-400'],
        ['key' => 'disabled', 'label' => 'Deshabilitadas', 'color' => 'red',     'status' => 'disabled', 'ring' => 'ring-red-400 border-red-400'],
        ['key' => 'resettable', 'label' => 'Reseteables', 'color' => 'amber', 'ring' => 'ring-amber-400 border-amber-400',
         'href' => route('stock.resettable', array_filter([
             'search'  => request('search'),
             'game_id' => request('game_id'),
         ]))],
    ];
@endphp
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
    @foreach ($statCards as $card)
        @php
            $hasHref  = isset($card['href']);
            $isActive = ! $hasHref && (string) $currentStatus === (string) ($card['status'] ?? '');
            $url      = $hasHref ? $card['href'] : request()->fullUrlWithQuery(['status' => $card['status'], 'page' => null]);
        @endphp
        <a href="{{ $url }}"
           class="group block rounded-lg border bg-white p-4 transition hover:shadow-sm
                  {{ $isActive ? 'ring-2 '.$card['ring'] : 'border-zinc-200 hover:border-zinc-300' }}">
            <div class="flex items-center justify-between">
                <span class="flex items-center gap-1 text-xs font-medium uppercase tracking-wide {{ $isActive ? 'text-zinc-900' : 'text-zinc-500' }}">
                    {{ $card['label'] }}
                    @if ($hasHref)
                        <svg class="h-3 w-3 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 17 17 7M9 7h8v8" />
                        </svg>
                    @endif
                </span>
                <span class="h-2 w-2 rounded-full bg-{{ $card['color'] }}-500"></span>
            </div>
            <div class="mt-2 font-mono text-2xl font-semibold">{{ number_format($stats[$card['key']]) }}</div>
        </a>
    @endforeach
</div>

{{-- Filtros --}}
<form method="GET" class="mb-6">
    <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">

            {{-- Buscar (ocupa toda la fila en lg) --}}
            <div class="sm:col-span-2 lg:col-span-6">
                <label class="mb-1.5 block text-xs font-medium text-zinc-600">Buscar</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-zinc-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="m21 21-4.35-4.35M17 11a6 6 0 1 1-12 0 6 6 0 0 1 12 0Z" />
                        </svg>
                    </span>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="email, gamer tag, mail email…"
                           class="w-full rounded-lg border-zinc-300 bg-zinc-50 py-2 pl-9 pr-3 text-sm font-mono
                                  transition focus:border-zinc-900 focus:bg-white focus:ring-1 focus:ring-zinc-900">
                </div>
            </div>

            {{-- Plataforma (múltiple) --}}
            @php
                $selPlatforms = array_values(array_filter((array) request('platform', []), fn ($v) => $v !== ''));
                $platLabel = match (true) {
                    count($selPlatforms) === 0 => 'Todas',
                    count($selPlatforms) === 1 => $selPlatforms[0],
                    default                    => count($selPlatforms) . ' seleccionadas',
                };
            @endphp
            <div>
                <label class="mb-1.5 block text-xs font-medium text-zinc-600">Plataforma</label>
                <details class="platform-filter relative">
                    <summary class="flex cursor-pointer list-none items-center justify-between rounded-lg border border-zinc-300 bg-zinc-50 py-2 pl-3 pr-9 text-sm transition focus:border-zinc-900 focus:bg-white [&::-webkit-details-marker]:hidden">
                        <span data-platform-summary class="truncate {{ count($selPlatforms) ? 'text-zinc-900' : 'text-zinc-500' }}">{{ $platLabel }}</span>
                        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-zinc-400">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                            </svg>
                        </span>
                    </summary>
                    <div class="absolute left-0 top-full z-20 mt-1 max-h-60 w-full min-w-[10rem] overflow-auto rounded-lg border border-zinc-200 bg-white p-1.5 shadow-lg">
                        @foreach ($platforms as $p)
                            <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-zinc-50">
                                <input type="checkbox" name="platform[]" value="{{ $p }}"
                                       @checked(in_array($p, $selPlatforms))
                                       class="rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900">
                                <span class="font-mono text-xs">{{ $p }}</span>
                            </label>
                        @endforeach
                    </div>
                </details>
            </div>

            {{-- Consola (type_console) --}}
            <div>
                <label class="mb-1.5 block text-xs font-medium text-zinc-600">Consola</label>
                <div class="relative">
                    <select name="type_console"
                            class="w-full appearance-none rounded-lg border-zinc-300 bg-zinc-50 py-2 pl-3 pr-9 text-sm
                                   transition focus:border-zinc-900 focus:bg-white focus:ring-1 focus:ring-zinc-900">
                        <option value="">Todas</option>
                        @foreach ($typeConsoles as $tc)
                            <option value="{{ $tc }}" @selected(request('type_console') === $tc)>{{ $tc }}</option>
                        @endforeach
                    </select>
                    <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-zinc-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                        </svg>
                    </span>
                </div>
            </div>

            {{-- Región --}}
            <div>
                <label class="mb-1.5 block text-xs font-medium text-zinc-600">Región</label>
                <div class="relative">
                    <select name="region"
                            class="w-full appearance-none rounded-lg border-zinc-300 bg-zinc-50 py-2 pl-3 pr-9 text-sm
                                   transition focus:border-zinc-900 focus:bg-white focus:ring-1 focus:ring-zinc-900">
                        <option value="">Todas</option>
                        @foreach ($regions as $r)
                            <option value="{{ $r }}" @selected(request('region') === $r)>{{ $r }}</option>
                        @endforeach
                    </select>
                    <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-zinc-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                        </svg>
                    </span>
                </div>
            </div>

            {{-- Tipo --}}
            <div>
                <label class="mb-1.5 block text-xs font-medium text-zinc-600">Tipo</label>
                <div class="relative">
                    <select name="type"
                            class="w-full appearance-none rounded-lg border-zinc-300 bg-zinc-50 py-2 pl-3 pr-9 text-sm
                                   transition focus:border-zinc-900 focus:bg-white focus:ring-1 focus:ring-zinc-900">
                        <option value="">Todos</option>
                        @foreach (['INDEPENDIENTE', 'MADRE', 'HIJA'] as $t)
                            <option value="{{ $t }}" @selected(request('type') === $t)>{{ $t }}</option>
                        @endforeach
                    </select>
                    <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-zinc-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                        </svg>
                    </span>
                </div>
            </div>

            {{-- Llaves --}}
            <div>
                <label class="mb-1.5 block text-xs font-medium text-zinc-600">Llaves</label>
                <div class="relative">
                    <select name="few_keys"
                            class="w-full appearance-none rounded-lg border-zinc-300 bg-zinc-50 py-2 pl-3 pr-9 text-sm
                                transition focus:border-zinc-900 focus:bg-white focus:ring-1 focus:ring-zinc-900">
                        <option value="">Todas</option>
                        <option value="1" @selected(request('few_keys') === '1')>Menos de 2 llaves</option>
                    </select>
                    <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-zinc-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                        </svg>
                    </span>
                </div>
            </div>

            {{-- Dual --}}
            <div>
                <label class="mb-1.5 block text-xs font-medium text-zinc-600">Dual</label>
                <div class="relative">
                    <select name="is_dual"
                            class="w-full appearance-none rounded-lg border-zinc-300 bg-zinc-50 py-2 pl-3 pr-9 text-sm
                                   transition focus:border-zinc-900 focus:bg-white focus:ring-1 focus:ring-zinc-900">
                        <option value="">Todas</option>
                        <option value="1" @selected(request('is_dual') === '1')>Solo DUAL</option>
                        <option value="0" @selected(request('is_dual') === '0')>No DUAL</option>
                    </select>
                    <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-zinc-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                        </svg>
                    </span>
                </div>
            </div>

            {{-- Status --}}
            <div>
                <label class="mb-1.5 block text-xs font-medium text-zinc-600">Status</label>
                <div class="relative">
                    <select name="status"
                            class="w-full appearance-none rounded-lg border-zinc-300 bg-zinc-50 py-2 pl-3 pr-9 text-sm
                                   transition focus:border-zinc-900 focus:bg-white focus:ring-1 focus:ring-zinc-900">
                        <option value="">Todos</option>
                        <option value="disabled" @selected(request('status') === 'disabled')>Deshabilitadas (todas)</option>
                        @foreach (['active', 'blocked', 'reset', 'archived'] as $s)
                            <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                    <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-zinc-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                        </svg>
                    </span>
                </div>
            </div>

            @php $orphan = request()->boolean('orphan'); @endphp

            {{-- <a href="{{ request()->fullUrlWithQuery(['orphan' => $orphan ? null : 1, 'page' => null]) }}"
            class="btn {{ $orphan ? 'btn-warning' : 'btn-outline-secondary' }}">
                {{ $orphan ? '← Volver al listado normal' : '⚠ Ver cuentas sin juego' }}
            </a> --}}
        </div>

        {{-- Acciones --}}
        <div class="mt-4 flex items-center gap-2 border-t border-zinc-100 pt-3">
            <button type="submit"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium
                           text-white shadow-sm transition hover:bg-zinc-700 focus:outline-none focus:ring-2
                           focus:ring-zinc-900 focus:ring-offset-1">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h18M6 12h12M10 19.5h4" />
                </svg>
                Filtrar
            </button>

            @if (request()->hasAny(['search', 'platform', 'type_console', 'region', 'type', 'status', 'is_dual', 'few_keys']))
                <a href="{{ route('accounts.index') }}"
                   class="inline-flex items-center rounded-lg border border-zinc-200 px-3 py-2 text-sm
                          text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900">
                    Limpiar
                </a>
            @endif
        </div>
    </div>
</form>

{{-- Tabla --}}
<div class="overflow-x-auto rounded-lg border border-zinc-200 bg-white">
    @php
        $th = function ($column, $label) {
            $currentSort = request('sort', 'created_at');
            $currentDir  = strtolower(request('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
            $isActive    = $currentSort === $column;
            $nextDir     = ($isActive && $currentDir === 'asc') ? 'desc' : 'asc';
            $url         = request()->fullUrlWithQuery(['sort' => $column, 'direction' => $nextDir, 'page' => null]);
            $arrow       = $isActive ? ($currentDir === 'asc' ? '▲' : '▼') : '↕';
            $arrowCls    = $isActive ? 'text-zinc-900' : 'text-zinc-300';
            $labelCls    = $isActive ? 'text-zinc-900' : 'text-zinc-600';

            return '<a href="'.$url.'" class="inline-flex items-center gap-1 hover:text-zinc-900 '.$labelCls.'">'
                . e($label)
                . '<span class="text-[9px] '.$arrowCls.'">'.$arrow.'</span></a>';
        };
    @endphp
    <table class="min-w-full text-sm">
        <thead class="bg-zinc-50 border-b border-zinc-200 text-xs uppercase tracking-wide text-zinc-600">
            <tr>
                <th class="px-4 py-2 text-left font-medium">Juego</th>
                <th class="px-4 py-2 text-left font-medium">{!! $th('email', 'Email') !!}</th>
                <th class="px-4 py-2 text-left font-medium">{!! $th('platform', 'Plat.') !!}</th>
                <th class="px-4 py-2 text-left font-medium">{!! $th('is_dual', 'Dual') !!}</th>
                <th class="px-4 py-2 text-left font-medium">{!! $th('region', 'Región') !!}</th>
                <th class="px-4 py-2 text-left font-medium">{!! $th('account_type', 'Tipo') !!}</th>
                <th class="px-4 py-2 text-left font-medium">Slots</th>
                <th class="px-4 py-2 text-left font-medium">{!! $th('keys_count', 'Llaves') !!}</th>
                <th class="px-4 py-2 text-left font-medium">{!! $th('reset_date', 'Reset') !!}</th>
                <th class="px-4 py-2 text-left font-medium">{!! $th('purchased_date', 'Compra') !!}</th>
                <th class="px-4 py-2 text-left font-medium">{!! $th('status', 'Status') !!}</th>
                <th class="px-2 py-2"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-100">
            @forelse ($accounts as $key => $a)
                @php
                    $statusColor = match($a->status) {
                        'active'   => 'emerald',
                        'blocked'  => 'red',
                        'reset'    => 'amber',
                        'archived' => 'zinc',
                        default    => 'zinc',
                    };
                    $capacity = $a->capacity();
                    $used = $a->active_assignments_count;
                    $timeBlocked = $a->isTimeBlocked();
                @endphp
                <tr class="hover:bg-zinc-50">
                    <td class="px-4 py-2.5">
                        @if ($a->game)
                            @php
                                // Reusa coverProduct() del modelo (normalizePlatform) para no fallar
                                // con variantes como "Switch2" vs SWITCH_2, que la normalización inline
                                // (solo espacios/guiones) no matcheaba → caía a otro producto (portada equivocada).
                                $match = $a->coverProduct();

                                // Nombre: el del producto correcto (limpio); si no hay match de plataforma,
                                // el nombre del juego SIN sufijo, así no dice "STEAM" en una cuenta PS5.
                                $name  = $match ? \App\Models\Game::stripPlatform($match->name) : $a->game->displayName();

                                // Tapa: la del producto de la plataforma; si no hay ninguno, cae a cualquier
                                // producto del juego (el arte suele ser el mismo entre plataformas).
                                $cover = ($match ?? $a->game->products->first())?->image_url;
                            @endphp
                           <a href="{{ route('accounts.show', $a) }}" class="flex items-center gap-2 max-w-[240px]">
                                @if ($cover)
                                    <img src="{{ $cover }}" alt=""
                                        class="w-8 h-10 object-cover rounded shrink-0 bg-zinc-100"
                                        onerror="this.style.display='none'">
                                @else
                                    <div class="w-8 h-10 rounded bg-zinc-100 shrink-0"></div>
                                @endif
                                <div class="">{{ $name }}</div>
                           </a>
                        @else
                            <span class="text-zinc-400">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-2.5 font-mono text-xs">
                        <a href="{{ route('accounts.show', $a) }}" class="hover:underline">{{ $a->email }}</a>
                        @if ($a->gamer_tag)
                            <div class="text-xs text-zinc-400">{{ $a->gamer_tag }}</div>
                        @endif
                    </td>
                    
                    <td class="px-4 py-2.5">
                        <span class="font-mono text-xs px-1.5 py-0.5 rounded bg-zinc-100">{{ $a->platform }}</span>
                    </td>
                    <td class="px-4 py-2.5">
                        <span class="font-mono text-xs px-1.5 py-0.5 rounded bg-zinc-100">{{ $a->is_dual ? 'Sí' : 'No' }}</span>
                    </td>
                    <td class="px-4 py-2.5 text-xs">{{ $a->region }}</td>
                    <td class="px-4 py-2.5 text-xs">{{ $a->account_type }}</td>
                   <td class="px-4 py-2.5 font-mono text-xs">
                        <div class="flex flex-col gap-0.5">
                            @foreach ($a->coveredPlatforms() as $plat)
                                @php
                                    $platCap  = $a->capacityFor($plat);
                                    $platUsed = $platCap - $a->freeSlotsFor($plat);
                                @endphp
                                <span>
                                    <span class="text-zinc-400">{{ $plat }}</span>
                                    <span class="{{ $platUsed >= $platCap ? 'text-red-600' : ($platUsed > 0 ? 'text-amber-600' : 'text-emerald-600') }}">
                                        {{ $platUsed }}/{{ $platCap }}
                                    </span>
                                </span>
                            @endforeach
                        </div>
                        @if ($a->isPostReset())
                            <span class="text-zinc-400 text-[10px]" title="post-reset">⟲</span>
                        @endif
                        @if ($timeBlocked)
                            <span class="text-amber-600 text-[10px]" title="Bloqueada por Nintendo">⏱</span>
                        @endif
                    </td>
                    <td class="px-4 py-2.5 font-mono text-xs">{{ $a->keys_count }}</td>
                    <td class="px-4 py-2.5 font-mono text-xs text-zinc-500">
                        {{ $a->reset_date?->format('Y-m-d') ?? '—' }}
                    </td>
                    <td class="px-4 py-2.5 font-mono text-xs text-zinc-500">
                        <input type="date"
                               value="{{ $a->purchased_date?->format('Y-m-d') }}"
                               data-purchased-date
                               data-original="{{ $a->purchased_date?->format('Y-m-d') }}"
                               data-url="{{ route('accounts.purchased-date.update', $a) }}"
                               class="w-32 rounded border border-transparent bg-transparent px-1 py-0.5 font-mono text-xs text-zinc-500 hover:border-zinc-300 focus:border-zinc-400 focus:bg-white focus:outline-none">
                    </td>
                    <td class="px-4 py-2.5">
                        <div class="flex flex-col gap-1 items-start">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-{{ $statusColor }}-50 px-2 py-0.5 text-xs font-medium text-{{ $statusColor }}-700 ring-1 ring-inset ring-{{ $statusColor }}-600/20">
                                <span class="h-1.5 w-1.5 rounded-full bg-{{ $statusColor }}-500"></span>
                                {{ $a->status }}
                            </span>
                            @if ($a->disable_reason)
                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-red-50 text-red-700 ring-1 ring-inset ring-red-200 font-medium">
                                    {{ $a->disableReasonLabel() }}
                                </span>
                            @endif
                        </div>
                    </td>
                    
                    <td class="px-2 py-2.5 text-right">
                        <a href="{{ route('accounts.edit', $a) }}"
                           class="text-xs text-zinc-500 hover:text-zinc-900">editar</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="px-4 py-12 text-center text-zinc-500">
                        No hay cuentas que coincidan con los filtros.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $accounts->links() }}
</div>

<script>
    // --- Filtro múltiple de plataforma ---
    (function () {
        const details = document.querySelector('details.platform-filter');
        if (!details) return;

        const summary = details.querySelector('[data-platform-summary]');

        const refresh = () => {
            const checked = details.querySelectorAll('input[name="platform[]"]:checked');
            const n = checked.length;
            summary.textContent = n === 0 ? 'Todas'
                                 : n === 1 ? checked[0].value
                                 : n + ' seleccionadas';
            summary.classList.toggle('text-zinc-900', n > 0);
            summary.classList.toggle('text-zinc-500', n === 0);
        };

        details.addEventListener('change', (e) => {
            if (e.target.matches('input[name="platform[]"]')) refresh();
        });

        // Cerrar al hacer click fuera.
        document.addEventListener('click', (e) => {
            if (details.open && !details.contains(e.target)) details.open = false;
        });
    })();

    document.addEventListener('change', function (e) {
        const input = e.target.closest('input[data-purchased-date]');
        if (!input) return;

        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        const original = input.dataset.original ?? '';

        // No cambies si el valor es el mismo que ya estaba guardado.
        if ((input.value || '') === original) return;

        // Evita envíos concurrentes sin deshabilitar el input (disabled roba el foco).
        if (input.dataset.saving === '1') return;
        input.dataset.saving = '1';

        fetch(input.dataset.url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ purchased_date: input.value || null }),
        })
        .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(data => {
            const saved = data.purchased_date ?? '';
            // Solo reescribir si difiere: reasignar value mueve el cursor/foco.
            if (input.value !== saved) input.value = saved;
            input.dataset.original = saved;
            input.classList.add('!border-emerald-400');
            setTimeout(() => input.classList.remove('!border-emerald-400'), 800);
        })
        .catch(() => {
            input.value = original;
            input.classList.add('!border-red-400');
            setTimeout(() => input.classList.remove('!border-red-400'), 1500);
        })
        .finally(() => { input.dataset.saving = '0'; });
    });
</script>

@endsection
