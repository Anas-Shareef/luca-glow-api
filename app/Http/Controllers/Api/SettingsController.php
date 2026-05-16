<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Models\CustomerGroup;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'general'  => Setting::group('general'),
            'regional' => Setting::group('regional'),
            'seo'      => Setting::group('seo'),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'store_name'     => 'string|max:100',
            'tagline'        => 'nullable|string|max:200',
            'support_email'  => 'nullable|email',
            'support_phone'  => 'nullable|string|max:20',
            'address'        => 'nullable|string|max:500',
            'gst_rate'       => 'numeric|min:0|max:100',
            'gst_number'     => 'nullable|string|max:20',
            'maintenance'    => 'boolean',
            'maintenance_msg'=> 'nullable|string|max:500',
        ]);

        $groups = [
            'store_name'      => 'general',
            'tagline'         => 'general',
            'support_email'   => 'general',
            'support_phone'   => 'general',
            'address'         => 'general',
            'gst_rate'        => 'regional',
            'gst_number'      => 'regional',
            'maintenance'     => 'general',
            'maintenance_msg' => 'general',
        ];

        foreach ($data as $key => $value) {
            Setting::set($key, $value, $groups[$key] ?? 'general');
        }

        return response()->json(['message' => 'Settings saved.', 'data' => $data]);
    }

    public function uploadMedia(Request $request): JsonResponse
    {
        $request->validate([
            'key'  => 'required|string|in:store_logo,store_logo_dark,store_favicon',
            'file' => 'required|image|max:2048',
        ]);

        $key = $request->key;
        $path = $request->file('file')->store('settings', 'public');

        // Delete old file if exists
        $old = Setting::get($key);
        if ($old) Storage::disk('public')->delete($old);

        Setting::set($key, $path, 'general');

        return response()->json([
            'key' => $key,
            'url' => asset(Storage::url($path)),
            'message' => 'Media uploaded successfully.'
        ]);
    }

    public function clearCache(Request $request): JsonResponse
    {
        $type = $request->get('type', 'all');

        match ($type) {
            'config' => Artisan::call('config:clear'),
            'route'  => Artisan::call('route:clear'),
            'view'   => Artisan::call('view:clear'),
            default  => (function () {
                Artisan::call('config:clear');
                Artisan::call('route:clear');
                Artisan::call('view:clear');
                Artisan::call('cache:clear');
            })(),
        };

        return response()->json(['message' => ucfirst($type) . ' cache cleared.']);
    }

    public function staff(): JsonResponse
    {
        $staff = User::with('roles')
            ->whereHas('roles')
            ->latest()
            ->get()
            ->map(fn ($u) => [
                'id'      => $u->id,
                'name'    => $u->name,
                'email'   => $u->email,
                'role'    => $u->getRoleNames()->first() ?? 'Viewer',
                'active'  => $u->is_active,
                'avatar'  => $u->initials,
                'joined'  => $u->created_at->toDateString(),
            ]);

        return response()->json($staff);
    }

    public function createStaff(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => 'required|email|unique:users,email',
            'name'     => 'required|string|max:100',
            'password' => 'required|string|min:8',
            'role'     => 'required|exists:roles,name',
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'is_active'=> true,
        ]);

        // Assign Role
        $user->assignRole($data['role']);

        return response()->json([
            'message' => "Staff member {$user->name} created successfully.",
            'staff'   => [
                'id'      => $user->id,
                'name'    => $user->name,
                'email'   => $user->email,
                'role'    => $data['role'],
                'active'  => true,
                'avatar'  => $user->initials,
                'joined'  => $user->created_at->toDateString(),
            ],
        ], 201);
    }

    public function updateRole(Request $request, User $user): JsonResponse
    {
        $request->validate(['role' => 'required|exists:roles,name']);
        $user->syncRoles([$request->role]);
        return response()->json(['role' => $request->role]);
    }

    public function toggleStaff(User $user): JsonResponse
    {
        $user->update(['is_active' => !$user->is_active]);
        return response()->json(['is_active' => $user->fresh()->is_active]);
    }

    public function deleteStaff(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();

        if ($currentUser && $currentUser->id === $user->id) {
            return response()->json(['message' => 'You cannot delete yourself.'], 403);
        }

        $user->delete();
        return response()->json(['message' => 'Staff member deleted.']);
    }
}
