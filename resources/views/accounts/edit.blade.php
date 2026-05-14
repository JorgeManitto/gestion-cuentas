@extends('layouts.app')
@section('title', 'Editar ' . $account->email)

@section('content')

<div class="mb-4">
    <a href="{{ route('accounts.show', $account) }}" class="text-sm text-zinc-500 hover:text-zinc-900">← Volver al detalle</a>
    <h1 class="text-xl font-semibold mt-2 font-mono">{{ $account->email }}</h1>
</div>

<form method="POST" action="{{ route('accounts.update', $account) }}">
    @csrf
    @method('PUT')
    @include('accounts._form')
</form>

@endsection
