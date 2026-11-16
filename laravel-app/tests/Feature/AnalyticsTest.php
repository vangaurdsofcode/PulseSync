<?php

namespace Tests\Feature;

use App\Jobs\ProcessIncomingEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_postgres_jsonb_analytics_aggregate_daily_views_leads_and_top_pages(): void
    {
        $user = User::factory()->create();
        $site = $user->sites()->create([
            'name' => 'Analytics site',
            'domain' => 'analytics.example',
            'api_token_hash' => hash('sha256', 'analytics-token'),
        ]);

        (new ProcessIncomingEvent($site->id, $this->event('page_view', 'https://analytics.example/')))->handle();
        (new ProcessIncomingEvent($site->id, $this->event('page_view', 'https://analytics.example/')))->handle();
        (new ProcessIncomingEvent($site->id, $this->event('lead', 'https://analytics.example/contact')))->handle();

        $this->actingAs($user)
            ->getJson('/dashboard/analytics?days=7')
            ->assertOk()
            ->assertJsonPath('data.page_views', 2)
            ->assertJsonPath('data.leads', 1)
            ->assertJsonPath('data.conversion_rate', 50)
            ->assertJsonPath('data.active_sites', 1)
            ->assertJsonPath('data.top_urls.0.url', 'https://analytics.example/');
    }

    private function event(string $type, string $url): array
    {
        return [
            'external_id' => (string) Str::uuid(),
            'type' => $type,
            'occurred_at' => now()->toIso8601String(),
            'payload' => ['page_url' => $url, 'fields' => ['email' => 'visitor@example.test']],
            'metadata' => ['source' => 'test'],
        ];
    }
}
