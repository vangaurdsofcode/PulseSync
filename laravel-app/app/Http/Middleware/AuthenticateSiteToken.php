<?php

namespace App\Http\Middleware;

use App\Models\Site;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateSiteToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainTextToken = $request->bearerToken();

        if (! $plainTextToken) {
            return response()->json(['message' => 'A valid site token is required.'], 401);
        }

        $site = Site::query()
            ->where('api_token_hash', hash('sha256', $plainTextToken))
            ->first();

        if (! $site) {
            return response()->json(['message' => 'A valid site token is required.'], 401);
        }

        $request->attributes->set('authenticatedSite', $site);

        return $next($request);
    }
}
