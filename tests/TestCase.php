<?php

namespace Tests;

use App\Models\User;
use App\Services\JwtTokenService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function actingAsUser(User $user): static
    {
        $token = app(JwtTokenService::class)->issueAccessToken($user);

        return $this->withHeader('Authorization', 'Bearer '.$token['token']);
    }
}
