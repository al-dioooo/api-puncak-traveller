<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class BookingReceiptMail extends Mailable
{
    public function __construct(public Booking $booking) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Puncak Travellers receipt {$this->booking->reference}",
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: self::renderHtml($this->booking),
        );
    }

    public static function renderHtml(Booking $booking): string
    {
        $booking->loadMissing(['user', 'event', 'items.ticketType']);

        $memberName = $booking->user?->name ?? $booking->attendee_name ?? 'Puncak Traveller';
        $memberEmail = $booking->user?->email ?? $booking->attendee_email ?? '';
        $event = $booking->event;
        $eventDate = $event?->full_date_label
            ?? $event?->starts_at?->timezone('Asia/Jakarta')->format('D, j M Y - H:i')
            ?? 'Event date to be confirmed';
        $eventLocation = $event?->location ?? $event?->place?->name ?? 'Puncak region';
        $ticketRows = $booking->items
            ->map(function ($item): string {
                $ticketName = e($item->ticketType?->name ?? 'Ticket');
                $quantity = (int) $item->quantity;
                $unitPrice = self::money((int) $item->unit_price);
                $amount = self::money((int) $item->quantity * (int) $item->unit_price);

                return "<tr><td>{$quantity}x</td><td>{$ticketName}</td><td>{$unitPrice}</td><td>{$amount}</td></tr>";
            })
            ->join('');

        if ($ticketRows === '') {
            $ticketRows = '<tr><td colspan="4">No ticket rows recorded.</td></tr>';
        }

        return '<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Puncak Travellers Receipt '.e($booking->reference).'</title>
  <style>
    body{font-family:Arial,sans-serif;color:#0f172a;margin:0;padding:32px;background:#f8fafc}
    .ticket{max-width:720px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:28px}
    .eyebrow{font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#64748b;font-weight:700}
    h1{font-size:26px;margin:8px 0 4px}
    h2{font-size:18px;margin:24px 0 8px}
    p{line-height:1.5}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{border-bottom:1px solid #e2e8f0;padding:10px;text-align:left;font-size:14px}
    th{background:#f8fafc;color:#475569}
    .total{font-size:20px;font-weight:800;text-align:right;margin-top:18px}
    .meta{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:18px}
    .box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:14px}
  </style>
</head>
<body>
  <main class="ticket">
    <div class="eyebrow">Booking receipt</div>
    <h1>'.e($booking->reference).'</h1>
    <p>This receipt confirms your Puncak Travellers booking and ticket order.</p>
    <section class="meta">
      <div class="box"><strong>Member</strong><br>'.e($memberName).'<br>'.e($memberEmail).'</div>
      <div class="box"><strong>Status</strong><br>'.e(str($booking->status)->headline()->toString()).'<br>'.e(str($booking->payment_status)->headline()->toString()).'</div>
      <div class="box"><strong>Event</strong><br>'.e($event?->title ?? 'Puncak Travellers event').'<br>'.e($eventDate).'</div>
      <div class="box"><strong>Location</strong><br>'.e($eventLocation).'</div>
    </section>
    <h2>Tickets</h2>
    <table>
      <thead><tr><th>Qty</th><th>Type</th><th>Price</th><th>Amount</th></tr></thead>
      <tbody>'.$ticketRows.'</tbody>
    </table>
    <p class="total">Total paid: '.self::money((int) $booking->total).'</p>
  </main>
</body>
</html>';
    }

    private static function money(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
