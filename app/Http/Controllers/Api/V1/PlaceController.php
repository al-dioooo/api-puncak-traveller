<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlaceResource;
use App\Models\Place;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlaceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min($request->integer('per_page', 15), 50));

        return PlaceResource::collection(
            Place::query()
                ->with('community')
                ->when($request->string('community')->isNotEmpty(), fn ($query) => $query->whereHas('community', fn ($community) => $community->where('slug', $request->string('community')->toString())))
                ->latest()
                ->paginate($perPage)
        );
    }

    public function show(Place $place): PlaceResource
    {
        return new PlaceResource($place->load('community'));
    }
}
