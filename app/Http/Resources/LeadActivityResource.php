<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'LeadActivity',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'lead_id', type: 'integer'),
        new OA\Property(property: 'actor_id', type: 'integer', nullable: true),
        new OA\Property(property: 'actor', ref: '#/components/schemas/User', nullable: true),
        new OA\Property(property: 'action', type: 'string', enum: ['created', 'updated', 'status_changed', 'deleted']),
        new OA\Property(
            property: 'changes',
            properties: [
                new OA\Property(property: 'before', type: 'object'),
                new OA\Property(property: 'after', type: 'object'),
            ],
            type: 'object',
            nullable: true,
        ),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
class LeadActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lead_id' => $this->lead_id,
            'actor_id' => $this->actor_id,
            'actor' => new UserResource($this->whenLoaded('actor')),
            'action' => $this->action?->value,
            'changes' => $this->changes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
