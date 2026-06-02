<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min($request->integer('per_page', 15), 50));

        return EventResource::collection(
            Event::query()
                ->with(['community', 'place'])
                ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->forStatus($request->string('status')->toString()))
                ->when($request->string('community')->isNotEmpty(), fn ($query) => $query->whereHas('community', fn ($community) => $community->where('slug', $request->string('community')->toString())))
                ->latest('starts_at')
                ->paginate($perPage)
        );
    }

    public function show(Event $event): EventResource
    {
        return new EventResource($event->load(['community', 'place', 'ticketTypes']));
    }
}
