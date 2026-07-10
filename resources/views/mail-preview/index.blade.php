@extends('layouts.app')

@section('title', 'Prueba de correos')

@section('content')
<div class="mx-auto max-w-6xl">

    <div class="mb-6">
        <h1 class="text-lg font-semibold tracking-tight">Prueba de correos de entrega</h1>
        <p class="mt-1 text-sm text-zinc-500">
            Previsualizá y enviá las distintas variantes del mail de entrega a un correo de prueba.
            Usa el mismo template que recibe el cliente.
        </p>
    </div>

    <div class="grid gap-6 lg:grid-cols-[380px_1fr]">

        {{-- ───────────── Formulario ───────────── --}}
        <form method="POST" action="{{ route('mail-preview.send') }}"
              class="h-fit rounded-lg border border-zinc-200 bg-white p-6">
            @csrf

            {{-- Consola --}}
            <label class="block text-sm font-medium text-zinc-700">Consola</label>
            <select name="platform" id="mp-platform"
                    class="mt-1 w-full rounded-md border-zinc-300 text-sm shadow-sm focus:border-zinc-900 focus:ring-zinc-900">
                @foreach ($platforms as $value => $label)
                    <option value="{{ $value }}" @selected(old('platform', 'PS5') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-zinc-400">PS4/PS5 usan el template de PlayStation; el resto, el genérico.</p>

            {{-- Variante --}}
            <label class="mt-5 block text-sm font-medium text-zinc-700">Variante</label>
            <div class="mt-2 space-y-2">
                @foreach ($variants as $value => $label)
                    <label class="flex cursor-pointer items-start gap-3 rounded-md border border-zinc-200 p-3 text-sm transition hover:bg-zinc-50 has-[:checked]:border-zinc-900 has-[:checked]:bg-zinc-50">
                        <input type="radio" name="variant" value="{{ $value }}"
                               class="mp-variant mt-0.5 border-zinc-300 text-zinc-900 focus:ring-zinc-900"
                               @checked(old('variant', 'normal') === $value)>
                        <span class="text-zinc-700">{{ $label }}</span>
                    </label>
                @endforeach
            </div>

            {{-- Email destino --}}
            <label class="mt-5 block text-sm font-medium text-zinc-700">Enviar a</label>
            <input type="email" name="email" required
                   value="{{ old('email', auth()->user()->email) }}"
                   placeholder="correo@ejemplo.com"
                   class="mt-1 w-full rounded-md border-zinc-300 text-sm shadow-sm focus:border-zinc-900 focus:ring-zinc-900">
            @error('email')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror

            <div class="mt-6 flex items-center gap-3 border-t border-zinc-100 pt-5">
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-zinc-700">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    Enviar prueba
                </button>
                <button type="button" id="mp-refresh"
                        class="rounded-md px-3 py-2 text-sm text-zinc-600 transition hover:bg-zinc-100">
                    Actualizar vista
                </button>
            </div>
        </form>

        {{-- ───────────── Preview ───────────── --}}
        <div class="rounded-lg border border-zinc-200 bg-white p-3">
            <div class="mb-2 flex items-center justify-between px-1">
                <span class="text-xs font-medium uppercase tracking-wide text-zinc-400">Vista previa</span>
                <a id="mp-open" href="#" target="_blank"
                   class="text-xs text-zinc-500 hover:text-zinc-900">Abrir en pestaña ↗</a>
            </div>
            <iframe id="mp-frame" title="Vista previa del correo"
                    class="h-[720px] w-full rounded-md border border-zinc-100 bg-zinc-50"></iframe>
        </div>
    </div>
</div>

<script>
(function () {
    const base     = @json(route('mail-preview.render'));
    const platform = document.getElementById('mp-platform');
    const frame    = document.getElementById('mp-frame');
    const open     = document.getElementById('mp-open');
    const refresh  = document.getElementById('mp-refresh');

    const currentVariant = () =>
        document.querySelector('.mp-variant:checked')?.value || 'normal';

    function url() {
        const q = new URLSearchParams({ variant: currentVariant(), platform: platform.value });
        return base + '?' + q.toString();
    }

    function render() {
        const u = url();
        frame.src = u;
        open.href = u;
    }

    platform.addEventListener('change', render);
    document.querySelectorAll('.mp-variant').forEach(r => r.addEventListener('change', render));
    refresh.addEventListener('click', render);

    render();
})();
</script>
@endsection
