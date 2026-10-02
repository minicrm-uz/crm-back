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

class LeadController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('auth.jwt')];
    }

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

    public function store(StoreLeadRequest $request): JsonResponse
    {
        $lead = Lead::create([
            ...$request->validated(),
            'owner_id' => $request->user()->id,
        ])->refresh();

        return (new LeadResource($lead))->response()->setStatusCode(201);
    }

    public function show(Request $request, Lead $lead): LeadResource
    {
        $this->authorize('view', $lead);

        return new LeadResource($lead);
    }

    public function update(UpdateLeadRequest $request, Lead $lead): LeadResource
    {
        $this->authorize('update', $lead);

        $lead->update($request->validated());

        return new LeadResource($lead);
    }

    public function updateStatus(UpdateLeadStatusRequest $request, Lead $lead): LeadResource
    {
        $this->authorize('update', $lead);

        $lead->update(['status' => $request->validated('status')]);

        return new LeadResource($lead);
    }

    public function destroy(Request $request, Lead $lead): JsonResponse
    {
        $this->authorize('delete', $lead);

        $lead->delete();

        return response()->json([], 204);
    }

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
