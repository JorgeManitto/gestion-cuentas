@php
    $mm = $order->mm_status ?? ['state'=>'na','label'=>'—','color'=>'zinc','ready'=>0,'oc'=>0,'missing'=>0];

    $classes = match ($mm['color']) {
        'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'blue'    => 'bg-yellow-50 text-yellow-700 ring-yellow-600/20',
        'red'     => 'bg-red-50 text-red-700 ring-red-600/20',
        default   => 'bg-zinc-100 text-zinc-600 ring-zinc-500/10',
    };

    // Detalle solo cuando hay mezcla de estados en la misma orden
    $extra = collect([
        $mm['ready']   ? $mm['ready'].' lista'.($mm['ready']===1?'':'s') : null,
        $mm['oc']      ? $mm['oc'].' OC'                                 : null,
        $mm['missing'] ? $mm['missing'].' sin cuenta'                    : null,
    ])->filter();
    $showExtra = $extra->count() > 1;
@endphp

<div class="flex flex-col gap-0.5">
    <span class="inline-flex w-fit items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $classes }}">
        {{ $mm['label'] }}
    </span>
    @if ($showExtra)
        <span class="text-[11px] text-zinc-400">{{ $extra->implode(' · ') }}</span>
    @endif
</div>