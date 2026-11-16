<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function show(): View
    {
        return view('dashboard', ['user' => request()->user()]);
    }

    public function analytics(Request $request): JsonResponse
    {
        $days = max(7, min(90, (int) $request->integer('days', 30)));
        $since = now()->subDays($days - 1)->startOfDay();

        $totals = DB::selectOne(
            <<<'SQL'
                SELECT
                    COUNT(*) FILTER (WHERE events.type = 'lead') AS leads,
                    COUNT(*) FILTER (WHERE events.type = 'page_view') AS page_views
                FROM events
                WHERE events.site_id IN (SELECT id FROM sites WHERE user_id = ?)
                  AND events.occurred_at >= ?
            SQL,
            [$request->user()->id, $since],
        );

        $daily = DB::select(
            <<<'SQL'
                SELECT
                    DATE_TRUNC('day', occurred_at)::date AS day,
                    COUNT(*) FILTER (WHERE type = 'page_view') AS page_views,
                    COUNT(*) FILTER (WHERE type = 'lead') AS leads
                FROM events
                WHERE site_id IN (SELECT id FROM sites WHERE user_id = ?)
                  AND occurred_at >= ?
                GROUP BY day
                ORDER BY day
            SQL,
            [$request->user()->id, $since],
        );

        $topUrls = DB::select(
            <<<'SQL'
                SELECT payload->>'page_url' AS url, COUNT(*) AS page_views
                FROM events
                WHERE site_id IN (SELECT id FROM sites WHERE user_id = ?)
                  AND type = 'page_view'
                  AND occurred_at >= ?
                GROUP BY url
                ORDER BY page_views DESC
                LIMIT 5
            SQL,
            [$request->user()->id, $since],
        );

        $pageViews = (int) $totals->page_views;
        $activeSites = $request->user()->sites()
            ->whereHas('events', fn ($query) => $query->where('occurred_at', '>=', now()->subDays(7)))
            ->count();

        return response()->json([
            'data' => [
                'leads' => (int) $totals->leads,
                'page_views' => $pageViews,
                'conversion_rate' => $pageViews > 0 ? round(((int) $totals->leads / $pageViews) * 100, 2) : 0,
                'active_sites' => $activeSites,
                'daily' => $daily,
                'top_urls' => $topUrls,
                'days' => $days,
            ],
        ]);
    }
}
