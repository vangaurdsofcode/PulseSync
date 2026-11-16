<?php

namespace Tests\Feature;

use App\Jobs\ProcessIncomingEvent;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EventIngestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_token_is_required_for_event_ingestion(): void
    {
        $this->postJson('/api/v1/events', [])->assertUnauthorized();

        $this->withToken('invalid')->postJson('/api/v1/events', [])->assertUnauthorized();
    }

    public function test_valid_event_is_queued_for_processing(): void
    {
        Queue::fake();
        $site = $this->makeSite();

        $this->withToken('secret-site-token')
            ->postJson('/api/v1/events', $this->eventPayload())
            ->assertAccepted()
            ->assertJsonPath('data.accepted', true);

        Queue::assertPushed(ProcessIncomingEvent::class, fn ($job) => $job->siteId === $site->id);
    }

    public function test_event_payload_is_validated_before_queueing(): void
    {
        Queue::fake();
        $this->makeSite();

        $this->withToken('secret-site-token')
            ->postJson('/api/v1/events', ['type' => 'other'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['external_id', 'occurred_at', 'payload']);

        Queue::assertNothingPushed();
    }

    private function makeSite(): Site
    {
        $user = User::factory()->create();

        return $user->sites()->create([
            'name' => 'Example',
            'domain' => 'example.test',
            'api_token_hash' => hash('sha256', 'secret-site-token'),
        ]);
    }

    private function eventPayload(): array
    {
        return [
            'external_id' => '684e1fc6-35ba-4f4f-86fd-61a7aed45bbc',
            'type' => 'lead',
            'occurred_at' => now()->toIso8601String(),
            'payload' => [
                'page_url' => 'https://example.test/contact',
                'browser' => 'Firefox',
                'fields' => ['email' => 'visitor@example.test'],
            ],
            'metadata' => ['campaign' => 'spring'],
        ];
    }
}
