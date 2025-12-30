# Security Hardening Checklist

## Server (Caddy)
- Remove identifying headers: `Server`, `X-Powered-By`, `Via`, `Alt-Svc`.
- Add security headers:
  - HSTS: `Strict-Transport-Security: max-age=31536000; includeSubDomains; preload`
  - `X-Content-Type-Options: nosniff`
  - `X-Frame-Options: DENY`
  - `Referrer-Policy: no-referrer`
  - `Permissions-Policy: geolocation=(), microphone=(), camera=()`
- Prefer single-domain routing to avoid CORS complexity.

## PHP/Laravel
- Set `expose_php = Off` (Dockerfile `conf.d/security.ini`).
- `APP_DEBUG=false` in production.
- Remove sensitive logs:
  - Do not log request bodies or tokens.
  - Log only non-sensitive identifiers (email, user_id).
- Rate-limit login (already enabled).
- CORS: explicit allowed origin and headers; keep credentials only if required.

## Frontend
- No secrets in the build: only `VITE_API_BASE_URL` is used.
- Consider CSP (Content Security Policy) if you serve via Caddy (tune for SPA inline requirements).

## Database
- Use Docker network DNS (`v-store-db:3306`), not host mappings.

## Operations
- CI auto-migrations after deploy.
- Optional seeding via manual command with `--force`.

