<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use jeremykenedy\LaravelRoles\Models\Role;

class AuthController extends Controller
{
    private function verifyHcaptcha($token)
    {
        $secret = env('HCAPTCHA_SECRET_KEY');
        $response = Http::withOptions([
            'verify' => false // DISABLES SSL VERIFICATION
        ])->asForm()->post('https://hcaptcha.com/siteverify', [
            'secret' => $secret,
            'response' => $this->sanitizeInput($token)
        ]);

        $body = $response->json();

        if (!$response->successful() || !$body['success']) {
            $errorCodes = $body['error-codes'] ?? ['unknown-error'];
            throw new \Exception('Captcha verification failed: ' . implode(', ', $errorCodes));
        }

        return true;
    }

    private function sanitizeInput($input)
    {
        if (is_array($input)) {
            return array_map([$this, 'sanitizeInput'], $input);
        }

        // Convert to string if not already
        $string = (string) $input;

        // Remove whitespace from beginning and end
        $string = trim($string);

        // Strip HTML and PHP tags
        $string = strip_tags($string);

        // Convert special characters to HTML entities
        $string = htmlspecialchars($string, ENT_QUOTES, 'UTF-8');

        // Remove excessive whitespace
        $string = preg_replace('/\s+/', ' ', $string);

        return $string;
    }

    public function register(Request $request)
    {
        try {
            $sanitizedData = $this->sanitizeInput($request->all());
            $validated = validator($sanitizedData, [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8',
                'hcaptchaToken' => 'required|string'
            ])->validate();

            $this->verifyHcaptcha($validated['hcaptchaToken']);

        } catch (ValidationException $e) {
            return response()->json(['message' => 'Invalid registration data', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
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
            $sanitizedData = $this->sanitizeInput($request->all());
            $validated = validator($sanitizedData, [
                'email' => 'required|string|email',
                'password' => 'required|string',
                'hcaptchaToken' => 'required|string'
            ])->validate();

            $this->verifyHcaptcha($validated['hcaptchaToken']);

        } catch (ValidationException $e) {
            return response()->json(['message' => 'Invalid login data', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if (!Auth::attempt(['email' => $validated['email'], 'password' => $validated['password']])) {
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
            $sanitizedData = $this->sanitizeInput($request->all());
            $validated = validator($sanitizedData, [
                'email' => 'nullable|string|email|max:255|unique:users,email,' . $user->id,
                'oldPassword' => 'required_with:newPassword|string',
                'newPassword' => 'nullable|string|min:8',
                'retypePassword' => 'nullable|string|same:newPassword',
            ])->validate();
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
        $sanitizedData = $this->sanitizeInput($request->all());
        $sanitizedUserId = $this->sanitizeInput($userId);

        $validated = validator($sanitizedData, [
            'role_id' => 'required|exists:roles,id',
        ])->validate();

        $user = User::findOrFail($sanitizedUserId);
        $role = Role::findOrFail($validated['role_id']);

        if ($user->hasRole($role->slug)) {
            return response()->json(['message' => 'User already has this role'], 400);
        }

        $user->attachRole($role);

        return response()->json(['message' => 'Role assigned successfully'], 200);
    }

    public function removeRole(Request $request, $userId)
    {
        $sanitizedData = $this->sanitizeInput($request->all());
        $sanitizedUserId = $this->sanitizeInput($userId);

        $validated = validator($sanitizedData, [
            'role_id' => 'required|exists:roles,id',
        ])->validate();

        $user = User::findOrFail($sanitizedUserId);
        $role = Role::findOrFail($validated['role_id']);

        if (!$user->hasRole($role->slug)) {
            return response()->json(['message' => 'User does not have this role'], 400);
        }

        $user->detachRole($role);

        return response()->json(['message' => 'Role removed successfully'], 200);
    }

    public function updateUserDetails(Request $request, $userId)
    {
        $sanitizedUserId = $this->sanitizeInput($userId);

        try {
            $user = User::findOrFail($sanitizedUserId);
        } catch (\Exception $e) {
            return response()->json(['message' => 'User not found'], 404);
        }

        try {
            $sanitizedData = $this->sanitizeInput($request->all());
            $validated = validator($sanitizedData, [
                'name' => 'nullable|string|max:255',
                'email' => 'nullable|string|email|max:255|unique:users,email,' . $sanitizedUserId,
                'newPassword' => 'nullable|string|min:8',
                'retypePassword' => 'nullable|string|same:newPassword',
            ])->validate();
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
        $sanitizedUserId = $this->sanitizeInput($userId);

        try {
            $user = User::with(['roles'])->findOrFail($sanitizedUserId);

            return response()->json($user, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'User not found'], 404);
        }
    }

    public function deleteUser($userId)
    {
        $sanitizedUserId = $this->sanitizeInput($userId);

        try {
            $user = User::findOrFail($sanitizedUserId);

            $user->delete();

            return response()->json(['message' => 'User deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'User not found'], 404);
        }
    }
}
