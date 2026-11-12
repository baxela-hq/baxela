import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import { toast } from 'sonner'
import {
  fetchFxSettings,
  saveFxSettings,
  syncFxRates,
  type FxSyncStatus,
} from '../api/currencies.api'

const FX_SETTINGS_KEY = ['fx-settings'] as const

/**
 * The FX settings rows (the key arrives masked or null — never the
 * secret) plus the machine-managed sync status parsed out of
 * fx_sync_status.
 */
export function useFxSettings() {
  const query = useQuery({
    queryKey: FX_SETTINGS_KEY,
    queryFn: fetchFxSettings,
  })

  let status: FxSyncStatus | null = null

  const rawStatus = query.data?.fx_sync_status

  if (rawStatus) {
    try {
      const parsed = JSON.parse(rawStatus)
      if (parsed && typeof parsed === 'object') status = parsed as FxSyncStatus
    } catch {
      status = null
    }
  }

  return {
    query,
    enabled: query.data?.fx_auto_sync_enabled === '1',
    keyConfigured: (query.data?.fx_api_key ?? '') !== '',
    provider: query.data?.fx_provider ?? 'openexchangerates',
    intervalHours: Number(query.data?.fx_sync_interval_hours ?? 24),
    status,
  }
}

/**
 * Save the editable FX fields + run "Sync now". The key item is only
 * included when the caller supplies it — omission keeps the stored
 * credential, `null` clears it (ConvertEmptyStringsToNull on the wire).
 */
export function useFxMutations() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation('shared/common')
  const { t } = useAppTranslation('core/currency')

  const invalidate = async () => {
    await queryClient.invalidateQueries({ queryKey: FX_SETTINGS_KEY })
    await queryClient.invalidateQueries({ queryKey: ['currencies'] })
    await queryClient.invalidateQueries({ queryKey: ['settings'] })
  }

  const save = useMutation({
    mutationFn: (items: Parameters<typeof saveFxSettings>[0]) =>
      saveFxSettings(items),
    onSuccess: async () => {
      toast.success(t('fx.saved'))
      await invalidate()
    },
    onError: (err: unknown) => {
      if (err instanceof ApiError) parseAndToastError(err)
      else toast.error(tMessage('error.general'))
    },
  })

  const syncNow = useMutation({
    mutationFn: () => syncFxRates(),
    onSuccess: async (result) => {
      if (result.outcome === 'synced') {
        toast.success(t('fx.sync-success'))
      } else if (result.outcome === 'sync_in_progress') {
        toast.info(t('fx.sync-in-progress'))
      } else {
        toast.info(t('fx.sync-skipped'))
      }
      await invalidate()
    },
    onError: (err: unknown) => {
      if (err instanceof ApiError) parseAndToastError(err)
      else toast.error(tMessage('error.general'))
      // statuses changed even on failure — refetch
      void invalidate()
    },
  })

  return { save, syncNow }
}
