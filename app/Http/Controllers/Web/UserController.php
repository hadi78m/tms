<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreUserRequest;
use App\Http\Requests\Web\UpdateUserRequest;
use App\Models\SyncedContractor;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::with(['roles', 'contractor'])
            ->latest()
            ->paginate(15);

        return view('users.index', compact('users'));
    }

    public function create(): View
    {
        $roles = Role::all();
        $contractors = SyncedContractor::all();

        return view('users.create', compact('roles', 'contractors'));
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $isContractor = in_array('contractor', $request->roles);

        $user = User::create([
            'name' => $request->name,
            'national_code' => $request->national_code,
            'username' => $request->national_code,
            'mobile' => $request->mobile,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'contractor_id' => $isContractor ? $request->contractor_id : null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $user->syncRoles($request->roles);

        return redirect()
            ->route('users.index')
            ->with('status', 'کاربر جدید با موفقیت ایجاد شد.');
    }

    public function edit(User $user): View
    {
        $roles = Role::all();
        $contractors = SyncedContractor::all();

        return view('users.edit', compact('user', 'roles', 'contractors'));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $isContractor = in_array('contractor', $request->roles);

        $data = [
            'name' => $request->name,
            'national_code' => $request->national_code,
            'username' => $request->national_code,
            'mobile' => $request->mobile,
            'email' => $request->email,
            'contractor_id' => $isContractor ? $request->contractor_id : null,
            'is_active' => $request->boolean('is_active', true),
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);
        $user->syncRoles($request->roles);

        return redirect()
            ->route('users.index')
            ->with('status', 'اطلاعات کاربر با موفقیت به‌روزرسانی شد.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return redirect()
                ->route('users.index')
                ->with('error', 'امکان حذف حساب کاربری خودتان وجود ندارد.');
        }

        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('status', 'کاربر با موفقیت حذف شد.');
    }
}
