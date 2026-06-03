<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpsertEventRequest;
use App\Http\Resources\EventResource;
use App\Models\Community;
use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

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
            ->with(['community', 'place', 'ticketTypes']);

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

        $now = now();

        $events = match ($validated['sort'] ?? 'date') {
            'price' => $query->get()
                ->sortBy(fn (Event $event): array => [
                    $event->ticketTypes->min('price') ?? PHP_INT_MAX,
                    $event->starts_at?->timestamp ?? PHP_INT_MAX,
                ])
                ->values(),
            'spots' => $query->get()
                ->sortBy(fn (Event $event): array => [
                    -1 * $event->ticketTypes->sum(fn (TicketType $ticketType): int => $ticketType->remaining),
                    $event->starts_at?->timestamp ?? PHP_INT_MAX,
                ])
                ->values(),
            default => $query->get()
                ->sortBy(fn (Event $event): array => [
                    $this->statusSortRank($event, $now),
                    -1 * ($event->created_at?->timestamp ?? 0),
                    (string) $event->id,
                ])
                ->values(),
        };

        $page = (int) ($validated['page'] ?? 1);
        $paginator = new LengthAwarePaginator(
            $events->forPage($page, $perPage)->values(),
            $events->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

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
        return new EventResource($event->load(['community', 'place', 'ticketTypes']));
    }

    private function statusSortRank(Event $event, mixed $now): int
    {
        if ($event->starts_at?->gt($now)) {
            return 0;
        }

        if ($event->ends_at?->lt($now)) {
            return 2;
        }

        return 1;
    }

    public function store(UpsertEventRequest $request): JsonResponse
    {
        $event = DB::transaction(function () use ($request): Event {
            $event = Event::query()->create([
                ...$request->eventAttributes(),
                ...$this->coverImageAttributes($request),
                'community_id' => $this->defaultCommunity()->id,
                'public_id' => $this->uniquePublicId('evt'),
                'status_label' => null,
            ]);

            $this->syncTicketTypes($event, $request->ticketPayload());

            return $event;
        });

        return (new EventResource($event->load(['community', 'place', 'ticketTypes'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpsertEventRequest $request, Event $event): EventResource
    {
        $event = DB::transaction(function () use ($request, $event): Event {
            $event->update([
                ...$request->eventAttributes(),
                ...$this->coverImageAttributes($request, $event),
            ]);
            $this->syncTicketTypes($event, $request->ticketPayload());

            return $event;
        });

        return new EventResource($event->load(['community', 'place', 'ticketTypes']));
    }

    public function destroy(Event $event): JsonResponse
    {
        if ($event->bookings()->exists()) {
            throw new ConflictHttpException('Events with booking history cannot be deleted.');
        }

        $event->ticketTypes()->delete();
        $event->delete();

        return response()->json(null, 204);
    }

    /**
     * @param  array<int, array{id: string|null, name: string, description: string|null, price: int, stock: int}>  $ticketPayload
     */
    private function syncTicketTypes(Event $event, array $ticketPayload): void
    {
        /** @var Collection<int, TicketType> $existingTickets */
        $existingTickets = $event->ticketTypes()->get()->keyBy('public_id');
        $seenIds = [];

        foreach ($ticketPayload as $ticketData) {
            $publicId = $ticketData['id'] ?: Str::slug($ticketData['name']);
            $publicId = $publicId !== '' ? $publicId : Str::lower(Str::random(8));
            $ticket = $existingTickets->get($publicId)
                ?? $event->ticketTypes()->where('name', $ticketData['name'])->first();

            if ($ticket !== null && $ticketData['stock'] < $ticket->sold) {
                throw new ConflictHttpException("Ticket capacity for {$ticket->name} cannot be lower than tickets already sold.");
            }

            if ($ticket === null) {
                $ticket = $event->ticketTypes()->create([
                    'public_id' => $publicId,
                    'name' => $ticketData['name'],
                    'description' => $ticketData['description'],
                    'price' => $ticketData['price'],
                    'currency' => 'IDR',
                    'quantity' => $ticketData['stock'],
                    'sold' => 0,
                    'capacity_label' => $ticketData['stock'] > 0 ? "{$ticketData['stock']} left" : 'Sold out',
                ]);
            } else {
                $ticket->update([
                    'public_id' => $publicId,
                    'name' => $ticketData['name'],
                    'description' => $ticketData['description'],
                    'price' => $ticketData['price'],
                    'quantity' => $ticketData['stock'],
                    'capacity_label' => max(0, $ticketData['stock'] - $ticket->sold).' left',
                ]);
            }

            $seenIds[] = $ticket->public_id;
        }

        $event->ticketTypes()
            ->whereNotIn('public_id', $seenIds)
            ->get()
            ->each(function (TicketType $ticketType): void {
                if ($ticketType->sold > 0) {
                    throw new ConflictHttpException("Ticket tier {$ticketType->name} cannot be removed because tickets have been sold.");
                }

                $ticketType->delete();
            });
    }

    /**
     * @return array<string, string>
     */
    private function coverImageAttributes(UpsertEventRequest $request, ?Event $event = null): array
    {
        if (! $request->hasFile('image')) {
            return [];
        }

        $path = $request->file('image')->store('events', 'public');
        $previousPath = $event?->cover_image;

        if ($previousPath && ! str_starts_with($previousPath, '/')) {
            Storage::disk('public')->delete($previousPath);
        }

        return [
            'cover_image' => $path,
            'image_alt' => $request->string('title')->toString(),
        ];
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

    private function uniquePublicId(string $prefix): string
    {
        do {
            $id = $prefix.'_'.Str::lower(Str::random(8));
        } while (Event::query()->where('public_id', $id)->exists());

        return $id;
    }
}
