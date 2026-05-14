@extends('layouts.app')
@section('title', 'Orders')

@section('content')

{{-- Stats — basadas en items, no en orders, porque un mismo order puede tener varios ítems en estados distintos --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
    @foreach ([
        'total'       => ['Orders totales',     'zinc',    'total'],
        'pending'     => ['Items pendientes',   'amber',   'pending'],
        'in_progress' => ['En proceso',         'blue',    'in_progress'],
        'delivered'   => ['Entregados',         'emerald', 'delivered'],
    ] as $key => [$label, $color, $statName])
        <div class="rounded-lg border border-zinc-200 bg-white p-4">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ $label }}</span>
                <span class="h-2 w-2 rounded-full bg-{{ $color }}-500"></span>
            </div>
            <div class="mt-2 font-mono text-2xl font-semibold">{{ number_format($stats[$statName]) }}</div>
        </div>
    @endforeach
</div>

{{-- Filtros --}}
<form method="GET" class="mb-4 flex gap-2 items-end">
    <div class="flex-1">
        <label class="block text-xs font-medium text-zinc-600 mb-1">Buscar</label>
        <input type="text" name="search" value="{{ request('search') }}"
               placeholder="email, nombre, order ID…"
               class="w-full rounded-md border-zinc-300 text-sm font-mono">
    </div>

    <div>
        <label class="block text-xs font-medium text-zinc-600 mb-1">Status (Woo)</label>
        <select name="wc_status" class="rounded-md border-zinc-300 text-sm">
            <option value="">Todos</option>
            @foreach (['pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed'] as $s)
                <option value="{{ $s }}" @selected(request('wc_status') === $s)>{{ $s }}</option>
            @endforeach
        </select>
    </div>

    <button type="submit"
            class="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-700">
        Filtrar
    </button>

    @if (request()->hasAny(['search', 'wc_status']))
        <a href="{{ route('orders.index') }}"
           class="text-sm text-zinc-500 hover:text-zinc-900 px-2 py-2">Limpiar</a>
    @endif
</form>

<div class="overflow-x-auto rounded-lg border border-zinc-200 bg-white">
    <table class="min-w-full text-sm">
        <thead class="bg-zinc-50 border-b border-zinc-200 text-xs uppercase tracking-wide text-zinc-600">
            <tr>
                <th class="px-4 py-2 text-left font-medium">Order</th>
                <th class="px-4 py-2 text-left font-medium">Cliente</th>
                <th class="px-4 py-2 text-left font-medium">Items</th>
                <th class="px-4 py-2 text-left font-medium">Total</th>
                <th class="px-4 py-2 text-left font-medium">Status Woo</th>
                <th class="px-4 py-2 text-left font-medium">Fulfillment</th>
                <th class="px-4 py-2 text-left font-medium">Fecha</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-100">
            @forelse ($orders as $o)
                @php
                    $wcColor = $o->wcStatusColor();
                    $fColor  = $o->fulfillmentColor();
                    $fLabel  = $o->fulfillmentSummary();
                @endphp
                <tr class="hover:bg-zinc-50 cursor-pointer"
                    onclick="window.location='{{ route('orders.show', $o) }}'">
                    <td class="px-4 py-2.5 font-mono text-xs">
                        <a href="{{ route('orders.show', $o) }}" class="hover:underline">
                            #{{ $o->wc_order_id }}
                        </a>
                    </td>
                    <td class="px-4 py-2.5">
                        <div class="font-medium">{{ $o->customer_name }}</div>
                        <div class="text-xs text-zinc-500 font-mono">{{ $o->customer_email }}</div>
                    </td>
                    <td class="px-4 py-2.5 text-xs text-zinc-600">
                        <span class="font-mono font-medium">{{ $o->items_count }}</span> ítem(s)
                        <div class="text-xs text-zinc-500 max-w-[200px] truncate">
                            @foreach ($o->items->take(2) as $it)
                                {{ $it->game_title }}{{ ! $loop->last ? ', ' : '' }}
                            @endforeach
                            @if ($o->items_count > 2) +{{ $o->items_count - 2 }} más @endif
                        </div>
                    </td>
                    <td class="px-4 py-2.5 font-mono text-xs">
                        @if ($o->total_amount)
                            {{ $o->currency }} {{ number_format($o->total_amount, 2) }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-4 py-2.5">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-{{ $wcColor }}-50 px-2 py-0.5 text-xs font-medium text-{{ $wcColor }}-700 ring-1 ring-inset ring-{{ $wcColor }}-600/20">
                            <span class="h-1.5 w-1.5 rounded-full bg-{{ $wcColor }}-500"></span>
                            {{ $o->wc_status }}
                        </span>
                    </td>
                    <td class="px-4 py-2.5">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-{{ $fColor }}-50 px-2 py-0.5 text-xs font-medium text-{{ $fColor }}-700 ring-1 ring-inset ring-{{ $fColor }}-600/20">
                            <span class="h-1.5 w-1.5 rounded-full bg-{{ $fColor }}-500"></span>
                            {{ str_replace('_', ' ', $fLabel) }}
                        </span>
                    </td>
                    <td class="px-4 py-2.5 text-xs text-zinc-500 font-mono">
                        {{ $o->order_date->format('Y-m-d H:i') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-4 py-12 text-center text-zinc-500">
                        No hay orders. Cuando llegue el primer webhook del plugin, va a aparecer acá.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $orders->links() }}</div>

@endsection
