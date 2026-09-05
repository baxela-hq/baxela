# Development Vite dev-server image for the admin panel. The application
# source is bind-mounted at runtime (see docker-compose.yml), so nothing is
# copied in here.

# Node 22 because Vite 7 requires >=22.12 (and Node 20 is EOL).
FROM node:22-bookworm-slim

ARG WWWUSER=1000
ARG WWWGROUP=1000

# pnpm matched to the admin lockfile format (lockfileVersion 9.0) — same as
# the production admin image. COREPACK_HOME is moved out of /root so the
# prepared pnpm stays usable after switching to the uid-matched user below.
ENV COREPACK_HOME=/usr/local/share/corepack
ENV COREPACK_ENABLE_DOWNLOAD_PROMPT=0
RUN corepack enable && corepack prepare pnpm@10 --activate

# Match the node user/group to the host user so files created inside the
# container (node_modules, build caches) are not root-owned on the host.
RUN groupmod -g ${WWWGROUP} node \
    && usermod -u ${WWWUSER} node

# Corepack may need to fetch other pnpm versions at runtime (packageManager
# pins), and the anonymous volume mounted at /app/node_modules is initialized
# from the image — both must be writable by the runtime user.
RUN chown -R node:node /usr/local/share/corepack \
    && install -d -o node -g node /app/node_modules

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /app

USER node

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["pnpm", "dev"]
