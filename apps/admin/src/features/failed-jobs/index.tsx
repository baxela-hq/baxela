import { getRouteApi } from '@tanstack/react-router'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { Search } from '@/components/search'
import { SkeletonWidget as SkeletonWidgetFromFile } from '@/components/shared/skeleton-widget'
import { HeaderActions } from '@/components/layout/header-actions'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Locales } from './data/routes'
import { DataTable } from './components/data-table'
import { useFailedJobsList } from './hooks/use-failed-jobs'

const route = getRouteApi('/_authenticated/failed-jobs/')

export function FailedJobs() {
  const search = route.useSearch()
  const navigate = route.useNavigate()
  const { tPageTitle } = useAppTranslation(Locales.FAILED_JOBS)

  const { data, isLoading, isSuccess } = useFailedJobsList(search)

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
              {tPageTitle('index.title')}
            </h2>
            <p className='text-muted-foreground'>
              {tPageTitle('index.subtitle')}
            </p>
          </div>
        </div>
        {isLoading && <SkeletonWidgetFromFile />}
        {isSuccess && <DataTable data={data} search={search} navigate={navigate} />}
      </Main>
    </>
  )
}
