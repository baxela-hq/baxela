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
import { useOptionValuesList } from './hooks/use-option-values';
import { useOneOption } from '../options/hooks/use-options';
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { getDefaultLanguage } from '@/shared/lib/locale.ts';

const route = getRouteApi('/_authenticated/catalog/option-values/$id/')

export function OptionValues() {
  const search = route.useSearch()
  const navigate = route.useNavigate()
  const { id } = route.useParams()
  const { tPageTitle } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.OPTION_VALUE)
  const entityName = {
    singular: tLabel("value"),
    plural: tLabel("values")
  };

  const {data, isLoading, isSuccess} = useOptionValuesList(id, search);

  const {data: optionData, isSuccess: optionIsSuccess} = useOneOption(id);

  const languageIndex = optionIsSuccess && optionData
    ? (getDefaultLanguage(optionData.translations) ?? 0)
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
              {' '} {optionIsSuccess && optionData.translations[languageIndex].title}
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

      <Dialogs optionId={id} />
    </Provider>
  )
}
