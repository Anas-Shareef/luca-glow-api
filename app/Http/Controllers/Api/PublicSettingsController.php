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
        ];

        return response()->json($settings);
    }

    private function getUrl(?string $path): ?string
    {
        if (!$path) return null;
        $url = filter_var($path, FILTER_VALIDATE_URL) ? $path : asset(Storage::url($path));
        if (str_starts_with($url, 'http://') && !str_contains($url, 'localhost') && !str_contains($url, '127.0.0.1')) {
            $url = 'https://' . substr($url, 7);
        }
        return $url;
    }
}
