import { createFileRoute, Outlet } from '@tanstack/react-router'

/**
 * Layout shell for the settings section — subpages render through the
 * Outlet. Bare /settings relocates to the store settings via the index
 * route; the redirect must live THERE, never on this layout: a parent
 * beforeLoad runs for every child match and would loop /settings/store
 * onto itself.
 */
export const Route = createFileRoute('/_authenticated/settings')({
  component: Outlet,
})
