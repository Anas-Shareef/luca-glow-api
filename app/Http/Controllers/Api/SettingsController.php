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
        $general = Setting::group('general');
        
        foreach (['store_logo', 'store_logo_dark', 'store_favicon'] as $key) {
            $general[$key . '_url'] = !empty($general[$key]) ? $this->ensureAbsoluteUrl($general[$key]) : null;
        }

        return response()->json([
            'general'  => $general,
            'regional' => Setting::group('regional'),
            'seo'      => Setting::group('seo'),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        foreach (['social_facebook', 'social_instagram', 'social_youtube', 'social_twitter'] as $key) {
            if ($request->filled($key)) {
                $value = trim($request->input($key));
                if (!preg_match('~^https?://~i', $value)) {
                    $request->merge([$key => 'https://' . ltrim($value, '/')]);
                }
            }
        }

        $data = $request->validate([
            'store_name'     => 'string|max:100',
            'tagline'        => 'nullable|string|max:200',
            'support_email'  => 'nullable|email',
            'support_phone'  => 'nullable|string|max:20',
            'support_whatsapp' => 'nullable|string|max:20',
            'address'        => 'nullable|string|max:500',
            'gst_rate'       => 'numeric|min:0|max:100',
            'gst_number'     => 'nullable|string|max:20',
            'maintenance'    => 'boolean',
            'maintenance_msg'=> 'nullable|string|max:500',
            'policy_privacy' => 'nullable|string',
            'policy_terms'   => 'nullable|string',
            'policy_return'  => 'nullable|string',
            'policy_faq'     => 'nullable|string',
            'policy_shipping'=> 'nullable|string',
            'social_facebook'=> 'nullable|url',
            'social_instagram'=> 'nullable|url',
            'social_youtube' => 'nullable|url',
            'social_twitter' => 'nullable|url',
            'instagram_feed' => 'nullable',
        ]);

        $groups = [
            'store_name'      => 'general',
            'tagline'         => 'general',
            'support_email'   => 'general',
            'support_phone'   => 'general',
            'support_whatsapp' => 'general',
            'address'         => 'general',
            'gst_rate'        => 'regional',
            'gst_number'      => 'regional',
            'maintenance'     => 'general',
            'maintenance_msg' => 'general',
            'policy_privacy'  => 'general',
            'policy_terms'    => 'general',
            'policy_return'   => 'general',
            'policy_faq'      => 'general',
            'policy_shipping' => 'general',
            'social_facebook' => 'general',
            'social_instagram'=> 'general',
            'social_youtube'  => 'general',
            'social_twitter'  => 'general',
            'instagram_feed'  => 'general',
        ];

        foreach ($data as $key => $value) {
            if ($key === 'instagram_feed' && is_array($value)) {
                $value = json_encode($value);
            }
            Setting::set($key, $value, $groups[$key] ?? 'general');
        }

        \Illuminate\Support\Facades\Cache::forget('storefront_data');

        return response()->json(['message' => 'Settings saved.', 'data' => $data]);
    }

    public function uploadMedia(Request $request): JsonResponse
    {
        $request->validate([
            'key'  => 'required|string|in:store_logo,store_logo_dark,store_favicon',
            'file' => 'required|image|max:2048',
        ]);

        $key = $request->key;
        
        // Convert the file to base64 so it persists on ephemeral filesystems (e.g. Render)
        $file = $request->file('file');
        $mime = $file->getMimeType();
        $base64Data = base64_encode(file_get_contents($file->getRealPath()));
        $base64 = "data:{$mime};base64,{$base64Data}";

        // Delete old file if it was a stored path
        $old = Setting::get($key);
        if ($old && !str_starts_with($old, 'data:') && !filter_var($old, FILTER_VALIDATE_URL)) {
            Storage::disk('public')->delete($old);
        }

        Setting::set($key, $base64, 'general');

        return response()->json([
            'key' => $key,
            'url' => $base64,
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

    public function getPromo(): JsonResponse
    {
        $mediaUrl = Setting::get('promo_image_url');
        if ($mediaUrl) {
            $mediaUrl = $this->ensureAbsoluteUrl($mediaUrl);
        }

        return response()->json([
            'tag'      => Setting::get('promo_tag', 'New Arrival'),
            'title'    => Setting::get('promo_title', 'Lykha Foundations'),
            'subtitle' => Setting::get('promo_subtitle', 'Glow Beyond Limits'),
            'image'    => $mediaUrl ?? 'https://images.unsplash.com/photo-1631730486572-226d1f595b68?auto=format&fit=crop&w=600&q=80',
            'slug'     => Setting::get('promo_slug', 'lykha-makeup'),
        ]);
    }

    public function updatePromo(Request $request): JsonResponse
    {
        $request->validate([
            'tag'      => 'required|string|max:50',
            'title'    => 'required|string|max:100',
            'subtitle' => 'required|string|max:100',
            'slug'     => 'required|string|max:100',
            'image'    => 'nullable|file|image|max:2048',
        ]);

        Setting::set('promo_tag', $request->tag, 'promo');
        Setting::set('promo_title', $request->title, 'promo');
        Setting::set('promo_subtitle', $request->subtitle, 'promo');
        Setting::set('promo_slug', $request->slug, 'promo');

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('settings', 'public');
            
            $oldPath = Setting::get('promo_image_path');
            if ($oldPath) {
                Storage::disk('public')->delete($oldPath);
            }
            
            $url = $this->ensureAbsoluteUrl($path);
            
            Setting::set('promo_image_path', $path, 'promo');
            Setting::set('promo_image_url', $url, 'promo');
        }

        \Illuminate\Support\Facades\Cache::forget('storefront_data');

        return response()->json([
            'message' => 'New Arrival promo card updated successfully.'
        ]);
    }

    private function ensureAbsoluteUrl(?string $path): ?string
    {
        if (!$path) return null;
        
        $path = trim(str_replace(["\r", "\n", "\t"], '', $path));

        if (str_starts_with($path, 'data:')) {
            return $path;
        }

        if (str_contains($path, 'localhost') || str_contains($path, '127.0.0.1')) {
            $parsed = parse_url($path);
            $path = ($parsed['path'] ?? '') . (isset($parsed['query']) ? '?' . $parsed['query'] : '');
        }

        if (filter_var($path, FILTER_VALIDATE_URL) || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            if (str_starts_with($path, 'http://')) {
                $path = 'https://' . substr($path, 7);
            }
            return $path;
        }

        $url = Storage::url($path);

        if (str_contains($url, 'localhost') || str_contains($url, '127.0.0.1')) {
            $parsed = parse_url($url);
            $url = ($parsed['path'] ?? '') . (isset($parsed['query']) ? '?' . $parsed['query'] : '');
        }
        
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            if (str_starts_with($url, 'http://')) {
                $url = 'https://' . substr($url, 7);
            }
            return $url;
        }

        $root = rtrim(request()->root(), '/');
        if (str_starts_with($root, 'http://') && !str_contains($root, 'localhost') && !str_contains($root, '127.0.0.1')) {
            $root = 'https://' . substr($root, 7);
        }

        return $root . '/' . ltrim($url, '/');
    }
}
