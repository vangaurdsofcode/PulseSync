<?php

namespace App\Jobs;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessIncomingEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(
        public readonly int $siteId,
        public readonly array $event,
    ) {}

    public function backoff(): array
    {
        return [10, 30, 120, 600];
    }

    public function handle(): void
    {
        Event::query()->updateOrCreate(
            ['site_id' => $this->siteId, 'external_id' => $this->event['external_id']],
            [
                'type' => $this->event['type'],
                'occurred_at' => $this->event['occurred_at'],
                'payload' => $this->event['payload'],
                'metadata' => $this->event['metadata'] ?? [],
            ],
        );
    }
}
