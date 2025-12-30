#!/bin/bash
set -e

echo "Deploying Johnrak Admin..."

# Pull latest changes
git pull origin prod

# Build and start containers
docker compose up -d --build --remove-orphans

# Run migrations
docker compose exec -T app php artisan migrate --force

# Optimize
docker compose exec -T app php artisan optimize

echo "Deployment complete!"