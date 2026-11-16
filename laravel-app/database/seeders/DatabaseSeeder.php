<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'demo@pulsesync.test'],
            ['name' => 'Alex Morgan', 'password' => Hash::make('password')],
        );

        $sites = collect([
            ['name' => 'Northstar Studio', 'domain' => 'northstar.example'],
            ['name' => 'Fieldnotes', 'domain' => 'fieldnotes.example'],
        ])->map(fn (array $attributes) => Site::query()->updateOrCreate(
            ['user_id' => $user->id, 'domain' => $attributes['domain']],
            [
                'name' => $attributes['name'],
                'api_token_hash' => hash('sha256', Str::random(48)),
            ],
        ));

        $faker = fake();
        $paths = ['/', '/services', '/contact', '/about', '/insights'];

        foreach (range(29, 0) as $daysAgo) {
            foreach (range(2, 12) as $sequence) {
                $eventNumber = $daysAgo * 20 + $sequence;
                $site = $sites[$eventNumber % $sites->count()];
                $isLead = $faker->boolean(14);
                $pageUrl = 'https://'.$site->domain.$faker->randomElement($paths);
                $payload = ['page_url' => $pageUrl, 'browser' => $faker->randomElement(['Chrome 132', 'Safari 18', 'Firefox 133'])];

                if ($isLead) {
                    $payload['fields'] = [
                        'name' => $faker->name(),
                        'email' => $faker->safeEmail(),
                        'message' => $faker->sentence(),
                    ];
                }

                Event::query()->updateOrCreate(
                    ['site_id' => $site->id, 'external_id' => sprintf('00000000-0000-4000-8000-%012d', $eventNumber)],
                    [
                        'type' => $isLead ? 'lead' : 'page_view',
                        'occurred_at' => Carbon::now()->subDays($daysAgo)->subMinutes($sequence * 31),
                        'payload' => $payload,
                        'metadata' => ['source' => 'demo-seed', 'campaign' => $faker->randomElement(['organic', 'newsletter', 'referral'])],
                    ],
                );
            }
        }
    }
}
