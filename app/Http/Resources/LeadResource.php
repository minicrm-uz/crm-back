<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Lead',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'owner_id', type: 'integer'),
        new OA\Property(property: 'owner', ref: '#/components/schemas/User', nullable: true),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'phone', type: 'string', nullable: true),
        new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
        new OA\Property(property: 'source', type: 'string', enum: ['Website', 'Referral', 'Social', 'Cold Call', 'Event', 'Other']),
        new OA\Property(property: 'status', type: 'string', enum: ['New', 'Contacted', 'Qualified', 'Won', 'Lost']),
        new OA\Property(property: 'note', type: 'string', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'deleted_at', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'LeadPage',
    properties: [
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Lead')),
        new OA\Property(property: 'links', type: 'object'),
        new OA\Property(
            property: 'meta',
            properties: [
                new OA\Property(property: 'current_page', type: 'integer'),
                new OA\Property(property: 'last_page', type: 'integer'),
                new OA\Property(property: 'per_page', type: 'integer'),
                new OA\Property(property: 'total', type: 'integer'),
            ],
            type: 'object',
        ),
    ],
    type: 'object',
)]
class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'owner_id' => $this->owner_id,
            'owner' => new UserResource($this->whenLoaded('owner')),
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'source' => $this->source?->value,
            'status' => $this->status?->value,
            'note' => $this->note,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
