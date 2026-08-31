import { getRouteApi } from '@tanstack/react-router'
import { ConfigDrawer } from '@/components/config-drawer'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { SkeletonWidget as SkeletonWidgetFromFile } from '@/components/shared/skeleton-widget'
import { ThemeSwitch } from '@/components/theme-switch'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { DataTable } from './components/data-table'
import { Dialogs } from './components/dialogs'
import { Provider } from './components/provider'
import { Locales } from './data/routes'
import { useProductCommentsList } from './hooks/use-product-comments'

const route = getRouteApi('/_authenticated/catalog/product-comments/')

export function ProductComments() {
  const search = route.useSearch()
  const navigate = route.useNavigate()
  const { tPageTitle } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.PRODUCT_COMMENT)
  const entityName = {
    singular: tLabel('comment'),
    plural: tLabel('comments'),
  }

  const { data, isLoading, isSuccess } = useProductCommentsList(search)

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
        {isSuccess && <DataTable data={data} search={search} navigate={navigate} />}
      </Main>

      <Dialogs />
    </Provider>
  )
}
