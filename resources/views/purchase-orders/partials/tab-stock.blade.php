{{-- ==================== TAB: STOCK ==================== --}}
@php
    // Paleta de color por consola (marca)
    $consoleStyles = [
        'PLAYSTATION' => 'bg-blue-50 text-blue-700 ring-blue-600/20',
        'XBOX'        => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'NINTENDO'    => 'bg-red-50 text-red-700 ring-red-600/20',
        'STEAM'       => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
    ];
    $consoleStyle = fn ($c) => $consoleStyles[strtoupper($c ?? '')] ?? 'bg-zinc-100 text-zinc-600 ring-zinc-500/20';

    // Resumen por consola sobre el resultado filtrado
    $byConsole = $stockAccounts->groupBy(fn ($a) => strtoupper($a->type_console ?? '—'))
                               ->map->count()
                               ->sortDesc();
@endphp

<div class="space-y-4">

    {{-- ---------- Encabezado + acción ---------- --}}
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-base font-semibold text-zinc-900">Stock de cuentas</h2>
            <p class="mt-0.5 text-sm text-zinc-500">Cuentas sin juego vinculado, ordenadas por región.</p>
        </div>
        <button type="button" onclick="document.getElementById('modal-create-stock').showModal()"
                class="inline-flex items-center gap-1.5 rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-zinc-800 shrink-0">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Agregar cuenta
        </button>
    </div>

    {{-- ---------- Tira de resumen ---------- --}}
    <div class="flex flex-wrap gap-2">
        <span class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-sm">
            <span class="font-semibold text-zinc-900">{{ $stockAccounts->count() }}</span>
            <span class="text-zinc-500">cuentas</span>
        </span>
        @foreach ($byConsole as $console => $count)
            <span class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium ring-1 ring-inset {{ $consoleStyle($console) }}">
                {{ $console }}
                <span class="rounded-full bg-white/60 px-1.5 text-xs">{{ $count }}</span>
            </span>
        @endforeach
    </div>

    {{-- ---------- Filtros ---------- --}}
    <form method="GET" action="{{ route('purchase-orders.index') }}"
          class="flex flex-wrap items-center gap-2 rounded-lg border border-zinc-200 bg-zinc-50/60 p-3">
        <input type="hidden" name="tab" value="stock">

        <div class="relative">
            <svg class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.3-4.3m0 0A7.5 7.5 0 105.2 5.2a7.5 7.5 0 0011.5 11.5z" />
            </svg>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar por email, región…"
                   class="rounded-md border-zinc-300 pl-9 text-sm px-3 py-1.5 w-64 focus:border-zinc-400 focus:ring-zinc-400">
        </div>

        <select name="type_console" class="rounded-md border-zinc-300 text-sm px-3 py-1.5 focus:border-zinc-400 focus:ring-zinc-400">
            <option value="all">Todas las consolas</option>
            @foreach ($uniqueConsoles as $c)
                <option value="{{ $c }}" @selected(request('type_console') === $c)>{{ $c }}</option>
            @endforeach
        </select>

        <select name="region" class="rounded-md border-zinc-300 text-sm px-3 py-1.5 focus:border-zinc-400 focus:ring-zinc-400">
            <option value="all">Todas las regiones</option>
            @foreach ($uniqueRegions as $r)
                <option value="{{ $r }}" @selected(request('region') === $r)>{{ $r }}</option>
            @endforeach
        </select>

        <button type="submit" class="rounded-md bg-zinc-900 px-4 py-1.5 text-sm font-medium text-white hover:bg-zinc-800">
            Filtrar
        </button>

        @if (request('q') || (request('type_console') && request('type_console') !== 'all') || (request('region') && request('region') !== 'all'))
            <a href="{{ route('purchase-orders.index', ['tab' => 'stock']) }}"
               class="text-sm text-zinc-500 hover:text-zinc-700 underline underline-offset-2">
                Limpiar
            </a>
        @endif
    </form>

    {{-- ---------- Tabla ---------- --}}
    <div class="overflow-x-auto rounded-lg border border-zinc-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-zinc-50 border-b border-zinc-200 text-xs uppercase tracking-wide text-zinc-500">
                <tr>
                    <th class="px-4 py-2.5 text-left font-medium">Email</th>
                    <th class="px-4 py-2.5 text-left font-medium">Consola</th>
                    <th class="px-4 py-2.5 text-left font-medium">Región</th>
                    <th class="px-4 py-2.5 text-left font-medium">Estado</th>
                    <th class="px-4 py-2.5 text-left font-medium">Tipo</th>
                    <th class="px-4 py-2.5 text-left font-medium">Comprada</th>
                    <th class="px-4 py-2.5 text-right font-medium">Acción</th>
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
                        $typeColor = match($acc->account_type) {
                            'MADRE' => 'violet',
                            'HIJA'  => 'sky',
                            default => 'zinc',
                        };
                    @endphp
                    <tr class="group hover:bg-zinc-50/80 transition-colors">
                        <td class="px-4 py-2.5 font-medium text-zinc-800 cursor-pointer group-hover:text-zinc-900"
                            onclick='openStockDetail(@json($accData))'>
                            {{ $acc->email }}
                        </td>
                        <td class="px-4 py-2.5">
                            <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $consoleStyle($acc->type_console) }}">
                                {{ $acc->type_console ?? '—' }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5 text-zinc-600">{{ $acc->region ?? '—' }}</td>
                        <td class="px-4 py-2.5">
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium
                                @class([
                                    'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20' => $acc->status === 'active',
                                    'bg-zinc-100 text-zinc-600' => $acc->status !== 'active',
                                ])">
                                <span @class([
                                    'h-1.5 w-1.5 rounded-full',
                                    'bg-emerald-500' => $acc->status === 'active',
                                    'bg-zinc-400' => $acc->status !== 'active',
                                ])></span>
                                {{ $acc->status }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5">
                            <span class="inline-flex items-center rounded-full bg-{{ $typeColor }}-50 px-2 py-0.5 text-xs font-medium text-{{ $typeColor }}-700 ring-1 ring-inset ring-{{ $typeColor }}-600/20">
                                {{ $acc->account_type ?? 'INDEPENDIENTE' }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5 font-mono text-xs text-zinc-500">
                            {{ $acc->purchased_date?->format('Y-m-d') ?? '—' }}
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            <a href="{{ route('accounts.edit', ['account'=>$acc->id]) }}"
                               class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-800 font-medium" target="_blank">
                                Editar
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-16 text-center">
                            <svg class="mx-auto h-10 w-10 text-zinc-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                            </svg>
                            <p class="mt-2 text-sm text-zinc-500">No hay cuentas sin juego con los filtros aplicados.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
