<?php

namespace App\Http\Requests\Api\V1;

use App\Models\TicketType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBookingRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.ticket_type_id' => ['required', 'integer', 'distinct', Rule::exists((new TicketType)->getTable(), 'id')],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10'],
        ];
    }

    public function idempotencyKey(): string
    {
        return (string) $this->headers->get('Idempotency-Key', '');
    }

    /**
     * @return array<int, array{ticket_type_id: int, quantity: int}>
     */
    public function bookingItems(): array
    {
        return $this->validated('items');
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->idempotencyKey() === '') {
                    $validator->errors()->add('Idempotency-Key', 'The Idempotency-Key header is required.');
                }
            },
        ];
    }
}
