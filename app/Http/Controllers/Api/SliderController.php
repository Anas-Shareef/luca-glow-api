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

        return response()->json($this->transform($slider->fresh()->load('media')));
    }

    public function destroy(Slider $slider): JsonResponse
    {
        $slider->clearMediaCollection('banner');
        $slider->delete();
        return response()->json(['message' => 'Slider deleted.']);
    }

    public function toggleStatus(Slider $slider): JsonResponse
    {
        $slider->update(['is_active' => !$slider->is_active]);
        return response()->json(['is_active' => $slider->fresh()->is_active]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $request->validate(['order' => 'required|array']);
        foreach ($request->order as $position => $id) {
            Slider::where('id', $id)->update(['sort_order' => $position]);
        }
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
            'image_url'   => $s->getFirstMediaUrl('banner', 'banner_webp'),
            'mobile_url'  => $s->getFirstMediaUrl('banner', 'mobile'),
        ];
    }
}
