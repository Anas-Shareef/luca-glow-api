<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Slider;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class SliderController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Slider::with('media')->orderBy('sort_order')->get()->map(fn ($s) => $this->transform($s))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'       => 'required|string|max:120',
            'subtitle'    => 'nullable|string|max:200',
            'button_text' => 'nullable|string|max:60',
            'link_url'    => 'nullable|string|max:255',
            'sort_order'  => 'nullable|integer',
            'is_active'   => 'boolean',
            'starts_at'   => 'nullable|date',
            'ends_at'     => 'nullable|date|after_or_equal:starts_at',
            'banner'      => 'required|image|mimes:jpeg,png,webp|max:8192',
        ]);

        $slider = Slider::create($data);

        if ($request->hasFile('banner')) {
            $slider->addMedia($request->file('banner'))
                ->usingFileName(Str::uuid() . '.webp')
                ->toMediaCollection('banner');
        }

        \Illuminate\Support\Facades\Cache::forget('storefront_data');

        return response()->json($this->transform($slider->load('media')), 201);
    }

    public function show(Slider $slider): JsonResponse
    {
        return response()->json($this->transform($slider->load('media')));
    }

    public function update(Request $request, Slider $slider): JsonResponse
    {
        $data = $request->validate([
            'title'       => 'string|max:120',
            'subtitle'    => 'nullable|string|max:200',
            'button_text' => 'nullable|string|max:60',
            'link_url'    => 'nullable|string|max:255',
            'sort_order'  => 'nullable|integer',
            'is_active'   => 'boolean',
            'starts_at'   => 'nullable|date',
            'ends_at'     => 'nullable|date',
        ]);

        $slider->update($data);

        if ($request->hasFile('banner')) {
            $slider->clearMediaCollection('banner');
            $slider->addMedia($request->file('banner'))
                ->usingFileName(Str::uuid() . '.webp')
                ->toMediaCollection('banner');
        }

        \Illuminate\Support\Facades\Cache::forget('storefront_data');

        return response()->json($this->transform($slider->fresh()->load('media')));
    }

    public function destroy(Slider $slider): JsonResponse
    {
        $slider->clearMediaCollection('banner');
        $slider->delete();
        \Illuminate\Support\Facades\Cache::forget('storefront_data');
        return response()->json(['message' => 'Slider deleted.']);
    }

    public function toggleStatus(Slider $slider): JsonResponse
    {
        $slider->update(['is_active' => !$slider->is_active]);
        \Illuminate\Support\Facades\Cache::forget('storefront_data');
        return response()->json(['is_active' => $slider->fresh()->is_active]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $request->validate(['order' => 'required|array']);
        foreach ($request->order as $position => $id) {
            Slider::where('id', $id)->update(['sort_order' => $position]);
        }
        \Illuminate\Support\Facades\Cache::forget('storefront_data');
        return response()->json(['message' => 'Sliders reordered.']);
    }

    private function transform(Slider $s): array
    {
        return [
            'id'          => $s->id,
            'title'       => $s->title,
            'subtitle'    => $s->subtitle,
            'button_text' => $s->button_text,
            'link_url'    => $s->link_url,
            'sort_order'  => $s->sort_order,
            'is_active'   => $s->is_active,
            'is_live'     => $s->isCurrentlyActive(),
            'starts_at'   => $s->starts_at?->toDateString(),
            'ends_at'     => $s->ends_at?->toDateString(),
            'image_url'   => $this->ensureAbsoluteUrl($s->getFirstMediaUrl('banner', 'banner_webp') ?: $s->getFirstMediaUrl('banner')),
            'mobile_url'  => $this->ensureAbsoluteUrl($s->getFirstMediaUrl('banner', 'mobile') ?: $s->getFirstMediaUrl('banner')),
        ];
    }

    private function ensureAbsoluteUrl(?string $url): ?string
    {
        if (!$url) return null;
        
        $url = trim(str_replace(["\r", "\n", "\t"], '', $url));

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            if (str_starts_with($url, 'http://') && !str_contains($url, 'localhost') && !str_contains($url, '127.0.0.1')) {
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
