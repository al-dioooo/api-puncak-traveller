<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class MemberController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min($request->integer('per_page', 15), 50));

        return UserResource::collection(
            User::query()
                ->withCount('bookings')
                ->latest()
                ->paginate($perPage)
        );
    }

    public function store(Request $request): UserResource
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc,dns', 'max:160', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults()],
            'role' => ['sometimes', Rule::in([User::ROLE_MEMBER, User::ROLE_ADMIN])],
            'status' => ['sometimes', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE])],
            'location' => ['sometimes', 'nullable', 'string', 'max:120'],
            'crew' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);

        $member = User::query()->create([
            ...$validated,
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'] ?? User::ROLE_MEMBER,
            'status' => $validated['status'] ?? User::STATUS_ACTIVE,
        ]);

        return new UserResource($member);
    }

    public function show(User $member): UserResource
    {
        return new UserResource($member);
    }

    public function update(Request $request, User $member): UserResource
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'email' => ['sometimes', 'required', 'email:rfc,dns', 'max:160', Rule::unique('users', 'email')->ignore($member->id)],
            'password' => ['sometimes', 'nullable', 'string', Password::defaults()],
            'role' => ['sometimes', Rule::in([User::ROLE_MEMBER, User::ROLE_ADMIN])],
            'status' => ['sometimes', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE])],
            'location' => ['sometimes', 'nullable', 'string', 'max:120'],
            'crew' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);

        if (($validated['role'] ?? $member->role) !== User::ROLE_ADMIN) {
            $this->ensureAnotherAdminExists($member);
        }

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $member->update($validated);

        return new UserResource($member->refresh());
    }

    public function destroy(User $member): JsonResponse
    {
        $this->ensureAnotherAdminExists($member);
        $member->delete();

        return response()->json(null, 204);
    }

    private function ensureAnotherAdminExists(User $member): void
    {
        if ($member->role !== User::ROLE_ADMIN) {
            return;
        }

        $anotherAdminExists = User::query()
            ->whereKeyNot($member->id)
            ->where('role', User::ROLE_ADMIN)
            ->exists();

        if (! $anotherAdminExists) {
            throw new ConflictHttpException('At least one administrator must remain active.');
        }
    }
}
