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

## Run locally without Docker

Install PHP 8.2+ with `pdo_pgsql`, Composer, Node.js, and PostgreSQL 16+. Create a
PostgreSQL role and database using pgAdmin or `psql`:

```sql
CREATE ROLE pulsesync LOGIN PASSWORD 'pulsesync';
CREATE DATABASE pulsesync OWNER pulsesync;
```

Then open PowerShell from the project folder:

```powershell
cd laravel-app
Copy-Item .env.example .env
composer install
npm ci
php artisan key:generate
php artisan migrate --seed
npm run build
```

Set `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in `laravel-app/.env` to match
your PostgreSQL setup. In separate PowerShell windows, run:

```powershell
cd laravel-app
php artisan serve
```

```powershell
cd laravel-app
php artisan queue:work --tries=5
```

Open <http://localhost:8000>. Demo login: `demo@pulsesync.test` / `password`.
To connect WordPress, install it separately (for example with XAMPP or Local),
copy `wordpress-plugin` into its `wp-content/plugins/pulsesync-connector` folder,
activate **PulseSync Connector**, and set its API endpoint to
`http://localhost:8000/api/v1/events`. Paste the site's dashboard token into
**Settings → PulseSync**.

## Run with Docker

Alternatively, install Docker Compose and run `docker compose up --build`. The
dashboard is at <http://localhost:8000> and WordPress at <http://localhost:8080>.
In that setup, set the plugin endpoint to `http://app:8000/api/v1/events`.

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
