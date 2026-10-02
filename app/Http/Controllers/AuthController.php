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
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    public function __construct(private readonly JwtTokenService $tokens) {}

    #[OA\Post(
        path: '/api/auth/register',
        summary: 'Register a new user and receive an access/refresh token pair',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string', minLength: 8),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/TokenPair')),
            new OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
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

    #[OA\Post(
        path: '/api/auth/login',
        summary: 'Exchange credentials for an access/refresh token pair',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/TokenPair')),
            new OA\Response(response: 401, description: 'Invalid credentials', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email')->toString())->first();

        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        return response()->json($this->tokenPayload($user));
    }

    #[OA\Post(
        path: '/api/auth/refresh',
        summary: 'Rotate a refresh token: revoke it and issue a fresh pair',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['refresh'],
                properties: [new OA\Property(property: 'refresh', type: 'string')],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/TokenPair')),
            new OA\Response(response: 401, description: 'Refresh token invalid, expired, or already used', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
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

    #[OA\Post(
        path: '/api/auth/logout',
        summary: 'Revoke every active refresh token for the current user',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Missing or invalid bearer', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function logout(Request $request): JsonResponse
    {
        $this->tokens->revokeAllForUser($request->user());

        return response()->json(['message' => 'Logged out.']);
    }

    #[OA\Get(
        path: '/api/auth/me',
        summary: 'Return the authenticated user',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'user', ref: '#/components/schemas/User')]),
            ),
            new OA\Response(response: 401, description: 'Missing or invalid bearer', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
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
