<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SiteManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_token_is_returned_once_stored_as_a_hash_and_can_be_rotated(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/dashboard/sites', [
            'name' => 'My site',
            'domain' => 'https://example.test/contact',
        ])->assertCreated()
            ->assertJsonPath('data.site.domain', 'example.test');

        $token = $response->json('data.token');
        $site = Site::query()->firstOrFail();

        $this->assertSame(64, strlen($site->api_token_hash));
        $this->assertSame(hash('sha256', $token), $site->api_token_hash);
        $this->actingAs($user)
            ->getJson('/dashboard/sites')
            ->assertOk()
            ->assertJsonMissingPath('data.0.api_token_hash');

        $newToken = $this->actingAs($user)
            ->postJson("/dashboard/sites/{$site->id}/token")
            ->assertOk()
            ->json('data.token');

        $this->assertNotSame($token, $newToken);
        $this->withToken($token)->postJson('/api/v1/events', $this->eventPayload())
            ->assertUnauthorized();
        $this->withToken($newToken)->postJson('/api/v1/events', $this->eventPayload())
            ->assertAccepted();
    }

    public function test_site_cannot_be_managed_by_another_user(): void
    {
        $owner = User::factory()->create();
        $site = $owner->sites()->create([
            'name' => 'Private site',
            'domain' => 'private.example',
            'api_token_hash' => hash('sha256', Str::random(48)),
        ]);

        $this->actingAs(User::factory()->create())
            ->deleteJson("/dashboard/sites/{$site->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('sites', ['id' => $site->id]);
    }

    private function eventPayload(): array
    {
        return [
            'external_id' => (string) Str::uuid(),
            'type' => 'page_view',
            'occurred_at' => now()->toIso8601String(),
            'payload' => ['page_url' => 'https://example.test/', 'browser' => 'Chrome'],
        ];
    }
}
