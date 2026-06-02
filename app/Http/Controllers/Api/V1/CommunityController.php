<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommunityResource;
use App\Models\Community;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
}
