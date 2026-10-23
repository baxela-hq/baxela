import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Header } from '@/components/layout/header'
import { HeaderActions } from '@/components/layout/header-actions'
import { Main } from '@/components/layout/main'
import { Search } from '@/components/search'
import { SkeletonWidget } from '@/components/shared/skeleton-widget'
import { DataTable } from './components/data-table'
import { Dialogs } from './components/dialogs'
import { Provider } from './components/provider'
import { Locales } from './data/routes'
import { useCurrencies } from './hooks/use-currencies'

export function Currencies() {
  const { tPageTitle } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.CURRENCY)
  const entityName = {
    singular: tLabel('currency'),
    plural: tLabel('currencies'),
  }

  const { data, isLoading, isSuccess } = useCurrencies()

  return (
    <Provider>
      <Header fixed>
        <Search />
        <HeaderActions />
      </Header>

      <Main className='flex flex-1 flex-col gap-4 sm:gap-6'>
        <div className='flex flex-wrap items-end justify-between gap-2'>
          <div>
            <h2 className='text-2xl font-bold tracking-tight'>
              {tPageTitle('index.title', { entity: entityName.plural })}
            </h2>
            <p className='text-muted-foreground'>
              {tPageTitle('index.subtitle', {
                entity: entityName.plural.toLowerCase(),
              })}
            </p>
          </div>
        </div>
        {isLoading && <SkeletonWidget />}
        {isSuccess && data && <DataTable data={data} />}
      </Main>

      <Dialogs />
    </Provider>
  )
}
