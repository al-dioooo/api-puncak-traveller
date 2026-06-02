<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreContactMessageRequest;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;

class ContactMessageController extends Controller
{
    public function __invoke(StoreContactMessageRequest $request): JsonResponse
    {
        $message = ContactMessage::query()->create($request->validated());

        return response()->json([
            'data' => [
                'id' => $message->id,
                'status' => 'received',
            ],
        ], 201);
    }
}
