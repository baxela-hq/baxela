# syntax=docker/dockerfile:1
# Production storefront image: Next.js build with pnpm, run as the standalone
# server (next.config.ts sets output: "standalone"). Build context is the
# repo root:
#   docker build -f infrastructure/docker/production/storefront/Dockerfile .

######## build: pnpm install + next build ########
# Node 22 to match the develop images; pnpm resolves from the storefront's
# packageManager pin (pnpm 11).
FROM node:22-bookworm-slim AS build
WORKDIR /app

ENV COREPACK_HOME=/usr/local/share/corepack
ENV COREPACK_ENABLE_DOWNLOAD_PROMPT=0
RUN corepack enable

# pnpm-workspace.yaml carries the pnpm 11 allowBuilds approvals — without it
# pnpm install fails on packages with (unapproved) postinstall scripts.
COPY apps/storefront/package.json apps/storefront/pnpm-lock.yaml apps/storefront/pnpm-workspace.yaml ./
RUN pnpm install --frozen-lockfile

# Next inlines NEXT_PUBLIC_* into the client bundle at build time.
ARG NEXT_PUBLIC_API_BASE_URL
ENV NEXT_PUBLIC_API_BASE_URL=$NEXT_PUBLIC_API_BASE_URL

COPY apps/storefront ./
RUN pnpm build

######## runtime: standalone Next.js server ########
FROM node:22-bookworm-slim AS runtime
WORKDIR /app

ENV NODE_ENV=production
# server.js binds to these; 0.0.0.0 so the published port works.
ENV HOSTNAME=0.0.0.0
ENV PORT=3000

COPY --from=build --chown=node:node /app/.next/standalone ./
COPY --from=build --chown=node:node /app/.next/static ./.next/static
COPY --from=build --chown=node:node /app/public ./public

USER node

EXPOSE 3000
CMD ["node", "server.js"]
