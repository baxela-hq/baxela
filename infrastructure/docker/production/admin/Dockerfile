# syntax=docker/dockerfile:1
# Production admin image: Vite build with pnpm, served as static files by
# nginx with SPA fallback. Build context is the repo root:
#   docker build -f infrastructure/docker/production/admin/Dockerfile .

######## build: pnpm install + vite build ########
# Node 22 because Vite 7 requires >=22.12 (and Node 20 is EOL).
FROM node:22-bookworm-slim AS build
WORKDIR /app

# pnpm matched to the lockfile format (lockfileVersion 9.0).
RUN corepack enable && corepack prepare pnpm@10 --activate

COPY apps/admin/package.json apps/admin/pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile

COPY apps/admin ./

# Vite inlines these into the bundle at build time.
ARG VITE_API_BASE_URL
ARG VITE_CLERK_PUBLISHABLE_KEY
ARG VITE_STORE_FRONT_URL
ENV VITE_API_BASE_URL=$VITE_API_BASE_URL
ENV VITE_CLERK_PUBLISHABLE_KEY=$VITE_CLERK_PUBLISHABLE_KEY
ENV VITE_STORE_FRONT_URL=$VITE_STORE_FRONT_URL

RUN pnpm build

######## runtime: nginx serving the SPA ########
FROM nginx:stable-bookworm AS runtime

COPY --from=build /app/dist /usr/share/nginx/html
COPY infrastructure/docker/production/admin/nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
