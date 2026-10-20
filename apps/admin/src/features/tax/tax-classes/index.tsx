import { getRouteApi } from '@tanstack/react-router';
import { Header } from '@/components/layout/header';
import { Main } from '@/components/layout/main';
import { Search } from '@/components/search'
import { SkeletonWidget as SkeletonWidgetFromFile } from '@/components/shared/skeleton-widget'
import { Locales } from './data/routes';
import { Dialogs } from './components/dialogs.tsx';
import { PrimaryButtons } from './components/primary-buttons.tsx';
import { Provider } from './components/provider.tsx';
import { DataTable } from './components/data-table.tsx';
import { useTaxClassesList } from './hooks/use-tax-classes';
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { HeaderActions } from '@/components/layout/header-actions'

const route = getRouteApi('/_authenticated/tax/tax-classes/')

export function TaxClasses() {
  const search = route.useSearch()
  const navigate = route.useNavigate()
  const { tPageTitle } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.TAX_CLASS)
  const entityName = {
    singular: tLabel("tax_class"),
    plural: tLabel("tax_classes")
  };

  const {data, isLoading, isSuccess} = useTaxClassesList(search);

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
              {tPageTitle("index.title", {entity: entityName.singular})}
            </h2>
            <p className='text-muted-foreground'>
              {tPageTitle("index.subtitle", {entity: entityName.plural.toLowerCase()})}
            </p>
          </div>
          <PrimaryButtons />
        </div>
        {isLoading &&  <SkeletonWidgetFromFile />}
        {isSuccess && <DataTable data={data} search={search} navigate={navigate} />}
      </Main>

      <Dialogs />
    </Provider>
  )
}
