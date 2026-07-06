{{-- ==================== TAB: STOCK ==================== --}}
<div class="rounded-lg border border-zinc-200 bg-white p-4">
   <div class="flex items-start justify-between mb-4">
        <p class="text-sm text-zinc-600">Cuentas sin juego vinculado. Ordenadas por región.</p>
        <button type="button" onclick="document.getElementById('modal-create-stock').showModal()"
                class="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 shrink-0">
            + Agregar cuenta
        </button>
    </div>

    <form method="GET" action="{{ route('purchase-orders.index') }}" class="flex flex-wrap gap-2 mb-4">
        <input type="hidden" name="tab" value="stock">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar por email, región…"
               class="rounded-md border-zinc-300 text-sm px-3 py-1.5 w-64">
        <select name="platform" class="rounded-md border-zinc-300 text-sm px-3 py-1.5">
            <option value="all">Todas las consolas</option>
            @foreach (['DUAL','PS4','PS5','XBOX_ONE','XBOX_SERIES','SWITCH','SWITCH_2','STEAM'] as $p)
                <option value="{{ $p }}" @selected(request('platform') === $p)>{{ $p }}</option>
            @endforeach
        </select>
        <select name="region" class="rounded-md border-zinc-300 text-sm px-3 py-1.5">
            <option value="all">Todas las regiones</option>
            @foreach ($uniqueRegions as $r)
                <option value="{{ $r }}" @selected(request('region') === $r)>{{ $r }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-md bg-zinc-900 px-4 py-1.5 text-sm font-medium text-white hover:bg-zinc-800">
            Filtrar
        </button>
    </form>

    <div class="overflow-x-auto rounded-lg border border-zinc-200">
        <table class="min-w-full text-sm">
            <thead class="bg-zinc-50 border-b border-zinc-200 text-xs uppercase tracking-wide text-zinc-600">
                <tr>
                    <th class="px-4 py-2 text-left font-medium">Email</th>
                    <th class="px-4 py-2 text-left font-medium">Consola</th>
                    <th class="px-4 py-2 text-left font-medium">Región</th>
                    <th class="px-4 py-2 text-left font-medium">Estado</th>

                    <th class="px-4 py-2 text-left font-medium">Tipo</th>
                    <th class="px-4 py-2 text-left font-medium">Comprada</th>
                    <th class="px-4 py-2 text-left font-medium">Acción</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100">
                @forelse ($stockAccounts as $acc)
                    @php
                        $accData = [
                            'id'             => $acc->id,
                            'email'          => $acc->email,
                            'password'       => $acc->password,
                            'platform'       => $acc->platform,
                            'type_console'   => $acc->type_console,
                            'region'         => $acc->region,
                            'account_type'   => $acc->account_type,
                            'is_dual'        => (bool) $acc->is_dual,
                            'status'         => $acc->status,
                            'mail_email'     => $acc->mail_email,
                            'mail_password'  => $acc->mail_password,
                            'gamer_tag'      => $acc->gamer_tag,
                            'birth_date'     => optional($acc->birth_date)->format('Y-m-d'),
                            'purchased_date' => optional($acc->purchased_date)->format('Y-m-d'),
                            'notes'          => $acc->notes,
                            'keys'           => $acc->keys->map(fn ($k) => [
                                                    'position' => $k->position,
                                                    'value'    => $k->key_value,
                                                ])->values(),
                        ];
                    @endphp
                    <tr class="hover:bg-zinc-50 cursor-pointer" >
                        <td class="px-4 py-2.5 font-medium" onclick='openStockDetail(@json($accData))'>{{ $acc->email }}</td>
                        <td class="px-4 py-2.5 font-mono text-xs">{{ $acc->type_console ?? '—' }}</td>
                        <td class="px-4 py-2.5">{{ $acc->region ?? '—' }}</td>
                        <td class="px-4 py-2.5">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                @class([
                                    'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20' => $acc->status === 'active',
                                    'bg-zinc-100 text-zinc-700' => $acc->status !== 'active',
                                ])">
                                {{ $acc->status }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5">
                            @php
                                $typeColor = match($acc->account_type) {
                                    'MADRE' => 'violet',
                                    'HIJA'  => 'sky',
                                    default => 'zinc',
                                };
                            @endphp
                            <span class="inline-flex items-center rounded-full bg-{{ $typeColor }}-50 px-2 py-0.5 text-xs font-medium text-{{ $typeColor }}-700 ring-1 ring-inset ring-{{ $typeColor }}-600/20">
                                {{ $acc->account_type ?? 'INDEPENDIENTE' }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5 font-mono text-xs text-zinc-500">
                            {{ $acc->purchased_date?->format('Y-m-d') ?? '—' }}
                        </td>
                        <td>
                            <a href="{{ route('accounts.edit', ['account'=>$acc->id]) }}" class="text-blue-600 hover:text-blue-900" target="_blank">Editar</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center text-zinc-500">
                            No hay cuentas sin juego con los filtros aplicados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
