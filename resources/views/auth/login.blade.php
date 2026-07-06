<!DOCTYPE html>
<html lang="es" class="h-full bg-zinc-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Iniciar sesión · Gestión de Cuentas</title>

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
</head>
<body class="h-full font-sans text-zinc-900 antialiased flex items-center justify-center px-6 py-12">

    <div class="w-full max-w-sm">
        <div class="mb-8 text-center">
            <h1 class="font-mono text-sm font-semibold tracking-tight">GESTIÓN DE CUENTAS</h1>
            <p class="mt-2 text-sm text-zinc-500">Iniciá sesión para continuar</p>
        </div>

        <div class="bg-white border border-zinc-200 rounded-md p-6">
            @if ($errors->any())
                <div class="mb-4 bg-red-50 border border-red-200 rounded p-3 text-sm text-red-800 space-y-1">
                    @foreach ($errors->all() as $error)
                        <div>✕ {{ $error }}</div>
                    @endforeach
                </div>
            @endif

            @if (session('status'))
                <div class="mb-4 bg-emerald-50 border border-emerald-200 rounded p-3 text-sm text-emerald-800">
                    ✓ {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium text-zinc-700 mb-1">
                        Email
                    </label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="username"
                        class="block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-zinc-900 focus:outline-none focus:ring-1 focus:ring-zinc-900"
                    >
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-zinc-700 mb-1">
                        Contraseña
                    </label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-zinc-900 focus:outline-none focus:ring-1 focus:ring-zinc-900"
                    >
                </div>

                <div class="flex items-center">
                    <input
                        id="remember"
                        name="remember"
                        type="checkbox"
                        class="h-4 w-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900"
                    >
                    <label for="remember" class="ml-2 text-sm text-zinc-600">
                        Recordarme
                    </label>
                </div>

                <button
                    type="submit"
                    class="w-full rounded-md bg-zinc-900 px-3 py-2 text-sm font-medium text-white hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-zinc-900 focus:ring-offset-2 transition"
                >
                    Iniciar sesión
                </button>
            </form>
        </div>

        <p class="mt-6 text-center text-xs text-zinc-400 font-mono">
            v1.0
        </p>
    </div>

</body>
</html>