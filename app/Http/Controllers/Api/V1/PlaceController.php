<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlaceResource;
use App\Models\Community;
use App\Models\Place;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

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

    public function store(Request $request): PlaceResource
    {
        $validated = $request->validate($this->rules());

        return new PlaceResource(Place::query()->create($this->normalizePayload($validated))->load('community'));
    }

    public function update(Request $request, Place $place): PlaceResource
    {
        $validated = $request->validate($this->rules(true));
        $place->update($this->normalizePayload($validated));

        return new PlaceResource($place->refresh()->load('community'));
    }

    public function destroy(Place $place): JsonResponse
    {
        if ($place->events()->exists()) {
            throw new ConflictHttpException('Places with linked events cannot be deleted.');
        }

        $place->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $partial = false): array
    {
        return [
            'community_id' => [$partial ? 'sometimes' : 'required', 'integer', 'exists:communities,id'],
            'community_slug' => ['sometimes', 'nullable', 'string', 'exists:communities,slug'],
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:160'],
            'lat' => [$partial ? 'sometimes' : 'required', 'numeric', 'between:-90,90'],
            'lng' => [$partial ? 'sometimes' : 'required', 'numeric', 'between:-180,180'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizePayload(array $validated): array
    {
        if (! empty($validated['community_slug'])) {
            $validated['community_id'] = Community::query()
                ->where('slug', $validated['community_slug'])
                ->value('id');
        }

        unset($validated['community_slug']);

        return $validated;
    }
}
