import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { Search } from '@/components/search'
import { HeaderActions } from '@/components/layout/header-actions'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Locales } from './data/routes'
import { ExportCard } from './components/export-card'
import { ImportCard, ImportCardTitle } from './components/import-card'
import { HistoryTable } from './components/history-table'

export function CatalogData() {
  const { tPageTitle, tLabel } = useAppTranslation(Locales.DATA)

  return (
    <>
      <Header fixed>
        <Search />
        <HeaderActions />
      </Header>

      <Main className='flex flex-1 flex-col gap-4 sm:gap-6'>
        <div>
          <h2 className='text-2xl font-bold tracking-tight'>
            {tPageTitle('title')}
          </h2>
          <p className='text-muted-foreground'>{tPageTitle('subtitle')}</p>
        </div>

        <div className='grid gap-4 lg:grid-cols-2'>
          <Card>
            <CardHeader>
              <CardTitle>
                <ImportCardTitle />
              </CardTitle>
              <CardDescription>{tLabel('import_description')}</CardDescription>
            </CardHeader>
            <CardContent>
              <ImportCard />
            </CardContent>
          </Card>

          <ExportCard />
        </div>

        <Card>
          <CardHeader>
            <CardTitle>{tLabel('history')}</CardTitle>
            <CardDescription>{tLabel('history_description')}</CardDescription>
          </CardHeader>
          <CardContent>
            <HistoryTable />
          </CardContent>
        </Card>
      </Main>
    </>
  )
}
