<?php

namespace App\Http\Middleware;

use App\Services\JwtTokenService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class JwtAuth
{
    public function __construct(private readonly JwtTokenService $tokens) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $token = $this->bearerToken($request);

        if ($token === null) {
            return $this->unauthorized('Authorization header missing or malformed.');
        }

        try {
            $user = $this->tokens->userFromAccessToken($token);
        } catch (Throwable $e) {
            return $this->unauthorized('Invalid or expired access token.');
        }

        if ($user === null) {
            return $this->unauthorized('Token subject no longer exists.');
        }

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }

    private function bearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if (! is_string($header)) {
            return null;
        }

        if (! str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($header, 7));

        return $token === '' ? null : $token;
    }

    private function unauthorized(string $message): JsonResponse
    {
        return response()->json(['message' => $message], 401);
    }
}
