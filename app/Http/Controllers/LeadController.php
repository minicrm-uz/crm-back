<?php

namespace App\Http\Controllers;

use App\Http\Requests\Leads\StoreLeadRequest;
use App\Http\Requests\Leads\UpdateLeadRequest;
use App\Http\Requests\Leads\UpdateLeadStatusRequest;
use App\Http\Resources\LeadActivityResource;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use OpenApi\Attributes as OA;

class LeadController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('auth.jwt')];
    }

    #[OA\Get(
        path: '/api/leads',
        summary: 'List leads owned by the authenticated user',
        tags: ['Leads'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', description: 'Substring search on name (trigram-indexed)', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['New', 'Contacted', 'Qualified', 'Won', 'Lost'])),
            new OA\Parameter(name: 'source', in: 'query', schema: new OA\Schema(type: 'string', enum: ['Website', 'Referral', 'Social', 'Cold Call', 'Event', 'Other'])),
            new OA\Parameter(name: 'sort', in: 'query', description: 'Field name, optionally prefixed with - for descending', schema: new OA\Schema(type: 'string', enum: ['created_at', '-created_at', 'updated_at', '-updated_at', 'name', '-name', 'status', '-status'])),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/LeadPage')),
            new OA\Response(response: 401, description: 'Unauthorized', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->integer('per_page', 20), 100);

        $leads = Lead::query()
            ->ownedBy($request->user()->id)
            ->search($request->string('q')->toString() ?: null)
            ->filterStatus($request->string('status')->toString() ?: null)
            ->filterSource($request->string('source')->toString() ?: null)
            ->sortBy($request->string('sort')->toString() ?: null)
            ->paginate($perPage)
            ->withQueryString();

        return LeadResource::collection($leads);
    }

    #[OA\Post(
        path: '/api/leads',
        summary: 'Create a lead owned by the authenticated user',
        tags: ['Leads'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'source'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255),
                    new OA\Property(property: 'phone', type: 'string', maxLength: 50, nullable: true),
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
                    new OA\Property(property: 'source', type: 'string', enum: ['Website', 'Referral', 'Social', 'Cold Call', 'Event', 'Other']),
                    new OA\Property(property: 'status', type: 'string', enum: ['New', 'Contacted', 'Qualified', 'Won', 'Lost'], nullable: true),
                    new OA\Property(property: 'note', type: 'string', nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Created',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Lead')]),
            ),
            new OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function store(StoreLeadRequest $request): JsonResponse
    {
        $lead = Lead::create([
            ...$request->validated(),
            'owner_id' => $request->user()->id,
        ])->refresh();

        return (new LeadResource($lead))->response()->setStatusCode(201);
    }

    #[OA\Get(
        path: '/api/leads/{lead}',
        summary: 'Show a lead by id (owner-only)',
        tags: ['Leads'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'lead', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Lead')])),
            new OA\Response(response: 403, description: 'Not the owner', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function show(Request $request, Lead $lead): LeadResource
    {
        $this->authorize('view', $lead);

        return new LeadResource($lead);
    }

    #[OA\Patch(
        path: '/api/leads/{lead}',
        summary: 'Update lead fields (status is handled by a dedicated endpoint)',
        tags: ['Leads'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'lead', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'name', type: 'string', maxLength: 255),
                new OA\Property(property: 'phone', type: 'string', maxLength: 50, nullable: true),
                new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
                new OA\Property(property: 'source', type: 'string', enum: ['Website', 'Referral', 'Social', 'Cold Call', 'Event', 'Other']),
                new OA\Property(property: 'note', type: 'string', nullable: true),
            ]),
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Lead')])),
            new OA\Response(response: 403, description: 'Not the owner', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function update(UpdateLeadRequest $request, Lead $lead): LeadResource
    {
        $this->authorize('update', $lead);

        $lead->update($request->validated());

        return new LeadResource($lead);
    }

    #[OA\Patch(
        path: '/api/leads/{lead}/status',
        summary: 'Transition the lead status (audited separately from field updates)',
        tags: ['Leads'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'lead', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [new OA\Property(property: 'status', type: 'string', enum: ['New', 'Contacted', 'Qualified', 'Won', 'Lost'])],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Lead')])),
            new OA\Response(response: 403, description: 'Not the owner', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function updateStatus(UpdateLeadStatusRequest $request, Lead $lead): LeadResource
    {
        $this->authorize('update', $lead);

        $lead->update(['status' => $request->validated('status')]);

        return new LeadResource($lead);
    }

    #[OA\Delete(
        path: '/api/leads/{lead}',
        summary: 'Soft-delete a lead (audit trail retained)',
        tags: ['Leads'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'lead', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Deleted'),
            new OA\Response(response: 403, description: 'Not the owner', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function destroy(Request $request, Lead $lead): JsonResponse
    {
        $this->authorize('delete', $lead);

        $lead->delete();

        return response()->json([], 204);
    }

    #[OA\Get(
        path: '/api/leads/{lead}/activities',
        summary: 'List the audit activity feed for a lead (owner-only)',
        tags: ['Leads'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'lead', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/LeadActivity')),
                ]),
            ),
            new OA\Response(response: 403, description: 'Not the owner', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function activities(Request $request, Lead $lead): AnonymousResourceCollection
    {
        $this->authorize('view', $lead);

        $perPage = min((int) $request->integer('per_page', 20), 100);

        $activities = $lead->activities()
            ->with('actor')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return LeadActivityResource::collection($activities);
    }
}
