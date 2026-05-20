<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * POST /api/v1/auth/login
     * Returns Sanctum token + user data for React frontend
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'Your account has been suspended.'], 403);
        }

        // Record last login
        $user->update(['last_login_at' => now()]);

        // Revoke old tokens, issue fresh one
        $user->tokens()->delete();
        $token = $user->createToken('auth-token', ['*'], now()->addDays(7))->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $this->userPayload($user),
        ]);
    }

    /**
     * POST /api/v1/auth/register
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name'         => 'required|string|max:100',
            'email'        => 'required|email|unique:users,email',
            'phone'        => 'required|string|max:20',
            'password'     => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name'         => $request->name,
            'email'        => $request->email,
            'phone'        => $request->phone,
            'password'     => Hash::make($request->password),
            'is_active'    => true,
            'customer_group_id' => \App\Models\CustomerGroup::where('name', 'First-Time')->value('id'),
        ]);

        $customerRole = \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'Customer', 'guard_name' => 'sanctum']
        );
        $user->assignRole($customerRole);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $this->userPayload($user),
        ], 201);
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json($this->userPayload($request->user()));
    }

    /**
     * PUT /api/v1/auth/profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name'             => 'sometimes|string|max:100',
            'password'         => 'sometimes|string|min:8|confirmed',
            'current_password' => 'required_with:password|string',
        ]);

        if (isset($data['password'])) {
            if (!Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['Current password is incorrect.'],
                ]);
            }
            $data['password'] = Hash::make($data['password']);
        }

        $user->update(array_filter($data, fn ($k) => $k !== 'current_password', ARRAY_FILTER_USE_KEY));

        return response()->json($this->userPayload($user->fresh()));
    }

    /**
     * POST /api/v1/auth/forgot-password
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email|exists:users,email']);
        Password::sendResetLink($request->only('email'));
        return response()->json(['message' => 'Password reset link sent.']);
    }

    /**
     * POST /api/v1/auth/reset-password
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
                $user->tokens()->delete();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'Password reset successfully.'])
            : response()->json(['message' => __($status)], 400);
    }

    /**
     * POST /api/v1/auth/google
     */
    public function googleLogin(Request $request): JsonResponse
    {
        $request->validate([
            'credential' => 'required|string',
        ]);

        $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $request->credential,
        ]);

        if ($response->failed()) {
            return response()->json(['message' => 'Invalid Google credential.'], 401);
        }

        $payload = $response->json();

        if ($payload['aud'] !== config('services.google.client_id')) {
            return response()->json(['message' => 'Invalid Google client registration.'], 401);
        }

        $email = $payload['email'];
        $name = $payload['name'] ?? $email;

        $user = User::where('email', $email)->first();
        if (!$user) {
            $user = User::create([
                'name'              => $name,
                'email'             => $email,
                'password'          => Hash::make(Str::random(16)),
                'phone'             => '',
                'is_active'         => true,
                'customer_group_id' => \App\Models\CustomerGroup::where('name', 'First-Time')->value('id') ?? 1,
            ]);

            $customerRole = \Spatie\Permission\Models\Role::firstOrCreate(
                ['name' => 'Customer', 'guard_name' => 'sanctum']
            );
            $user->assignRole($customerRole);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'Your account has been suspended.'], 403);
        }

        $user->update(['last_login_at' => now()]);

        $user->tokens()->delete();
        $token = $user->createToken('auth-token', ['*'], now()->addDays(7))->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $this->userPayload($user),
        ]);
    }

    // ── Private helpers ───────────────────────────────────────
    private function userPayload(User $user): array
    {
        $roles = [];
        $permissions = [];

        try {
            $roles = $user->getRoleNames();
            $permissions = $user->getAllPermissions()->pluck('name');
        } catch (\Exception $e) {
            // Log or ignore if roles system fails
        }

        return [
            'id'            => $user->id,
            'name'          => $user->name,
            'email'         => $user->email,
            'phone'         => $user->phone,
            'roles'         => $roles,
            'permissions'   => $permissions,
            'avatar'        => $user->initials,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
        ];
    }
}
