@extends('layouts.app')
@section('title', 'Juegos a corregir')

@section('content')

<div class="mb-4 flex items-center justify-between">
    <div>
        <h1 class="text-xl font-semibold">Juegos a corregir</h1>
        <p class="text-sm text-zinc-500">
            Cuentas cuyo juego no tiene un producto para su plataforma, o sin juego asignado.
        </p>
    </div>
    <a href="{{ route('accounts.index') }}" class="text-sm text-zinc-500 hover:text-zinc-900">← Volver a cuentas</a>
</div>

@if (session('success'))
    <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">
        {{ session('success') }}
    </div>
@endif

<form method="GET" class="mb-4">
    <input type="text" name="search" value="{{ request('search') }}"
           placeholder="Buscar por email o gamer tag…"
           class="w-full max-w-sm rounded-lg border-zinc-300 bg-zinc-50 py-2 px-3 text-sm font-mono
                  transition focus:border-zinc-900 focus:bg-white focus:ring-1 focus:ring-zinc-900">
</form>

<div class="overflow-x-auto rounded-lg border border-zinc-200 bg-white">
    <table class="min-w-full text-sm">
        <thead class="bg-zinc-50 border-b border-zinc-200 text-xs uppercase tracking-wide text-zinc-600">
            <tr>
                <th class="px-4 py-2 text-left font-medium">Juego actual</th>
                <th class="px-4 py-2 text-left font-medium">Email</th>
                <th class="px-4 py-2 text-left font-medium">Plat.</th>
                <th class="px-4 py-2 text-left font-medium">Productos del juego</th>
                <th class="px-2 py-2"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-100">
            @forelse ($accounts as $a)
                @php
                    $firstProduct = $a->game?->products->first();
                    $cover        = $firstProduct?->image_url;
                    $name         = $a->game ? $a->game->displayName() : '— sin juego —';
                    $prodPlatforms = $a->game ? $a->game->products->pluck('platform')->unique()->implode(', ') : '';
                @endphp
                <tr class="hover:bg-zinc-50">
                    <td class="px-4 py-2.5">
                        <div class="flex items-center gap-2 max-w-[260px]">
                            @if ($cover)
                                <img src="{{ $cover }}" alt=""
                                     class="w-8 h-10 object-cover rounded shrink-0 bg-zinc-100"
                                     onerror="this.style.display='none'">
                            @else
                                <div class="w-8 h-10 rounded bg-zinc-100 shrink-0"></div>
                            @endif
                            <div class="truncate {{ $a->game ? '' : 'text-zinc-400' }}">{{ $name }}</div>
                        </div>
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
                    <td class="px-4 py-2.5 text-xs text-zinc-500">{{ $prodPlatforms ?: '—' }}</td>
                    <td class="px-2 py-2.5 text-right">
                        <a href="{{ route('accounts.reassign.form', $a) }}"
                           class="inline-flex rounded-md bg-zinc-900 px-2.5 py-1 text-xs font-medium text-white hover:bg-zinc-700">
                            Corregir
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-12 text-center text-zinc-500">
                        No hay cuentas para corregir 🎉
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
