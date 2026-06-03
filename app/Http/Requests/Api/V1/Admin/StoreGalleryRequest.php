<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\Event;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGalleryRequest extends FormRequest
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
        return [
            'image' => ['required', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            'title' => ['required', 'string', 'max:160'],
            'event_id' => ['sometimes', 'nullable', 'string', 'max:120'],
            'category' => ['required', 'string', Rule::in(array_keys(Event::ACTIVITY_LABELS))],
        ];
    }

    public function linkedEvent(): ?Event
    {
        $eventId = $this->string('event_id')->toString();

        if ($eventId === '') {
            return null;
        }

        $query = Event::query()
            ->where('public_id', $eventId)
            ->orWhere('slug', $eventId);

        if (ctype_digit($eventId)) {
            $query->orWhereKey($eventId);
        }

        return $query->first();
    }
}
