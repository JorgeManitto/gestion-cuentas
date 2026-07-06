@php
    /** @var \App\Models\Account $account */
    /** @var \Illuminate\Support\Collection $secondaryUsageByPlatform */
@endphp

<div class="rounded-lg border border-zinc-200 bg-white p-5 space-y-4">
    <div class="flex items-center justify-between">
        <div class="text-[11px] font-semibold uppercase tracking-wider text-zinc-500">
            Stock secundario
        </div>
        <div class="text-xs text-zinc-400 font-mono">
            {{ $account->secondaryFreeSlots() }}/{{ $account->secondaryCapacity() }} libres
        </div>
    </div>

    @error('secondary')
        <div class="rounded-md bg-red-50 border border-red-200 text-red-700 text-xs px-3 py-2">
            {{ $message }}
        </div>
    @enderror

    @if ($secondaryUsageByPlatform->isEmpty())
        <p class="text-sm text-zinc-500 italic">Esta cuenta no admite stock secundario.</p>
    @else
        <div class="space-y-3">
            @foreach ($secondaryUsageByPlatform as $platform => $u)
                <div class="rounded-md border border-zinc-200 p-3">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-sm font-medium">{{ $platform }}</span>
                            <span class="text-xs text-zinc-500">{{ $u['used'] }}/{{ $u['capacity'] }} usados</span>
                        </div>

                        <div class="flex items-center gap-1.5">
                            {{-- − liberar placeholder (solo usos sin datos de cliente) --}}
                            <form method="POST" action="{{ route('accounts.secondary-usage.decrement', $account) }}" onsubmit="return confirm('¿Seguro que querés liberar un slot de {{ $platform }}?')">
                                @csrf
                                <input type="hidden" name="platform" value="{{ $platform }}">
                                <button type="submit" @disabled($u['used'] <= 0)
                                        class="w-7 h-7 rounded border border-zinc-200 text-zinc-600 hover:bg-zinc-50 disabled:opacity-40 disabled:cursor-not-allowed text-base leading-none">
                                    −
                                </button>
                            </form>

                            {{-- + agregar uso secundario --}}
                            <form method="POST" action="{{ route('accounts.secondary-usage.increment', $account) }}" onsubmit="return confirm('¿Agregar un uso secundario en {{ $platform }}?')">
                                @csrf
                                <input type="hidden" name="platform" value="{{ $platform }}">
                                <button type="submit" @disabled($u['free'] <= 0)
                                        class="w-7 h-7 rounded bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed text-base leading-none">
                                    +
                                </button>
                            </form>
                        </div>
                    </div>

                    {{-- pills de slots --}}
                    <div class="flex flex-wrap gap-1.5">
                        @for ($i = 1; $i <= $u['capacity']; $i++)
                            @php $filled = $i <= $u['used']; @endphp
                            <span class="h-6 min-w-[1.5rem] px-1.5 inline-flex items-center justify-center rounded text-[11px] font-mono
                                {{ $filled
                                    ? 'bg-emerald-100 text-emerald-700 ring-1 ring-inset ring-emerald-200'
                                    : 'bg-zinc-50 text-zinc-400 ring-1 ring-inset ring-zinc-200' }}">
                                {{ $i }}
                            </span>
                        @endfor
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>