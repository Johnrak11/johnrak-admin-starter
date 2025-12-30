# Build Stage
FROM node:18-alpine as build-stage
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# Production Stage
FROM caddy:alpine
COPY --from=build-stage /app/dist /srv
# Caddyfile will be mounted
