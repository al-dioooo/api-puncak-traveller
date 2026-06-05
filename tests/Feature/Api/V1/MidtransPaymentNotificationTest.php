<?php

namespace Tests\Feature\Api\V1;

use App\Models\Booking;
use App\Models\Community;
use App\Models\Event;
use App\Models\Place;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MidtransPaymentNotificationTest extends TestCase
{
    public function test_midtrans_settlement_notification_marks_booking_paid(): void
    {
        [$booking, $ticketType] = $this->createPendingMidtransBooking();

        $this->postJson(route('api.v1.payments.midtrans.notification'), $this->signedPayload($booking, [
            'transaction_status' => 'settlement',
            'status_code' => '200',
        ]))
            ->assertOk()
            ->assertJsonPath('data.status', Booking::STATUS_CONFIRMED)
            ->assertJsonPath('data.paymentStatus', Booking::PAYMENT_PAID);

        $booking->refresh();
        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->status);
        $this->assertSame(Booking::PAYMENT_PAID, $booking->payment_status);
        $this->assertNotNull($booking->paid_at);
        $this->assertNull($booking->stock_released_at);
        $this->assertSame(1, $ticketType->refresh()->sold);
    }

    public function test_midtrans_expired_notification_marks_booking_failed_and_releases_stock_once(): void
    {
        [$booking, $ticketType] = $this->createPendingMidtransBooking();
        $payload = $this->signedPayload($booking, [
            'transaction_status' => 'expire',
            'status_code' => '202',
        ]);

        $this->postJson(route('api.v1.payments.midtrans.notification'), $payload)
            ->assertOk()
            ->assertJsonPath('data.status', Booking::STATUS_CANCELLED)
            ->assertJsonPath('data.paymentStatus', Booking::PAYMENT_FAILED);

        $this->assertSame(0, $ticketType->refresh()->sold);
        $this->assertNotNull($booking->refresh()->stock_released_at);

        $this->postJson(route('api.v1.payments.midtrans.notification'), $payload)->assertOk();

        $this->assertSame(0, $ticketType->refresh()->sold);
    }

    public function test_midtrans_notification_rejects_invalid_signature(): void
    {
        [$booking] = $this->createPendingMidtransBooking();
        $payload = $this->signedPayload($booking, [
            'transaction_status' => 'settlement',
            'status_code' => '200',
        ]);
        $payload['signature_key'] = 'invalid-signature';

        $this->postJson(route('api.v1.payments.midtrans.notification'), $payload)
            ->assertForbidden();

        $this->assertSame(Booking::PAYMENT_PENDING, $booking->refresh()->payment_status);
    }

    public function test_booking_owner_can_sync_paid_midtrans_status(): void
    {
        [$booking] = $this->createPendingMidtransBooking();
        Sanctum::actingAs($booking->user);

        config()->set('services.midtrans.status_api_base_url', 'https://api.sandbox.midtrans.com/v2');
        Http::fake([
            'https://api.sandbox.midtrans.com/v2/'.$booking->midtrans_order_id.'/status' => Http::response($this->signedPayload($booking, [
                'transaction_status' => 'settlement',
                'status_code' => '200',
            ])),
        ]);

        $this->postJson(route('api.v1.bookings.payment-status.sync', $booking))
            ->assertOk()
            ->assertJsonPath('data.status', Booking::STATUS_CONFIRMED)
            ->assertJsonPath('data.paymentStatus', Booking::PAYMENT_PAID);

        $this->assertSame(Booking::PAYMENT_PAID, $booking->refresh()->payment_status);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.sandbox.midtrans.com/v2/'.$booking->midtrans_order_id.'/status');
    }

    public function test_booking_payment_sync_is_limited_to_booking_owner(): void
    {
        [$booking] = $this->createPendingMidtransBooking();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('api.v1.bookings.payment-status.sync', $booking))
            ->assertForbidden()
            ->assertJsonPath('message', 'You are not allowed to refresh this booking payment status.');

        $this->assertSame(Booking::PAYMENT_PENDING, $booking->refresh()->payment_status);
    }

    /**
     * @return array{0: Booking, 1: TicketType}
     */
    private function createPendingMidtransBooking(): array
    {
        config()->set('services.midtrans.server_key', 'SB-Mid-server-test');
        $reference = 'PTR-26-'.Str::upper(Str::random(6));

        $user = User::factory()->create(['name' => 'Alex Puncak', 'email' => 'alex@example.com']);
        $community = Community::factory()->create();
        $place = Place::factory()->for($community)->create();
        $event = Event::factory()->for($community)->for($place)->upcoming()->create();
        $ticketType = TicketType::factory()->for($event)->create([
            'public_id' => '21k',
            'price' => 185000,
            'quantity' => 42,
            'sold' => 1,
        ]);
        $booking = Booking::factory()->for($user)->for($event)->create([
            'reference' => $reference,
            'status' => Booking::STATUS_PENDING,
            'payment_status' => Booking::PAYMENT_PENDING,
            'payment_provider' => 'midtrans',
            'midtrans_order_id' => $reference,
            'subtotal' => 185000,
            'booking_fee' => 5000,
            'total' => 190000,
            'currency' => 'IDR',
        ]);
        $booking->items()->create([
            'ticket_type_id' => $ticketType->id,
            'quantity' => 1,
            'unit_price' => 185000,
        ]);

        return [$booking, $ticketType];
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function signedPayload(Booking $booking, array $overrides = []): array
    {
        $payload = [
            'order_id' => $booking->midtrans_order_id,
            'transaction_id' => 'midtrans-transaction-id',
            'transaction_status' => 'settlement',
            'status_code' => '200',
            'gross_amount' => '190000.00',
            'payment_type' => 'bank_transfer',
            'fraud_status' => 'accept',
            ...$overrides,
        ];

        $payload['signature_key'] = hash('sha512', implode('', [
            $payload['order_id'],
            $payload['status_code'],
            $payload['gross_amount'],
            'SB-Mid-server-test',
        ]));

        return $payload;
    }
}
