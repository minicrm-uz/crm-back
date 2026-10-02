<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RefreshRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\JwtTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(private readonly JwtTokenService $tokens) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request) {
            return User::create([
                'name' => $request->string('name')->toString(),
                'email' => $request->string('email')->toString(),
                'password' => $request->string('password')->toString(),
            ]);
        });

        return response()->json($this->tokenPayload($user), 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email')->toString())->first();

        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        return response()->json($this->tokenPayload($user));
    }

    public function refresh(RefreshRequest $request): JsonResponse
    {
        $result = $this->tokens->rotateRefreshToken($request->string('refresh')->toString());

        if ($result === null) {
            return response()->json(['message' => 'Refresh token is invalid, expired, or already used.'], 401);
        }

        return response()->json([
            'user' => new UserResource($result['user']),
            'access' => $this->formatToken($result['access']),
            'refresh' => $this->formatToken($result['refresh']),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->tokens->revokeAllForUser($request->user());

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => new UserResource($request->user())]);
    }

    private function tokenPayload(User $user): array
    {
        $pair = $this->tokens->issueTokens($user);

        return [
            'user' => new UserResource($user),
            'access' => $this->formatToken($pair['access']),
            'refresh' => $this->formatToken($pair['refresh']),
        ];
    }

    private function formatToken(array $token): array
    {
        return [
            'token' => $token['token'],
            'expires_at' => $token['expires_at']->toIso8601String(),
            'expires_in' => $token['expires_in'],
        ];
    }
}
