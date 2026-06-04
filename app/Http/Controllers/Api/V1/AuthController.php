<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc,dns', 'max:160', 'unique:users,email'],
            'password' => ['required', 'string', PasswordRule::defaults()],
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => User::ROLE_MEMBER,
            'status' => User::STATUS_ACTIVE,
        ]);

        $token = $user->createToken('frontend')->plainTextToken;

        return response()->json([
            'user' => $this->sessionUser($user),
            'token' => $token,
            'tokenType' => 'Bearer',
            'message' => 'Account created successfully.',
        ], 201);
    }

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
            'user' => $this->sessionUser($user),
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
        $returnTo = $request->query('return_to', '/account');
        $isMobileFlow = is_string($returnTo) && $this->isAllowedMobileCallback($returnTo);

        $state = Crypt::encryptString(json_encode([
            'return_to' => $isMobileFlow ? $returnTo : $this->safeReturnPath($returnTo),
        ], JSON_THROW_ON_ERROR));

        $google = Socialite::driver('google')
            ->stateless()
            ->with(['state' => $state]);

        if ($isMobileFlow && config('services.google.mobile_redirect')) {
            $google->redirectUrl((string) config('services.google.mobile_redirect'));
        }

        return $google->redirect();
    }

    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        $google = Socialite::driver('google')->stateless();

        if ($this->isMobileGoogleCallback($request) && config('services.google.mobile_redirect')) {
            $google->redirectUrl((string) config('services.google.mobile_redirect'));
        }

        $googleUser = $google->user();

        $user = User::query()->updateOrCreate(
            ['email' => $googleUser->getEmail()],
            [
                'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: 'Puncak Traveller',
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
            ]
        );

        $token = $user->createToken('frontend')->plainTextToken;
        $exchangeCode = Str::random(64);
        $callbackUrl = $this->callbackUrlFromOAuthState($request->query('state'));

        Cache::put($this->oauthExchangeCacheKey($exchangeCode), [
            'token' => $token,
            'user_id' => $user->id,
        ], now()->addMinutes(2));

        return redirect()->away($callbackUrl.(str_contains($callbackUrl, '?') ? '&' : '?').http_build_query([
            'code' => $exchangeCode,
        ]));
    }

    public function exchangeGoogleCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:64'],
        ]);

        $payload = Cache::pull($this->oauthExchangeCacheKey($validated['code']));

        if (! is_array($payload) || empty($payload['token']) || empty($payload['user_id'])) {
            throw ValidationException::withMessages([
                'code' => ['The Google sign-in session has expired. Please try again.'],
            ]);
        }

        $user = User::query()->findOrFail($payload['user_id']);

        return response()->json([
            'user' => $this->sessionUser($user),
            'token' => $payload['token'],
            'tokenType' => 'Bearer',
            'message' => 'Signed in successfully.',
        ]);
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

    private function callbackUrlFromOAuthState(mixed $state): string
    {
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        if (! is_string($state) || $state === '') {
            return $frontendUrl.'/auth/google/callback';
        }

        try {
            $decoded = json_decode(Crypt::decryptString($state), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            return $frontendUrl.'/auth/google/callback';
        }

        $returnTo = $decoded['return_to'] ?? '/account';

        if (! is_string($returnTo) || $returnTo === '') {
            return $frontendUrl.'/auth/google/callback';
        }

        if ($this->isAllowedMobileCallback($returnTo)) {
            return $returnTo;
        }

        return $frontendUrl.'/auth/google/callback?'.http_build_query([
            'return_to' => $this->safeReturnPath($returnTo),
        ]);
    }

    private function isAllowedMobileCallback(string $returnTo): bool
    {
        $parts = parse_url($returnTo);

        if (! is_array($parts)) {
            return false;
        }

        return in_array($parts['scheme'] ?? '', ['mobilepuncaktraveller', 'exp'], true)
            && str_contains($returnTo, '/auth/google/callback');
    }

    private function isMobileGoogleCallback(Request $request): bool
    {
        return $request->routeIs('api.v1.auth.mobile.google.callback')
            || $request->is('api/v1/auth/mobile/google/callback');
    }

    private function oauthExchangeCacheKey(string $code): string
    {
        return "oauth:google:exchange:{$code}";
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionUser(User $user): array
    {
        return [
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'status' => $user->status,
            'avatarUrl' => $user->avatar,
            'location' => $user->location,
            'crew' => $user->crew,
        ];
    }
}
