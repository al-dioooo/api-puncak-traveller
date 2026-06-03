<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommunityResource;
use App\Models\Community;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CommunityController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min($request->integer('per_page', 15), 50));

        return CommunityResource::collection(
            Community::query()
                ->with('children')
                ->whereNull('parent_id')
                ->latest()
                ->paginate($perPage)
        );
    }

    public function show(Community $community): CommunityResource
    {
        return new CommunityResource($community->load('children'));
    }

    public function store(Request $request): CommunityResource
    {
        $validated = $request->validate($this->rules());
        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);

        return new CommunityResource(Community::query()->create($validated));
    }

    public function update(Request $request, Community $community): CommunityResource
    {
        $validated = $request->validate($this->rules($community));

        if (array_key_exists('slug', $validated) && $validated['slug'] === null) {
            unset($validated['slug']);
        }

        $community->update($validated);

        return new CommunityResource($community->refresh()->load('children'));
    }

    public function destroy(Community $community): JsonResponse
    {
        if ($community->events()->exists() || $community->places()->exists() || $community->children()->exists()) {
            throw new ConflictHttpException('Communities with linked events, places, or child communities cannot be deleted.');
        }

        $community->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(?Community $community = null): array
    {
        return [
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:communities,id'],
            'name' => [$community ? 'sometimes' : 'required', 'string', 'max:140'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:160', 'alpha_dash:ascii', Rule::unique('communities', 'slug')->ignore($community?->id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'image_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'member_count' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
