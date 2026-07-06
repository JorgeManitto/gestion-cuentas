@extends('layouts.app')
@section('title', 'Editar ' . $account->email)

@section('content')

<div class="mb-4">
    <a href="{{ route('accounts.show', $account) }}" class="text-sm text-zinc-500 hover:text-zinc-900">← Volver al detalle</a>
    <h1 class="text-xl font-semibold mt-2 font-mono">{{ $account->email }}</h1>
</div>

<div class="flex gap-4 mb-2">
    <div class="rounded-lg bg-white">
        @include('accounts._secondary-usage', ['account' => $account,'secondaryUsageByPlatform' => $secondaryUsageByPlatform,])
    </div>
</div>

<form method="POST" action="{{ route('accounts.update', $account) }}">
    @csrf
    @method('PUT')
    @if ($account->exists)
        @php
            $capacity     = $account->capacity();
            $usedSlots    = $account->assignments->where('status', 'active')->count();
            $free         = $account->freeSlots();
            $placeholders = $account->assignments
                ->where('status', 'active')
                ->whereNull('customer_name')
                ->whereNull('customer_email');
        @endphp

        <div class="flex gap-4 mb-6">
            <div class="rounded-lg border border-zinc-200 bg-white p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Usos</div>
                    <span class="font-mono text-xs {{ $usedSlots >= $capacity ? 'text-red-600' : ($usedSlots > 0 ? 'text-amber-600' : 'text-emerald-600') }}">
                        {{ $usedSlots }}/{{ $capacity }}
                    </span>
                </div>

                <div class="text-xs text-zinc-500">
                    {{ $free }} slot(s) libre(s)
                    @if ($account->isPostReset())
                        <span class="text-amber-600">· post-reset</span>
                    @endif
                </div>

                @if ($account->canPickUsagePlatform())
                    <div class="space-y-1.5">
                        @foreach ($account->coveredPlatforms() as $plat)
                            @php
                                $platFree        = $account->freeSlotsFor($plat);
                                $platPlaceholder = $account->assignments
                                    ->where('status', 'active')
                                    ->where('platform', $plat)
                                    ->whereNull('customer_name')
                                    ->whereNull('customer_email')
                                    ->isNotEmpty();
                            @endphp
                            <div class="flex items-center gap-2">
                                <span class="w-12 font-mono text-xs font-medium">{{ $plat }}</span>
                                <button type="submit" form="account-usage-decrement-{{ $plat }}"
                                        class="flex-1 rounded-md bg-white px-2 py-1.5 text-xs font-medium text-zinc-700 ring-1 ring-inset ring-zinc-300 hover:bg-zinc-100 disabled:opacity-40 disabled:cursor-not-allowed"
                                        {{ ! $platPlaceholder ? 'disabled' : '' }}>−1</button>
                                <button type="submit" form="account-usage-increment-{{ $plat }}"
                                        class="flex-1 rounded-md bg-zinc-900 px-2 py-1.5 text-xs font-medium text-white hover:bg-zinc-700 disabled:opacity-40 disabled:cursor-not-allowed"
                                        {{ $platFree <= 0 ? 'disabled' : '' }}>+1</button>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex gap-2">
                        <button type="submit" form="account-usage-decrement"
                                class="flex-1 rounded-md bg-white px-2 py-1.5 text-xs font-medium text-zinc-700 ring-1 ring-inset ring-zinc-300 hover:bg-zinc-100 disabled:opacity-40 disabled:cursor-not-allowed"
                                {{ $placeholders->isEmpty() ? 'disabled' : '' }}
                                title="Libera el placeholder más reciente (sin datos de cliente)">
                            −1 uso
                        </button>
                        <button type="submit" form="account-usage-increment"
                                class="flex-1 rounded-md bg-zinc-900 px-2 py-1.5 text-xs font-medium text-white hover:bg-zinc-700 disabled:opacity-40 disabled:cursor-not-allowed"
                                {{ $free <= 0 ? 'disabled' : '' }}>
                            +1 uso
                        </button>
                    </div>
                @endif
            </div>

            <div class="rounded-lg border border-zinc-200 bg-white p-5 space-y-3">
                <div class="flex items-center justify-between">
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Reset</div>
                    @if ($account->isPostReset())
                        <span class="text-xs text-amber-600">post-reset activo</span>
                    @endif
                </div>
                <div class="font-mono text-sm">{{ $account->reset_date?->format('Y-m-d') ?? '—' }}</div>
                @if ($account->isPostReset())
                    <div class="text-xs text-zinc-500">
                        Capacidad reducida: <span class="font-mono">{{ $account->maxAfterReset() }}</span>
                        de {{ $account->initialCapacity() }} originales
                    </div>
                @endif
                @if ($account->canReset())
                    <button type="button" onclick="openResetModal()"
                            class="w-full rounded-md bg-amber-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-amber-700">
                        ⟲ Resetear cuenta
                    </button>
                @else
                    <button type="button" disabled
                            class="w-full rounded-md bg-amber-600/40 px-3 py-1.5 text-sm font-medium text-white cursor-not-allowed"
                            title="Disponible el {{ $account->resetCooldownUntil()->format('Y-m-d') }}">
                        ⟲ Resetear cuenta
                    </button>
                    <div class="text-xs text-zinc-500 mt-1">
                        {{ $account->reset_date ? 'Reset bloqueado' : 'Bloqueado desde la compra' }} ·
                        disponible el
                        <span class="font-mono">{{ $account->resetCooldownUntil()->format('Y-m-d') }}</span>
                    </div>
                @endif
                @error('reset')
                    <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                @enderror
            </div>

            

        </div>
    @endif
    @include('accounts._form')
</form>
    {{-- Acciones operativas: forms separados (NO pueden ir anidados en el form de edición) --}}
    @if ($account->canPickUsagePlatform())
        @foreach ($account->coveredPlatforms() as $plat)
            <form id="account-usage-increment-{{ $plat }}" method="POST"
                action="{{ route('accounts.usage.increment', $account) }}" class="hidden"
                onsubmit="return confirm('¿Agregar 1 uso manual en {{ $plat }}?');">
                @csrf
                <input type="hidden" name="platform" value="{{ $plat }}">
            </form>
            <form id="account-usage-decrement-{{ $plat }}" method="POST"
                action="{{ route('accounts.usage.decrement', $account) }}" class="hidden"
                onsubmit="return confirm('¿Liberar el placeholder más reciente de {{ $plat }}?');">
                @csrf
                <input type="hidden" name="platform" value="{{ $plat }}">
            </form>
        @endforeach
    @else
        <form id="account-usage-increment" method="POST"
            action="{{ route('accounts.usage.increment', $account) }}" class="hidden"
            onsubmit="return confirm('¿Agregar 1 uso manual (placeholder en próximo slot libre)?');">
            @csrf
        </form>
        <form id="account-usage-decrement" method="POST"
            action="{{ route('accounts.usage.decrement', $account) }}" class="hidden"
            onsubmit="return confirm('¿Liberar el placeholder más reciente?\n\nNo borra asignaciones con datos de cliente reales.');">
            @csrf
        </form>
    @endif

    {{-- ════════ MODAL RESET ════════ --}}
@php $nextKey = $account->nextAvailableKey(); @endphp
<div id="reset-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-zinc-900/50" onclick="closeResetModal()"></div>

    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md" onclick="event.stopPropagation()">
            <form method="POST" action="{{ route('accounts.reset', $account) }}">
                @csrf
                <input type="hidden" name="key_id" value="{{ $nextKey?->id }}">

                <div class="px-6 py-4 border-b border-zinc-200">
                    <h3 class="text-lg font-semibold">Resetear cuenta en la plataforma</h3>
                    <p class="text-xs text-zinc-500 font-mono mt-1">{{ $account->email }}</p>
                </div>

                <div class="px-6 py-4 space-y-4 text-sm">
                    <p class="text-zinc-500">
                        Ingresá a la plataforma con estos datos y realizá el reset manualmente.
                        La llave que se muestra se consumirá al confirmar.
                    </p>

                    <div class="rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 font-mono text-xs space-y-1">
                        <div><span class="text-zinc-400">Email:</span> {{ $account->email }}</div>
                        <div><span class="text-zinc-400">Password:</span> {{ $account->password ?? '—' }}</div>
                        <div>
                            <span class="text-zinc-400">Llave:</span>
                            @if ($nextKey)
                                {{ $nextKey->key_value }} <span class="text-zinc-400">#{{ $nextKey->position }}</span>
                            @else
                                <span class="text-amber-600">sin llave disponible</span>
                            @endif
                        </div>
                    </div>

                    <p class="font-medium">¿Ya realizaste el reseteo en la plataforma?</p>
                </div>

                <div class="px-6 py-3 border-t border-zinc-200 flex justify-end gap-2">
                    <button type="button" onclick="closeResetModal()"
                            class="rounded-md bg-white px-3 py-1.5 text-sm font-medium text-zinc-700 ring-1 ring-inset ring-zinc-300 hover:bg-zinc-100">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="rounded-md bg-amber-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-amber-700">
                        Sí, ya reseteé
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    function openResetModal() {
        document.getElementById('reset-modal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    function closeResetModal() {
        document.getElementById('reset-modal').classList.add('hidden');
        document.body.style.overflow = '';
    }
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !document.getElementById('reset-modal').classList.contains('hidden')) {
            closeResetModal();
        }
    });
</script>


@endsection
