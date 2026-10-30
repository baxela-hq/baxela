import { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Input } from '@/components/ui/input'
import { SkeletonWidget } from '@/components/shared/skeleton-widget'
import { fetchLanguages } from '@/features/core/languages/api/languages.api'
import { DataTable } from '@/features/core/languages/components/data-table'
import { Locales } from '@/features/core/languages/data/routes'

/**
 * The language management table, embedded as a Settings tab. Fetches the
 * full list (active + inactive) — activation is the whole point here.
 */
export function LanguagesTab() {
  const { tPlaceHolder } = useAppTranslation(Locales.SHARED_COMMON)
  const [search, setSearch] = useState('')

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
    <div className='space-y-4'>
      <div className='flex justify-end'>
        <Input
          value={search}
          onChange={(event) => setSearch(event.target.value)}
          placeholder={tPlaceHolder('search')}
          className='w-48'
        />
      </div>
      {isLoading && <SkeletonWidget />}
      {isSuccess && data && <DataTable data={filtered} />}
    </div>
  )
}
