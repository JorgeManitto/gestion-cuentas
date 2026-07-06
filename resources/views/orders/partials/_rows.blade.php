@forelse ($orders as $o)
    @php
        $fColor = $o->statusColor();
        $fLabel = $o->statusLabel();

        // Color por consola (heurística por substring, case-insensitive)
        $consoleColor = function (string $c) {
            $c = strtolower($c);
            return match (true) {
                str_contains($c, 'playstation'), str_contains($c, 'ps') => 'blue',
                str_contains($c, 'xbox')                                => 'green',
                str_contains($c, 'switch'), str_contains($c, 'nintendo') => 'red',
                str_contains($c, 'pc'), str_contains($c, 'steam')        => 'violet',
                default                                                  => 'zinc',
            };
        };
        $consoles = $o->items->pluck('platform_normalized')->filter()->unique()->values();
    @endphp
    <tr class="hover:bg-zinc-50 cursor-pointer"
        onclick="window.location='{{ route('orders.show', $o) }}'">
        <td class="px-4 py-2.5 align-middle" onclick="event.stopPropagation()">
            <input type="checkbox" value="{{ $o->id }}" class="row-check rounded border-zinc-300">
        </td>
        <td class="px-4 py-2.5 font-mono text-xs align-middle">
            <a href="{{ route('orders.show', $o) }}" class="hover:underline">#{{ $o->wc_order_id }}</a>
        </td>
        <td class="px-4 py-2.5 align-middle">
            <div class="flex items-center gap-3">
                <div class="flex -space-x-2 shrink-0">
                    @foreach ($o->items->take(3) as $it)
                        @if ($it->wooProduct?->image_url)
                            <img src="{{ $it->wooProduct->image_url }}" alt="{{ $it->game_title }}" title="{{ $it->game_title }}"
                                 class="h-10 w-10 rounded object-cover ring-2 ring-white bg-zinc-100">
                        @else
                            <div class="h-10 w-10 rounded bg-zinc-200 ring-2 ring-white flex items-center justify-center text-[10px] text-zinc-500 font-mono">?</div>
                        @endif
                    @endforeach
                    @if ($o->items_count > 3)
                        <div class="h-10 w-10 rounded bg-zinc-100 ring-2 ring-white flex items-center justify-center text-[10px] text-zinc-600 font-mono">+{{ $o->items_count - 3 }}</div>
                    @endif
                </div>
                <div class="flex flex-col min-w-0">
                    <span class="text-xs font-mono font-medium text-zinc-700">{{ $o->items_count }} ítem(s)</span>
                    <span class="text-xs text-zinc-500 max-w-[220px] truncate">
                        @foreach ($o->items->take(2) as $it){{ $it->game_title }}{{ ! $loop->last ? ', ' : '' }}@endforeach
                        @if ($o->items_count > 2) +{{ $o->items_count - 2 }} más @endif
                    </span>
                </div>
            </div>
        </td>

        <td class="px-3 py-2">
            @include('orders.partials._matchmaking-status', ['order' => $o])
        </td>
        {{-- Consola --}}
        <td class="px-4 py-2.5 align-middle">
            @if ($consoles->isNotEmpty())
                <div class="flex flex-wrap gap-1">
                    @foreach ($consoles as $console)
                        @php $cc = $consoleColor($console); @endphp
                        <span class="inline-flex items-center gap-1 rounded-full bg-{{ $cc }}-50 px-2 py-0.5 text-xs font-medium text-{{ $cc }}-700 ring-1 ring-inset ring-{{ $cc }}-600/20">
                            <span class="h-1.5 w-1.5 rounded-full bg-{{ $cc }}-500"></span>
                            {{ $console }}
                        </span>
                    @endforeach
                </div>
            @else
                <span class="text-xs text-zinc-400">—</span>
            @endif
        </td>

        <td class="px-4 py-2.5 align-middle">
            <div class="font-medium">{{ $o->customer_name }}</div>
            <div class="text-xs text-zinc-500 font-mono">{{ $o->customer_email }}</div>
        </td>
        <td class="px-4 py-2.5 align-middle">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-{{ $fColor }}-50 px-2 py-0.5 text-xs font-medium text-{{ $fColor }}-700 ring-1 ring-inset ring-{{ $fColor }}-600/20">
                <span class="h-1.5 w-1.5 rounded-full bg-{{ $fColor }}-500"></span>
                {{ str_replace('_', ' ', $fLabel) }}
            </span>
        </td>
        <td class="px-4 py-2.5 text-xs text-zinc-500 font-mono align-middle">{{ $o->order_date->format('Y-m-d H:i') }}</td>
    </tr>
@empty
    <tr>
        <td colspan="7" class="px-4 py-12 text-center text-zinc-500">
            No hay orders. Cuando llegue el primer webhook del plugin, va a aparecer acá.
        </td>
    </tr>
@endforelse