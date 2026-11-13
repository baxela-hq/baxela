import { useState } from 'react';
import { getRouteApi } from '@tanstack/react-router';
import { Header } from '@/components/layout/header';
import { Main } from '@/components/layout/main';
import { Search } from '@/components/search'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Locales } from './data/routes';
import { DataTable } from './components/data-table.tsx';
import { CreditNoteDataTable } from './components/credit-note-data-table';
import { useInvoicesList, useCreditNotesList } from './hooks/use-invoices';
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { HeaderActions } from '@/components/layout/header-actions'

const route = getRouteApi('/_authenticated/order/invoices/')

/**
 * The order documents page (gap-B1): invoices and their credit notes —
 * the full document chain on one screen. The tab shares the URL state:
 * a number search or the page applies to whichever table is showing.
 */
export function Invoices() {
  const search = route.useSearch()
  const navigate = route.useNavigate()
  const [tab, setTab] = useState<'invoices' | 'credit-notes'>('invoices')
  const { tPageTitle } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel: tInvoiceLabel } = useAppTranslation(Locales.INVOICE)
  const entityName = {
    singular: tInvoiceLabel("invoice"),
    plural: tInvoiceLabel("invoices")
  };

  const invoices = useInvoicesList(search);
  const creditNotes = useCreditNotesList(search);

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

        <Tabs value={tab} onValueChange={(value) => setTab(value as 'invoices' | 'credit-notes')}>
          <TabsList>
            <TabsTrigger value='invoices'>{tInvoiceLabel('invoices')}</TabsTrigger>
            <TabsTrigger value='credit-notes'>{tInvoiceLabel('credit_notes')}</TabsTrigger>
          </TabsList>
        </Tabs>

        {tab === 'invoices' ? (
          invoices.isLoading ? (
            <div className='flex items-center justify-center py-24'>
              <span className='animate-pulse text-muted-foreground'>
                {tPageTitle("index.loading", {entity: entityName.plural})}
              </span>
            </div>
          ) : invoices.isSuccess && invoices.data ? (
            <DataTable
              data={invoices.data}
              search={search}
              navigate={navigate}
            />
          ) : null
        ) : creditNotes.isLoading ? (
          <div className='flex items-center justify-center py-24'>
            <span className='animate-pulse text-muted-foreground'>
              {tPageTitle("index.loading", {entity: tInvoiceLabel('credit_notes')})}
            </span>
          </div>
        ) : creditNotes.isSuccess && creditNotes.data ? (
          <CreditNoteDataTable
            data={creditNotes.data}
            search={search}
            navigate={navigate}
          />
        ) : null}
      </Main>
    </>
  )
}
