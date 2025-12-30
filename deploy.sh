#!/bin/bash
set -e

echo "Deploying Johnrak Admin..."

if [ ! -d .git ]; then
  echo "Error: repository not initialized."
  exit 1
fi

# Pull latest changes
git pull origin prod

# Ensure backend/.env exists
if [ ! -f backend/.env ]; then
  echo "Creating backend/.env"
  cat > backend/.env <<'EOF'
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost
APP_KEY=

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

# Install PHP dependencies (run as root due to bind-mounted perms)
docker compose exec -T -u root app composer install --no-dev -n --prefer-dist

# Fix storage permissions
docker compose exec -T -u root app sh -lc 'chown -R www-data:www-data storage bootstrap/cache && chmod -R 775 storage bootstrap/cache'

# Generate app key or set if missing/empty
if ! docker compose exec -T app php artisan key:generate --force; then
  echo "key:generate failed"
fi
# Ensure APP_KEY exists and is non-empty
docker compose exec -T app sh -lc '
  if grep -q "^APP_KEY=" .env; then
    if grep -q "^APP_KEY=$" .env; then
      KEY=$(php -r '\''echo base64_encode(random_bytes(32));'\'');
      sed -i "s/^APP_KEY=$/APP_KEY=base64:${KEY}/" .env
    fi
  else
    KEY=$(php -r '\''echo base64_encode(random_bytes(32));'\'');
    echo "APP_KEY=base64:${KEY}" >> .env
  fi
'

# Wait a bit for DB
sleep 10

# Run migrations with retry
for i in {1..5}; do
  if docker compose exec -T app php artisan migrate --force; then
    break
  fi
  echo "Migration attempt $i failed; retrying in 5s..."
  sleep 5
done

# Optimize
docker compose exec -T app php artisan optimize

echo "Deployment complete!"