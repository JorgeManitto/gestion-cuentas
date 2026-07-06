@extends('layouts.app')

@section('title', $game->canonical_name)

@section('content')
    <div class="mb-6">
        <a href="{{ route('games.index') }}"
           class="text-sm text-zinc-500 hover:text-zinc-900 inline-flex items-center gap-1">
            ← Volver a juegos
        </a>
    </div>

    <div class="flex gap-6 mb-8">
        <div class="w-48 h-48 flex-shrink-0 bg-zinc-100 border border-zinc-200 rounded-md overflow-hidden flex items-center justify-center">
            @if ($game->cover_image_url)
                <img
                    src="{{ $game->cover_image_url }}"
                    alt="{{ $game->canonical_name }}"
                    class="w-full h-full object-cover"
                >
            @else
                <span class="font-mono text-xs text-zinc-400">SIN IMAGEN</span>
            @endif
        </div>

        <div class="flex-1 min-w-0">
            <h1 class="text-2xl font-semibold tracking-tight">{{ $game->canonical_name }}</h1>
            <dl class="mt-3 space-y-1 text-sm">
                <div class="flex gap-2">
                    <dt class="text-zinc-500 w-24">Slug</dt>
                    <dd class="font-mono text-zinc-900">{{ $game->slug }}</dd>
                </div>
                <div class="flex gap-2">
                    <dt class="text-zinc-500 w-24">Normalizado</dt>
                    <dd class="font-mono text-zinc-900">{{ $game->normalized_name }}</dd>
                </div>
                <div class="flex gap-2">
                    <dt class="text-zinc-500 w-24">Productos</dt>
                    <dd class="text-zinc-900">{{ $game->products->count() }}</dd>
                </div>
                <div class="flex gap-2">
                    <dt class="text-zinc-500 w-24">Cuentas</dt>
                    <dd class="text-zinc-900">{{ $game->accounts->count() }}</dd>
                </div>
            </dl>
        </div>
    </div>

    {{-- Productos de WooCommerce --}}
    <section class="mb-8">
        <h2 class="text-sm font-mono font-semibold tracking-tight text-zinc-500 mb-3 uppercase">
            Productos en WooCommerce
        </h2>

        @if ($game->products->isEmpty())
            <div class="bg-white border border-zinc-200 rounded-md p-6 text-center">
                <p class="text-sm text-zinc-500">Este juego no tiene productos asociados.</p>
            </div>
        @else
            <div class="bg-white border border-zinc-200 rounded-md overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-zinc-50 border-b border-zinc-200">
                        <tr class="text-left text-xs uppercase tracking-wide text-zinc-500">
                            <th class="px-4 py-2 font-medium w-16">ID</th>
                            <th class="px-4 py-2 font-medium w-16">Img</th>
                            <th class="px-4 py-2 font-medium">Nombre</th>
                            <th class="px-4 py-2 font-medium w-32">Plataforma</th>
                            <th class="px-4 py-2 font-medium w-40">Última sync</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @foreach ($game->products as $product)
                            <tr class="hover:bg-zinc-50">
                                <td class="px-4 py-2 font-mono text-xs text-zinc-500">
                                    {{ $product->id }}
                                </td>
                                <td class="px-4 py-2">
                                    @if ($product->image_url)
                                        <img src="{{ $product->image_url }}"
                                             alt=""
                                             class="w-10 h-10 object-cover rounded border border-zinc-200">
                                    @else
                                        <div class="w-10 h-10 bg-zinc-100 rounded border border-zinc-200"></div>
                                    @endif
                                </td>
                                <td class="px-4 py-2">{{ $product->name }}</td>
                                <td class="px-4 py-2">
                                    @if ($product->platform)
                                        <span class="inline-flex items-center rounded bg-zinc-100 px-2 py-0.5 text-xs font-mono text-zinc-700">
                                            {{ $product->platform }}
                                        </span>
                                    @else
                                        <span class="text-zinc-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-xs text-zinc-500">
                                    {{ $product->last_synced_at?->diffForHumans() ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- Cuentas vinculadas --}}
    <section>
        <h2 class="text-sm font-mono font-semibold tracking-tight text-zinc-500 mb-3 uppercase">
            Cuentas
        </h2>

        @if ($game->accounts->isEmpty())
            <div class="bg-white border border-zinc-200 rounded-md p-6 text-center">
                <p class="text-sm text-zinc-500">No hay cuentas asociadas a este juego.</p>
            </div>
        @else
            <div class="bg-white border border-zinc-200 rounded-md overflow-hidden">
                <ul class="divide-y divide-zinc-100">
                    @foreach ($game->accounts as $account)
                        <li class="px-4 py-3 flex items-center justify-between hover:bg-zinc-50">
                            <div>
                                <a href="{{ route('accounts.show', $account) }}"
                                   class="text-sm font-medium text-zinc-900 hover:underline">
                                    {{ $account->email ?? "Cuenta #{$account->id}" }}
                                </a>
                            </div>
                            <span class="text-xs text-zinc-500 font-mono">
                                #{{ $account->id }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>
@endsection