# Docker environments

Docker assets for the Baxela monorepo, organized per environment:

```
docker/
  develop/     # full-stack dev: php-fpm + nginx + MySQL + Redis (+ optional Mailpit)
  production/  # single-server deploy: backend, admin, queue, scheduler, MySQL, Redis
```

- **develop/** — source is bind-mounted from `apps/backend`; no host PHP
  required. See [develop/README.md](develop/README.md).
- **production/** — self-contained images built from the repo root (usable
  as-is in Kubernetes later), env injected at runtime. See
  [production/README.md](production/README.md).

Each environment is an independent compose project (`baxela-develop` /
`baxela-production`) with its own `.env` and its own volumes; they can run
side by side without clashing.

A `infrastructure/k8s/` directory will slot in as a sibling once container
orchestration moves off a single server.
