<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        $users = User::orderBy('name')->paginate(15);

        return view('users.index', compact('users'));
    }

    public function create()
    {
        return view('users.create', [
            'user'  => new User(),
            'roles' => User::ROLES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'role'     => ['required', Rule::in(array_keys(User::ROLES))],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        User::create($data); // el cast 'hashed' hashea la password sola

        return redirect()->route('users.index')
            ->with('success', 'Usuario creado correctamente.');
    }

    public function edit(User $user)
    {
        return view('users.edit', [
            'user'  => $user,
            'roles' => User::ROLES,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role'     => ['required', Rule::in(array_keys(User::ROLES))],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        // Si no escribieron contraseña nueva, no la tocamos
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()->route('users.index')
            ->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors('No podés eliminar tu propia cuenta.');
        }

        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'Usuario eliminado.');
    }
}