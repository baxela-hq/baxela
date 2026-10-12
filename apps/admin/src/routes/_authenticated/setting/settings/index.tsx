import { createFileRoute, redirect } from '@tanstack/react-router'

/**
 * Moved: store settings now live inside the settings section. Pure
 * URL-relocation redirect (not an auth guard), so old bookmarks keep
 * working.
 */
export const Route = createFileRoute('/_authenticated/setting/settings/')({
  beforeLoad: () => {
    throw redirect({ to: '/settings/store' })
  },
})
