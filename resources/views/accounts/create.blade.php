@extends('layouts.app')
@section('title', 'Nueva cuenta')

@section('content')

<div class="mb-4">
    <a href="{{ route('accounts.index') }}" class="text-sm text-zinc-500 hover:text-zinc-900">← Volver al listado</a>
    <h1 class="text-xl font-semibold mt-2">Nueva cuenta</h1>
</div>

<form method="POST" action="{{ route('accounts.store') }}">
    @csrf
    @include('accounts._form')
</form>

@endsection
