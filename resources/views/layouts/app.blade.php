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
        @keyframes slideDown {
            from { transform: translateY(-100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .animate-slide-down { animation: slideDown 0.2s ease-out; }
        summary::-webkit-details-marker { display: none; }
    </style>
</head>
<body class="h-full font-sans text-zinc-900 antialiased">

    {{-- Definición de links en un solo lugar para no repetir markup --}}
    @php
        $navLinks = [
            ['route' => 'orders.index', 'pattern' => 'orders.*', 'label' => 'Órdenes', 'badge' => $ordersBadgeCount ?? 0, 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
            [
                'label'    => 'Cuentas',
                'icon'     => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
                'children' => [
                    ['route' => 'accounts.index',   'pattern' => 'accounts.index',       'label' => 'Listado'],
                    ['route' => 'stock.resettable', 'pattern' => 'stock.resettable', 'label' => 'Stock Reseteable'],
                    ['route' => 'accounts.secondary-stock', 'pattern' => 'accounts.secondary-stock', 'label' => 'Stock Secundario'],
                ],
            ],
            ['route' => 'purchase-orders.index', 'pattern' => 'purchase-orders.*', 'label' => 'Compras', 'badge' => $purchasesBadgeCount ?? 0, 'params' => ['tab' => 'ordenes', 'status' => 'pending'], 'icon' => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17M17 13v4a2 2 0 11-2 2 2 2 0 012-2'],
            ['route' => 'games.index',            'pattern' => 'games.*',           'label' => 'Juegos',  'icon' => 'M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['route' => 'users.index', 'pattern' => 'users.*', 'label' => 'Usuarios', 'admin' => true, 'icon' => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1a4 4 0 100-8 4 4 0 000 8z'],
            ['route' => 'accounts.bulk-assign', 'pattern' => 'accounts.bulk-assign*', 'label' => 'Asignación masiva', 'admin' => true,'icon' => 'M12 6v6m0 0l-2-2m2 2l2-2m2 8H9a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v7a2 2 0 01-2 2z'],
        ];
    @endphp

    <div class="flex h-full">

        {{-- ===================== SIDEBAR ===================== --}}
        <aside id="sidebar"
               class="fixed inset-y-0 left-0 z-40 w-60 -translate-x-full border-r border-zinc-200 bg-white
                      transition-transform duration-200 ease-out
                      lg:static lg:translate-x-0 flex flex-col">

            {{-- Brand --}}
            <div class="flex h-14 items-center gap-2 border-b border-zinc-200 px-5 shrink-0">
                <a href="{{ route('orders.index') }}"
                   class="font-mono text-sm font-semibold tracking-tight">
                    GESTIÓN DE CUENTAS
                </a>
            </div>

            {{-- Navegación --}}
            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                @foreach ($navLinks as $link)
                    @continue(($link['admin'] ?? false) && ! auth()->user()?->isAdmin())

                    @if (!empty($link['children']))
                        {{-- ===== Item con acordeón ===== --}}
                        @php
                            $childPatterns = array_column($link['children'], 'pattern');
                            $groupOpen = request()->routeIs(...$childPatterns);
                        @endphp
                        <details class="group" @if ($groupOpen) open @endif>
                            <summary class="flex cursor-pointer list-none items-center gap-3 rounded-md px-3 py-2 text-sm
                                            text-zinc-600 transition hover:bg-zinc-100 hover:text-zinc-900">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $link['icon'] }}" />
                                </svg>
                                {{ $link['label'] }}
                                <svg class="ml-auto h-4 w-4 shrink-0 transition-transform duration-200 group-open:rotate-180"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </summary>

                            <div class="mt-1 space-y-1 pl-4">
                                @foreach ($link['children'] as $child)
                                    @php $childActive = request()->routeIs($child['pattern']); @endphp
                                    <a href="{{ route($child['route']) }}"
                                    class="flex items-center gap-3 rounded-md px-3 py-2 text-sm transition
                                            {{ $childActive
                                                    ? 'bg-zinc-900 text-white'
                                                    : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900' }}">
                                        <span class="ml-1 h-1.5 w-1.5 shrink-0 rounded-full
                                                    {{ $childActive ? 'bg-white' : 'bg-zinc-300' }}"></span>
                                        {{ $child['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </details>
                    @else
                        {{-- ===== Item simple (igual que antes) ===== --}}
                        @php $active = request()->routeIs($link['pattern']); @endphp
                        <a href="{{ route($link['route'], $link['params'] ?? []) }}"
                        class="group flex items-center gap-3 rounded-md px-3 py-2 text-sm transition
                                {{ $active
                                        ? 'bg-zinc-900 text-white'
                                        : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900' }}">
                            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $link['icon'] }}" />
                            </svg>
                            {{ $link['label'] }}

                            @if (!empty($link['badge']))
                                <span class="ml-auto inline-flex min-w-6 min-h-6 items-center justify-center rounded-full px-1.5 py-0.5 text-[11px] font-medium
                                            {{ $active ? 'bg-white/20 text-white' : 'bg-zinc-900 text-white' }}">
                                    {{ $link['badge'] }}
                                </span>
                            @endif
                        </a>
                    @endif
                @endforeach
            </nav>

            {{-- Pie de sidebar --}}
            <div class="border-t border-zinc-200 px-5 py-3 text-[11px] text-zinc-400 font-mono shrink-0">
                v1.0 · Panel interno
            </div>
        </aside>

        {{-- Overlay para mobile cuando la sidebar está abierta --}}
        <div id="sidebar-overlay"
             class="fixed inset-0 z-30 hidden bg-zinc-900/40 lg:hidden"></div>

        {{-- ===================== COLUMNA PRINCIPAL ===================== --}}
        <div class="flex min-w-0 flex-1 flex-col">

            {{-- Navbar --}}
            <header class="sticky top-0 z-20 flex h-14 items-center justify-between
                           border-b border-zinc-200 bg-white px-4 sm:px-6 shrink-0">

                {{-- Botón hamburguesa (solo mobile) --}}
                <button type="button" id="sidebar-toggle"
                        class="-ml-1 rounded-md p-2 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 lg:hidden"
                        aria-label="Abrir menú">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>

                {{-- Logo visible en mobile (en desktop ya está en la sidebar) --}}
                <a href="{{ route('orders.index') }}"
                   class="font-mono text-sm font-semibold tracking-tight lg:hidden">
                    GESTIÓN DE CUENTAS
                </a>

                {{-- Empuja el bloque de usuario a la derecha --}}
                <div class="flex flex-1 items-center justify-end gap-4">
                    <span class="hidden text-sm text-zinc-600 sm:inline">
                        {{ auth()->user()->name ?? auth()->user()->email }}
                    </span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="rounded-md px-3 py-1.5 text-sm text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900">
                            Salir
                        </button>
                    </form>
                </div>
            </header>

            {{-- Flashes --}}
            @if (session('success'))
                <div class="border-b border-emerald-200 bg-emerald-50 animate-slide-down">
                    <div class="px-4 py-2 text-sm text-emerald-800 sm:px-6">
                        ✓ {{ session('success') }}
                    </div>
                </div>
            @endif

            @if ($errors->any())
                <div class="border-b border-red-200 bg-red-50 animate-slide-down">
                    <div class="px-4 py-2 text-sm text-red-800 sm:px-6">
                        @foreach ($errors->all() as $error)
                            <div>✕ {{ $error }}</div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Contenido --}}
            <main class="flex-1 overflow-y-auto px-4 py-6 sm:px-6">
                <div class="mx-auto">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    <script>
    (function () {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        const toggle  = document.getElementById('sidebar-toggle');

        const open  = () => { sidebar.classList.remove('-translate-x-full'); overlay.classList.remove('hidden'); };
        const close = () => { sidebar.classList.add('-translate-x-full');    overlay.classList.add('hidden');    };

        toggle?.addEventListener('click', open);
        overlay?.addEventListener('click', close);
        // Cerrar al navegar (mobile)
        sidebar?.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
            if (window.innerWidth < 1024) close();
        }));
    })();
    </script>

</body>
</html>