<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommunityResource;
use App\Http\Resources\EventResource;
use App\Http\Resources\GalleryResource;
use App\Models\Community;
use App\Models\Event;
use App\Models\Gallery;
use App\Models\Place;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LandingController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $upcomingEvents = Event::query()
            ->with(['community', 'place', 'ticketTypes'])
            ->forStatus('upcoming')
            ->orderBy('starts_at', 'asc')
            ->limit(3)
            ->get();

        $liveEvent = Event::query()
            ->with(['community', 'place', 'ticketTypes'])
            ->forStatus('ongoing')
            ->orderBy('starts_at', 'desc')
            ->first();

        $communities = Community::query()
            ->with('children')
            ->whereNotNull('parent_id')
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get();

        $gallery = Gallery::query()
            ->with([
                'community',
                'event' => fn ($query) => $query
                    ->with(['community', 'place', 'ticketTypes']),
            ])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'data' => [
                'hero_stats' => $this->heroStats(),
                'upcoming_events' => EventResource::collection($upcomingEvents)->resolve($request),
                'activities' => $this->activities(),
                'live_event' => $liveEvent ? (new EventResource($liveEvent))->resolve($request) : null,
                'communities' => CommunityResource::collection($communities)->resolve($request),
                'gallery' => GalleryResource::collection($gallery)->resolve($request),
            ],
        ]);
    }

    /**
     * @return array<int, array{label: string, value: int}>
     */
    private function heroStats(): array
    {
        return [
            [
                'label' => 'Active members',
                'value' => User::query()->where('role', User::ROLE_MEMBER)->count(),
            ],
            [
                'label' => 'Events hosted',
                'value' => Event::query()->count(),
            ],
            [
                'label' => 'Mountain regions',
                'value' => Place::query()->count(),
            ],
        ];
    }

    /**
     * @return array<int, array{activity_type: string, activity_label: string, upcoming_count: int}>
     */
    private function activities(): array
    {
        $activityCounts = Event::query()
            ->forStatus('upcoming')
            ->get(['activity_type'])
            ->countBy('activity_type');

        return collect(Event::ACTIVITY_LABELS)
            ->map(fn (string $label, string $type): array => [
                'activity_type' => $type,
                'activity_label' => $label,
                'upcoming_count' => (int) ($activityCounts[$type] ?? 0),
            ])
            ->values()
            ->all();
    }
}
