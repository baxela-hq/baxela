import { getRouteApi } from '@tanstack/react-router';
import { Header } from '@/components/layout/header';
import { Main } from '@/components/layout/main';
import { Search } from '@/components/search'
import { Locales } from './data/routes';
import { DataTable } from './components/data-table.tsx';
import { useInvoicesList } from './hooks/use-invoices';
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { HeaderActions } from '@/components/layout/header-actions'

const route = getRouteApi('/_authenticated/order/invoices/')

export function Invoices() {
  const search = route.useSearch()
  const navigate = route.useNavigate()
  const { tPageTitle } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.INVOICE)
  const entityName = {
    singular: tLabel("invoice"),
    plural: tLabel("invoices")
  };

  const { data, isLoading, isSuccess } = useInvoicesList(search);

  return (
    <>
      <Header fixed>
        <Search />
        <HeaderActions />
      </Header>

      <Main className='flex flex-1 flex-col gap-4 sm:gap-6'>
        <div className='flex flex-wrap items-end justify-between gap-2'>
          <div>
            <h2 className='text-2xl font-bold tracking-tight'>
              {tPageTitle("index.title", {entity: entityName.singular})}
            </h2>
            <p className='text-muted-foreground'>
              {tPageTitle("index.subtitle", {entity: entityName.plural})}
            </p>
          </div>
        </div>
        {isLoading && (
          <div className='flex items-center justify-center py-24'>
            <span className='animate-pulse text-muted-foreground'>
              {tPageTitle("index.loading", {entity: entityName.plural})}
            </span>
          </div>
        )}
        {isSuccess && data && (
          <DataTable
            data={data}
            search={search}
            navigate={navigate}
          />
        )}
      </Main>
    </>
  )
}
