<?php

namespace App\Http\Controllers;

use App\Http\Resources\LeadResource;
use App\Models\Lead;
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

    public function show(Request $request, Lead $lead): LeadResource
    {
        $this->authorize('view', $lead);

        return new LeadResource($lead);
    }
}
