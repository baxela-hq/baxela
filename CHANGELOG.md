# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
from its first stable release. Pre-alpha development happens on `main`.

## [Unreleased]

### Added

- MIT `LICENSE` at the repo root (the admin panel keeps its upstream shadcn-admin MIT
  license and credits).
- Governance: `SECURITY.md`, `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`, issue templates,
  pull-request template.
- CI: gitleaks secrets scan on every push and pull request.

### Changed

- Truth pass over READMEs and `AGENTS.md`: removed the unimplemented Scramble API-docs
  claim and links to non-existent docs (`architecture.md`, `/docs/api`, `/docs/database`,
  `/docs/deployment`); the Bruno collection is now referenced as the de-facto API
  reference.
- `apps/backend/docs/modules.md` rewritten to describe the 19 real modules (the previous
  tree described an aspirational structure); `docs/v1/modules.md` tree now lists all 19
  modules and gained sections for Audit, Discount, Notification, Support, and Tax.
- Admin package renamed `shadcn-admin` → `baxela-admin`.

### Removed

- Upstream shadcn-admin governance files from `apps/admin/.github/` (FUNDING, stale
  issue-closer workflow, upstream templates) and its upstream `CHANGELOG.md`.
