## Summary

<!-- What does this PR change and why? Link the issue it closes (Closes #123). -->

## Scope

<!-- Which app(s) does this touch: backend / admin / storefront / infra / docs -->

## Checklist

- [ ] Commit subjects follow `docs/COMMIT_CONVENTION.md` (CI validates every commit)
- [ ] Backend changes: `composer test` passes in `apps/backend`
- [ ] Admin changes: `pnpm build && pnpm lint` pass in `apps/admin`
- [ ] Storefront changes: `pnpm build` passes in `apps/storefront`
- [ ] Translation changes: `node scripts/check-i18n-parity.mjs` passes
- [ ] No secrets, tokens, or real DSNs in the diff
