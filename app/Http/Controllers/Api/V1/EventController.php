<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', Rule::in(['all', 'upcoming', 'ongoing', 'completed'])],
            'activity' => ['sometimes', 'nullable', Rule::in(['all', ...array_keys(Event::ACTIVITY_LABELS)])],
            'sort' => ['sometimes', 'nullable', Rule::in(['date', 'price', 'spots'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $perPage = max(1, min((int) ($validated['per_page'] ?? 12), 50));
        $query = Event::query()
            ->with(['community', 'place', 'ticketTypes'])
            ->withMin('ticketTypes as starting_price', 'price')
            ->withSum('ticketTypes as ticket_quantity_sum', 'quantity')
            ->withSum('ticketTypes as ticket_sold_sum', 'sold');

        if (($validated['status'] ?? 'all') !== 'all') {
            $query->forStatus($validated['status']);
        }

        if (($validated['activity'] ?? 'all') !== 'all') {
            $query->where(fn ($query) => $query
                ->where('activity', $validated['activity'])
                ->orWhere('activity_type', $validated['activity']));
        }

        if (! empty($validated['q'])) {
            $search = $validated['q'];
            $query->where(function ($query) use ($search): void {
                $query
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('region', 'like', "%{$search}%")
                    ->orWhere('status_label', 'like', "%{$search}%")
                    ->orWhereHas('community', fn ($community) => $community->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('place', fn ($place) => $place->where('name', 'like', "%{$search}%"));
            });
        }

        match ($validated['sort'] ?? 'date') {
            'price' => $query->orderBy('starting_price')->orderBy('starts_at'),
            'spots' => $query->orderByRaw('(coalesce(ticket_quantity_sum, 0) - coalesce(ticket_sold_sum, 0)) desc')->orderBy('starts_at'),
            default => $query->orderBy('starts_at'),
        };

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => EventResource::collection($paginator->getCollection())->resolve($request),
            'meta' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Event $event): EventResource
    {
        return new EventResource($event->load(['community', 'place', 'ticketTypes'])->loadMin('ticketTypes as starting_price', 'price'));
    }
}
