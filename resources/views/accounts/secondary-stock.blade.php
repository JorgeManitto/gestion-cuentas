{{--
    Listado de cuentas con su estado de elegibilidad de STOCK SECUNDARIO.
    Ruta: GET /accounts/secondary-stock  →  AccountController@secondaryStock

    Nota: las clases son Tailwind (default de Laravel). Adaptalas a tu design system
    si usás Bootstrap u otro. Si tu layout no es 'layouts.app' / @section('content'),
    ajustá las dos líneas de abajo.
--}}
@extends('layouts.app')

@section('content')
<div class="mx-auto py-6">

    <div class="mb-6">
        <h1 class="text-xl font-semibold text-gray-900">Stock secundario · elegibilidad</h1>
        <p class="mt-1 text-sm text-gray-500">
            Una cuenta habilita stock secundario solo si <strong>todos los cupos principales están completos</strong>
            y pasaron al menos <strong>2 meses desde la última venta principal</strong>.
        </p>
    </div>

    {{-- Filtros --}}
    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-gray-600">Plataforma</label>
            <select name="platform" class="mt-1 rounded-md border-gray-300 text-sm">
                <option value="">Todas</option>
                @foreach ($platforms as $p)
                    <option value="{{ $p }}" @selected(request('platform') === $p)>{{ $p }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600">Buscar</label>
            <input type="text" name="search" value="{{ request('search') }}"
                placeholder="email, gamer tag o producto"
                class="mt-1 rounded-md border-gray-300 text-sm">
        </div>
        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" name="eligible" value="1" @checked(request()->boolean('eligible'))
                   class="rounded border-gray-300">
            Solo habilitadas
        </label>
        <button class="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-medium text-white">Filtrar</button>
    </form>

    <div class="overflow-x-auto rounded-lg border border-gray-200">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3"></th>   {{-- imagen --}}
                    <th class="px-4 py-3">Cuenta</th>
                    <th class="px-4 py-3">Plataforma</th>
                    <th class="px-4 py-3">Cupos principales</th>
                    <th class="px-4 py-3">Venta (2 meses)</th>
                    <th class="px-4 py-3">Estado secundario</th>
                    <th class="px-4 py-3">Detalle</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @forelse ($accounts as $account)
                    @php($elig = $account->secondary ?? [])
                    @php($cover = $account->coverProduct())
                    <tr class="{{ $elig['eligible'] ? 'bg-white' : '' }}">
                        <td class="px-4 py-3">
                            @if ($cover?->image_url)
                                <img src="{{ $cover->image_url }}" alt=""
                                    class="w-10 h-14 object-cover rounded bg-gray-100"
                                    onerror="this.style.display='none'">
                            @else
                                <div class="w-10 h-14 rounded bg-gray-100"></div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('accounts.show', $account) }}" target="blank" class="font-medium text-gray-900 hover:underline">
                                {{ $account->email }}
                            </a>
                            @if ($account->game)
                                <div class="text-xs text-gray-500">{{ $account->game->displayName() }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-700">{{ $account->platform }}</td>
                        {{-- Cupos principales --}}
                        <td class="px-4 py-3">
                            @if (data_get($elig, 'primary_full'))
                                <span class="text-green-700">Completos</span>
                            @else
                                <span class="text-gray-500">Con cupos libres</span>
                            @endif
                        </td>

                        {{-- Venta (2 meses) --}}
                        <td class="px-4 py-3">
                            @if (data_get($elig, 'last_primary_sale'))
                                {{ $elig['last_primary_sale']->format('Y-m-d') }}
                                <span class="text-xs text-gray-400">({{ data_get($elig, 'months_since_sale') }} m)</span>
                            @else
                                <span class="text-gray-400">Sin registro</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @include('accounts.partials.secondary-badge', ['eligibility' => $elig])
                        </td>
                        {{-- Detalle --}}
                        <td class="px-4 py-3 text-xs text-gray-500">
                            @if (data_get($elig, 'eligible'))
                                Cumple ambas condiciones.
                            @else
                                {{ implode(' ', data_get($elig, 'reasons', [])) }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">No hay cuentas para mostrar.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $accounts->links() }}
    </div>
</div>
@endsection
