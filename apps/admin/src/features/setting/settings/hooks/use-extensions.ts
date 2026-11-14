import { useQuery } from '@tanstack/react-query'
import {
  fetchDriverCredentials,
  fetchExtensionUpdates,
  fetchExtensions,
} from '../api/extensions.api'

/**
 * The Extensions tab's three server-state queries (marketplace P5.4) —
 * deliberately independent: one section failing never suspends the
 * others, and each owns its loading/empty/error rendering.
 */

export const EXTENSIONS_CACHE_KEY = 'extensions'
export const EXTENSION_CREDENTIALS_CACHE_KEY = 'extension-driver-credentials'
export const EXTENSION_UPDATES_CACHE_KEY = 'extension-updates'

export function useExtensionsList() {
  return useQuery({
    queryKey: [EXTENSIONS_CACHE_KEY],
    queryFn: fetchExtensions,
  })
}

export function useDriverCredentials() {
  return useQuery({
    queryKey: [EXTENSION_CREDENTIALS_CACHE_KEY],
    queryFn: fetchDriverCredentials,
  })
}

export function useExtensionUpdates() {
  return useQuery({
    queryKey: [EXTENSION_UPDATES_CACHE_KEY],
    queryFn: fetchExtensionUpdates,
  })
}
