<?php

namespace App\Http\Controllers;

use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LeadController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $attributes = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'site_id' => ['nullable', 'integer'],
        ]);

        $query = Event::query()
            ->where('type', 'lead')
            ->whereIn('site_id', $request->user()->sites()->select('id'))
            ->with('site:id,name,domain')
            ->latest('occurred_at');

        if (! empty($attributes['site_id'])) {
            $query->where('site_id', $attributes['site_id']);
        }

        if (! empty($attributes['search'])) {
            $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $attributes['search']).'%';
            $query->where(function ($builder) use ($term): void {
                $builder->whereRaw("(payload->'fields')::text ILIKE ?", [$term])
                    ->orWhereRaw("payload->>'page_url' ILIKE ?", [$term]);
            });
        }

        return EventResource::collection($query->paginate(20));
    }
}
