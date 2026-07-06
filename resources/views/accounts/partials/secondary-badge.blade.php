{{--
    Badge reutilizable de estado de stock secundario.

    Uso:
        @include('accounts.partials.secondary-badge', ['account' => $account])
    o pasando una elegibilidad ya calculada:
        @include('accounts.partials.secondary-badge', ['eligibility' => $elig])
--}}
@php($elig = $eligibility ?? $account->secondaryEligibility())

@if ($elig['eligible'])
    <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">
        <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>
        Secundario habilitado
    </span>
@else
    <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800"
          title="{{ implode(' ', $elig['reasons']) }}">
        <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
        Secundario bloqueado
    </span>
@endif
