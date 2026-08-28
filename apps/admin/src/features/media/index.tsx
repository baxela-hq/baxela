import { getRouteApi } from '@tanstack/react-router'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { ConfigDrawer } from '@/components/config-drawer'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { Dialogs } from './components/dialogs'
import { MediaBrowser } from './components/media-browser'
import { Provider } from './components/provider'
import { Locales } from './data/routes'

const route = getRouteApi('/_authenticated/media/')

export function Media() {
  const search = route.useSearch()
  const navigate = route.useNavigate()
  const { tPageTitle } = useAppTranslation(Locales.MEDIA)

  const folderId = search.folder ?? null

  const handleFolderChange = (nextFolderId: number | null) => {
    navigate({
      search: (prev) => ({
        ...prev,
        // TanStack Router keeps absent keys unchanged — a param is only
        // removed from the URL by explicitly setting it to undefined
        folder: nextFolderId ?? undefined,
      }),
    })
  }

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
              {tPageTitle('index.title')}
            </h2>
            <p className='text-muted-foreground'>
              {tPageTitle('index.subtitle')}
            </p>
          </div>
        </div>

        <MediaBrowser
          folderId={folderId}
          onFolderChange={handleFolderChange}
          mode='manage'
        />
      </Main>

      <Dialogs currentFolderId={folderId} />
    </Provider>
  )
}
