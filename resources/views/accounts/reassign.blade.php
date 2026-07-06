@extends('layouts.app')
@section('title', 'Reasignar juego')

@section('content')

@php
    $current      = $account->game;
    $currentCover = $current?->products->first()?->image_url;
@endphp

<div class="mb-4 flex items-center justify-between">
    <h1 class="text-xl font-semibold">Reasignar juego</h1>
    <a href="{{ route('accounts.mismatched') }}" class="text-sm text-zinc-500 hover:text-zinc-900">← Volver</a>
</div>

{{-- Cuenta --}}
<div class="mb-6 rounded-lg border border-zinc-200 bg-white p-4">
    <div class="mb-2 text-xs uppercase tracking-wide text-zinc-500">Cuenta</div>
    <div class="font-mono text-sm">{{ $account->email }}</div>
    <div class="mt-1 text-xs text-zinc-500">
        Plataforma:
        <span class="font-mono px-1.5 py-0.5 rounded bg-zinc-100">{{ $account->platform }}</span>
        · Juego actual: <strong>{{ $current ? $current->displayName() : '— sin juego —' }}</strong>
    </div>
</div>

{{-- Buscador --}}
<form method="GET" class="mb-4">
    <div class="flex gap-2">
        <input type="text" name="q" value="{{ $q }}"
               placeholder="Buscar juego por nombre…"
               class="w-full max-w-md rounded-lg border-zinc-300 bg-zinc-50 py-2 px-3 text-sm
                      transition focus:border-zinc-900 focus:bg-white focus:ring-1 focus:ring-zinc-900">
        <button class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-700">
            Buscar
        </button>
    </div>
</form>

{{-- Resultados --}}
<div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
    @forelse ($games as $g)
        @php
            $cover     = $g->products->first()?->image_url;
            $platforms = $g->products->pluck('platform')->unique();
            $hasWanted = $platforms->contains(function ($p) use ($wantPlatform) {
                $pp = str_replace([' ', '-', '|', '/'], '', strtolower($p));
                if ($pp === 'xboxseriesxs') $pp = 'xboxseries';
                return $pp === $wantPlatform;
            });
            $isCurrent = $account->game_id === $g->id;
        @endphp
        <div class="rounded-lg border p-3 {{ $hasWanted ? 'border-emerald-300 bg-emerald-50/40' : 'border-zinc-200 bg-white' }}">
            <div class="flex items-start gap-3">
                @if ($cover)
                    <img src="{{ $cover }}" alt=""
                         class="w-10 h-14 object-cover rounded shrink-0 bg-zinc-100"
                         onerror="this.style.display='none'">
                @else
                    <div class="w-10 h-14 rounded bg-zinc-100 shrink-0"></div>
                @endif
                <div class="min-w-0">
                    <div class="truncate text-sm font-medium">{{ $g->displayName() }}</div>
                    <div class="mt-1 flex flex-wrap gap-1">
                        @foreach ($platforms as $p)
                            <span class="rounded bg-zinc-100 px-1.5 py-0.5 font-mono text-[10px]">{{ $p }}</span>
                        @endforeach
                    </div>
                    @if ($hasWanted)
                        <div class="mt-1 text-[11px] text-emerald-700">✓ tiene producto {{ $account->platform }}</div>
                    @endif
                </div>
            </div>

            <form method="POST" action="{{ route('accounts.reassign', $account) }}" class="mt-3">
                @csrf
                <input type="hidden" name="game_id" value="{{ $g->id }}">
                <button @disabled($isCurrent)
                    class="w-full rounded-md px-2.5 py-1.5 text-xs font-medium
                           {{ $isCurrent ? 'bg-zinc-200 text-zinc-500 cursor-default' : 'bg-zinc-900 text-white hover:bg-zinc-700' }}">
                    {{ $isCurrent ? 'Juego actual' : 'Asignar este' }}
                </button>
            </form>
        </div>
    @empty
        <div class="col-span-full rounded-lg border border-zinc-200 bg-white p-6 text-center text-sm text-zinc-500">
            No se encontraron juegos para “{{ $q }}”.
        </div>
    @endforelse
</div>

{{-- Quitar juego --}}
<form method="POST" action="{{ route('accounts.reassign', $account) }}" class="mt-6">
    @csrf
    <input type="hidden" name="game_id" value="">
    <button class="rounded-lg border border-zinc-200 px-3 py-2 text-sm text-zinc-500
                   transition hover:bg-zinc-100 hover:text-zinc-900">
        Quitar juego (dejar sin asignar)
    </button>
</form>

@endsection
