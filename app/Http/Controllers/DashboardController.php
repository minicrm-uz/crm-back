<?php

namespace App\Http\Controllers;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use OpenApi\Attributes as OA;

class DashboardController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('auth.jwt')];
    }

    #[OA\Get(
        path: '/api/dashboard/stats',
        summary: 'Owner-scoped aggregates for the dashboard',
        tags: ['Dashboard'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'total', type: 'integer'),
                        new OA\Property(property: 'by_status', type: 'object', description: 'Count per lead_status enum case (zero-filled)'),
                        new OA\Property(property: 'by_source', type: 'object', description: 'Count per lead_source enum case (zero-filled)'),
                        new OA\Property(property: 'won_rate', type: 'number', format: 'float', description: 'Won / total * 100, rounded to 2 decimals'),
                        new OA\Property(property: 'this_week', type: 'integer'),
                        new OA\Property(property: 'this_month', type: 'integer'),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthorized', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function stats(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $base = fn () => Lead::query()->ownedBy($userId);

        $total = $base()->count();

        $byStatus = $base()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $bySource = $base()
            ->selectRaw('source, COUNT(*) as count')
            ->groupBy('source')
            ->pluck('count', 'source');

        return response()->json([
            'total' => $total,
            'by_status' => $this->fillBuckets(LeadStatus::cases(), $byStatus),
            'by_source' => $this->fillBuckets(LeadSource::cases(), $bySource),
            'won_rate' => $this->wonRate($total, (int) ($byStatus[LeadStatus::Won->value] ?? 0)),
            'this_week' => $base()->where('created_at', '>=', now()->startOfWeek())->count(),
            'this_month' => $base()->where('created_at', '>=', now()->startOfMonth())->count(),
        ]);
    }

    /**
     * Ensure every enum case is present in the response so the frontend
     * never has to handle missing keys. Zero-fills unused buckets.
     *
     * @param  array<int, \BackedEnum>  $cases
     */
    private function fillBuckets(array $cases, $counts): array
    {
        return collect($cases)
            ->mapWithKeys(fn ($case) => [$case->value => (int) ($counts[$case->value] ?? 0)])
            ->all();
    }

    private function wonRate(int $total, int $won): float
    {
        if ($total === 0) {
            return 0.0;
        }

        return round(($won / $total) * 100, 2);
    }
}
