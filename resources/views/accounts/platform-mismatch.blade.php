@extends('layouts.app')
@section('title', 'Cuentas con juego de otra consola')

@section('content')

<div class="mb-4 flex items-start justify-between gap-2">
    <div>
        <a href="{{ route('accounts.index') }}" class="text-sm text-zinc-500 hover:text-zinc-900">← Volver al listado</a>
        <h1 class="text-lg font-semibold mt-1">Cuentas con juego de otra consola</h1>
        <p class="text-sm text-zinc-500 max-w-2xl">
            La plataforma de la cuenta no coincide con la del juego apuntado por
            <span class="font-mono">game_id</span> (ej: cuenta PS4 apuntando a un juego PS5).
            Reasigná cada una al juego correcto.
        </p>
    </div>
    <a href="{{ route('accounts.platform-mismatch.export', request()->query()) }}"
       class="rounded-md bg-zinc-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-zinc-700 shrink-0">
        Exportar CSV
    </a>
</div>

<div class="grid grid-cols-3 gap-4 mb-4">
    <div class="rounded-lg border border-zinc-200 bg-white p-4">
        <div class="text-xs uppercase tracking-wide text-zinc-500">Total</div>
        <div class="text-2xl font-semibold">{{ $stats['total'] }}</div>
    </div>
    <div class="rounded-lg border border-zinc-200 bg-white p-4">
        <div class="text-xs uppercase tracking-wide text-zinc-500">Cuentas PS4</div>
        <div class="text-2xl font-semibold">{{ $stats['ps4'] }}</div>
    </div>
    <div class="rounded-lg border border-zinc-200 bg-white p-4">
        <div class="text-xs uppercase tracking-wide text-zinc-500">Cuentas PS5</div>
        <div class="text-2xl font-semibold">{{ $stats['ps5'] }}</div>
    </div>
</div>

<form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
    <div>
        <label class="block text-xs text-zinc-500 mb-1">Buscar</label>
        <input type="text" name="search" value="{{ request('search') }}"
               placeholder="email, gamer tag o juego"
               class="rounded-md border-zinc-300 text-sm w-64">
    </div>
    <div>
        <label class="block text-xs text-zinc-500 mb-1">Plataforma cuenta</label>
        <select name="platform" class="rounded-md border-zinc-300 text-sm">
            <option value="">Todas</option>
            @foreach ($platforms as $p)
                <option value="{{ $p }}" {{ request('platform') === $p ? 'selected' : '' }}>{{ $p }}</option>
            @endforeach
        </select>
    </div>
    <button type="submit" class="rounded-md bg-zinc-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-zinc-700">
        Filtrar
    </button>
    @if (request('search') || request('platform'))
        <a href="{{ route('accounts.platform-mismatch') }}" class="text-sm text-zinc-500 hover:text-zinc-900">Limpiar</a>
    @endif
</form>

<div class="rounded-lg border border-zinc-200 bg-white overflow-hidden">
    @if ($accounts->isEmpty())
        <div class="px-5 py-10 text-center text-sm text-zinc-500">
            No se encontraron cuentas con este tipo de error.
        </div>
    @else
        <table class="min-w-full text-sm">
            <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-600">
                <tr>
                    <th class="px-4 py-2 text-left font-medium">Cuenta</th>
                    <th class="px-4 py-2 text-left font-medium">Plataforma cuenta</th>
                    <th class="px-4 py-2 text-left font-medium">Juego apuntado</th>
                    <th class="px-4 py-2 text-left font-medium">Plataforma(s) del juego</th>
                    <th class="px-4 py-2 text-left font-medium">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100">
                @foreach ($accounts as $account)
                    <tr>
                        <td class="px-4 py-2">
                            <a href="{{ route('accounts.show', $account) }}" class="font-mono text-xs hover:underline break-all">
                                {{ $account->email }}
                            </a>
                            @if ($account->gamer_tag)
                                <div class="text-xs text-zinc-400 font-mono">{{ $account->gamer_tag }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            <span class="inline-flex rounded bg-zinc-100 px-1.5 py-0.5 font-mono text-xs">{{ $account->platform }}</span>
                        </td>
                        <td class="px-4 py-2">
                            <span class="text-xs">{{ optional($account->game)->canonical_name ?? '—' }}</span>
                            <div class="text-xs text-zinc-400 font-mono">game_id: {{ $account->game_id }}</div>
                        </td>
                        <td class="px-4 py-2">
                            @foreach ($account->game_platforms ?? [] as $gp)
                                <span class="inline-flex rounded bg-red-50 px-1.5 py-0.5 font-mono text-xs text-red-700 ring-1 ring-inset ring-red-600/20">{{ $gp }}</span>
                            @endforeach
                        </td>
                        <td class="px-4 py-2">
                            <a href="{{ route('accounts.reassign', $account) }}"
                               class="rounded-md bg-white px-2 py-1 text-xs font-medium text-zinc-700 ring-1 ring-inset ring-zinc-300 hover:bg-zinc-100">
                                Reasignar
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="mt-4">
    {{ $accounts->links() }}
</div>

@endsection