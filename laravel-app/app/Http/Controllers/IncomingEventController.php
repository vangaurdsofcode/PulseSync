<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIncomingEventRequest;
use App\Jobs\ProcessIncomingEvent;
use App\Models\Site;
use Illuminate\Http\JsonResponse;

class IncomingEventController extends Controller
{
    public function store(StoreIncomingEventRequest $request): JsonResponse
    {
        /** @var Site $site */
        $site = $request->attributes->get('authenticatedSite');
        ProcessIncomingEvent::dispatch($site->id, $request->validated());

        return response()->json([
            'data' => [
                'accepted' => true,
                'external_id' => $request->validated('external_id'),
            ],
        ], 202);
    }
}
