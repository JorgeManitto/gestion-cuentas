@extends('layouts.app')

@section('title', 'Juegos')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Juegos</h1>
            <p class="text-sm text-zinc-500 mt-1">
                {{ $games->total() }} {{ Str::plural('juego', $games->total()) }} en total
            </p>
        </div>

        <form method="GET" action="{{ route('games.index') }}" class="flex gap-2">
            <input
                type="text"
                name="q"
                value="{{ $search }}"
                placeholder="Buscar por nombre o slug..."
                class="w-72 rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-zinc-900 focus:outline-none focus:ring-1 focus:ring-zinc-900"
            >
            @if ($search !== '')
                <a href="{{ route('games.index') }}"
                   class="text-sm text-zinc-500 hover:text-zinc-900 self-center">
                    Limpiar
                </a>
            @endif
        </form>
    </div>

    @if ($games->isEmpty())
        <div class="bg-white border border-zinc-200 rounded-md p-12 text-center">
            <p class="text-sm text-zinc-500">
                @if ($search !== '')
                    No se encontraron juegos para "<span class="font-mono">{{ $search }}</span>".
                @else
                    Todavía no hay juegos sincronizados.
                @endif
            </p>
        </div>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
            @foreach ($games as $game)
                <a href="{{ route('games.show', $game) }}"
                   class="group block bg-white border border-zinc-200 rounded-md overflow-hidden hover:border-zinc-900 transition">
                    <div class="aspect-square bg-zinc-100 flex items-center justify-center overflow-hidden">
                        @if ($game->cover_image_url)
                            <img
                                src="{{ $game->cover_image_url }}"
                                alt="{{ $game->canonical_name }}"
                                loading="lazy"
                                class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                            >
                        @else
                            <span class="font-mono text-xs text-zinc-400">SIN IMAGEN</span>
                        @endif
                    </div>
                    <div class="p-3">
                        <p class="text-sm font-medium text-zinc-900 line-clamp-2 leading-snug">
                            {{ $game->canonical_name }}
                        </p>
                        <p class="mt-1 text-xs text-zinc-500 font-mono">
                            {{ $game->products_count }} {{ Str::plural('producto', $game->products_count) }}
                            · {{ $game->accounts_count }} {{ Str::plural('cuenta', $game->accounts_count) }}
                        </p>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-8">
            {{ $games->links() }}
        </div>
    @endif
@endsection