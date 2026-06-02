<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\GalleryResource;
use App\Models\Gallery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GalleryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['sometimes', 'nullable', Rule::in(['all', 'trail-run', 'walk', 'camping', 'hike', 'wellness'])],
            'year' => ['sometimes', 'nullable', Rule::in(['all', '2026', '2025'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $perPage = max(1, min((int) ($validated['per_page'] ?? 12), 50));
        $query = Gallery::query()
            ->with(['community', 'event'])
            ->latest();

        if (($validated['category'] ?? 'all') !== 'all') {
            $query->where('category', $validated['category']);
        }

        if (($validated['year'] ?? 'all') !== 'all') {
            $query->where('year', $validated['year']);
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => GalleryResource::collection($paginator->getCollection())->resolve($request),
            'meta' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Gallery $gallery): GalleryResource
    {
        return new GalleryResource($gallery->load([
            'community',
            'event' => fn ($query) => $query
                ->with(['community', 'place'])
                ->withMin('ticketTypes as starting_price', 'price'),
        ]));
    }
}
