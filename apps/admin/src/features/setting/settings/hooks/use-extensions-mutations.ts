import { useMutation, useQueryClient } from '@tanstack/react-query'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import { toast } from 'sonner'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import {
  deleteDriverCredential,
  disableExtension,
  enableExtension,
  updateDriverCredentials,
} from '../api/extensions.api'
import {
  EXTENSION_CREDENTIALS_CACHE_KEY,
  EXTENSION_UPDATES_CACHE_KEY,
  EXTENSIONS_CACHE_KEY,
} from './use-extensions'
import { Locales } from '../data/routes'

/**
 * The Extensions tab mutations (marketplace P5.4).
 *
 * Credential secret-retention lifecycle (review F6): both credential
 * mutations run with gcTime 0, and the ONLY sanctioned call pattern is
 * the exported `run*` helpers — mutateAsync + reset() in finally — so
 * the observer detaches the moment ITS OWN operation settles and the
 * submitted plaintext leaves the mutation cache. Callers MUST keep the
 * submit action disabled while pending (single-flight): an overlapping
 * submission could otherwise reset the observer while a second
 * mutation is still attached to it. This avoids application-managed
 * retention; it is not a memory-zeroization guarantee.
 */

export function useToggleExtension() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tAction } = useAppTranslation(Locales.PAYMENT_CREDENTIALS)

  const mutation = useMutation({
    mutationFn: ({ slug, enable }: { slug: string; enable: boolean }) =>
      enable ? enableExtension(slug) : disableExtension(slug),
    onSuccess: async (_result, variables) => {
      // Persisted state changed — the worker caveat is rendered by the
      // component (F2); the toast only confirms the persisted write.
      toast.success(
        tMessage('success.record.updated', {
          name: `${variables.slug} (${tAction(variables.enable ? 'enable' : 'disable')})`,
        })
      )
      await queryClient.invalidateQueries({
        queryKey: [EXTENSIONS_CACHE_KEY],
      })
      await queryClient.invalidateQueries({
        queryKey: [EXTENSION_UPDATES_CACHE_KEY],
      })
    },
    onError: (err: unknown) => {
      if (err instanceof ApiError) parseAndToastError(err)
      else toast.error(tMessage('error.general'))
    },
  })

  return {
    mutation,
    /** Single-flight runner: awaits the observer's OWN settlement, then detaches it. */
    run: (input: { slug: string; enable: boolean }) =>
      mutation.mutateAsync(input).finally(() => mutation.reset()),
  }
}

export type CredentialPatchInput = {
  driver: string
  credentials: Record<string, string>
}

export type CredentialDeleteInput = { driver: string; key: string }

export function useUpdateDriverCredentials() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  const mutation = useMutation({
    gcTime: 0,
    mutationFn: ({ driver, credentials }: CredentialPatchInput) =>
      updateDriverCredentials(driver, credentials),
    onSuccess: async () => {
      toast.success(tMessage('success.record.updated'))
      await queryClient.invalidateQueries({
        queryKey: [EXTENSION_CREDENTIALS_CACHE_KEY],
      })
    },
    // Error payloads log/toast message and key names only — never the
    // submitted variables.
    onError: (err: unknown, variables) => {
      void variables // explicitly unused: never surface the values
      if (err instanceof ApiError) parseAndToastError(err)
      else toast.error(tMessage('error.general'))
    },
  })

  return {
    mutation,
    run: (input: CredentialPatchInput) =>
      mutation.mutateAsync(input).finally(() => mutation.reset()),
  }
}

export function useDeleteDriverCredential() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  const mutation = useMutation({
    gcTime: 0,
    mutationFn: ({ driver, key }: CredentialDeleteInput) =>
      deleteDriverCredential(driver, key),
    onSuccess: async (_result, variables) => {
      toast.success(
        tMessage('success.record.deleted', { name: `${variables.driver} / ${variables.key}` })
      )
      await queryClient.invalidateQueries({
        queryKey: [EXTENSION_CREDENTIALS_CACHE_KEY],
      })
    },
    onError: (err: unknown) => {
      if (err instanceof ApiError) parseAndToastError(err)
      else toast.error(tMessage('error.general'))
    },
  })

  return {
    mutation,
    run: (input: CredentialDeleteInput) =>
      mutation.mutateAsync(input).finally(() => mutation.reset()),
  }
}

export type ToggleRunner = ReturnType<typeof useToggleExtension>['run']
