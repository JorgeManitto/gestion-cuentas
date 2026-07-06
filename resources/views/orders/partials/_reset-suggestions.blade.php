{{-- resources/views/orders/partials/_reset-suggestions.blade.php

     Sección de sugerencias: cuentas compatibles con cada item que están llenas
     pero ya son reseteables. Resetear una libera cupos para poder asignar.

     Espera:
       $order        → la orden
       $suggestions  → array [item_id => Collection<Account>] (solo items con sugerencias)
--}}
@php
$suggestions = $suggestions ?? [];
@endphp

@if (! empty($suggestions))
<div class="mt-8 rounded-lg border border-amber-200 bg-amber-50/40 p-5">
    <h2 class="text-lg font-semibold text-amber-900">Sugerencias: cuentas para resetear</h2>
    <p class="text-sm text-amber-800/80 mt-1 mb-4">
        Estos ítems no tienen cupos libres ahora, pero hay cuentas compatibles que ya cumplen
        la ventana de reseteo. Podés resetear una para liberar cupos y luego asignar.
    </p>

    <div class="space-y-5">
        @foreach ($order->items as $item)
            @php
                $itemSuggestions = $suggestions[$item->id] ?? null;
            @endphp
            @if ($itemSuggestions && $itemSuggestions->isNotEmpty())
                <div>
                    <div class="text-sm font-medium text-zinc-700 mb-2">
                        {{ $item->game_title }}
                        <span class="text-xs font-normal text-zinc-500">· {{ $item->platform_normalized }}</span>
                        <span class="text-xs font-normal text-amber-600">· {{ $itemSuggestions->count() }} reseteable(s)</span>
                    </div>

                    <div class="overflow-x-auto rounded-md border border-amber-200 bg-white">
                        <table class="min-w-full text-sm">
                            <thead class="bg-amber-50 text-left text-amber-700">
                                <tr>
                                    <th class="px-3 py-2 font-medium">Cuenta</th>
                                    <th class="px-3 py-2 font-medium">Plataforma</th>
                                    <th class="px-3 py-2 font-medium">Cupos post-reset</th>
                                    <th class="px-3 py-2 font-medium">Antigüedad</th>
                                    <th class="px-3 py-2 font-medium text-right">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-amber-100">
                                @foreach ($itemSuggestions as $acc)
                                    @php
                                        $ref     = $acc->stockRotationReference();
                                        $source  = $acc->stockRotationSource();   // 'reset' | 'compra' | null
                                        $ageDays = $acc->stockRotationAgeInDays();
                                    @endphp
                                    <tr class="hover:bg-amber-50/60">
                                        <td class="px-3 py-2">
                                            <a href="{{ route('accounts.show', $acc) }}"
                                               class="font-medium hover:underline">{{ $acc->email }}</a>
                                        </td>
                                        <td class="px-3 py-2">
                                            {{ $acc->platform }}
                                            @if ($acc->is_dual)
                                                <span class="text-xs text-zinc-400">(dual)</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2">
                                            {{ $acc->maxAfterReset() }}
                                            <span class="text-xs text-zinc-400">cupos</span>
                                        </td>
                                        <td class="px-3 py-2">
                                            @if ($ageDays !== null)
                                                {{ intdiv($ageDays, 30) }} m
                                                <span class="text-xs text-zinc-400">
                                                    ({{ $source }} {{ $ref?->format('d/m/Y') }})
                                                </span>
                                            @else
                                                <span class="text-zinc-400">sin referencia</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            @php
                                                $key = $acc->nextAvailableKey();
                                                $resetPayload = [
                                                    'resetUrl'    => route('accounts.reset', $acc),
                                                    'email'       => $acc->email,
                                                    'password'    => $acc->password,
                                                    'keyId'       => $key?->id,
                                                    'keyValue'    => $key?->key_value,
                                                    'keyPosition' => $key?->position,
                                                ];
                                            @endphp
                                            <button type="button"
                                                    onclick='openResetModal(@json($resetPayload))'
                                                    class="rounded-md bg-amber-500 hover:bg-amber-600 text-white px-3 py-1.5 text-xs font-medium">
                                                Resetear
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @endforeach
    </div>
</div>
@endif