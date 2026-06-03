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
use Illuminate\Support\Facades\Password;
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

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc,dns'],
        ]);

        Password::sendResetLink($validated);

        return response()->json([
            'message' => 'If an account exists for that email, a password reset link has been sent.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email:rfc,dns'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json([
            'message' => 'Password reset successfully.',
        ]);
    }

    public function redirectToGoogle(Request $request): RedirectResponse
    {
        $state = Crypt::encryptString(json_encode([
            'return_to' => $this->safeReturnPath($request->query('return_to', '/account')),
        ], JSON_THROW_ON_ERROR));

        return Socialite::driver('google')
            ->stateless()
            ->with(['state' => $state])
            ->redirect();
    }

    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        $googleUser = Socialite::driver('google')->stateless()->user();

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
        $exchangeCode = Str::random(64);
        $returnTo = $this->returnPathFromOAuthState($request->query('state'));
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        Cache::put($this->oauthExchangeCacheKey($exchangeCode), [
            'token' => $token,
            'user_id' => $user->id,
        ], now()->addMinutes(2));

        return redirect()->away($frontendUrl.'/auth/google/callback?'.http_build_query([
            'code' => $exchangeCode,
            'return_to' => $returnTo,
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

    private function returnPathFromOAuthState(mixed $state): string
    {
        if (! is_string($state) || $state === '') {
            return '/account';
        }

        try {
            $decoded = json_decode(Crypt::decryptString($state), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            return '/account';
        }

        return $this->safeReturnPath($decoded['return_to'] ?? '/account');
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
