<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\SavedEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavedEventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $savedEvents = $request->user()
            ->savedEvents()
            ->with(['event.ticketTypes'])
            ->latest()
            ->get();

        return response()->json([
            'data' => $savedEvents->map(fn (SavedEvent $savedEvent): array => $this->card($savedEvent->event))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'eventSlug' => ['required', 'string'],
        ]);

        $event = Event::query()->where('slug', $validated['eventSlug'])->first();

        abort_if($event === null, 404, 'Event not found.');

        $savedEvent = SavedEvent::query()->firstOrCreate([
            'user_id' => $request->user()->id,
            'event_id' => $event->id,
        ]);

        return response()->json([
            'data' => $this->card($event->loadMissing('ticketTypes')),
        ], $savedEvent->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, string $eventSlug): JsonResponse
    {
        $event = Event::query()->where('slug', $eventSlug)->first();

        abort_if($event === null, 404, 'Event not found.');

        SavedEvent::query()
            ->whereBelongsTo($request->user())
            ->whereBelongsTo($event)
            ->delete();

        return response()->json(null, 204);
    }

    private function card(Event $event): array
    {
        return [
            'id' => 'SAVED-'.str($event->slug)->upper()->replace('-', '_')->toString(),
            'status' => 'saved',
            'badge' => 'Saved',
            'title' => $event->title,
            'date' => $event->date_label ?? $event->starts_at?->timezone('Asia/Jakarta')->format('D, j M Y - H:i'),
            'location' => $event->location ?? $event->place?->name ?? 'Puncak region',
            'reference' => 'Saved event',
            'ticketLabel' => $event->spots_label ?? 'Book when ready',
            'primaryAction' => 'Book ticket',
            'primaryHref' => $event->booking_href ?? "/events/{$event->slug}/booking",
            'secondaryAction' => 'View details',
            'secondaryHref' => $event->detail_href ?? "/events/{$event->slug}",
        ];
    }
}
