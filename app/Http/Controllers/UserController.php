<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        return view('users.index');
    }

    public function json(Request $request)
    {
        return User::adminTable($request, [
            'search' => ['name', 'email'],
            'filters' => [],
            'sortable' => ['name', 'email', 'created_at'],
            'with' => [],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create($validated);

        return response()->json([
            'success' => true,
            'provider' => User::adminTransform($user),
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => "required|email|unique:users,email,{$id}",
            'password' => 'nullable|string|min:6',
        ]);

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'success' => true,
            'provider' => User::adminTransform($user),
        ]);
    }

    public function destroy($id)
    {
        User::findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }
}
