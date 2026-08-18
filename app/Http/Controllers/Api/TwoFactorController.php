<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ConfirmTwoFactorRequest;
use App\Http\Requests\Api\DisableTwoFactorRequest;
use App\Http\Requests\Api\VerifyTwoFactorRequest;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    public function __construct(private TwoFactorService $twoFactor) {}

    public function verify(VerifyTwoFactorRequest $request): JsonResponse
    {
        $cacheKey = $this->cacheKey($request->string('twoFactorToken')->toString());
        $userId = Cache::get($cacheKey);

        if ($userId === null) {
            throw ValidationException::withMessages([
                'code' => ['This verification session has expired. Please sign in again.'],
            ]);
        }

        $user = User::query()->find($userId);

        if ($user === null || ! $user->hasTwoFactorEnabled()) {
            Cache::forget($cacheKey);

            throw ValidationException::withMessages([
                'code' => ['Unable to verify authenticator code.'],
            ]);
        }

        if (! $this->twoFactor->verify($user, $request->string('code')->toString())) {
            throw ValidationException::withMessages([
                'code' => ['The authenticator code is invalid.'],
            ]);
        }

        Cache::forget($cacheKey);

        $token = $user->createToken('finance-tracker')->plainTextToken;

        return response()->json([
            'user' => $this->userPayload($user),
            'token' => $token,
        ]);
    }

    public function setup(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages([
                'code' => ['Disable the current authenticator before setting up a new one.'],
            ]);
        }

        $secret = $this->twoFactor->generateSecret();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
        ])->save();

        return response()->json([
            'secret' => $secret,
            'otpauthUrl' => $this->twoFactor->otpAuthUrl($user, $secret),
        ]);
    }

    public function confirm(ConfirmTwoFactorRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->two_factor_secret === null) {
            throw ValidationException::withMessages([
                'code' => ['Start authenticator setup before confirming.'],
            ]);
        }

        if (! $this->twoFactor->verify($user, $request->string('code')->toString())) {
            throw ValidationException::withMessages([
                'code' => ['The authenticator code is invalid.'],
            ]);
        }

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
        ])->save();

        return response()->json([
            'user' => $this->userPayload($user->fresh()),
            'message' => 'Authenticator enabled.',
        ]);
    }

    public function disable(DisableTwoFactorRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The password is incorrect.'],
            ]);
        }

        if (! $user->hasTwoFactorEnabled()) {
            return response()->json([
                'user' => $this->userPayload($user),
                'message' => 'Authenticator is not enabled.',
            ]);
        }

        if (! $this->twoFactor->verify($user, $request->string('code')->toString())) {
            throw ValidationException::withMessages([
                'code' => ['The authenticator code is invalid.'],
            ]);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return response()->json([
            'user' => $this->userPayload($user->fresh()),
            'message' => 'Authenticator disabled.',
        ]);
    }

    public static function issueChallengeToken(User $user): string
    {
        $token = Str::random(64);
        Cache::put(self::cacheKey($token), $user->id, now()->addMinutes(5));

        return $token;
    }

    private static function cacheKey(string $token): string
    {
        return 'two_factor_challenge:'.$token;
    }

    /** @return array<string, mixed> */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'twoFactorEnabled' => $user->hasTwoFactorEnabled(),
        ];
    }
}
