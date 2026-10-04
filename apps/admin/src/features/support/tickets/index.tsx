import { getRouteApi } from '@tanstack/react-router'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { Search } from '@/components/search'
import { SkeletonWidget as SkeletonWidgetFromFile } from '@/components/shared/skeleton-widget'
import { DataTable } from './components/data-table'
import { Dialogs } from './components/dialogs'
import { Provider } from './components/provider'
import { Locales } from './data/routes'
import { useTicketsList } from './hooks/use-tickets'
import { HeaderActions } from '@/components/layout/header-actions'

const route = getRouteApi('/_authenticated/support/tickets/')

export function Tickets() {
  const search = route.useSearch()
  const navigate = route.useNavigate()
  const { tPageTitle } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.SUPPORT_TICKET)
  const entityName = {
    singular: tLabel('ticket'),
    plural: tLabel('tickets'),
  }

  const { data, isLoading, isSuccess } = useTicketsList(search)

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
              {tPageTitle('index.title', { entity: entityName.singular })}
            </h2>
            <p className='text-muted-foreground'>
              {tPageTitle('index.subtitle', {
                entity: entityName.plural.toLowerCase(),
              })}
            </p>
          </div>
        </div>
        {isLoading && <SkeletonWidgetFromFile />}
        {isSuccess && (
          <DataTable data={data} search={search} navigate={navigate} />
        )}
      </Main>

      <Dialogs />
    </Provider>
  )
}
