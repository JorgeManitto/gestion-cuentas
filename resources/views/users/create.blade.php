@extends('layouts.app')

@section('title', 'Nuevo usuario')

@section('content')
    <div class="mx-auto max-w-xl">
        <div class="mb-6">
            <a href="{{ route('users.index') }}" class="text-sm text-zinc-500 hover:text-zinc-900">← Volver</a>
            <h1 class="mt-2 text-lg font-semibold tracking-tight">Nuevo usuario</h1>
        </div>

        <form method="POST" action="{{ route('users.store') }}" class="rounded-lg border border-zinc-200 bg-white p-6">
            @csrf
            @include('users._form')

            <div class="mt-6 flex items-center justify-end gap-3 border-t border-zinc-100 pt-5">
                <a href="{{ route('users.index') }}" class="rounded-md px-3.5 py-2 text-sm text-zinc-600 transition hover:bg-zinc-100">Cancelar</a>
                <button type="submit" class="rounded-md bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white transition hover:bg-zinc-700">Crear usuario</button>
            </div>
        </form>
    </div>
@endsection