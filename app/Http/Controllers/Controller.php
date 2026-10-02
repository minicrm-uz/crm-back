<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    description: 'JWT-authenticated REST API for lead management. Access tokens are short-lived HS256 JWTs; refresh tokens are opaque strings persisted (hashed) server-side so they can be rotated and revoked.',
    title: 'Mini CRM API',
)]
#[OA\Server(url: '/', description: 'This host')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    description: 'Pass the access token issued by /api/auth/login or /api/auth/refresh.',
    bearerFormat: 'JWT',
    scheme: 'bearer',
)]
#[OA\Schema(
    schema: 'Token',
    properties: [
        new OA\Property(property: 'token', type: 'string'),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'expires_in', type: 'integer', description: 'Seconds until expiry'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'TokenPair',
    properties: [
        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
        new OA\Property(property: 'access', ref: '#/components/schemas/Token'),
        new OA\Property(property: 'refresh', ref: '#/components/schemas/Token'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ValidationError',
    properties: [
        new OA\Property(property: 'message', type: 'string'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(type: 'array', items: new OA\Items(type: 'string')),
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'Error',
    properties: [new OA\Property(property: 'message', type: 'string')],
    type: 'object',
)]
abstract class Controller
{
    use AuthorizesRequests;
}
