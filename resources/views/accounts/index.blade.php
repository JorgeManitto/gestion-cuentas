@extends('layouts.app')
@section('title', 'Cuentas')

@section('content')

<div class="mb-4 flex items-center justify-between">
    <h1 class="text-xl font-semibold">Cuentas</h1>
    <a href="{{ route('accounts.create') }}"
       class="rounded-md bg-zinc-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-zinc-700">
        + Nueva cuenta
    </a>
</div>

{{-- Stats --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
    @foreach ([
        'total'    => ['Total',     'zinc'],
        'active'   => ['Activas',   'emerald'],
        'blocked'  => ['Bloqueadas','red'],
        'archived' => ['Archivadas','zinc'],
    ] as $key => [$label, $color])
        <div class="rounded-lg border border-zinc-200 bg-white p-4">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ $label }}</span>
                <span class="h-2 w-2 rounded-full bg-{{ $color }}-500"></span>
            </div>
            <div class="mt-2 font-mono text-2xl font-semibold">{{ number_format($stats[$key]) }}</div>
        </div>
    @endforeach
</div>

{{-- Filtros --}}
<form method="GET" class="mb-4 flex flex-wrap gap-2 items-end">
    <div class="flex-1 min-w-[200px]">
        <label class="block text-xs font-medium text-zinc-600 mb-1">Buscar</label>
        <input type="text" name="search" value="{{ request('search') }}"
               placeholder="email, gamer tag, mail email…"
               class="w-full rounded-md border-zinc-300 text-sm font-mono">
    </div>

    <div>
        <label class="block text-xs font-medium text-zinc-600 mb-1">Plataforma</label>
        <select name="platform" class="rounded-md border-zinc-300 text-sm">
            <option value="">Todas</option>
            @foreach ($platforms as $p)
                <option value="{{ $p }}" @selected(request('platform') === $p)>{{ $p }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-xs font-medium text-zinc-600 mb-1">Región</label>
        <select name="region" class="rounded-md border-zinc-300 text-sm">
            <option value="">Todas</option>
            @foreach ($regions as $r)
                <option value="{{ $r }}" @selected(request('region') === $r)>{{ $r }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-xs font-medium text-zinc-600 mb-1">Tipo</label>
        <select name="type" class="rounded-md border-zinc-300 text-sm">
            <option value="">Todos</option>
            @foreach (['INDEPENDIENTE', 'MADRE', 'HIJA'] as $t)
                <option value="{{ $t }}" @selected(request('type') === $t)>{{ $t }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-xs font-medium text-zinc-600 mb-1">Status</label>
        <select name="status" class="rounded-md border-zinc-300 text-sm">
            <option value="">Todos</option>
            @foreach (['active', 'blocked', 'reset', 'archived'] as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
            @endforeach
        </select>
    </div>

    <button type="submit"
            class="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-700">
        Filtrar
    </button>

    @if (request()->hasAny(['search', 'platform', 'region', 'type', 'status']))
        <a href="{{ route('accounts.index') }}"
           class="text-sm text-zinc-500 hover:text-zinc-900 px-2 py-2">Limpiar</a>
    @endif
</form>

{{-- Tabla --}}
<div class="overflow-x-auto rounded-lg border border-zinc-200 bg-white">
    <table class="min-w-full text-sm">
        <thead class="bg-zinc-50 border-b border-zinc-200 text-xs uppercase tracking-wide text-zinc-600">
            <tr>
                <th class="px-4 py-2 text-left font-medium">Email</th>
                <th class="px-4 py-2 text-left font-medium">Juego</th>
                <th class="px-4 py-2 text-left font-medium">Plat.</th>
                <th class="px-4 py-2 text-left font-medium">Región</th>
                <th class="px-4 py-2 text-left font-medium">Tipo</th>
                <th class="px-4 py-2 text-left font-medium">Slots</th>
                <th class="px-4 py-2 text-left font-medium">Llaves</th>
                <th class="px-4 py-2 text-left font-medium">Reset</th>
                <th class="px-4 py-2 text-left font-medium">Status</th>
                <th class="px-2 py-2"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-100">
            @forelse ($accounts as $a)
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
                    <td class="px-4 py-2.5 font-mono text-xs">
                        <a href="{{ route('accounts.show', $a) }}" class="hover:underline">{{ $a->email }}</a>
                        @if ($a->gamer_tag)
                            <div class="text-xs text-zinc-400">{{ $a->gamer_tag }}</div>
                        @endif
                    </td>
                    <td class="px-4 py-2.5">
                        @if ($a->game)
                            <div class="max-w-[200px] truncate">{{ $a->game->canonical_name }}</div>
                        @else
                            <span class="text-zinc-400">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-2.5">
                        <span class="font-mono text-xs px-1.5 py-0.5 rounded bg-zinc-100">{{ $a->platform }}</span>
                    </td>
                    <td class="px-4 py-2.5 text-xs">{{ $a->region }}</td>
                    <td class="px-4 py-2.5 text-xs">{{ $a->account_type }}</td>
                    <td class="px-4 py-2.5 font-mono text-xs">
                        <span class="{{ $used >= $capacity ? 'text-red-600' : ($used > 0 ? 'text-amber-600' : 'text-emerald-600') }}">
                            {{ $used }}/{{ $capacity }}
                        </span>
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

@endsection
