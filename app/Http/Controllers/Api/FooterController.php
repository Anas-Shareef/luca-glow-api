<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FooterSection;
use App\Models\FooterLink;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FooterController extends Controller
{
    // Public: get dynamic footer links tree
    public function index(): JsonResponse
    {
        $sections = FooterSection::with('links')
            ->where('is_active', true)
            ->orderBy('order')
            ->get();

        return response()->json($sections);
    }

    // Admin: get all footer sections
    public function adminIndex(): JsonResponse
    {
        $sections = FooterSection::with('links')
            ->orderBy('order')
            ->get();

        return response()->json($sections);
    }

    // Admin: create a section
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'     => 'required|string|max:100',
            'order'     => 'integer',
            'is_active' => 'boolean',
        ]);

        $section = FooterSection::create([
            'title'     => $data['title'],
            'order'     => $data['order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Footer section created successfully.',
            'data'    => $section->load('links'),
        ], 21);
    }

    // Admin: update a section
    public function update(Request $request, $id): JsonResponse
    {
        $section = FooterSection::findOrFail($id);

        $data = $request->validate([
            'title'     => 'string|max:100',
            'order'     => 'integer',
            'is_active' => 'boolean',
        ]);

        $section->update($data);

        return response()->json([
            'message' => 'Footer section updated successfully.',
            'data'    => $section->load('links'),
        ]);
    }

    // Admin: delete a section
    public function destroy($id): JsonResponse
    {
        $section = FooterSection::findOrFail($id);
        $section->delete();

        return response()->json([
            'message' => 'Footer section deleted successfully.',
        ]);
    }

    // Admin: store a link inside a section
    public function storeLink(Request $request, $sectionId): JsonResponse
    {
        $section = FooterSection::findOrFail($sectionId);

        $data = $request->validate([
            'label' => 'required|string|max:100',
            'url'   => 'required|string|max:255',
            'order' => 'integer',
        ]);

        $link = FooterLink::create([
            'footer_section_id' => $section->id,
            'label'             => $data['label'],
            'url'               => $data['url'],
            'order'             => $data['order'] ?? 0,
        ]);

        return response()->json([
            'message' => 'Footer link added successfully.',
            'data'    => $link,
        ], 21);
    }

    // Admin: update a link
    public function updateLink(Request $request, $linkId): JsonResponse
    {
        $link = FooterLink::findOrFail($linkId);

        $data = $request->validate([
            'label' => 'string|max:100',
            'url'   => 'string|max:255',
            'order' => 'integer',
        ]);

        $link->update($data);

        return response()->json([
            'message' => 'Footer link updated successfully.',
            'data'    => $link,
        ]);
    }

    // Admin: delete a link
    public function destroyLink($linkId): JsonResponse
    {
        $link = FooterLink::findOrFail($linkId);
        $link->delete();

        return response()->json([
            'message' => 'Footer link deleted successfully.',
        ]);
    }
}
