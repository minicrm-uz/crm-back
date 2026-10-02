<?php

namespace App\Services;

use App\Models\RefreshToken;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

class JwtTokenService
{
    public function __construct(
        private readonly string $secret,
        private readonly string $algo,
        private readonly int $accessTtl,
        private readonly int $refreshTtl,
        private readonly string $issuer,
    ) {
        if ($this->secret === '' || $this->secret === null) {
            throw new RuntimeException('JWT_SECRET is not configured.');
        }
    }

    public function issueTokens(User $user): array
    {
        return [
            'access' => $this->issueAccessToken($user),
            'refresh' => $this->issueRefreshToken($user),
        ];
    }

    public function issueAccessToken(User $user): array
    {
        $now = Carbon::now();
        $expiresAt = $now->copy()->addSeconds($this->accessTtl);

        $payload = [
            'iss' => $this->issuer,
            'sub' => (string) $user->id,
            'iat' => $now->getTimestamp(),
            'exp' => $expiresAt->getTimestamp(),
            'jti' => (string) Str::uuid(),
        ];

        return [
            'token' => JWT::encode($payload, $this->secret, $this->algo),
            'expires_at' => $expiresAt,
            'expires_in' => $this->accessTtl,
        ];
    }

    public function issueRefreshToken(User $user): array
    {
        $raw = $this->randomToken();
        $expiresAt = Carbon::now()->addSeconds($this->refreshTtl);

        RefreshToken::create([
            'user_id' => $user->id,
            'token_hash' => $this->hashToken($raw),
            'expires_at' => $expiresAt,
        ]);

        return [
            'token' => $raw,
            'expires_at' => $expiresAt,
            'expires_in' => $this->refreshTtl,
        ];
    }

    /**
     * Verify a signed access token and return its payload.
     *
     * Throws any of firebase/php-jwt's exceptions on failure (expired,
     * signature mismatch, malformed, etc.). Callers should treat all such
     * failures as 401.
     */
    public function parseAccessToken(string $jwt): object
    {
        return JWT::decode($jwt, new Key($this->secret, $this->algo));
    }

    public function userFromAccessToken(string $jwt): ?User
    {
        $payload = $this->parseAccessToken($jwt);

        return User::find((int) $payload->sub);
    }

    /**
     * Rotate a refresh token: revoke the presented one and issue a fresh
     * access + refresh pair. Returns null if the presented token is invalid,
     * expired, revoked, or belongs to a different user.
     */
    public function rotateRefreshToken(string $raw): ?array
    {
        $record = RefreshToken::where('token_hash', $this->hashToken($raw))->first();

        if ($record === null || ! $record->isActive()) {
            return null;
        }

        $user = $record->user;

        if ($user === null) {
            return null;
        }

        $record->update([
            'revoked_at' => Carbon::now(),
            'last_used_at' => Carbon::now(),
        ]);

        return [
            'user' => $user,
            'access' => $this->issueAccessToken($user),
            'refresh' => $this->issueRefreshToken($user),
        ];
    }

    public function revokeRefreshToken(string $raw): bool
    {
        return (bool) RefreshToken::where('token_hash', $this->hashToken($raw))
            ->whereNull('revoked_at')
            ->update(['revoked_at' => Carbon::now()]);
    }

    public function revokeAllForUser(User $user): int
    {
        return RefreshToken::where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => Carbon::now()]);
    }

    private function randomToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    }

    private function hashToken(string $raw): string
    {
        return hash('sha256', $raw);
    }
}
