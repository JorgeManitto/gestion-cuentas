<!DOCTYPE html>
<html lang="es" class="h-full bg-zinc-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Gestión de Cuentas')</title>

    {{-- Tailwind CDN para arrancar sin npm. Cuando estés listo, podés
         instalar Tailwind localmente con `npm install -D tailwindcss` --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            fontFamily: {
              sans: ['system-ui', 'sans-serif'],
              mono: ['ui-monospace', 'SFMono-Regular', 'Menlo', 'monospace'],
            }
          }
        }
      }
    </script>

    <style>
        /* Animación sutil para los flashes */
        @keyframes slideDown {
            from { transform: translateY(-100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .animate-slide-down { animation: slideDown 0.2s ease-out; }
    </style>
</head>
<body class="h-full font-sans text-zinc-900 antialiased">

    <header class="border-b border-zinc-200 bg-white">
        <div class="mx-auto max-w-7xl px-6 py-3 flex items-center justify-between">
            <div class="flex items-center gap-8">
                <a href="{{ route('orders.index') }}" class="font-mono text-sm font-semibold tracking-tight">
                    GESTIÓN DE CUENTAS
                </a>
                <nav class="flex gap-6 text-sm">
                    <a href="{{ route('orders.index') }}"
                       class="{{ request()->routeIs('orders.*') ? 'text-zinc-900 font-medium' : 'text-zinc-500 hover:text-zinc-900' }}">
                        Orders
                    </a>
                    <a href="{{ route('accounts.index') }}"
                       class="{{ request()->routeIs('accounts.*') ? 'text-zinc-900 font-medium' : 'text-zinc-500 hover:text-zinc-900' }}">
                        Cuentas
                    </a>
                    <a href="{{ route('purchase-orders.index') }}"
                       class="{{ request()->routeIs('purchase-orders.*') ? 'text-zinc-900 font-medium' : 'text-zinc-500 hover:text-zinc-900' }}">
                        Compras
                    </a>
                </nav>
            </div>
        </div>
    </header>

    @if (session('success'))
        <div class="bg-emerald-50 border-b border-emerald-200 animate-slide-down">
            <div class="mx-auto max-w-7xl px-6 py-2 text-sm text-emerald-800">
                ✓ {{ session('success') }}
            </div>
        </div>
    @endif

    @if ($errors->any())
        <div class="bg-red-50 border-b border-red-200 animate-slide-down">
            <div class="mx-auto max-w-7xl px-6 py-2 text-sm text-red-800">
                @foreach ($errors->all() as $error)
                    <div>✕ {{ $error }}</div>
                @endforeach
            </div>
        </div>
    @endif

    <main class="mx-auto max-w-7xl px-6 py-6">
        @yield('content')
    </main>

</body>
</html>
