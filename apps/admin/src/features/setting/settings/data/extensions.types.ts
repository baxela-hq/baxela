/**
 * Types for the Extensions tab (marketplace P5.4), written from the
 * published P5.1–P5.3 contracts — never from assumptions:
 *
 *  - P5.1 (70b20c9e): five-field extension resource; enable/disable
 *    return the same shape.
 *  - P5.2 (d0ada70d): credential STATES only (values never leave the
 *    server); `stored`, not `setting`.
 *  - P5.3 (ffef089f): availability envelope; the inner per-package map
 *    is passed through uninterpreted, so `state` stays an open string —
 *    unknown values render as unknown, never as up-to-date.
 */

/** P5.1 — GET core/admin/extensions and the toggle responses. */
export interface Extension {
  slug: string
  enabled: boolean
  installed: boolean
  version: string | null
  skip_reason: string | null
}

/** P5.2 — GET payment/admin/credentials rows. */
export type CredentialState = 'stored' | 'env' | 'none' | 'undecryptable'

export interface DriverCredentialStates {
  driver: string
  keys: Record<string, CredentialState>
  is_configured: boolean
}

/** P5.2 — PATCH/DELETE credential responses (post-mutation state). */
export interface DriverCredentialEnvelope {
  data: DriverCredentialStates
}

/** P5.3 — GET core/admin/extensions/updates envelope. */
export type UpdateAvailability =
  | 'ok'
  | 'marketplace_unreachable'
  | 'marketplace_error'
  | 'marketplace_unconfigured'

export interface ExtensionUpdateEntry {
  /** Open string: the six marketplace states are rendered, anything else is "unknown". */
  state: string
  latest: string | null
  update_available: boolean
  current_yanked: boolean
}

export interface ExtensionUpdatesEnvelope {
  availability: UpdateAvailability
  cached: boolean
  generated_at: string | null
  data: Record<string, ExtensionUpdateEntry>
}

/**
 * Stale fallback predicate (P5.4 F5): `cached` alone can be a fresh
 * 15-minute cache hit — staleness is cached data served ONLY because
 * the marketplace failed, using the exact published availability
 * values (a future fifth value must not silently read as stale).
 */
export function isStaleSnapshot(envelope: ExtensionUpdatesEnvelope): boolean {
  return (
    envelope.cached &&
    (envelope.availability === 'marketplace_unreachable' ||
      envelope.availability === 'marketplace_error')
  )
}

/** The per-package states the marketplace contract defines today. */
export const KNOWN_UPDATE_STATES = [
  'up_to_date',
  'update_available',
  'installed_newer_than_registry',
  'no_compatible_release',
  'unknown_package',
  'package_yanked',
] as const
