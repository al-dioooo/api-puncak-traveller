<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\Event;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpsertEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $event = $this->route('event');

        return [
            'title' => ['required', 'string', 'max:160'],
            'slug' => [
                'required',
                'string',
                'max:180',
                'alpha_dash:ascii',
                Rule::unique('events', 'slug')->ignore($event?->id),
            ],
            'category' => ['required', 'string', 'max:80'],
            'activity' => ['required', 'string', Rule::in(array_keys(Event::ACTIVITY_LABELS))],
            'description' => ['required', 'string', 'max:10000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'location' => ['required', 'string', 'max:180'],
            'status' => ['required', Rule::in([
                Event::PUBLICATION_DRAFT,
                Event::PUBLICATION_PUBLISHED,
                Event::PUBLICATION_ARCHIVED,
            ])],
            'tickets' => ['sometimes', 'array'],
            'tickets.*.id' => ['sometimes', 'nullable', 'string', 'max:80'],
            'tickets.*.name' => ['required_with:tickets', 'string', 'max:120'],
            'tickets.*.description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'tickets.*.price' => ['required_with:tickets', 'integer', 'min:0'],
            'tickets.*.stock' => ['required_with:tickets', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function eventAttributes(): array
    {
        $validated = $this->validated();

        return [
            'title' => $validated['title'],
            'slug' => $validated['slug'],
            'description' => $validated['description'],
            'category' => $validated['category'],
            'activity' => $validated['activity'],
            'activity_type' => $validated['activity'],
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'],
            'publication_status' => $validated['status'],
            'location' => $validated['location'],
            'venue_name' => $validated['location'],
            'detail_href' => "/events/{$validated['slug']}",
            'booking_href' => "/events/{$validated['slug']}/booking",
        ];
    }

    /**
     * @return array<int, array{id: string|null, name: string, description: string|null, price: int, stock: int}>
     */
    public function ticketPayload(): array
    {
        return collect($this->validated('tickets', []))
            ->map(fn (array $ticket): array => [
                'id' => $ticket['id'] ?? null,
                'name' => $ticket['name'],
                'description' => $ticket['description'] ?? null,
                'price' => (int) $ticket['price'],
                'stock' => (int) $ticket['stock'],
            ])
            ->values()
            ->all();
    }

    protected function prepareForValidation(): void
    {
        $status = $this->input('status', Event::PUBLICATION_DRAFT);

        $this->merge([
            'status' => str((string) $status)->lower()->replace(' ', '-')->toString(),
            'tickets' => collect($this->input('tickets', []))
                ->map(function (array $ticket): array {
                    return [
                        ...$ticket,
                        'price' => $this->integerLike($ticket['price'] ?? 0),
                        'stock' => $this->integerLike($ticket['stock'] ?? 0),
                    ];
                })
                ->values()
                ->all(),
        ]);
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new ValidationException($validator, response()->json([
            'message' => 'The event payload is invalid.',
            'errors' => $validator->errors(),
        ], 422));
    }

    private function integerLike(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        return (int) preg_replace('/[^\d]/', '', (string) $value);
    }
}
