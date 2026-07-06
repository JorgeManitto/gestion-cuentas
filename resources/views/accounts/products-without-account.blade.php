@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800">Productos sin cuenta</h1>
            <p class="text-sm text-gray-500">
                Productos de PlayStation (PS4/PS5) que no tienen ninguna cuenta cubriéndolos.
            </p>
        </div>

        <a href="{{ route('accounts.products-without-account.export', request()->query()) }}"
           class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
            Exportar CSV
        </a>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-xs uppercase tracking-wide text-gray-500">Total</div>
            <div class="mt-1 text-2xl font-semibold text-gray-800">{{ $stats['total'] }}</div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-xs uppercase tracking-wide text-gray-500">PS4</div>
            <div class="mt-1 text-2xl font-semibold text-gray-800">{{ $stats['ps4'] }}</div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-xs uppercase tracking-wide text-gray-500">PS5</div>
            <div class="mt-1 text-2xl font-semibold text-gray-800">{{ $stats['ps5'] }}</div>
        </div>
    </div>

    {{-- Filtros --}}
    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-[220px]">
            <label class="block text-xs font-medium text-gray-600 mb-1">Buscar</label>
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Nombre de producto o juego…"
                   class="w-full rounded-lg border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
        </div>

        <div class="min-w-[160px]">
            <label class="block text-xs font-medium text-gray-600 mb-1">Plataforma</label>
            <select name="platform"
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                <option value="">Todas</option>
                @foreach ($targetPlatforms as $p)
                    <option value="{{ $p }}" @selected(request('platform') === $p)>{{ $p }}</option>
                @endforeach
            </select>
        </div>

        <button type="submit"
                class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-900">
            Filtrar
        </button>

        @if (request()->hasAny(['search', 'platform']))
            <a href="{{ route('accounts.products-without-account') }}"
               class="text-sm text-gray-500 hover:text-gray-700 underline">Limpiar</a>
        @endif
    </form>

    {{-- Tabla --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <th class="px-4 py-3 w-16"></th>
                    <th class="px-4 py-3">Producto</th>
                    <th class="px-4 py-3">Juego</th>
                    <th class="px-4 py-3">Plataforma</th>
                    <th class="px-4 py-3 w-24">Product ID</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($products as $product)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            @if ($product->image_url)
                                <img src="{{ $product->image_url }}" alt=""
                                     class="h-10 w-10 rounded object-cover bg-gray-100">
                            @else
                                <div class="h-10 w-10 rounded bg-gray-100"></div>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $product->name }}</td>
                        <td class="px-4 py-3">
                            @if ($product->game)
                                <a href="{{ route('accounts.index', ['search' => $product->game->canonical_name]) }}"
                                target="_blank"
                                class="inline-flex items-center gap-1 text-emerald-700 hover:text-emerald-800 hover:underline">
                                    {{ $product->game->canonical_name }}
                                    <svg class="h-3.5 w-3.5 opacity-60" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                    </svg>
                                </a>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700">
                                {{ $product->normalized_platform }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-400">{{ $product->id }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-gray-400">
                            No hay productos sin cuenta con estos filtros. 🎉
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Paginación --}}
    <div class="mt-4">
        {{ $products->links() }}
    </div>

</div>
@endsection