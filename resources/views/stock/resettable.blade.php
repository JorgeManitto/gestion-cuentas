{{-- resources/views/stock/resettable.blade.php --}}
{{-- AJUSTÁ el layout y las clases a tu sistema de diseño (asumí Tailwind, como suele venir Laravel). --}}
@extends('layouts.app')

@section('content')
<div class="mx-auto px-4 py-6">

    <div class="mb-6">
        <h1 class="text-2xl font-bold">Stock de Productos Reseteables</h1>
        <p class="text-sm text-gray-500">
            Cuentas con todos los slots ocupados y
            {{ \App\Models\Account::RESET_ELIGIBLE_MONTHS }} meses o más desde su última asignación.
            Ordenadas por antigüedad de rotación (último reset, o compra si nunca se reseteó):
            las que hace más tiempo esperan, primero.
        </p>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 gap-4 mb-6">
        <div class="rounded-lg border p-4">
            <div class="text-2xl font-bold">{{ $stats['accounts'] }}</div>
            <div class="text-sm text-gray-500">Cuentas reseteables</div>
        </div>
        <div class="rounded-lg border p-4">
            <div class="text-2xl font-bold">{{ $stats['games'] }}</div>
            <div class="text-sm text-gray-500">Juegos afectados</div>
        </div>
    </div>

    {{-- Filtros --}}
    <form method="GET" action="{{ route('stock.resettable') }}" class="flex flex-wrap gap-3 mb-6">
        <input type="text" name="search" value="{{ request('search') }}"
               placeholder="Buscar por juego o email…"
               class="border rounded px-3 py-2 flex-1 min-w-[220px]">
        <select name="platform" class="border rounded px-3 py-2">
            <option value="">Todas las plataformas</option>
            @foreach ($platforms as $p)
                <option value="{{ $p }}" @selected(request('platform') === $p)>{{ $p }}</option>
            @endforeach
        </select>
        <button type="submit" class="border rounded px-4 py-2 bg-gray-100 hover:bg-gray-200">Filtrar</button>
        @if (request()->hasAny(['search', 'platform', 'game_id']))
            <a href="{{ route('stock.resettable') }}" class="px-4 py-2 text-sm text-gray-500 hover:underline">Limpiar</a>
        @endif
    </form>

    {{-- Mensajes de sesión / errores (el reset usa back()->with('success')) --}}
    @if (session('success'))
        <div class="mb-4 rounded border border-green-300 bg-green-50 text-green-800 px-4 py-2 text-sm">
            {{ session('success') }}
        </div>
    @endif
    @error('reset')
        <div class="mb-4 rounded border border-red-300 bg-red-50 text-red-800 px-4 py-2 text-sm">{{ $message }}</div>
    @enderror

    <div class="overflow-x-auto rounded-lg border">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-500">
                <tr>
                    <th class="px-4 py-2 font-medium">#</th>
                    <th class="px-4 py-2 font-medium">Juego</th>
                    <th class="px-4 py-2 font-medium">Cuenta</th>
                    <th class="px-4 py-2 font-medium">Plataforma</th>
                    <th class="px-4 py-2 font-medium">Slots</th>
                    <th class="px-4 py-2 font-medium">Últ. asignación</th>
                    <th class="px-4 py-2 font-medium">Ref. rotación</th>
                    <th class="px-4 py-2 font-medium">Antigüedad</th>
                    <th class="px-4 py-2 font-medium text-right">Acción</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($accounts as $account)
                    @php
                        $last    = $account->lastAssignmentDate();
                        $ref     = $account->stockRotationReference();
                        $source  = $account->stockRotationSource();   // 'reset' | 'compra' | null
                        $ageDays = $account->stockRotationAgeInDays();
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 text-gray-400">{{ $accounts->firstItem() + $loop->index }}</td>
                        <td class="px-4 py-2">{{ $account->game?->canonical_name ?? '—' }}</td>
                        <td class="px-4 py-2">
                            <a href="{{ route('accounts.show', $account) }}" class="font-medium hover:underline">
                                {{ $account->email }}
                            </a>
                        </td>
                        <td class="px-4 py-2">
                            {{ $account->platform }}
                            @if ($account->is_dual)
                                <span class="text-xs text-gray-400">(dual)</span>
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            {{ $account->capacity() }}/{{ $account->capacity() }}
                            <span class="text-xs text-gray-400">ocupados</span>
                        </td>
                        <td class="px-4 py-2 text-gray-500">
                            {{ $last?->format('d/m/Y') ?? '—' }}
                        </td>
                        <td class="px-4 py-2">
                            {{ $ref?->format('d/m/Y') ?? '—' }}
                            @if ($source)
                                <span class="ml-1 inline-block rounded px-1.5 py-0.5 text-xs
                                    {{ $source === 'reset' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $source === 'reset' ? 'reset' : 'compra' }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-2 font-medium">
                            @if ($ageDays !== null)
                                {{ intdiv($ageDays, 30) }} m
                                <span class="text-xs text-gray-400">({{ $ageDays }} d)</span>
                            @else
                                <span class="text-gray-400">sin referencia</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right">
                                
                            @php $nextKey = $account->nextAvailableKey(); @endphp
                            <button type="button"
                                    class="reset-trigger rounded bg-amber-500 hover:bg-amber-600 text-white px-3 py-1.5 text-xs font-medium"
                                    data-email="{{ $account->email }}"
                                    data-password="{{ $account->password }}"
                                    data-key="{{ $nextKey?->key_value }}"
                                    data-key-id="{{ $nextKey?->id }}"
                                    data-action="{{ route('accounts.reset', $account) }}">
                                Resetear
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-10 text-center text-gray-500">
                            No hay cuentas reseteables por ahora.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($accounts->hasPages())
        <div class="mt-4">
            {{ $accounts->links() }}
        </div>
    @endif

</div>
{{-- Modal de reset --}}
<div id="reset-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div id="reset-backdrop" class="absolute inset-0 bg-black/40"></div>

    <div class="relative w-full max-w-md rounded-lg bg-white shadow-xl">
        <div class="border-b px-5 py-3">
            <h2 class="text-base font-semibold">Resetear cuenta en la plataforma</h2>
        </div>

        <div class="space-y-3 px-5 py-4 text-sm">
            <p class="text-gray-500">
                Ingresá a la plataforma con estos datos y realizá el reset manualmente.
                La llave que se muestra se consumirá al confirmar.
            </p>

            <div class="rounded border bg-gray-50 px-3 py-2 font-mono text-xs space-y-1">
                <div><span class="text-gray-400">Email:</span> <span id="rm-email"></span></div>
                <div><span class="text-gray-400">Password:</span> <span id="rm-password"></span></div>
                <div><span class="text-gray-400">Llave:</span> <span id="rm-key"></span></div>
            </div>

            <p class="font-medium">¿Ya realizaste el reseteo en la plataforma?</p>
        </div>

        <form method="POST" id="rm-form" class="flex justify-end gap-2 border-t px-5 py-3">
            @csrf
            <input type="hidden" name="key_id" id="rm-key-id">
            <button type="button" id="rm-cancel"
                    class="rounded border px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100">
                Cancelar
            </button>
            <button type="submit"
                    class="rounded bg-amber-500 hover:bg-amber-600 px-4 py-1.5 text-sm font-medium text-white">
                Sí, ya reseteé
            </button>
        </form>
    </div>
</div>

<script>
(function () {
    const modal = document.getElementById('reset-modal');
    const close = () => modal.classList.add('hidden');

    document.querySelectorAll('.reset-trigger').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.getElementById('rm-email').textContent    = btn.dataset.email || '—';
            document.getElementById('rm-password').textContent = btn.dataset.password || '—';
            document.getElementById('rm-key').textContent      = btn.dataset.key || 'sin llave disponible';
            document.getElementById('rm-key-id').value         = btn.dataset.keyId || '';
            document.getElementById('rm-form').action          = btn.dataset.action;
            modal.classList.remove('hidden');
        });
    });

    document.getElementById('rm-cancel').addEventListener('click', close);
    document.getElementById('rm-backdrop').addEventListener('click', close);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
})();
</script>
@endsection