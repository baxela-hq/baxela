# Docker environments

Docker assets for the Baxela monorepo. The compose entrypoints live at the
repo root; this directory holds the per-environment build assets (Dockerfiles,
nginx configs, php.ini, entrypoints) that they reference:

```
docker-compose.yml             # develop stack (default): docker compose up -d
docker-compose.prod.yml        # production stack (see below)
.env.example                   # develop compose variables (copy to .env)
.env.production.example        # production compose variables (copy to .env.production)
infrastructure/docker/
  develop/                     # build assets: backend Dockerfile, php.ini, nginx vhost
  production/                  # build assets: backend/admin Dockerfiles, nginx, entrypoint
```

- **develop/** — source is bind-mounted from `apps/backend`; no host PHP
  required. See [develop/README.md](develop/README.md).
- **production/** — self-contained images built from the repo root (usable
  as-is in Kubernetes later), env injected at runtime. See
  [production/README.md](production/README.md).

Each environment is an independent compose project (`baxela-develop` /
`baxela-production`) with its own env file and its own volumes; they can run
side by side without clashing.

> **Heads-up for servers:** a bare `docker compose up -d` from the repo root
> starts the *develop* stack. In production always run the full command:
> `docker compose --env-file .env.production -f docker-compose.prod.yml up -d --build`.

A `infrastructure/k8s/` directory will slot in as a sibling once container
orchestration moves off a single server.
