<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $rateLimitKey = $this->loginRateLimitKey($request);

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            return response()->json([
                'message' => 'Too many login attempts.',
            ], 429);
        }

        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            RateLimiter::hit($rateLimitKey);

            return response()->json([
                'message' => 'Unable to sign in with those credentials.',
            ], 401);
        }

        RateLimiter::clear($rateLimitKey);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        /** @var User $user */
        $user = $request->user();
        $token = $user->createToken('frontend')->plainTextToken;

        return response()->json([
            'user' => [
                'id' => (string) $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'token' => $token,
            'tokenType' => 'Bearer',
            'message' => 'Signed in successfully.',
        ]);
    }

    public function user(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(null, 204);
    }

    public function redirectToGoogle(Request $request): RedirectResponse
    {
        $request->session()->put('auth.return_to', $this->safeReturnPath(
            $request->query('return_to', '/account')
        ));

        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        $googleUser = Socialite::driver('google')->user();

        $user = User::query()->updateOrCreate(
            ['email' => $googleUser->getEmail()],
            [
                'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: 'Puncak Traveller',
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
            ]
        );

        Auth::login($user, true);
        $request->session()->regenerate();

        $token = $user->createToken('frontend')->plainTextToken;
        $returnTo = $request->session()->pull('auth.return_to', '/account');
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        return redirect()->away($frontendUrl.$returnTo.'?'.http_build_query([
            'auth_token' => $token,
            'signed_in' => '1',
        ]));
    }

    private function loginRateLimitKey(LoginRequest $request): string
    {
        return Str::lower($request->string('email')->toString()).'|'.$request->ip();
    }

    private function safeReturnPath(mixed $returnTo): string
    {
        $path = is_string($returnTo) ? $returnTo : '/account';

        if (! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return '/account';
        }

        return $path;
    }
}
