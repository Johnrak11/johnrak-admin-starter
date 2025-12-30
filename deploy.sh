#!/usr/bin/env sh
set -e

# Load environment variables
if [ -f backend/.env ]; then
  # Use the backend .env if it exists
  export $(grep -v '^#' backend/.env | xargs)
elif [ -f .env ]; then
  export $(grep -v '^#' .env | xargs)
fi

echo "Deploying Johnrak Admin..."

# 1. Check and Create Database (using existing v-store-db)
# We assume v-store-db is running and accessible via docker exec
if [ -z "$DB_DATABASE" ]; then
  echo "DB_DATABASE is not set. Skipping DB creation check."
else
  echo "Checking database '$DB_DATABASE' in container 'v-store-db'..."
  # Use root/root as requested
  docker exec v-store-db mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS $DB_DATABASE;"
  echo "Database check complete."
fi

# 2. Deploy Containers
# Pull not needed if building locally, but good practice if using images
# docker compose pull

echo "Building and starting containers..."
docker compose down --remove-orphans || true
docker compose up -d --build

# 3. Run Migrations
echo "Running migrations..."
# Wait for backend to be ready (simple sleep or check)
sleep 5
docker exec johnrak-admin-backend php artisan migrate --force

echo "Deployment finished!"
