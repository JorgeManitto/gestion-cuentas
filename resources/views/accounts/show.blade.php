@extends('layouts.app')
@section('title', 'Cuenta ' . $account->email)

@section('content')

@php
    $statusColor = match($account->status) {
        'active'   => 'emerald',
        'blocked'  => 'red',
        'reset'    => 'amber',
        'archived' => 'zinc',
        default    => 'zinc',
    };

    // Agrupar assignments por status para mostrar activas arriba, expiradas/revocadas después
    $activeAssignments  = $account->assignments->where('status', 'active')->sortBy('slot_number');
    $historyAssignments = $account->assignments->whereIn('status', ['expired', 'revoked'])
                                              ->sortByDesc('updated_at');
@endphp

{{-- HEADER --}}
<div class="mb-4 flex items-center justify-between gap-2">
    <a href="{{ route('accounts.index') }}" class="text-sm text-zinc-500 hover:text-zinc-900">← Volver al listado</a>

    <div class="flex gap-2">
        <a href="{{ route('accounts.edit', $account) }}"
           class="rounded-md bg-zinc-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-zinc-700">
            Editar
        </a>

        @if ($account->isDisabled())
            <form method="POST" action="{{ route('accounts.enable', $account) }}"
                  onsubmit="return confirm('¿Habilitar la cuenta {{ $account->email }}?');">
                @csrf
                <button type="submit"
                        class="rounded-md bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-700">
                    Habilitar
                </button>
            </form>
        @else
            <button type="button" onclick="openDisableModal()"
                    class="rounded-md bg-white px-3 py-1.5 text-sm font-medium text-red-700 ring-1 ring-inset ring-red-300 hover:bg-red-50">
                Deshabilitar
            </button>
        @endif

        <form method="POST" action="{{ route('accounts.destroy', $account) }}"
              onsubmit="return confirm('¿Eliminar la cuenta {{ $account->email }}? Soft-delete, recuperable.');">
            @csrf
            @method('DELETE')
            <button type="submit"
                    class="rounded-md bg-white px-3 py-1.5 text-sm font-medium text-red-700 ring-1 ring-inset ring-red-300 hover:bg-red-50">
                Eliminar
            </button>
        </form>
    </div>
</div>

{{-- BANNER si está deshabilitada --}}
@if ($account->isDisabled() && $account->disable_reason)
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-red-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18.364 5.636M5.636 18.364l12.728-12.728"/>
            </svg>
            <div class="flex-1">
                <div class="font-medium text-red-900 text-sm">
                    Cuenta deshabilitada · {{ $account->disableReasonLabel() }}
                </div>
                @if ($account->notes)
                    <div class="text-xs text-red-800 mt-1 whitespace-pre-wrap">{{ $account->notes }}</div>
                @endif
            </div>
        </div>
    </div>
@endif

<div class="grid grid-cols-3 gap-6">

    {{-- ════════ COLUMNA IZQUIERDA: identidad ════════ --}}
    <div class="col-span-1 space-y-4">

        <div class="rounded-lg border border-zinc-200 bg-white p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-medium uppercase tracking-wide text-zinc-500">Cuenta</span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-{{ $statusColor }}-50 px-2 py-0.5 text-xs font-medium text-{{ $statusColor }}-700 ring-1 ring-inset ring-{{ $statusColor }}-600/20">
                    <span class="h-1.5 w-1.5 rounded-full bg-{{ $statusColor }}-500"></span>
                    {{ $account->status }}
                </span>
            </div>
            <div class="font-mono text-sm break-all">{{ $account->email }}</div>
            @if ($account->gamer_tag)
                <div class="text-xs text-zinc-500 font-mono mt-1">{{ $account->gamer_tag }}</div>
            @endif
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-5 space-y-3">
            <div>
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 mb-1">Juego</div>
                <div class="font-medium">
                    @if ($account->game)
                        {{ $account->game->canonical_name }}
                    @else
                        <span class="text-zinc-400">—</span>
                    @endif
                </div>
            </div>
            <hr class="border-zinc-100">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 mb-1">Plataforma</div>
                    <div class="font-mono text-sm">{{ $account->platform }}</div>
                </div>
                <div>
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 mb-1">Región</div>
                    <div class="font-mono text-sm">{{ $account->region }}</div>
                </div>
                <div>
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 mb-1">Tipo</div>
                    <div class="font-mono text-sm">{{ $account->account_type }}</div>
                </div>
                <div>
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 mb-1">Nacimiento</div>
                    <div class="font-mono text-sm">{{ $account->birth_date?->format('Y-m-d') ?? '—' }}</div>
                </div>
            </div>
        </div>

        @if ($account->parent || $account->children->isNotEmpty())
            <div class="rounded-lg border border-zinc-200 bg-white p-5 space-y-2">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Jerarquía</div>
                @if ($account->parent)
                    <div class="text-sm">
                        Madre:
                        <a href="{{ route('accounts.show', $account->parent) }}"
                           class="font-mono text-xs hover:underline">{{ $account->parent->email }}</a>
                    </div>
                @endif
                @if ($account->children->isNotEmpty())
                    <div class="text-sm">
                        Hijas: {{ $account->children->count() }}
                        <ul class="mt-1 space-y-0.5">
                            @foreach ($account->children as $child)
                                <li>
                                    <a href="{{ route('accounts.show', $child) }}"
                                       class="font-mono text-xs hover:underline">{{ $child->email }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif

        {{-- ── FECHAS + RESET ── --}}
        <div class="rounded-lg border border-zinc-200 bg-white p-5 space-y-3">
            <div>
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 mb-1">Compra</div>
                <div class="font-mono text-sm">{{ $account->purchased_date?->format('Y-m-d') ?? '—' }}</div>
            </div>
            <div>
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 mb-1">
                    Reset
                    @if ($account->isPostReset())
                        <span class="ml-1 text-xs normal-case font-normal text-amber-600">· post-reset activo</span>
                    @endif
                </div>
                <div class="font-mono text-sm">{{ $account->reset_date?->format('Y-m-d') ?? '—' }}</div>
                @if ($account->isPostReset())
                    <div class="text-xs text-zinc-500 mt-1">
                        Capacidad reducida: <span class="font-mono">{{ $account->maxAfterReset() }}</span> slots
                        (de {{ $account->initialCapacity() }} originales)
                    </div>
                @endif
            </div>

            {{-- BOTÓN RESETEAR --}}
            <form method="POST" action="{{ route('accounts.reset', $account) }}"
                  onsubmit="return confirm('¿RESETEAR la cuenta {{ $account->email }}?\n\nEsto va a:\n  · marcar como expiradas las {{ $activeAssignments->count() }} asignación(es) activa(s)\n  · reducir la capacidad a {{ $account->maxAfterReset() }} slots\n\nLas asignaciones quedan en el historial pero los slots se liberan.');">
                @csrf
                <button type="submit"
                        class="w-full rounded-md bg-amber-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-amber-700">
                    ⟲ Resetear cuenta
                </button>
            </form>

            @if ($account->isTimeBlocked())
                <div class="rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-xs">
                    <div class="font-medium text-amber-900">⏱ Bloqueada por Nintendo (4° uso)</div>
                    <div class="text-amber-800 mt-0.5">
                        Desbloquea el {{ $account->timeBlockUnlockAt()->format('d/m/Y') }}
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- ════════ COLUMNA CENTRO+DERECHA ════════ --}}
    <div class="col-span-2 space-y-4">

        {{-- ── CREDENCIALES ── --}}
        <div class="rounded-lg border border-zinc-200 bg-white p-5 space-y-3">
            <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Credenciales</div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <div class="text-xs text-zinc-500 mb-0.5">Email cuenta</div>
                    <div class="font-mono text-sm break-all">{{ $account->email }}</div>
                </div>
                <div>
                    <div class="text-xs text-zinc-500 mb-0.5">Password cuenta</div>
                    <div class="font-mono text-sm break-all">{{ $account->password }}</div>
                </div>
                <div>
                    <div class="text-xs text-zinc-500 mb-0.5">Email del correo</div>
                    <div class="font-mono text-sm break-all">{{ $account->mail_email ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-xs text-zinc-500 mb-0.5">Password del correo</div>
                    <div class="font-mono text-sm break-all">{{ $account->mail_password ?? '—' }}</div>
                </div>
            </div>
        </div>

        {{-- ── LLAVES ── --}}
        <div class="rounded-lg border border-zinc-200 bg-white">
            <div class="border-b border-zinc-200 px-5 py-3 flex items-center justify-between">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">
                    Llaves de recuperación
                </div>
                <span class="text-xs text-zinc-500">{{ $account->keys->count() }} totales</span>
            </div>
            @if ($account->keys->isEmpty())
                <div class="px-5 py-6 text-center text-sm text-zinc-500">Sin llaves cargadas.</div>
            @else
                <div class="p-4 grid grid-cols-5 gap-2">
                    @foreach ($account->keys as $k)
                        <div class="rounded border border-zinc-200 p-2 text-center {{ $k->used_at ? 'bg-zinc-50 opacity-60' : 'bg-white' }}">
                            <div class="text-xs text-zinc-400">#{{ $k->position }}</div>
                            <div class="font-mono text-sm font-medium {{ $k->used_at ? 'line-through' : '' }}">{{ $k->key_value }}</div>
                            @if ($k->used_at)
                                <div class="text-xs text-zinc-500">{{ $k->used_at->format('Y-m-d') }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ── ASIGNACIONES ACTIVAS + BOTONES MANUALES ── --}}
        <div class="rounded-lg border border-zinc-200 bg-white">
            <div class="border-b border-zinc-200 px-5 py-3 flex items-center justify-between gap-3">
                <div>
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Asignaciones activas</div>
                    <div class="text-xs text-zinc-500 mt-0.5">
                        {{ $activeAssignments->count() }}/{{ $account->capacity() }} ocupados
                        · {{ $account->freeSlots() }} libre(s)
                        @if ($account->isPostReset())
                            <span class="text-amber-600">· post-reset</span>
                        @endif
                    </div>
                </div>

                {{-- BOTONES DE USO MANUAL --}}
                <div class="flex gap-1">
                    <form method="POST" action="{{ route('accounts.usage.decrement', $account) }}"
                          onsubmit="return confirm('¿Liberar el placeholder más reciente?\n\nNo borra asignaciones con datos de cliente reales.');">
                        @csrf
                        <button type="submit"
                                class="rounded-md bg-white px-2 py-1 text-xs font-medium text-zinc-700 ring-1 ring-inset ring-zinc-300 hover:bg-zinc-100"
                                {{ $activeAssignments->whereNull('customer_name')->whereNull('customer_email')->isEmpty() ? 'disabled' : '' }}
                                title="Libera el placeholder más reciente (sin datos de cliente)">
                            −1 uso
                        </button>
                    </form>
                    <form method="POST" action="{{ route('accounts.usage.increment', $account) }}"
                          onsubmit="return confirm('¿Agregar 1 uso manual (placeholder en próximo slot libre)?');">
                        @csrf
                        <button type="submit"
                                class="rounded-md bg-zinc-900 px-2 py-1 text-xs font-medium text-white hover:bg-zinc-700"
                                {{ $account->freeSlots() <= 0 ? 'disabled' : '' }}>
                            +1 uso
                        </button>
                    </form>
                </div>
            </div>

            @if ($activeAssignments->isEmpty())
                <div class="px-5 py-6 text-center text-sm text-zinc-500">Sin asignaciones activas.</div>
            @else
                <table class="min-w-full text-sm">
                    <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-600">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium">Slot</th>
                            <th class="px-4 py-2 text-left font-medium">Cliente</th>
                            <th class="px-4 py-2 text-left font-medium">Email</th>
                            <th class="px-4 py-2 text-left font-medium">Asignada</th>
                            <th class="px-4 py-2 text-left font-medium">Order</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @foreach ($activeAssignments as $as)
                            @php
                                $isPlaceholder = ! $as->customer_name && ! $as->customer_email;
                            @endphp
                            <tr class="{{ $isPlaceholder ? 'bg-amber-50/40' : '' }}">
                                <td class="px-4 py-2 font-mono text-xs">#{{ $as->slot_number }}</td>
                                <td class="px-4 py-2">
                                    @if ($isPlaceholder)
                                        <span class="text-xs italic text-amber-700">ocupado · sin info</span>
                                    @else
                                        {{ $as->customer_name ?? '—' }}
                                    @endif
                                </td>
                                <td class="px-4 py-2 font-mono text-xs">{{ $as->customer_email ?? '—' }}</td>
                                <td class="px-4 py-2 font-mono text-xs text-zinc-500">{{ $as->assigned_at?->format('Y-m-d') ?? '—' }}</td>
                                <td class="px-4 py-2 font-mono text-xs">
                                    @if ($as->woo_order_id)
                                        #{{ $as->woo_order_id }}
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- ── HISTORIAL (expired + revoked) ── --}}
        @if ($historyAssignments->isNotEmpty())
            <div class="rounded-lg border border-zinc-200 bg-white">
                <div class="border-b border-zinc-200 px-5 py-3 flex items-center justify-between">
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">
                        Historial de activaciones
                    </div>
                    <span class="text-xs text-zinc-500">{{ $historyAssignments->count() }} entrada(s)</span>
                </div>
                <table class="min-w-full text-sm">
                    <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-600">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium">Slot</th>
                            <th class="px-4 py-2 text-left font-medium">Cliente</th>
                            <th class="px-4 py-2 text-left font-medium">Email</th>
                            <th class="px-4 py-2 text-left font-medium">Asignada</th>
                            <th class="px-4 py-2 text-left font-medium">Estado</th>
                            <th class="px-4 py-2 text-left font-medium">Cerrada</th>
                            <th class="px-4 py-2 text-left font-medium">Order</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @foreach ($historyAssignments as $as)
                            @php
                                $statusBadgeColor = $as->status === 'expired' ? 'amber' : 'red';
                            @endphp
                            <tr class="text-zinc-500">
                                <td class="px-4 py-2 font-mono text-xs">#{{ $as->slot_number }}</td>
                                <td class="px-4 py-2 line-through">
                                    {{ $as->customer_name ?? '—' }}
                                </td>
                                <td class="px-4 py-2 font-mono text-xs line-through">{{ $as->customer_email ?? '—' }}</td>
                                <td class="px-4 py-2 font-mono text-xs">{{ $as->assigned_at?->format('Y-m-d') ?? '—' }}</td>
                                <td class="px-4 py-2">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-{{ $statusBadgeColor }}-50 px-1.5 py-0.5 text-xs font-medium text-{{ $statusBadgeColor }}-700 ring-1 ring-inset ring-{{ $statusBadgeColor }}-600/20">
                                        {{ $as->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 font-mono text-xs">{{ $as->updated_at?->format('Y-m-d') ?? '—' }}</td>
                                <td class="px-4 py-2 font-mono text-xs">{{ $as->woo_order_id ? '#' . $as->woo_order_id : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($account->notes && ! $account->isDisabled())
            {{-- Si está deshabilitada, las notas ya se muestran en el banner. Si no, acá. --}}
            <div class="rounded-lg border border-zinc-200 bg-white p-5">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 mb-2">Notas</div>
                <div class="text-sm whitespace-pre-wrap">{{ $account->notes }}</div>
            </div>
        @endif
    </div>
</div>


{{-- ════════ MODAL DESHABILITAR ════════ --}}
<div id="disable-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-zinc-900/50" onclick="closeDisableModal()"></div>

    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md" onclick="event.stopPropagation()">

            <form method="POST" action="{{ route('accounts.disable', $account) }}">
                @csrf

                <div class="px-6 py-4 border-b border-zinc-200">
                    <h3 class="text-lg font-semibold">Deshabilitar cuenta</h3>
                    <p class="text-xs text-zinc-500 font-mono mt-1">{{ $account->email }}</p>
                </div>

                <div class="px-6 py-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 mb-1">Motivo</label>
                        <select name="reason" required
                                class="w-full rounded-md border-zinc-300 text-sm">
                            @foreach (App\Models\Account::DISABLE_REASONS as $key => $label)
                                <option value="{{ $key }}" {{ $account->disable_reason === $key ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-zinc-700 mb-1">Comentario (opcional)</label>
                        <textarea name="notes" rows="3" maxlength="2000"
                                  placeholder="¿Por qué se deshabilita esta cuenta?"
                                  class="w-full rounded-md border-zinc-300 text-sm">{{ old('notes', $account->notes) }}</textarea>
                    </div>
                </div>

                <div class="px-6 py-3 border-t border-zinc-200 flex justify-end gap-2">
                    <button type="button" onclick="closeDisableModal()"
                            class="rounded-md bg-white px-3 py-1.5 text-sm font-medium text-zinc-700 ring-1 ring-inset ring-zinc-300 hover:bg-zinc-100">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="rounded-md bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700">
                        Deshabilitar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openDisableModal() {
        document.getElementById('disable-modal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    function closeDisableModal() {
        document.getElementById('disable-modal').classList.add('hidden');
        document.body.style.overflow = '';
    }
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !document.getElementById('disable-modal').classList.contains('hidden')) {
            closeDisableModal();
        }
    });
</script>

@endsection
