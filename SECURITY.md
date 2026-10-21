# Security Policy

## Supported versions

Baxela is pre-1.0: only the latest commit on `main` receives security fixes.

## Reporting a vulnerability

Please use GitHub's **private vulnerability reporting** on this repository
(Report a vulnerability → Security advisories) instead of opening a public issue.
Include reproduction steps and the affected paths where possible; you'll get an
acknowledgement within 72 hours.

## Secrets policy

- Secrets live only in environment variables. Committed `.env.example` files carry
  placeholders exclusively — never real keys, tokens, or DSNs.
- Audit performed 2026-10-21: all tracked files scanned; no real secrets found.
  The only literal DSN in-tree is a dummy fixture used by
  `apps/backend/Modules/Core/tests/Feature/Sentry/ErrorReportingTest.php`.
- CI runs a gitleaks scan on every push and pull request; new findings fail the build.
