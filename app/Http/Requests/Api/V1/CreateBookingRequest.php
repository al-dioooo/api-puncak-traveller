<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Event;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'eventSlug' => ['required', 'string'],
            'termsAccepted' => ['accepted'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ticketTierId' => ['required', 'string', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10'],
            'attendees' => ['sometimes', 'array'],
            'attendees.*.name' => ['required_with:attendees', 'string', 'max:120'],
            'attendees.*.email' => ['required_with:attendees', 'email', 'max:255'],
            'attendees.*.ticketTierId' => ['required_with:attendees', 'string'],
        ];
    }

    public function event(): ?Event
    {
        return Event::query()->where('slug', $this->string('eventSlug')->toString())->first();
    }

    /**
     * @return array<int, array{ticket_tier_id: string, quantity: int}>
     */
    public function bookingItems(): array
    {
        return collect($this->validated('items'))
            ->map(fn (array $item): array => [
                'ticket_tier_id' => (string) $item['ticketTierId'],
                'quantity' => (int) $item['quantity'],
            ])
            ->all();
    }

    public function idempotencyKey(): string
    {
        return (string) $this->headers->get('Idempotency-Key', '');
    }
}
