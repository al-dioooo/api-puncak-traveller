<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\GalleryResource;
use App\Models\Gallery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GalleryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min($request->integer('per_page', 15), 50));

        return GalleryResource::collection(
            Gallery::query()
                ->with(['community', 'event'])
                ->when($request->string('community')->isNotEmpty(), fn ($query) => $query->whereHas('community', fn ($community) => $community->where('slug', $request->string('community')->toString())))
                ->when($request->string('event')->isNotEmpty(), fn ($query) => $query->whereHas('event', fn ($event) => $event->where('slug', $request->string('event')->toString())))
                ->latest()
                ->paginate($perPage)
        );
    }

    public function show(Gallery $gallery): GalleryResource
    {
        return new GalleryResource($gallery->load(['community', 'event']));
    }
}
