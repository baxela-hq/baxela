# Development Next.js dev-server image for the storefront. The application
# source is bind-mounted at runtime (see docker-compose.yml), so nothing is
# copied in here.

# Node 22 to match the admin image and the production build environment.
FROM node:22-bookworm-slim

ARG WWWUSER=1000
ARG WWWGROUP=1000

# pnpm prepared for offline-ish starts; the storefront's packageManager pin
# (currently pnpm 11) is fetched by corepack on first start into the shared
# COREPACK_HOME below. COREPACK_HOME is moved out of /root so the prepared
# pnpm stays usable after switching to the uid-matched user below.
ENV COREPACK_HOME=/usr/local/share/corepack
ENV COREPACK_ENABLE_DOWNLOAD_PROMPT=0
RUN corepack enable && corepack prepare pnpm@10 --activate

# Match the node user/group to the host user so files created inside the
# container (node_modules, .next build cache) are not root-owned on the host.
RUN groupmod -g ${WWWGROUP} node \
    && usermod -u ${WWWUSER} node

# Corepack fetches the pinned pnpm version at runtime, and the anonymous
# volumes mounted at /app/node_modules and /app/.next are initialized from
# the image — all must be writable by the runtime user.
RUN chown -R node:node /usr/local/share/corepack \
    && install -d -o node -g node /app/node_modules /app/.next

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /app

USER node

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
# next dev binds to localhost by default; -H 0.0.0.0 makes it reachable
# through the compose port mapping.
CMD ["pnpm", "exec", "next", "dev", "-H", "0.0.0.0"]
