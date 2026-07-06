@extends('layouts.app')

@section('title', 'Usuarios')

@section('content')
    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-lg font-semibold tracking-tight">Usuarios</h1>
            <p class="mt-0.5 text-sm text-zinc-500">Administrá las cuentas que acceden al panel.</p>
        </div>
        <a href="{{ route('users.create') }}"
           class="inline-flex items-center gap-2 rounded-md bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white transition hover:bg-zinc-700">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            Nuevo usuario
        </a>
    </div>

    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white">
        <table class="min-w-full divide-y divide-zinc-200 text-sm">
            <thead class="bg-zinc-50 text-left text-xs font-medium uppercase tracking-wide text-zinc-500">
                <tr>
                    <th class="px-4 py-3">Nombre</th>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3">Rol</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100">
                @forelse ($users as $user)
                    <tr class="hover:bg-zinc-50">
                        <td class="px-4 py-3 font-medium text-zinc-900">{{ $user->name }}</td>
                        <td class="px-4 py-3 text-zinc-600">{{ $user->email }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                {{ $user->isAdmin() ? 'bg-zinc-900 text-white' : 'bg-zinc-100 text-zinc-700' }}">
                                {{ \App\Models\User::ROLES[$user->role] ?? $user->role }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('users.edit', $user) }}"
                                   class="rounded-md px-2.5 py-1.5 text-zinc-600 transition hover:bg-zinc-100 hover:text-zinc-900">
                                    Editar
                                </a>
                                @if ($user->id !== auth()->id())
                                    <form method="POST" action="{{ route('users.destroy', $user) }}"
                                          onsubmit="return confirm('¿Eliminar a {{ $user->name }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="rounded-md px-2.5 py-1.5 text-red-600 transition hover:bg-red-50">
                                            Eliminar
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-10 text-center text-zinc-500">
                            No hay usuarios cargados todavía.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($users->hasPages())
        <div class="mt-4">
            {{ $users->links() }}
        </div>
    @endif
@endsection