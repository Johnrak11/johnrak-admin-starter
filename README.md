# Johnrak Admin (Backend + Admin UI)

## Overview

- Laravel 11 backend with Sanctum auth and owner-only access
- Vue 3 admin frontend (folder: `frontend`)
- Two-Factor Authentication (TOTP), recovery codes, trusted devices
- Portfolio sync token issuance for the portfolio site to fetch data with token + OTP

## Requirements

- PHP 8.2+
- Composer
- Node.js 18+ and npm (for admin frontend)
- SQLite or MySQL (default: SQLite)

## Local Setup

- Backend
  - Copy env: `cp .env.example .env`
  - Set values:
    - `APP_URL=http://localhost:8000`
    - `FRONTEND_ORIGIN=http://localhost:5173`
    - `SUPER_ADMIN_EMAIL=admin@johnrak.online`
    - `SUPER_ADMIN_PASSWORD=ChangeThisPassword!` (change)
    - `PORTFOLIO_SYNC_TOKEN_TTL_MINUTES=1440`
  - Install: `composer install`
  - Key: `php artisan key:generate`
  - Migrate: `php artisan migrate`
  - Seed owner: `php artisan db:seed --class=Database\Seeders\JohnrakSeeder`
  - Optional: seed portfolio demo data (replaces lists): `php artisan db:seed --class=Database\Seeders\PortfolioFromJsonSeeder`
    - Ensure `.env` has `SUPER_ADMIN_EMAIL`, `SUPER_ADMIN_PASSWORD`, `SUPER_ADMIN_NAME` set
    - Run owner seeder first; portfolio seeder requires the owner user to exist
    - Portfolio seeder reads from `database/data/profile.json` and replaces experience, education, skills, certifications, and projects for that owner
  - Serve: `php artisan serve`
- Admin Frontend
  - `cd frontend`
  - `cp .env.example .env` (create and set `VITE_API_BASE_URL=http://localhost:8000`)
  - `npm install`
  - Dev: `npm run dev`

## Features

- 2FA
  - In admin, go to Security → Enable 2FA → Scan QR → Confirm code
  - Recovery codes shown once; store securely
  - Imagick is optional for PNG QR; SVG rendered when Imagick is missing
- Trusted device
  - After a successful 2FA login, “Remember this device” stores a local token
  - Future logins can skip TOTP from that device
- Portfolio sync token
  - Generate in Security: “Generate Sync Token”
  - Token TTL configurable by `PORTFOLIO_SYNC_TOKEN_TTL_MINUTES`
  - Portfolio site calls the public endpoint with token + OTP

## API References

- Auth: `/api/auth/login`, `/api/auth/login/2fa`, `/api/auth/me`, `/api/auth/logout`
- 2FA: `/api/security/2fa/setup`, `/api/security/2fa/confirm`, `/api/security/2fa/disable`, `/api/security/2fa/status`, `/api/security/2fa/regenerate-recovery-codes`
- Portfolio sync
  - Issue token (owner): `/api/security/portfolio-sync/token`
  - Public sync: `/api/client/portfolio/sync` (body: `{ "token": "...", "otp": "123456" }`)

## Production Setup

- Backend (Laravel)
  - Set env:
    - `APP_ENV=production`, `APP_DEBUG=false`
    - `APP_URL=https://admin.yourdomain`
    - `FRONTEND_ORIGIN=https://admin.yourdomain` (or wherever the admin UI is hosted)
    - Set secure `JOHNRAK_ADMIN_*` vars
    - `PORTFOLIO_SYNC_TOKEN_TTL_MINUTES` as needed
  - Install: `composer install --no-dev --prefer-dist --optimize-autoloader`
  - Key: one-time `php artisan key:generate` (before initial deploy)
  - Migrate: `php artisan migrate --force`
  - Cache config/routes:
    - `php artisan config:cache`
    - `php artisan route:cache`
  - Web server: point document root to `backend/public`, enable HTTPS, set proper PHP-FPM
  - Queue/schedule: set cron for `php artisan schedule:run` and run queue workers if needed
- Admin Frontend
  - `cd frontend && npm ci && npm run build`
  - Serve `frontend/dist` with a static server / Nginx
  - Set `VITE_API_BASE_URL=https://admin.yourdomain`

## Commands To Avoid In Production

- `php artisan migrate:fresh` or `php artisan db:wipe` (drops tables and data)
- `php artisan db:seed --class=Database\Seeders\PortfolioFromJsonSeeder` unless you intend to replace all portfolio lists
- `composer update` without a verified lock file (stick to `composer install`)
- `npm run dev` (use `npm run build` + static hosting)
- Any command that clears caches mid-traffic without planning (e.g., `optimize:clear`) unless necessary

## Sync Flow (Portfolio → Admin)

- Admin owner generates token (Security page)
- Portfolio calls `/api/client/portfolio/sync` with token + current OTP:
  - Validates token expiry and OTP (TOTP)
  - Returns the Profile JSON for the portfolio site to cache/display

## Code References

- Token issuance: [ClientPortfolioSyncController](file:///Users/apple/DEV/Project/Personal%20web/johnrak-admin-starter%20copy/backend/app/Http/Controllers/ClientPortfolioSyncController.php#L12-L27)
- Sync endpoint: [ClientPortfolioSyncController](file:///Users/apple/DEV/Project/Personal%20web/johnrak-admin-starter%20copy/backend/app/Http/Controllers/ClientPortfolioSyncController.php#L29-L63)
- Export builder: [PortfolioExportService](file:///Users/apple/DEV/Project/Personal%20web/johnrak-admin-starter%20copy/backend/app/Services/PortfolioExportService.php)
- Routes: [api.php](file:///Users/apple/DEV/Project/Personal%20web/johnrak-admin-starter%20copy/backend/routes/api.php)
- 2FA controller: [TwoFactorController](file:///Users/apple/DEV/Project/Personal%20web/johnrak-admin-starter%20copy/backend/app/Http/Controllers/TwoFactorController.php)

## Troubleshooting

### "Route not found" or "NotFoundHttpException" after pulling changes or switching environments

If you encounter 404 errors for API routes that definitely exist in `routes/api.php`, your route cache is likely stale. This often happens when moving the project between machines (e.g., Mac to Windows).

To fix this, run:

```bash
php artisan optimize:clear
```

This will clear all caches (route, config, cache, etc.) and force Laravel to register the new routes.
