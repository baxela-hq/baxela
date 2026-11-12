import { SkeletonWidget } from '@/components/shared/skeleton-widget'
import { DataTable } from '@/features/core/currencies/components/data-table'
import { Dialogs } from '@/features/core/currencies/components/dialogs'
import { FxSyncCard } from '@/features/core/currencies/components/fx-sync-card'
import { PrimaryButtons } from '@/features/core/currencies/components/primary-buttons'
import { Provider } from '@/features/core/currencies/components/provider'
import { useCurrencies } from '@/features/core/currencies/hooks/use-currencies'

/**
 * The currency management table, embedded as a Settings tab. Wraps the
 * feature's own provider/dialogs so the edit drawer and create flow work
 * exactly as they did on the standalone page, with the FX auto-sync card
 * above the table (Part E).
 */
export function CurrenciesTab() {
  const { data, isLoading, isSuccess } = useCurrencies()

  return (
    <Provider>
      <FxSyncCard />
      <div className='mb-4 flex justify-end'>
        <PrimaryButtons />
      </div>
      {isLoading && <SkeletonWidget />}
      {isSuccess && data && <DataTable data={data} />}
      <Dialogs />
    </Provider>
  )
}
