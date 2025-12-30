# Johnrak Admin Deployment Flow and Troubleshooting

## Overview
- Single domain: `https://admin.johnrak.online` serves both the SPA and the API.
- Path-based routing:
  - UI: all non-API paths
  - API: `/api/*` and `/sanctum/*` forwarded to PHP-FPM (backend)
- Backend: Laravel + Sanctum issuing bearer tokens
- Database: MariaDB `v-store-db` on Docker network

## Components and Connections
- Caddy (public reverse proxy): `v-store-web`
  - Serves UI via `reverse_proxy johnrak-admin-frontend:80`
  - For `/api/*` and `/sanctum/*`, routes to `php_fastcgi johnrak-admin-backend:9000`
  - Root for backend FastCGI is the backend’s document root (`/var/www/public` inside backend image)
- Frontend: `johnrak-admin-frontend` (Caddy + built SPA)
- Backend: `johnrak-admin-backend` (PHP-FPM at `9000/tcp`, Laravel app at `/var/www`)
- Database: `v-store-db` (MariaDB), reachable from backend via service DNS, not host mapping

## Caddy Config (single-domain, path-based routing)
Use in the public Caddy (v-store-web):

```
admin.johnrak.online {
  route {
    handle_path /api/* {
      root * /var/www/public
      php_fastcgi johnrak-admin-backend:9000
    }

    handle_path /sanctum/* {
      root * /var/www/public
      php_fastcgi johnrak-admin-backend:9000
    }

    reverse_proxy johnrak-admin-frontend:80
  }
}
```

Notes:
- FastCGI requires pointing `root` to a filesystem path with `index.php`. Since backend owns the code, you don’t need to mount it into Caddy; just set the root to match backend’s docroot and forward to the backend PHP-FPM.
- Ordering matters: route API handles before the SPA fallback.

## Backend .env (production essentials)
- `APP_URL=https://admin.johnrak.online`
- `FRONTEND_ORIGIN=https://admin.johnrak.online` (no backticks)
- `SANCTUM_STATEFUL_DOMAINS=admin.johnrak.online`
- `SESSION_DOMAIN=.johnrak.online`
- `DB_HOST=v-store-db`
- `DB_PORT=3306`

Why:
- Linux Docker: use container DNS (`v-store-db:3306`), not `host.docker.internal:3307`.
- Backticks around URLs break CORS origin matching.

## Frontend API Base URL
- `VITE_API_BASE_URL=https://admin.johnrak.online`
- Frontend calls relative `/api/...`; same-origin avoids CORS complexity.

## Fix Applied: JSON Body Parsing Under FastCGI
- Problem: POSTing JSON returned 422 "email required" despite body being sent.
- Cause: Under FastCGI, Laravel sometimes needs explicit merging of JSON payload into the request before validation.
- Fix: In `AuthController@login`, merge JSON into the request prior to `$request->validate(...)`.

## Common Pitfalls Encountered
- 404 for `/api/*`: Caddy routing to frontend container; backend not reached.
  - Fix: Path-based routing to PHP-FPM with correct root and ordering.
- FastCGI 404 "File not found": Caddy points to a root that doesn’t exist inside proxy; missing `index.php` at that path.
  - Fix: Use backend’s docroot (`/var/www/public`) for FastCGI routes; no need to mount backend into Caddy.
- CORS mismatches: backticks around `FRONTEND_ORIGIN` and wrong `APP_URL`.
  - Fix: remove backticks; align domains.
- DB connectivity failures: using `host.docker.internal`.
  - Fix: use `v-store-db:3306` on Docker network.

## Verification Steps
- Reload Caddy and check adapted config:
  - `caddy fmt`, `caddy adapt`, `caddy reload`
- API checks:
  - `curl -i -X POST https://admin.johnrak.online/api/auth/login -H "Accept: application/json" -d "email=...&password=...&device_name=web"`
  - Expect 200 with token or proper 4xx JSON errors.
- UI checks:
  - Login from `https://admin.johnrak.online/login` should reach backend without network/CORS errors.

## Rollback/Alternate
- If you prefer a separate subdomain for API, configure `admin-service.johnrak.online` in Caddy with `php_fastcgi johnrak-admin-backend:9000` and serve `/var/www/public`.
- Keep SPA on `admin.johnrak.online` via reverse proxy.

## CI/CD (GitHub Actions)
- Workflow: `.github/workflows/deploy.yml`
- Trigger: push to `prod`
- Action: SSH into server using `${{ secrets.HOST }}`, `${{ secrets.USERNAME }}`, `${{ secrets.SSH_KEY }}`
- Steps executed remotely:
  - Clone or update repo to `~/projects/johnrak-admin-starter`
  - Force checkout and reset to `origin/prod`
  - Run `deploy.sh` non-interactively

Notes:
- No `sudo` is used; ensure the `USERNAME` is in the `docker` group on the server so Docker commands run without prompts.
- `deploy.sh` handles DB presence, container rebuild/restart, and migrations; it’s idempotent and non-interactive.
- If seeding is required, run manually: `docker exec johnrak-admin-backend php artisan db:seed --class=PortfolioFromJsonSeeder --force`.

## Backups
- Configure in admin at Security → Backup.
- When enabled and configured, daily DB backup runs at 12:00 AM and uploads to your S3-compatible bucket (AWS S3 free tier, Cloudflare R2, Backblaze B2, DigitalOcean Spaces).
- Manual backup via “Backup now” button.
