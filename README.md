# PulseSync

PulseSync connects WordPress lead capture to a Laravel analytics dashboard.

```mermaid
flowchart LR
  Visitor --> Widget[WordPress widget]
  Widget --> Queue[WordPress MySQL retry queue]
  Queue -->|Bearer token| API[Laravel API]
  API --> Jobs[Database queue]
  Jobs --> PG[(PostgreSQL JSONB)]
  PG --> Dashboard[Vue analytics dashboard]
```

## Run locally

Requires Docker Compose.

```sh
docker compose up --build
```

Open the dashboard at <http://localhost:8000>. Demo login: `demo@pulsesync.test` /
`password`. WordPress runs at <http://localhost:8080>; finish its setup, activate
**PulseSync Connector**, then set its endpoint to
`http://app:8000/api/v1/events` and paste the site's dashboard token into
**Settings → PulseSync**.

## Data

PostgreSQL stores users, sites, and site-scoped events. Event payloads and metadata
use JSONB; metadata has a GIN index. WordPress MySQL stores queued deliveries for
five-minute retries. Demo analytics are seeded on startup.

## Deploy

Use Render with Neon PostgreSQL for Laravel and Pantheon or InstaWP for WordPress.
Set `APP_KEY`, `APP_URL`, and `DB_*`, run migrations, then configure the plugin
endpoint and site token. Free-tier availability varies. Keep the demo account for
local use only.

## Checks

```sh
cd laravel-app
composer install
php artisan test
vendor/bin/pint --test
npm ci && npm run build

cd ../wordpress-plugin
composer install
vendor/bin/phpunit
vendor/bin/phpcs
```

GitHub Actions runs the same tests and quality checks on pushes and pull requests.
