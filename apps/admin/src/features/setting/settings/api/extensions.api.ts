import {
  deleteRequest,
  getRequest,
  patchRequest,
  postRequest,
} from '@/shared/lib/api-client'
import {
  type DriverCredentialEnvelope,
  type DriverCredentialStates,
  type Extension,
  type ExtensionUpdatesEnvelope,
} from '../data/extensions.types'

/**
 * HTTP access for the Extensions tab (marketplace P5.4) — a pure
 * consumer of the frozen P5.1–P5.3 endpoints. No second Axios instance,
 * no auth logic (the shared client owns both).
 */

const EXTENSIONS_URL = 'core/admin/extensions'
const CREDENTIALS_URL = 'payment/admin/credentials'

/**
 * The single URL constructor for both toggle actions (P5.4 F8): the
 * slug is a Composer package name (vendor/package), split into the two
 * route parameters and encoded independently — encodeURIComponent over
 * the whole slug would encode the separator. A malformed slug yields
 * null; callers disable the action, never a half-built endpoint.
 */
export function extensionToggleUrl(
  slug: string,
  action: 'enable' | 'disable'
): string | null {
  const segments = slug.split('/')

  if (segments.length !== 2 || segments.some((s) => s.length === 0)) {
    return null
  }

  const [vendor, pkg] = segments

  return `${EXTENSIONS_URL}/${encodeURIComponent(vendor)}/${encodeURIComponent(pkg)}/${action}`
}

export function fetchExtensions(): Promise<{ data: Extension[] }> {
  return getRequest<{ data: Extension[] }>(EXTENSIONS_URL)
}

export function enableExtension(slug: string): Promise<{ data: Extension }> {
  const url = extensionToggleUrl(slug, 'enable')

  if (url === null) {
    return Promise.reject(new Error('invalid extension slug'))
  }

  return postRequest<{ data: Extension }, Record<string, never>>(url, {})
}

export function disableExtension(slug: string): Promise<{ data: Extension }> {
  const url = extensionToggleUrl(slug, 'disable')

  if (url === null) {
    return Promise.reject(new Error('invalid extension slug'))
  }

  return postRequest<{ data: Extension }, Record<string, never>>(url, {})
}

export function fetchDriverCredentials(): Promise<{
  data: DriverCredentialStates[]
}> {
  return getRequest<{ data: DriverCredentialStates[] }>(CREDENTIALS_URL)
}

/** Partial update (PATCH semantics): submitted keys upsert, omitted keys keep their state. */
export function updateDriverCredentials(
  driver: string,
  credentials: Record<string, string>
): Promise<DriverCredentialEnvelope> {
  return patchRequest<DriverCredentialEnvelope, Record<string, string>>(
    `${CREDENTIALS_URL}/${encodeURIComponent(driver)}`,
    credentials
  )
}

/** Removes the database override; the environment fallback may become effective again. */
export function deleteDriverCredential(
  driver: string,
  key: string
): Promise<DriverCredentialEnvelope> {
  return deleteRequest<DriverCredentialEnvelope>(
    `${CREDENTIALS_URL}/${encodeURIComponent(driver)}/${encodeURIComponent(key)}`
  )
}

export function fetchExtensionUpdates(): Promise<{
  data: ExtensionUpdatesEnvelope
}> {
  return getRequest<{ data: ExtensionUpdatesEnvelope }>(
    `${EXTENSIONS_URL}/updates`
  )
}
