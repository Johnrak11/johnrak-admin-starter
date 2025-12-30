#!/bin/bash
set -e

echo "Deploying Johnrak Admin..."

if [ ! -d .git ]; then
  echo "Error: repository not initialized."
  exit 1
fi

# Pull latest changes
git pull origin prod

# Ensure .env exists
if [ ! -f .env ]; then
  echo "Creating default .env (adjust as needed)"
  cat > .env <<'EOF'
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=admin
DB_USERNAME=admin
DB_PASSWORD=admin123

SANCTUM_STATEFUL_DOMAINS=admin.johnrak.online
SESSION_DRIVER=file
CACHE_DRIVER=file
QUEUE_CONNECTION=sync
EOF
fi

# Build and start containers
docker compose up -d --build --remove-orphans

# Install PHP dependencies (since code is bind-mounted)
docker compose exec -T app composer install --no-dev -n --prefer-dist

# Generate app key if missing
docker compose exec -T app php artisan key:generate --force || true

# Run migrations
docker compose exec -T app php artisan migrate --force

# Optimize
docker compose exec -T app php artisan optimize

echo "Deployment complete!"