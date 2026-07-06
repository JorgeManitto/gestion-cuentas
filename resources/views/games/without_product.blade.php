@extends('layouts.app')
@section('title', 'Juegos sin producto')

@section('content')

<div class="mb-4 flex items-center justify-between">
    <div>
        <h1 class="text-xl font-semibold">Juegos sin producto</h1>
        <p class="mt-1 text-sm text-zinc-500">
            Juegos con <code class="rounded bg-zinc-100 px-1 py-0.5 text-xs">canonical_name</code>
            pero sin ningún producto de WooCommerce asociado.
        </p>
    </div>
    <a href="{{ route('accounts.index') }}"
       class="rounded-md border border-zinc-200 px-3 py-1.5 text-sm font-medium text-zinc-600 hover:bg-zinc-100">
        ← Cuentas
    </a>
</div>

{{-- Stat --}}
<div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-4">
    <div class="rounded-lg border border-zinc-200 bg-white p-4">
        <div class="flex items-center justify-between">
            <span class="text-xs font-medium uppercase tracking-wide text-zinc-500">Sin producto</span>
            <span class="h-2 w-2 rounded-full bg-amber-500"></span>
        </div>
        <div class="mt-2 font-mono text-2xl font-semibold">{{ number_format($total) }}</div>
    </div>
</div>

{{-- Buscar --}}
<form method="GET" class="mb-6">
    <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
        <label class="mb-1.5 block text-xs font-medium text-zinc-600">Buscar</label>
        <div class="flex gap-2">
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="nombre, normalizado o slug…"
                   class="w-full rounded-lg border-zinc-300 bg-zinc-50 px-3 py-2 text-sm
                          transition focus:border-zinc-900 focus:bg-white focus:ring-1 focus:ring-zinc-900">
            <button type="submit"
                    class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-700">
                Filtrar
            </button>
            @if (request('search'))
                <a href="{{ route('games.without-product') }}"
                   class="rounded-lg border border-zinc-200 px-3 py-2 text-sm text-zinc-500 hover:bg-zinc-100">
                    Limpiar
                </a>
            @endif
        </div>
    </div>
</form>

{{-- Tabla --}}
<div class="overflow-x-auto rounded-lg border border-zinc-200 bg-white">
    <table class="min-w-full text-sm">
        <thead class="border-b border-zinc-200 bg-zinc-50 text-xs uppercase tracking-wide text-zinc-600">
            <tr>
                <th class="px-4 py-2 text-left font-medium">Juego (canonical_name)</th>
                <th class="px-4 py-2 text-left font-medium">Normalizado</th>
                <th class="px-4 py-2 text-left font-medium">Slug</th>
                <th class="px-4 py-2 text-left font-medium">Cuentas</th>
                <th class="px-2 py-2"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-100">
            @forelse ($games as $g)
                <tr class="hover:bg-zinc-50">
                    <td class="px-4 py-2.5 font-medium">{{ $g->canonical_name }}</td>
                    <td class="px-4 py-2.5 font-mono text-xs text-zinc-500">{{ $g->normalized_name }}</td>
                    <td class="px-4 py-2.5 font-mono text-xs text-zinc-400">{{ $g->slug }}</td>
                    <td class="px-4 py-2.5 font-mono text-xs">{{ $g->accounts_count }}</td>
                    <td class="px-2 py-2.5 text-right">
                        @if ($g->accounts_count > 0)
                            <a href="{{ route('accounts.index', ['game_id' => $g->id]) }}"
                               class="text-xs text-zinc-500 hover:text-zinc-900">ver cuentas</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-12 text-center text-zinc-500">
                        No hay juegos sin producto: todos tienen al menos un producto de WooCommerce.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $games->links() }}
</div>

@endsection
