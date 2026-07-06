@foreach ([
    'total'       => ['Orders totales',   'zinc',    'total'],
    'pending'     => ['Items pendientes', 'amber',   'pending'],
    'in_progress' => ['En proceso',       'blue',    'in_progress'],
    'delivered'   => ['Entregados',       'emerald', 'delivered'],
] as $key => [$label, $color, $statName])
    <div class="rounded-lg border border-zinc-200 bg-white p-4">
        <div class="flex items-center justify-between">
            <span class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ $label }}</span>
            <span class="h-2 w-2 rounded-full bg-{{ $color }}-500"></span>
        </div>
        <div class="mt-2 font-mono text-2xl font-semibold">{{ number_format($stats[$statName]) }}</div>
    </div>
@endforeach