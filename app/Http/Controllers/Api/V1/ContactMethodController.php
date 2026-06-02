<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ContactMethod;
use Illuminate\Http\JsonResponse;

class ContactMethodController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => ContactMethod::query()
                ->orderBy('sort_order')
                ->get()
                ->map(fn (ContactMethod $method): array => [
                    'title' => $method->title,
                    'value' => $method->value,
                    'description' => $method->description,
                    'icon' => $method->icon,
                ])
                ->values(),
        ]);
    }
}
