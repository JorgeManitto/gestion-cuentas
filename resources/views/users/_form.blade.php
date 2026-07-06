@php
    /** @var \App\Models\User $user */
    $isEdit = $user->exists;
@endphp

<div class="space-y-5">
    <div>
        <label for="name" class="mb-1 block text-sm font-medium text-zinc-700">Nombre</label>
        <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}"
               class="w-full rounded-md border px-3 py-2 text-sm focus:outline-none focus:ring-1
                      @error('name') border-red-400 focus:border-red-500 focus:ring-red-500
                      @else border-zinc-300 focus:border-zinc-900 focus:ring-zinc-900 @enderror">
        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="email" class="mb-1 block text-sm font-medium text-zinc-700">Email</label>
        <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}"
               class="w-full rounded-md border px-3 py-2 text-sm focus:outline-none focus:ring-1
                      @error('email') border-red-400 focus:border-red-500 focus:ring-red-500
                      @else border-zinc-300 focus:border-zinc-900 focus:ring-zinc-900 @enderror">
        @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="role" class="mb-1 block text-sm font-medium text-zinc-700">Rol</label>
        <select name="role" id="role"
                class="w-full rounded-md border px-3 py-2 text-sm focus:outline-none focus:ring-1
                       @error('role') border-red-400 focus:border-red-500 focus:ring-red-500
                       @else border-zinc-300 focus:border-zinc-900 focus:ring-zinc-900 @enderror">
            @foreach ($roles as $value => $label)
                <option value="{{ $value }}" @selected(old('role', $user->role) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('role') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="password" class="mb-1 block text-sm font-medium text-zinc-700">
            Contraseña
            @if ($isEdit)
                <span class="font-normal text-zinc-400">— dejala en blanco para no cambiarla</span>
            @endif
        </label>
        <input type="password" name="password" id="password" autocomplete="new-password"
               class="w-full rounded-md border px-3 py-2 text-sm focus:outline-none focus:ring-1
                      @error('password') border-red-400 focus:border-red-500 focus:ring-red-500
                      @else border-zinc-300 focus:border-zinc-900 focus:ring-zinc-900 @enderror">
        @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="password_confirmation" class="mb-1 block text-sm font-medium text-zinc-700">Repetir contraseña</label>
        <input type="password" name="password_confirmation" id="password_confirmation" autocomplete="new-password"
               class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-900 focus:outline-none focus:ring-1 focus:ring-zinc-900">
    </div>
</div>