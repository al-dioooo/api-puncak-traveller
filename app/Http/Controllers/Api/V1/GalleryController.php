<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreGalleryRequest;
use App\Http\Resources\GalleryResource;
use App\Models\Community;
use App\Models\Event;
use App\Models\Gallery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
            ->orderBy('created_at', 'desc');

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
                ->with(['community', 'place', 'ticketTypes']),
        ]));
    }

    public function store(StoreGalleryRequest $request): JsonResponse
    {
        $event = $request->linkedEvent();
        $path = $request->file('image')->store('gallery', 'public');
        $title = $request->string('title')->toString();
        $publicId = $this->uniquePublicId($title);

        $gallery = Gallery::query()->create([
            'community_id' => $event?->community_id ?? $this->defaultCommunity()->id,
            'event_id' => $event?->id,
            'public_id' => $publicId,
            'title' => $title,
            'event_label' => $event?->title ?? 'Unlinked Event',
            'category' => $request->string('category')->toString(),
            'year' => now()->format('Y'),
            'image_path' => $path,
            'image_alt' => $title,
            'caption' => $title,
        ]);

        return (new GalleryResource($gallery->load(['community', 'event'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Gallery $gallery): GalleryResource
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:160'],
            'caption' => ['sometimes', 'nullable', 'string', 'max:500'],
            'event_id' => ['sometimes', 'nullable', 'string', 'max:120'],
            'event_label' => ['sometimes', 'nullable', 'string', 'max:160'],
            'category' => ['sometimes', 'required', 'string', Rule::in(array_keys(Event::ACTIVITY_LABELS))],
            'year' => ['sometimes', 'required', 'string', Rule::in(['2025', '2026'])],
            'image_alt' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        if (array_key_exists('event_id', $validated)) {
            $event = $this->findEvent($validated['event_id']);
            $validated['event_id'] = $event?->id;
            $validated['community_id'] = $event?->community_id ?? $gallery->community_id;
            $validated['event_label'] = $event?->title ?? ($validated['event_label'] ?? $gallery->event_label);
        }

        if (array_key_exists('title', $validated) && ! array_key_exists('caption', $validated)) {
            $validated['caption'] = $validated['title'];
        }

        $gallery->update($validated);

        return new GalleryResource($gallery->refresh()->load(['community', 'event']));
    }

    public function download(Gallery $gallery): JsonResponse|RedirectResponse|BinaryFileResponse
    {
        if (! $gallery->image_path) {
            return response()->json(['message' => 'Gallery image is missing.'], 404);
        }

        if (str_starts_with($gallery->image_path, '/')) {
            return redirect()->away(rtrim((string) config('app.frontend_url'), '/').$gallery->image_path);
        }

        if (! Storage::disk('public')->exists($gallery->image_path)) {
            return response()->json(['message' => 'Gallery image file is missing.'], 404);
        }

        return response()->download(
            Storage::disk('public')->path($gallery->image_path),
            Str::slug($gallery->title ?? $gallery->caption ?? 'gallery-photo').'.'.pathinfo($gallery->image_path, PATHINFO_EXTENSION)
        );
    }

    public function destroy(Gallery $gallery): JsonResponse
    {
        $this->deleteStoredFile($gallery);
        $gallery->delete();

        return response()->json(null, 204);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'string'],
        ]);

        $ids = collect($validated['ids'])->unique()->values();
        $galleries = Gallery::query()->whereIn('public_id', $ids)->get();

        foreach ($galleries as $gallery) {
            $this->deleteStoredFile($gallery);
            $gallery->delete();
        }

        return response()->json([
            'message' => 'Gallery photos deleted successfully.',
            'data' => [
                'deleted' => $galleries->count(),
                'missing' => $ids->count() - $galleries->count(),
            ],
        ]);
    }

    private function deleteStoredFile(Gallery $gallery): void
    {
        if ($gallery->image_path && ! str_starts_with($gallery->image_path, '/')) {
            Storage::disk('public')->delete($gallery->image_path);
        }
    }

    private function defaultCommunity(): Community
    {
        return Community::query()->firstOrCreate(
            ['slug' => 'puncak-travellers'],
            [
                'name' => 'Puncak Travellers',
                'description' => 'Healthy highland adventures across West Java.',
                'member_count' => 0,
            ]
        );
    }

    private function findEvent(?string $eventId): ?Event
    {
        if (! $eventId) {
            return null;
        }

        $query = Event::query()
            ->where('public_id', $eventId)
            ->orWhere('slug', $eventId);

        if (ctype_digit($eventId)) {
            $query->orWhereKey($eventId);
        }

        return $query->first();
    }

    private function uniquePublicId(string $title): string
    {
        $base = Str::slug($title) ?: 'gallery-photo';
        $publicId = $base;
        $counter = 2;

        while (Gallery::query()->where('public_id', $publicId)->exists()) {
            $publicId = "{$base}-{$counter}";
            $counter++;
        }

        return $publicId;
    }
}
