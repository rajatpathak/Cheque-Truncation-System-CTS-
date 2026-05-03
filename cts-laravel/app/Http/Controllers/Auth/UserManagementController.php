<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::with('roles')
            ->when($request->branch_code, fn($q) => $q->where('branch_code', $request->branch_code))
            ->when($request->role,        fn($q) => $q->role($request->role))
            ->when($request->active,      fn($q) => $q->where('is_active', $request->active))
            ->paginate(30);

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'               => 'required|string|max:200',
            'username'           => 'required|string|unique:users|max:50',
            'email'              => 'required|email|unique:users',
            'branch_code'        => 'required|string',
            'mobile'             => 'nullable|string|size:10',
            'role'               => 'required|string',
            'daily_cheque_limit' => 'nullable|integer|min:1|max:500',
        ]);

        $user = User::create([
            'name'               => $request->name,
            'username'           => $request->username,
            'email'              => $request->email,
            'password'           => Hash::make('Temp@' . now()->format('Ymd')),
            'branch_code'        => $request->branch_code,
            'mobile'             => $request->mobile,
            'is_active'          => true,
            'daily_cheque_limit' => $request->daily_cheque_limit ?? config('cts.processing.max_cheques_per_user_day'),
            'password_changed_at'=> null,  // Force password change on first login
        ]);

        $user->assignRole($request->role);
        activity()->on($user)->log("USER_CREATED by {$request->user()->name}");

        return response()->json($user->load('roles'), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json(User::with(['roles', 'permissions'])->findOrFail($id));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->update($request->only(['name', 'email', 'branch_code', 'mobile', 'daily_cheque_limit']));
        return response()->json($user);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        User::findOrFail($id)->update(['is_active' => false]);
        return response()->json(['status' => 'DEACTIVATED']);
    }

    public function enable(Request $request, int $id): JsonResponse
    {
        User::findOrFail($id)->update(['is_active' => true, 'disabled_at' => null]);
        return response()->json(['status' => 'ENABLED']);
    }

    public function disable(Request $request, int $id): JsonResponse
    {
        User::findOrFail($id)->update(['is_active' => false, 'disabled_at' => now()]);
        return response()->json(['status' => 'DISABLED']);
    }

    public function assignRoles(Request $request, int $id): JsonResponse
    {
        $request->validate(['roles' => 'required|array']);
        $user = User::findOrFail($id);
        $user->syncRoles($request->roles);
        activity()->on($user)->withProperties(['roles' => $request->roles])->log('ROLES_ASSIGNED');
        return response()->json(['status' => 'ROLES_UPDATED', 'roles' => $user->getRoleNames()]);
    }

    public function setLimits(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'daily_cheque_limit' => 'required|integer|min:1|max:' . config('cts.processing.max_cheques_per_user_day'),
        ]);

        User::findOrFail($id)->update(['daily_cheque_limit' => $request->daily_cheque_limit]);
        return response()->json(['status' => 'LIMITS_UPDATED']);
    }

    public function accessReview(Request $request): JsonResponse
    {
        $month = $request->month ?? now()->format('Y-m');
        return response()->json(
            User::with('roles')->get()->map(fn($u) => [
                'id'          => $u->id,
                'name'        => $u->name,
                'branch_code' => $u->branch_code,
                'roles'       => $u->getRoleNames(),
                'is_active'   => $u->is_active,
                'last_login'  => $u->last_login_at,
            ])
        );
    }
}
