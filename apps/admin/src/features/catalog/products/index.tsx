import { getRouteApi } from '@tanstack/react-router';
import { ConfigDrawer } from '@/components/config-drawer';
import { Header } from '@/components/layout/header';
import { Main } from '@/components/layout/main';
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { SkeletonWidget as SkeletonWidgetFromFile } from '@/components/shared/skeleton-widget'
import { ThemeSwitch } from '@/components/theme-switch';
import { Locales } from './data/routes';
import { Dialogs } from './components/dialogs.tsx';
import { PrimaryButtons } from './components/primary-buttons.tsx';
import { Provider } from './components/provider.tsx';
import { DataTable } from './components/data-table.tsx';
import { useProductsList } from './hooks/use-products';
import { useAppTranslation } from '@/hooks/useAppTranslation'

const route = getRouteApi('/_authenticated/catalog/products/')

export function Products() {
  const search = route.useSearch()
  const navigate = route.useNavigate()
  const { tPageTitle } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.PRODUCT)
  const entityName = {
    singular: tLabel("product"),
    plural: tLabel("products")
  };

  const {data, isLoading, isSuccess} = useProductsList(search);


  return (
    <Provider>
      <Header fixed>
        <Search />
        <div className='ms-auto flex items-center space-x-4'>
          <ThemeSwitch />
          <ConfigDrawer />
          <ProfileDropdown />
        </div>
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
