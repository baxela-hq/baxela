import { useAuthStore } from '@/stores/auth-store'

/** Wildcard reported by the backend for super-admin users. */
const WILDCARD = '*'

function check(permission: string, permissions: string[] | undefined): boolean {
  return !!permissions?.includes(WILDCARD) || !!permissions?.includes(permission)
}

/**
 * Whether the signed-in user holds a permission. Super-admins carry the
 * `['*']` wildcard from the account payload.
 */
export function useHasPermission(permission: string): boolean {
  const permissions = useAuthStore((state) => state.user?.permissions)
  return check(permission, permissions)
}

/** Store-backed variant usable outside React components (e.g. beforeLoad). */
export function hasPermission(permission: string): boolean {
  return check(permission, useAuthStore.getState().user?.permissions)
}
