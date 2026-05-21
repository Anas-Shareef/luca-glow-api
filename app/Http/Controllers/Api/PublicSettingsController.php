<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class PublicSettingsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $general = Setting::group('general');
        $regional = Setting::group('regional');

        $settings = [
            'store_name'    => $general['store_name'] ?? 'LUCA × LYKHA',
            'tagline'       => $general['tagline'] ?? 'Feel The Change · Since 2016',
            'support_email' => $general['support_email'] ?? 'Support@lucasworld.in',
            'support_phone' => $general['support_phone'] ?? '+91 95679 03350',
            'support_whatsapp' => $general['support_whatsapp'] ?? '+91 95679 03350',
            'address'       => $general['address'] ?? 'Northern Sky Apartments, 1704, B Block, Pumpwell, Mangalore.',
            'logo_url'      => $this->getUrl($general['store_logo'] ?? null),
            'logo_dark_url' => $this->getUrl($general['store_logo_dark'] ?? null),
            'favicon_url'   => $this->getUrl($general['store_favicon'] ?? null),
            'maintenance'   => (bool) ($general['maintenance'] ?? false),
            'maintenance_msg' => $general['maintenance_msg'] ?? 'We\'re upgrading our store experience. Back soon!',
            'currency'      => 'INR',
            'currency_symbol' => '₹',
            'policy_privacy'  => $general['policy_privacy'] ?? '',
            'policy_terms'    => $general['policy_terms'] ?? '',
            'policy_return'   => $general['policy_return'] ?? '',
            'policy_faq'      => $general['policy_faq'] ?? '',
            'policy_shipping' => $general['policy_shipping'] ?? '',
            'social_facebook' => $general['social_facebook'] ?? '',
            'social_instagram'=> $general['social_instagram'] ?? '',
            'social_youtube'  => $general['social_youtube'] ?? '',
            'social_twitter'  => $general['social_twitter'] ?? '',
        ];

        return response()->json($settings);
    }

    private function getUrl(?string $path): ?string
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
