import { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Header } from '@/components/layout/header'
import { HeaderActions } from '@/components/layout/header-actions'
import { Main } from '@/components/layout/main'
import { Search } from '@/components/search'
import { SkeletonWidget } from '@/components/shared/skeleton-widget'
import { Input } from '@/components/ui/input'
import { fetchLanguages } from './api/languages.api'
import { DataTable } from './components/data-table'
import { Locales } from './data/routes'

export function Languages() {
  const { tPageTitle, tPlaceHolder } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.LANGUAGE)
  const [search, setSearch] = useState('')

  const entityName = {
    singular: tLabel('language'),
    plural: tLabel('languages'),
  }

  // The management page needs every row (active + inactive); the shared
  // useLanguages() hook stays active-only for translation tabs.
  const { data, isLoading, isSuccess } = useQuery({
    queryKey: ['languages', 'all'],
    queryFn: () => fetchLanguages(true),
  })

  const filtered = useMemo(() => {
    if (!data) return []
    const q = search.trim().toLowerCase()
    if (!q) return data
    return data.filter(
      (row) =>
        row.code.toLowerCase().includes(q) ||
        row.name.toLowerCase().includes(q) ||
        (row.native_name ?? '').toLowerCase().includes(q)
    )
  }, [data, search])

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
              {tPageTitle('index.title', { entity: entityName.plural })}
            </h2>
            <p className='text-muted-foreground'>
              {tPageTitle('index.subtitle', {
                entity: entityName.plural.toLowerCase(),
              })}
            </p>
          </div>
          <Input
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder={tPlaceHolder('search')}
            className='w-48'
          />
        </div>
        {isLoading && <SkeletonWidget />}
        {isSuccess && data && <DataTable data={filtered} />}
      </Main>
    </>
  )
}
