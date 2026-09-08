import { getRouteApi } from '@tanstack/react-router';
import { ConfigDrawer } from '@/components/config-drawer';
import { Header } from '@/components/layout/header';
import { Main } from '@/components/layout/main';
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { SkeletonWidget as SkeletonWidgetFromFile } from '@/components/shared/skeleton-widget'
import { ThemeSwitch } from '@/components/theme-switch';
import { Locales } from './data/routes.ts';
import { Dialogs } from './components/dialogs.tsx';
import { PrimaryButtons } from './components/primary-buttons.tsx';
import { Provider } from './components/provider.tsx';
import { DataTable } from './components/data-table.tsx';
import { useMenuLinksList } from './hooks/use-menu-links';
import { useOneMenu } from '../menus/hooks/use-menus';
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { getDefaultLanguage } from '@/shared/lib/locale.ts';

const route = getRouteApi('/_authenticated/menu/menu-links/$id/')

export function MenuLinks() {
  const search = route.useSearch()
  const navigate = route.useNavigate()
  const { id } = route.useParams()
  const { tPageTitle } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.MENU_LINK)
  const entityName = {
    singular: tLabel("link"),
    plural: tLabel("links")
  };

  const {data, isLoading, isSuccess} = useMenuLinksList(id, search);

  const {data: menuData, isSuccess: menuIsSuccess} = useOneMenu(id);

  const languageIndex = menuIsSuccess && menuData
    ? (getDefaultLanguage(menuData.translations) ?? 0)
    : 0;


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
              {' '} {menuIsSuccess && menuData.translations[languageIndex].title}
            </h2>
            <p className='text-muted-foreground'>
              {tPageTitle("index.subtitle", {entity: entityName.plural.toLowerCase()})}
            </p>
          </div>
          <PrimaryButtons />
        </div>
        {isLoading &&  <SkeletonWidgetFromFile />}
        {isSuccess && <DataTable data={data} search={search} navigate={navigate} menuId={id} />}
      </Main>

      <Dialogs menuId={id} />
    </Provider>
  )
}
