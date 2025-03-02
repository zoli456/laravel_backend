<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use jeremykenedy\LaravelRoles\Models\Role;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Invalid registration data', 'errors' => $e->errors()], 422);
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $role = Role::where('slug', 'user')->first();
        if ($role) {
            $user->attachRole($role);
        }

        return response()->json(['message' => 'User registered successfully'], 201);
    }

    public function login(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|string|email',
                'password' => 'required|string',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Invalid login data', 'errors' => $e->errors()], 422);
        }

        if (!Auth::attempt($validated)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $user = Auth::user();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json(['token' => $token, 'user' => $user], 200);
    }

    public function userProfile()
    {
        $user = Auth::user();
        return response()->json([
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roles->pluck('name'),
        ], 200);
    }

    public function logout()
    {
        Auth::user()->tokens()->delete();
        return response()->json(['message' => 'Logged out successfully'], 200);
    }

    public function updateCredentials(Request $request)
    {
        $user = Auth::user();

        try {
            $validated = $request->validate([
                'email' => 'nullable|string|email|max:255|unique:users,email,' . $user->id,
                'oldPassword' => 'required_with:newPassword|string',
                'newPassword' => 'nullable|string|min:8',
                'retypePassword' => 'nullable|string|same:newPassword',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Invalid data', 'errors' => $e->errors()], 422);
        }

        if (isset($validated['newPassword'])) {
            if (!Hash::check($validated['oldPassword'], $user->password)) {
                return response()->json(['message' => 'Current password is incorrect'], 403);
            }
            $user->password = Hash::make($validated['newPassword']);
        }

        if (isset($validated['email'])) {
            $user->email = $validated['email'];
        }

        $user->save();

        return response()->json(['message' => 'User credentials updated successfully'], 200);
    }

    public function listUsers()
    {
        $users = User::with('roles')->get();
        return response()->json($users, 200);
    }

    public function listRoles()
    {
        $roles = Role::all(['id', 'name', 'slug']);
        return response()->json($roles, 200);
    }

    public function assignRole(Request $request, $userId)
    {
        $request->validate([
            'role_id' => 'required|exists:roles,id',
        ]);

        $user = User::findOrFail($userId);
        $role = Role::findOrFail($request->role_id);

        if ($user->hasRole($role->slug)) {
            return response()->json(['message' => 'User already has this role'], 400);
        }

        $user->attachRole($role);

        return response()->json(['message' => 'Role assigned successfully'], 200);
    }

    public function removeRole(Request $request, $userId)
    {
        $request->validate([
            'role_id' => 'required|exists:roles,id',
        ]);

        $user = User::findOrFail($userId);
        $role = Role::findOrFail($request->role_id);

        if (!$user->hasRole($role->slug)) {
            return response()->json(['message' => 'User does not have this role'], 400);
        }

        $user->detachRole($role);

        return response()->json(['message' => 'Role removed successfully'], 200);
    }

    public function updateUserDetails(Request $request, $userId)
    {
        try {
            $user = User::findOrFail($userId);
        } catch (\Exception $e) {
            return response()->json(['message' => 'User not found'], 404);
        }

        try {
            $validated = $request->validate([
                'name' => 'nullable|string|max:255',
                'email' => 'nullable|string|email|max:255|unique:users,email,' . $userId,
                'newPassword' => 'nullable|string|min:8',
                'retypePassword' => 'nullable|string|same:newPassword',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Invalid input data', 'errors' => $e->errors()], 422);
        }

        try {
            if (isset($validated['name'])) {
                $user->name = $validated['name'];
            }

            if (isset($validated['email'])) {
                $user->email = $validated['email'];
            }

            if (isset($validated['newPassword'])) {
                $user->password = Hash::make($validated['newPassword']);
            }

            $user->save();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to update user details'], 500);
        }

        return response()->json(['message' => 'User details updated successfully'], 200);
    }

    public function getUserById($userId)
    {
        try {
            $user = User::with(['roles'])->findOrFail($userId);

            return response()->json($user, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'User not found'], 404);
        }
    }
    public function deleteUser($userId)
    {
        try {
            $user = User::findOrFail($userId);

            $user->delete();

            return response()->json(['message' => 'User deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'User not found'], 404);
        }
    }
}
