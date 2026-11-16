<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SiteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sites = $request->user()->sites()
            ->withCount(['events as lead_count' => fn ($query) => $query->where('type', 'lead')])
            ->latest()
            ->get(['id', 'name', 'domain', 'created_at']);

        return response()->json(['data' => $sites]);
    }

    public function store(Request $request): JsonResponse
    {
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'domain' => ['required', 'url', 'max:255'],
        ]);

        $domain = strtolower((string) parse_url($attributes['domain'], PHP_URL_HOST));
        if (! $domain) {
            return response()->json(['message' => 'Enter a valid website URL.'], 422);
        }

        $token = Str::random(48);
        $site = $request->user()->sites()->create([
            'name' => $attributes['name'],
            'domain' => $domain,
            'api_token_hash' => hash('sha256', $token),
        ]);

        return response()->json([
            'data' => ['site' => $site->only('id', 'name', 'domain'), 'token' => $token],
        ], 201);
    }

    public function rotateToken(Request $request, Site $site): JsonResponse
    {
        abort_unless($site->user_id === $request->user()->id, 404);

        $token = Str::random(48);
        $site->update(['api_token_hash' => hash('sha256', $token)]);

        return response()->json(['data' => ['token' => $token]]);
    }

    public function destroy(Request $request, Site $site): JsonResponse
    {
        abort_unless($site->user_id === $request->user()->id, 404);
        $site->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }
}
