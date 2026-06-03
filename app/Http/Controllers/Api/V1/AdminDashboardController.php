<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Event;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $thirtyDaysAgo = now()->subDays(30);

        return response()->json([
            'data' => [
                'stats' => [
                    'eventsCount' => Event::query()->count(),
                    'upcomingEvents' => Event::query()->where('starts_at', '>', now())->count(),
                    'bookingsCount' => Booking::query()->where('created_at', '>=', $thirtyDaysAgo)->count(),
                    'ticketsSold' => (int) Booking::query()
                        ->with('items')
                        ->where('created_at', '>=', $thirtyDaysAgo)
                        ->get()
                        ->sum(fn (Booking $booking): int => $booking->items->sum('quantity')),
                    'revenue' => (int) Booking::query()
                        ->where('created_at', '>=', $thirtyDaysAgo)
                        ->whereIn('payment_status', [Booking::PAYMENT_PAID, Booking::PAYMENT_REFUNDED])
                        ->sum('total'),
                ],
                'chart' => $this->bookingChart(),
                'recentBookings' => $this->recentBookings(),
                'upcomingEvents' => $this->upcomingEvents(),
            ],
        ]);
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    private function bookingChart(): array
    {
        $labels = [];
        $values = [];
        $start = now()->startOfWeek()->subWeeks(7);

        for ($index = 0; $index < 8; $index++) {
            $weekStart = $start->copy()->addWeeks($index);
            $weekEnd = $weekStart->copy()->endOfWeek();
            $labels[] = 'W'.($index + 1);
            $values[] = Booking::query()
                ->whereBetween('created_at', [$weekStart, $weekEnd])
                ->count();
        }

        return compact('labels', 'values');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentBookings(): array
    {
        return Booking::query()
            ->with(['user', 'event'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn (Booking $booking): array => [
                'reference' => $booking->reference,
                'member' => [
                    'name' => $booking->user?->name ?? $booking->attendee_name ?? 'Guest',
                    'email' => $booking->user?->email ?? $booking->attendee_email,
                ],
                'event' => $booking->event?->title ?? 'Puncak Travellers event',
                'total' => (int) $booking->total,
                'status' => $booking->payment_status,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function upcomingEvents(): array
    {
        return Event::query()
            ->with('ticketTypes')
            ->where('starts_at', '>', now())
            ->orderBy('starts_at', 'asc')
            ->limit(5)
            ->get()
            ->map(function (Event $event): array {
                $sold = (int) $event->ticketTypes->sum('sold');
                $total = (int) $event->ticketTypes->sum('quantity');

                return [
                    'title' => $event->title,
                    'date' => $event->starts_at?->timezone('Asia/Jakarta')->format('j M Y') ?? $event->date_label,
                    'sold' => $sold,
                    'total' => $total,
                    'percentage' => $total > 0 ? (int) round(($sold / $total) * 100) : 0,
                    'live' => $event->starts_at?->lte(now()) && $event->ends_at?->gte(now()),
                ];
            })
            ->values()
            ->all();
    }
}
